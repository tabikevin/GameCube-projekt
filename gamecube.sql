-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gép: localhost:8889
-- Létrehozás ideje: 2026. Ápr 23. 09:30
-- Kiszolgáló verziója: 8.0.40
-- PHP verzió: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Adatbázis: `gamecube`
--

DELIMITER $$
--
-- Eljárások
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_game_key` (IN `p_product_id` INT, IN `p_key_code` VARCHAR(255), OUT `p_key_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_key_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM products WHERE id = p_product_id) THEN
        SET p_key_id = 0;
        SET p_success = FALSE;
        SET p_message = 'A termék nem létezik';
        ROLLBACK;
    ELSEIF EXISTS (SELECT 1 FROM game_keys WHERE key_code = p_key_code) THEN
        SET p_key_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Ez a kulcs már létezik az adatbázisban';
        ROLLBACK;
    ELSE
        INSERT INTO game_keys (product_id, key_code, is_sold) 
        VALUES (p_product_id, p_key_code, 0);
        
        SET p_key_id = LAST_INSERT_ID();
        SET p_success = TRUE;
        SET p_message = 'Kulcs sikeresen hozzáadva';
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_product` (IN `p_name` VARCHAR(200), IN `p_short_description` TEXT, IN `p_long_description` TEXT, IN `p_platform` ENUM('pc','ps','xbox','switch'), IN `p_tag` ENUM('top','new','sale','normal'), IN `p_price` INT, IN `p_original_price` INT, IN `p_image_url` VARCHAR(255), OUT `p_product_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_discount INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_product_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF p_original_price IS NOT NULL AND p_original_price > p_price THEN
        SET v_discount = ROUND((1 - p_price / p_original_price) * 100);
    END IF;
    
    INSERT INTO products (name, short_description, long_description, platform, tag, price, original_price, discount_percent, image_url, is_active)
    VALUES (p_name, p_short_description, p_long_description, p_platform, p_tag, p_price, p_original_price, v_discount, p_image_url, 1);
    
    SET p_product_id = LAST_INSERT_ID();
    SET p_success = TRUE;
    SET p_message = 'Termék sikeresen hozzáadva';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_to_cart` (IN `p_user_id` INT, IN `p_session_id` VARCHAR(100), IN `p_product_id` INT, IN `p_quantity` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_available INT;
    DECLARE v_existing_qty INT;
    
    SELECT COUNT(*) INTO v_available 
    FROM game_keys 
    WHERE product_id = p_product_id AND is_sold = 0;
    
    IF v_available < p_quantity THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Nincs elég készlet. Elérhető: ', v_available);
    ELSE
        SELECT quantity INTO v_existing_qty
        FROM cart
        WHERE (user_id = p_user_id OR session_id = p_session_id) AND product_id = p_product_id;
        
        IF v_existing_qty IS NOT NULL THEN
            IF v_existing_qty + p_quantity > v_available THEN
                SET p_success = FALSE;
                SET p_message = CONCAT('Maximum ', v_available, ' db rendelhető');
            ELSE
                UPDATE cart 
                SET quantity = quantity + p_quantity
                WHERE (user_id = p_user_id OR session_id = p_session_id) AND product_id = p_product_id;
                
                SET p_success = TRUE;
                SET p_message = 'Kosár frissítve';
            END IF;
        ELSE
            INSERT INTO cart (user_id, session_id, product_id, quantity)
            VALUES (p_user_id, p_session_id, p_product_id, p_quantity);
            
            SET p_success = TRUE;
            SET p_message = 'Termék kosárba helyezve';
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_approve_order` (IN `p_order_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_user_id INT;
    DECLARE v_product_id INT;
    DECLARE v_quantity INT;
    DECLARE v_assigned_keys INT DEFAULT 0;
    DECLARE v_key_code VARCHAR(255);
    DECLARE v_counter INT;
    DECLARE done INT DEFAULT 0;
    
    DECLARE order_items_cursor CURSOR FOR
        SELECT product_id, quantity
        FROM order_items
        WHERE order_id = p_order_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt a jóváhagyás során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT user_id INTO v_user_id FROM orders WHERE id = p_order_id AND status = 'pending';
    
    IF v_user_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A rendelés nem található vagy nem függőben lévő státuszú';
        ROLLBACK;
    ELSE
        OPEN order_items_cursor;
        
        items_loop: LOOP
            FETCH order_items_cursor INTO v_product_id, v_quantity;
            IF done THEN LEAVE items_loop; END IF;
            
            IF (SELECT COUNT(*) FROM game_keys WHERE product_id = v_product_id AND is_sold = 0) < v_quantity THEN
                SET p_success = FALSE;
                SET p_message = CONCAT('Nincs elég kulcs a termékhez (ID: ', v_product_id, ')');
                CLOSE order_items_cursor;
                ROLLBACK;
                LEAVE items_loop;
            END IF;
            
            SET v_counter = 0;
            WHILE v_counter < v_quantity DO
                CALL sp_assign_key(v_product_id, v_user_id, p_order_id, v_key_code, @key_success, @key_msg);
                SET v_counter = v_counter + 1;
                SET v_assigned_keys = v_assigned_keys + 1;
            END WHILE;
        END LOOP;
        
        CLOSE order_items_cursor;
        
        IF p_success IS NULL OR p_success != FALSE THEN
            UPDATE orders 
            SET status = 'paid', paid_at = NOW()
            WHERE id = p_order_id;
            
            SET p_success = TRUE;
            SET p_message = CONCAT('Rendelés jóváhagyva, ', v_assigned_keys, ' kulcs kiosztva');
            COMMIT;
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_assign_key` (IN `p_product_id` INT, IN `p_user_id` INT, IN `p_order_id` INT, OUT `p_key_code` VARCHAR(255), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_key_id INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_key_code = NULL;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT id, key_code INTO v_key_id, p_key_code
    FROM game_keys
    WHERE product_id = p_product_id AND is_sold = 0
    LIMIT 1
    FOR UPDATE;
    
    IF v_key_id IS NOT NULL THEN
        UPDATE game_keys
        SET is_sold = 1, 
            sold_to_user_id = p_user_id, 
            sold_at = NOW(),
            order_id = p_order_id
        WHERE id = v_key_id;
        
        SET p_success = TRUE;
        SET p_message = 'Kulcs sikeresen kiosztva';
        COMMIT;
    ELSE
        SET p_key_code = NULL;
        SET p_success = FALSE;
        SET p_message = 'Nincs elérhető kulcs ehhez a termékhez';
        ROLLBACK;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_audit_log_create` (IN `p_table_name` VARCHAR(50), IN `p_record_id` INT, IN `p_action` ENUM('INSERT','UPDATE','DELETE'), IN `p_old_values` JSON, IN `p_new_values` JSON, IN `p_user_id` INT, IN `p_ip_address` VARCHAR(45), OUT `p_new_id` INT)   BEGIN
    INSERT INTO audit_log (table_name, record_id, action, old_values, new_values, user_id, ip_address)
    VALUES (p_table_name, p_record_id, p_action, p_old_values, p_new_values, p_user_id, p_ip_address);
    
    SET p_new_id = LAST_INSERT_ID();
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_audit_log_delete_old` (IN `p_days_to_keep` INT, OUT `p_deleted_count` INT)   BEGIN
    DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL p_days_to_keep DAY);
    SET p_deleted_count = ROW_COUNT();
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_audit_log_get_all` (IN `p_page` INT, IN `p_page_size` INT, IN `p_table_name` VARCHAR(50), IN `p_action` ENUM('INSERT','UPDATE','DELETE'), IN `p_record_id` INT, IN `p_user_id` INT, IN `p_date_from` DATETIME, IN `p_date_to` DATETIME)   BEGIN
    DECLARE v_offset INT;
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_page_size, 50);
    SET v_offset = (IFNULL(p_page, 1) - 1) * v_limit;
    
    SELECT al.*, u.username
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE (p_table_name IS NULL OR al.table_name = p_table_name)
      AND (p_action IS NULL OR al.action = p_action)
      AND (p_record_id IS NULL OR al.record_id = p_record_id)
      AND (p_user_id IS NULL OR al.user_id = p_user_id)
      AND (p_date_from IS NULL OR al.created_at >= p_date_from)
      AND (p_date_to IS NULL OR al.created_at <= p_date_to)
    ORDER BY al.created_at DESC
    LIMIT v_limit OFFSET v_offset;
    
    SELECT COUNT(*) as total_count
    FROM audit_log al
    WHERE (p_table_name IS NULL OR al.table_name = p_table_name)
      AND (p_action IS NULL OR al.action = p_action)
      AND (p_record_id IS NULL OR al.record_id = p_record_id)
      AND (p_user_id IS NULL OR al.user_id = p_user_id)
      AND (p_date_from IS NULL OR al.created_at >= p_date_from)
      AND (p_date_to IS NULL OR al.created_at <= p_date_to);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_audit_log_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT al.*, u.username
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE al.id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_audit_log_get_by_record` (IN `p_table_name` VARCHAR(50), IN `p_record_id` INT)   BEGIN
    SELECT al.*, u.username
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE al.table_name = p_table_name AND al.record_id = p_record_id
    ORDER BY al.created_at DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_audit_log_get_stats` (IN `p_date_from` DATE, IN `p_date_to` DATE)   BEGIN
    SELECT 
        table_name,
        action,
        COUNT(*) as count
    FROM audit_log
    WHERE (p_date_from IS NULL OR DATE(created_at) >= p_date_from)
      AND (p_date_to IS NULL OR DATE(created_at) <= p_date_to)
    GROUP BY table_name, action
    ORDER BY table_name, action;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bulk_add_keys` (IN `p_product_id` INT, IN `p_keys_json` JSON, OUT `p_added_count` INT, OUT `p_skipped_count` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_key VARCHAR(255);
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_total INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_added_count = 0;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;
    
    SET p_added_count = 0;
    SET p_skipped_count = 0;
    SET v_total = JSON_LENGTH(p_keys_json);
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM products WHERE id = p_product_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A termék nem létezik';
        ROLLBACK;
    ELSE
        WHILE v_index < v_total DO
            SET v_key = JSON_UNQUOTE(JSON_EXTRACT(p_keys_json, CONCAT('$[', v_index, ']')));
            
            IF NOT EXISTS (SELECT 1 FROM game_keys WHERE key_code = v_key) THEN
                INSERT INTO game_keys (product_id, key_code, is_sold) VALUES (p_product_id, v_key, 0);
                SET p_added_count = p_added_count + 1;
            ELSE
                SET p_skipped_count = p_skipped_count + 1;
            END IF;
            
            SET v_index = v_index + 1;
        END WHILE;
        
        SET p_success = TRUE;
        SET p_message = CONCAT(p_added_count, ' kulcs hozzáadva, ', p_skipped_count, ' kihagyva (már létezik)');
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cancel_order` (IN `p_order_id` INT, IN `p_refund_keys` BOOLEAN, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_freed_keys INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT status INTO v_status FROM orders WHERE id = p_order_id;
    
    IF v_status IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A rendelés nem található';
        ROLLBACK;
    ELSEIF v_status = 'cancelled' THEN
        SET p_success = FALSE;
        SET p_message = 'A rendelés már törölve van';
        ROLLBACK;
    ELSE
        IF p_refund_keys AND v_status = 'paid' THEN
            UPDATE game_keys 
            SET is_sold = 0, sold_to_user_id = NULL, sold_at = NULL, order_id = NULL
            WHERE order_id = p_order_id;
            
            SET v_freed_keys = ROW_COUNT();
        END IF;
        
        UPDATE orders SET status = 'cancelled' WHERE id = p_order_id;
        
        SET p_success = TRUE;
        IF v_freed_keys > 0 THEN
            SET p_message = CONCAT('Rendelés törölve, ', v_freed_keys, ' kulcs felszabadítva');
        ELSE
            SET p_message = 'Rendelés törölve';
        END IF;
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cart_clear` (IN `p_user_id` INT, IN `p_session_id` VARCHAR(100), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    START TRANSACTION;
    
    DELETE FROM cart 
    WHERE (p_user_id IS NOT NULL AND user_id = p_user_id)
       OR (p_session_id IS NOT NULL AND session_id = p_session_id);
    
    SET p_success = TRUE;
    SET p_message = 'Kosár sikeresen ürítve';
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cart_create` (IN `p_user_id` INT, IN `p_session_id` VARCHAR(100), IN `p_product_id` INT, IN `p_quantity` INT, OUT `p_new_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_existing_id INT;
    DECLARE v_available_keys INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kosárhoz adás során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM products WHERE id = p_product_id AND is_active = 1) THEN
        SET p_success = FALSE;
        SET p_message = 'A termék nem található vagy nem elérhető';
        SET p_new_id = NULL;
    ELSE
        SELECT COUNT(*) INTO v_available_keys FROM game_keys WHERE product_id = p_product_id AND is_sold = 0;
        
        IF v_available_keys < IFNULL(p_quantity, 1) THEN
            SET p_success = FALSE;
            SET p_message = CONCAT('Nincs elég készlet. Elérhető: ', v_available_keys);
            SET p_new_id = NULL;
        ELSE
            SELECT id INTO v_existing_id FROM cart 
            WHERE product_id = p_product_id 
              AND ((p_user_id IS NOT NULL AND user_id = p_user_id) 
                   OR (p_session_id IS NOT NULL AND session_id = p_session_id));
            
            IF v_existing_id IS NOT NULL THEN
                UPDATE cart SET quantity = quantity + IFNULL(p_quantity, 1), updated_at = CURRENT_TIMESTAMP
                WHERE id = v_existing_id;
                SET p_new_id = v_existing_id;
            ELSE
                INSERT INTO cart (user_id, session_id, product_id, quantity)
                VALUES (p_user_id, p_session_id, p_product_id, IFNULL(p_quantity, 1));
                SET p_new_id = LAST_INSERT_ID();
            END IF;
            
            SET p_success = TRUE;
            SET p_message = 'Termék hozzáadva a kosárhoz';
        END IF;
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cart_delete` (IN `p_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a törlés során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM cart WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A kosár elem nem található';
    ELSE
        DELETE FROM cart WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Termék eltávolítva a kosárból';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cart_get` (IN `p_user_id` INT, IN `p_session_id` VARCHAR(100))   BEGIN
    SELECT c.*, p.name, p.price, p.image_url, p.platform,
           (SELECT COUNT(*) FROM game_keys WHERE product_id = c.product_id AND is_sold = 0) as available_keys,
           (c.quantity * p.price) as line_total
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE (p_user_id IS NOT NULL AND c.user_id = p_user_id)
       OR (p_session_id IS NOT NULL AND c.session_id = p_session_id)
    ORDER BY c.created_at;
    
    SELECT SUM(c.quantity * p.price) as cart_total, SUM(c.quantity) as total_items
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE (p_user_id IS NOT NULL AND c.user_id = p_user_id)
       OR (p_session_id IS NOT NULL AND c.session_id = p_session_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cart_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT c.*, p.name, p.price, p.image_url, p.platform
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cart_update` (IN `p_id` INT, IN `p_quantity` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_product_id INT;
    DECLARE v_available_keys INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kosár módosítása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT product_id INTO v_product_id FROM cart WHERE id = p_id;
    
    IF v_product_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A kosár elem nem található';
    ELSE
        SELECT COUNT(*) INTO v_available_keys FROM game_keys WHERE product_id = v_product_id AND is_sold = 0;
        
        IF p_quantity > v_available_keys THEN
            SET p_success = FALSE;
            SET p_message = CONCAT('Nincs elég készlet. Elérhető: ', v_available_keys);
        ELSEIF p_quantity <= 0 THEN
            DELETE FROM cart WHERE id = p_id;
            SET p_success = TRUE;
            SET p_message = 'Termék eltávolítva a kosárból';
        ELSE
            UPDATE cart SET quantity = p_quantity, updated_at = CURRENT_TIMESTAMP WHERE id = p_id;
            SET p_success = TRUE;
            SET p_message = 'Kosár sikeresen módosítva';
        END IF;
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_change_password` (IN `p_user_id` INT, IN `p_new_password_hash` VARCHAR(255), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
    END;
    
    UPDATE users 
    SET password_hash = p_new_password_hash
    WHERE id = p_user_id;
    
    IF ROW_COUNT() > 0 THEN
        SET p_success = TRUE;
        SET p_message = 'Jelszó sikeresen módosítva';
    ELSE
        SET p_success = FALSE;
        SET p_message = 'Felhasználó nem található';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_stock` (IN `p_product_id` INT, OUT `p_available_keys` INT, OUT `p_total_keys` INT, OUT `p_sold_keys` INT)   BEGIN
    SELECT 
        COUNT(CASE WHEN is_sold = 0 THEN 1 END),
        COUNT(*),
        COUNT(CASE WHEN is_sold = 1 THEN 1 END)
    INTO p_available_keys, p_total_keys, p_sold_keys
    FROM game_keys
    WHERE product_id = p_product_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_coupons_create` (IN `p_code` VARCHAR(50), IN `p_discount_type` ENUM('percent','fixed'), IN `p_discount_value` INT, IN `p_min_order_value` INT, IN `p_max_uses` INT, IN `p_valid_from` TIMESTAMP, IN `p_valid_until` TIMESTAMP, OUT `p_new_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kupon létrehozása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF EXISTS (SELECT 1 FROM coupons WHERE code = p_code) THEN
        SET p_success = FALSE;
        SET p_message = 'Ez a kuponkód már létezik';
        SET p_new_id = NULL;
    ELSEIF p_discount_type = 'percent' AND (p_discount_value < 1 OR p_discount_value > 100) THEN
        SET p_success = FALSE;
        SET p_message = 'Százalékos kedvezmény 1-100 között lehet';
        SET p_new_id = NULL;
    ELSE
        INSERT INTO coupons (code, discount_type, discount_value, min_order_value, max_uses, valid_from, valid_until, is_active)
        VALUES (UPPER(p_code), p_discount_type, p_discount_value, IFNULL(p_min_order_value, 0), 
                p_max_uses, p_valid_from, p_valid_until, 1);
        
        SET p_new_id = LAST_INSERT_ID();
        SET p_success = TRUE;
        SET p_message = 'Kupon sikeresen létrehozva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_coupons_delete` (IN `p_id` INT, IN `p_hard_delete` BOOLEAN, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kupon törlése során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM coupons WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A kupon nem található';
    ELSEIF p_hard_delete = TRUE THEN
        DELETE FROM coupons WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Kupon véglegesen törölve';
    ELSE
        UPDATE coupons SET is_active = 0 WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Kupon inaktiválva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_coupons_get_all` (IN `p_page` INT, IN `p_page_size` INT, IN `p_is_active` TINYINT, IN `p_show_expired` BOOLEAN)   BEGIN
    DECLARE v_offset INT;
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_page_size, 20);
    SET v_offset = (IFNULL(p_page, 1) - 1) * v_limit;
    
    SELECT *, 
           CASE WHEN NOW() BETWEEN valid_from AND valid_until AND is_active = 1 
                     AND (max_uses IS NULL OR used_count < max_uses) THEN 1 ELSE 0 END as is_currently_valid
    FROM coupons
    WHERE (p_is_active IS NULL OR is_active = p_is_active)
      AND (p_show_expired = TRUE OR valid_until >= NOW())
    ORDER BY created_at DESC
    LIMIT v_limit OFFSET v_offset;
    
    SELECT COUNT(*) as total_count FROM coupons
    WHERE (p_is_active IS NULL OR is_active = p_is_active)
      AND (p_show_expired = TRUE OR valid_until >= NOW());
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_coupons_get_by_code` (IN `p_code` VARCHAR(50))   BEGIN
    SELECT *, 
           CASE WHEN NOW() BETWEEN valid_from AND valid_until AND is_active = 1 
                     AND (max_uses IS NULL OR used_count < max_uses) THEN 1 ELSE 0 END as is_currently_valid
    FROM coupons
    WHERE code = UPPER(p_code);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_coupons_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT *, 
           CASE WHEN NOW() BETWEEN valid_from AND valid_until AND is_active = 1 
                     AND (max_uses IS NULL OR used_count < max_uses) THEN 1 ELSE 0 END as is_currently_valid
    FROM coupons
    WHERE id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_coupons_update` (IN `p_id` INT, IN `p_code` VARCHAR(50), IN `p_discount_type` ENUM('percent','fixed'), IN `p_discount_value` INT, IN `p_min_order_value` INT, IN `p_max_uses` INT, IN `p_valid_from` TIMESTAMP, IN `p_valid_until` TIMESTAMP, IN `p_is_active` TINYINT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kupon módosítása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM coupons WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A kupon nem található';
    ELSEIF p_code IS NOT NULL AND EXISTS (SELECT 1 FROM coupons WHERE code = UPPER(p_code) AND id != p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'Ez a kuponkód már létezik';
    ELSE
        UPDATE coupons SET
            code = IFNULL(UPPER(p_code), code),
            discount_type = IFNULL(p_discount_type, discount_type),
            discount_value = IFNULL(p_discount_value, discount_value),
            min_order_value = IFNULL(p_min_order_value, min_order_value),
            max_uses = IFNULL(p_max_uses, max_uses),
            valid_from = IFNULL(p_valid_from, valid_from),
            valid_until = IFNULL(p_valid_until, valid_until),
            is_active = IFNULL(p_is_active, is_active)
        WHERE id = p_id;
        
        SET p_success = TRUE;
        SET p_message = 'Kupon sikeresen módosítva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_coupon` (IN `p_code` VARCHAR(50), IN `p_discount_type` ENUM('percent','fixed'), IN `p_discount_value` INT, IN `p_min_order_value` INT, IN `p_max_uses` INT, IN `p_valid_from` TIMESTAMP, IN `p_valid_until` TIMESTAMP, OUT `p_coupon_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_coupon_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
    END;
    
    IF EXISTS (SELECT 1 FROM coupons WHERE code = p_code) THEN
        SET p_coupon_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Ez a kuponkód már létezik';
    ELSE
        INSERT INTO coupons (code, discount_type, discount_value, min_order_value, max_uses, valid_from, valid_until)
        VALUES (p_code, p_discount_type, p_discount_value, COALESCE(p_min_order_value, 0), p_max_uses, p_valid_from, p_valid_until);
        
        SET p_coupon_id = LAST_INSERT_ID();
        SET p_success = TRUE;
        SET p_message = 'Kupon sikeresen létrehozva';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_full_order` (IN `p_user_id` INT, IN `p_payment_method` ENUM('online_card','bank_transfer','paypal'), IN `p_billing_name` VARCHAR(100), IN `p_billing_address` VARCHAR(255), IN `p_billing_city` VARCHAR(100), IN `p_billing_zip` VARCHAR(20), IN `p_billing_country` VARCHAR(100), IN `p_billing_tax_number` VARCHAR(50), IN `p_coupon_code` VARCHAR(50), OUT `p_order_id` INT, OUT `p_order_number` VARCHAR(20), OUT `p_total_price` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_cart_total INT DEFAULT 0;
    DECLARE v_discount INT DEFAULT 0;
    DECLARE v_coupon_id INT;
    DECLARE v_cart_count INT;
    DECLARE v_product_id INT;
    DECLARE v_quantity INT;
    DECLARE v_price INT;
    DECLARE v_available INT;
    DECLARE done INT DEFAULT 0;
    
    DECLARE cart_cursor CURSOR FOR 
        SELECT c.product_id, c.quantity, p.price
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.user_id = p_user_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 @sqlstate = RETURNED_SQLSTATE, @errno = MYSQL_ERRNO, @text = MESSAGE_TEXT;
        SET p_order_id = 0;
        SET p_success = FALSE;
        SET p_message = CONCAT('Hiba: ', @text);
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT COUNT(*) INTO v_cart_count FROM cart WHERE user_id = p_user_id;
    
    IF v_cart_count = 0 THEN
        SET p_order_id = 0;
        SET p_success = FALSE;
        SET p_message = 'A kosár üres';
        ROLLBACK;
    ELSE
        OPEN cart_cursor;
        
        check_loop: LOOP
            FETCH cart_cursor INTO v_product_id, v_quantity, v_price;
            IF done THEN LEAVE check_loop; END IF;
            
            SELECT COUNT(*) INTO v_available 
            FROM game_keys 
            WHERE product_id = v_product_id AND is_sold = 0;
            
            IF v_available < v_quantity THEN
                SET p_order_id = 0;
                SET p_success = FALSE;
                SET p_message = CONCAT('Nincs elég készlet a termékből (ID: ', v_product_id, ')');
                CLOSE cart_cursor;
                ROLLBACK;
                LEAVE check_loop;
            END IF;
            
            SET v_cart_total = v_cart_total + (v_price * v_quantity);
        END LOOP;
        
        CLOSE cart_cursor;
        
        IF p_success IS NULL OR p_success != FALSE THEN
            IF p_coupon_code IS NOT NULL AND p_coupon_code != '' THEN
                SELECT id, 
                       CASE discount_type 
                           WHEN 'percent' THEN ROUND(v_cart_total * discount_value / 100)
                           ELSE discount_value 
                       END
                INTO v_coupon_id, v_discount
                FROM coupons
                WHERE code = p_coupon_code
                  AND is_active = 1
                  AND NOW() BETWEEN valid_from AND valid_until
                  AND (max_uses IS NULL OR used_count < max_uses)
                  AND v_cart_total >= min_order_value;
                
                IF v_coupon_id IS NOT NULL THEN
                    UPDATE coupons SET used_count = used_count + 1 WHERE id = v_coupon_id;
                END IF;
            END IF;
            
            SET p_total_price = v_cart_total - v_discount;
            SET p_order_number = fn_generate_order_number();
            
            INSERT INTO orders (
                user_id, order_number, total_price, status, payment_method,
                billing_name, billing_address, billing_city, billing_zip,
                billing_country, billing_tax_number
            ) VALUES (
                p_user_id, p_order_number, p_total_price, 'pending', p_payment_method,
                p_billing_name, p_billing_address, p_billing_city, p_billing_zip,
                p_billing_country, p_billing_tax_number
            );
            
            SET p_order_id = LAST_INSERT_ID();
            
            INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
            SELECT p_order_id, c.product_id, c.quantity, p.price, p.price * c.quantity
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.user_id = p_user_id;
            
            DELETE FROM cart WHERE user_id = p_user_id;
            
            IF p_payment_method != 'bank_transfer' THEN
                CALL sp_approve_order(p_order_id, @approve_success, @approve_msg);
            END IF;
            
            SET p_success = TRUE;
            SET p_message = 'Rendelés sikeresen létrehozva';
            COMMIT;
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_favorites_check` (IN `p_user_id` INT, IN `p_product_id` INT, OUT `p_is_favorite` BOOLEAN)   BEGIN
    SET p_is_favorite = EXISTS (SELECT 1 FROM favorites WHERE user_id = p_user_id AND product_id = p_product_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_favorites_create` (IN `p_user_id` INT, IN `p_product_id` INT, OUT `p_new_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kedvencekhez adás során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_user_id AND is_active = 1) THEN
        SET p_success = FALSE;
        SET p_message = 'Érvénytelen felhasználó';
        SET p_new_id = NULL;
    ELSEIF NOT EXISTS (SELECT 1 FROM products WHERE id = p_product_id AND is_active = 1) THEN
        SET p_success = FALSE;
        SET p_message = 'A termék nem található';
        SET p_new_id = NULL;
    ELSEIF EXISTS (SELECT 1 FROM favorites WHERE user_id = p_user_id AND product_id = p_product_id) THEN
        SELECT id INTO p_new_id FROM favorites WHERE user_id = p_user_id AND product_id = p_product_id;
        SET p_success = TRUE;
        SET p_message = 'A termék már a kedvencek között van';
    ELSE
        INSERT INTO favorites (user_id, product_id) VALUES (p_user_id, p_product_id);
        SET p_new_id = LAST_INSERT_ID();
        SET p_success = TRUE;
        SET p_message = 'Termék hozzáadva a kedvencekhez';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_favorites_delete` (IN `p_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    IF NOT EXISTS (SELECT 1 FROM favorites WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A kedvenc nem található';
    ELSE
        DELETE FROM favorites WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Termék eltávolítva a kedvencekből';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_favorites_delete_by_user_product` (IN `p_user_id` INT, IN `p_product_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    IF NOT EXISTS (SELECT 1 FROM favorites WHERE user_id = p_user_id AND product_id = p_product_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A kedvenc nem található';
    ELSE
        DELETE FROM favorites WHERE user_id = p_user_id AND product_id = p_product_id;
        SET p_success = TRUE;
        SET p_message = 'Termék eltávolítva a kedvencekből';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_favorites_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT f.*, p.name, p.price, p.platform, p.image_url
    FROM favorites f
    JOIN products p ON p.id = f.product_id
    WHERE f.id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_favorites_get_by_user` (IN `p_user_id` INT, IN `p_page` INT, IN `p_page_size` INT)   BEGIN
    DECLARE v_offset INT;
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_page_size, 20);
    SET v_offset = (IFNULL(p_page, 1) - 1) * v_limit;
    
    SELECT f.id, f.created_at as added_at, p.*,
           (SELECT COUNT(*) FROM game_keys WHERE product_id = p.id AND is_sold = 0) as available_keys
    FROM favorites f
    JOIN products p ON p.id = f.product_id
    WHERE f.user_id = p_user_id AND p.is_active = 1
    ORDER BY f.created_at DESC
    LIMIT v_limit OFFSET v_offset;
    
    SELECT COUNT(*) as total_count
    FROM favorites f
    JOIN products p ON p.id = f.product_id
    WHERE f.user_id = p_user_id AND p.is_active = 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_game_keys_create` (IN `p_product_id` INT, IN `p_key_code` VARCHAR(255), OUT `p_new_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kulcs hozzáadása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM products WHERE id = p_product_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A megadott termék nem létezik';
        SET p_new_id = NULL;
    ELSEIF EXISTS (SELECT 1 FROM game_keys WHERE key_code = p_key_code) THEN
        SET p_success = FALSE;
        SET p_message = 'Ez a kulcs már létezik az adatbázisban';
        SET p_new_id = NULL;
    ELSE
        INSERT INTO game_keys (product_id, key_code, is_sold)
        VALUES (p_product_id, p_key_code, 0);
        
        SET p_new_id = LAST_INSERT_ID();
        SET p_success = TRUE;
        SET p_message = 'Kulcs sikeresen hozzáadva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_game_keys_create_bulk` (IN `p_product_id` INT, IN `p_keys_json` JSON, OUT `p_added_count` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_key VARCHAR(255);
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_total INT;
    DECLARE v_added INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kulcsok hozzáadása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM products WHERE id = p_product_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A megadott termék nem létezik';
        SET p_added_count = 0;
    ELSE
        SET v_total = JSON_LENGTH(p_keys_json);
        
        WHILE v_index < v_total DO
            SET v_key = JSON_UNQUOTE(JSON_EXTRACT(p_keys_json, CONCAT('$[', v_index, ']')));
            
            IF NOT EXISTS (SELECT 1 FROM game_keys WHERE key_code = v_key) THEN
                INSERT INTO game_keys (product_id, key_code, is_sold) VALUES (p_product_id, v_key, 0);
                SET v_added = v_added + 1;
            END IF;
            
            SET v_index = v_index + 1;
        END WHILE;
        
        SET p_added_count = v_added;
        SET p_success = TRUE;
        SET p_message = CONCAT(v_added, ' kulcs sikeresen hozzáadva (', v_total - v_added, ' duplikált)');
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_game_keys_delete` (IN `p_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_is_sold TINYINT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kulcs törlése során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT is_sold INTO v_is_sold FROM game_keys WHERE id = p_id;
    
    IF v_is_sold IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A kulcs nem található';
    ELSEIF v_is_sold = 1 THEN
        SET p_success = FALSE;
        SET p_message = 'Eladott kulcs nem törölhető';
    ELSE
        DELETE FROM game_keys WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Kulcs sikeresen törölve';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_game_keys_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT gk.*, p.name as product_name, u.username as sold_to_username
    FROM game_keys gk
    LEFT JOIN products p ON p.id = gk.product_id
    LEFT JOIN users u ON u.id = gk.sold_to_user_id
    WHERE gk.id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_game_keys_get_by_product` (IN `p_product_id` INT, IN `p_is_sold` TINYINT, IN `p_page` INT, IN `p_page_size` INT)   BEGIN
    DECLARE v_offset INT;
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_page_size, 50);
    SET v_offset = (IFNULL(p_page, 1) - 1) * v_limit;
    
    SELECT gk.*, u.username as sold_to_username
    FROM game_keys gk
    LEFT JOIN users u ON u.id = gk.sold_to_user_id
    WHERE gk.product_id = p_product_id
      AND (p_is_sold IS NULL OR gk.is_sold = p_is_sold)
    ORDER BY gk.created_at DESC
    LIMIT v_limit OFFSET v_offset;
    
    SELECT COUNT(*) as total_count,
           SUM(CASE WHEN is_sold = 0 THEN 1 ELSE 0 END) as available_count,
           SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) as sold_count
    FROM game_keys
    WHERE product_id = p_product_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_game_keys_update` (IN `p_id` INT, IN `p_key_code` VARCHAR(255), IN `p_product_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_is_sold TINYINT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a kulcs módosítása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT is_sold INTO v_is_sold FROM game_keys WHERE id = p_id;
    
    IF v_is_sold IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A kulcs nem található';
    ELSEIF v_is_sold = 1 THEN
        SET p_success = FALSE;
        SET p_message = 'Eladott kulcs nem módosítható';
    ELSEIF p_key_code IS NOT NULL AND EXISTS (SELECT 1 FROM game_keys WHERE key_code = p_key_code AND id != p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'Ez a kulcs már létezik';
    ELSE
        UPDATE game_keys SET
            key_code = IFNULL(p_key_code, key_code),
            product_id = IFNULL(p_product_id, product_id)
        WHERE id = p_id;
        
        SET p_success = TRUE;
        SET p_message = 'Kulcs sikeresen módosítva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_test_keys` (IN `p_product_id` INT, IN `p_count` INT, IN `p_prefix` VARCHAR(20), OUT `p_added_count` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_key_code VARCHAR(255);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_added_count = 0;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;
    
    IF p_count > 1000 THEN
        SET p_added_count = 0;
        SET p_success = FALSE;
        SET p_message = 'Maximum 1000 kulcs generálható egyszerre';
    ELSEIF NOT EXISTS (SELECT 1 FROM products WHERE id = p_product_id) THEN
        SET p_added_count = 0;
        SET p_success = FALSE;
        SET p_message = 'A termék nem létezik';
    ELSE
        START TRANSACTION;
        
        WHILE v_counter < p_count DO
            SET v_key_code = CONCAT(
                COALESCE(p_prefix, 'KEY'),
                '-',
                UPPER(SUBSTRING(MD5(RAND()), 1, 4)),
                '-',
                UPPER(SUBSTRING(MD5(RAND()), 1, 4)),
                '-',
                UPPER(SUBSTRING(MD5(RAND()), 1, 4)),
                '-',
                UPPER(SUBSTRING(MD5(RAND()), 1, 4))
            );
            
            IF NOT EXISTS (SELECT 1 FROM game_keys WHERE key_code = v_key_code) THEN
                INSERT INTO game_keys (product_id, key_code, is_sold) VALUES (p_product_id, v_key_code, 0);
                SET v_counter = v_counter + 1;
            END IF;
        END WHILE;
        
        SET p_added_count = v_counter;
        SET p_success = TRUE;
        SET p_message = CONCAT(v_counter, ' teszt kulcs sikeresen generálva');
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_cart` (IN `p_user_id` INT, IN `p_session_id` VARCHAR(100))   BEGIN
    SELECT 
        c.id as cart_id,
        c.product_id,
        c.quantity,
        p.name,
        p.price,
        p.image_url,
        p.platform,
        c.quantity * p.price as subtotal,
        (SELECT COUNT(*) FROM game_keys gk WHERE gk.product_id = c.product_id AND gk.is_sold = 0) as available
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = p_user_id OR c.session_id = p_session_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_cart_summary` (IN `p_user_id` INT, IN `p_session_id` VARCHAR(100), OUT `p_item_count` INT, OUT `p_total_quantity` INT, OUT `p_total_price` INT)   BEGIN
    SELECT 
        COUNT(*),
        COALESCE(SUM(c.quantity), 0),
        COALESCE(SUM(c.quantity * p.price), 0)
    INTO p_item_count, p_total_quantity, p_total_price
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = p_user_id OR c.session_id = p_session_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_daily_report` (IN `p_date` DATE)   BEGIN
    SELECT 
        p_date as report_date,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = p_date) as new_users,
        (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = p_date) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = p_date AND status = 'paid') as paid_orders,
        (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE DATE(created_at) = p_date AND status = 'paid') as revenue,
        (SELECT COUNT(*) FROM game_keys WHERE DATE(sold_at) = p_date) as keys_sold;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_favorites` (IN `p_user_id` INT)   BEGIN
    SELECT 
        p.*,
        COUNT(gk.id) as available_keys,
        f.created_at as added_at
    FROM favorites f
    JOIN products p ON p.id = f.product_id
    LEFT JOIN game_keys gk ON gk.product_id = p.id AND gk.is_sold = 0
    WHERE f.user_id = p_user_id AND p.is_active = 1
    GROUP BY p.id
    ORDER BY f.created_at DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_low_stock_products` (IN `p_threshold` INT)   BEGIN
    SELECT 
        p.id,
        p.name,
        p.platform,
        COUNT(gk.id) as available_keys
    FROM products p
    LEFT JOIN game_keys gk ON gk.product_id = p.id AND gk.is_sold = 0
    WHERE p.is_active = 1
    GROUP BY p.id
    HAVING available_keys <= p_threshold
    ORDER BY available_keys ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_order_details` (IN `p_order_id` INT, IN `p_user_id` INT)   BEGIN
    SELECT o.*, u.username, u.email
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.id = p_order_id 
      AND (p_user_id IS NULL OR o.user_id = p_user_id);
    
    SELECT 
        oi.*,
        p.name as product_name,
        p.platform,
        p.image_url
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = p_order_id;
    
    SELECT 
        gk.key_code,
        p.name as product_name
    FROM game_keys gk
    JOIN products p ON p.id = gk.product_id
    JOIN orders o ON o.id = gk.order_id
    WHERE gk.order_id = p_order_id
      AND (p_user_id IS NULL OR o.user_id = p_user_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_period_report` (IN `p_start_date` DATE, IN `p_end_date` DATE)   BEGIN
    SELECT 
        p_start_date as period_start,
        p_end_date as period_end,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date) as new_users,
        (SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date AND status = 'paid') as total_orders,
        (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date AND status = 'paid') as total_revenue,
        (SELECT COALESCE(AVG(total_price), 0) FROM orders WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date AND status = 'paid') as avg_order_value,
        (SELECT COUNT(*) FROM game_keys WHERE DATE(sold_at) BETWEEN p_start_date AND p_end_date) as keys_sold;
    
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as orders,
        SUM(total_price) as revenue
    FROM orders
    WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date AND status = 'paid'
    GROUP BY DATE(created_at)
    ORDER BY date;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_platform_stats` ()   BEGIN
    SELECT 
        p.platform,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(DISTINCT CASE WHEN gk.is_sold = 0 THEN gk.id END) as available_keys,
        COUNT(DISTINCT CASE WHEN gk.is_sold = 1 THEN gk.id END) as sold_keys,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN oi.total_price END), 0) as total_revenue
    FROM products p
    LEFT JOIN game_keys gk ON gk.product_id = p.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id
    WHERE p.is_active = 1
    GROUP BY p.platform;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_related_products` (IN `p_product_id` INT, IN `p_limit` INT)   BEGIN
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_limit, 5);
    
    SELECT p.*, COUNT(gk.id) as available_keys
    FROM products p
    LEFT JOIN game_keys gk ON gk.product_id = p.id AND gk.is_sold = 0
    WHERE p.is_active = 1 
      AND p.id != p_product_id
      AND p.platform = (SELECT platform FROM products WHERE id = p_product_id)
    GROUP BY p.id
    ORDER BY RAND()
    LIMIT v_limit;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_top_products_report` (IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_limit` INT)   BEGIN
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_limit, 10);
    
    SELECT 
        p.id,
        p.name,
        p.platform,
        COUNT(DISTINCT o.id) as order_count,
        SUM(oi.quantity) as units_sold,
        SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE o.status = 'paid'
      AND (p_start_date IS NULL OR DATE(o.created_at) >= p_start_date)
      AND (p_end_date IS NULL OR DATE(o.created_at) <= p_end_date)
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT v_limit;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_user_activity_report` (IN `p_limit` INT)   BEGIN
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_limit, 20);
    
    SELECT 
        u.id,
        u.username,
        u.email,
        u.created_at as registered_at,
        u.last_login_at,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total_price END), 0) as total_spent,
        MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT v_limit;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_user_stats` (IN `p_user_id` INT)   BEGIN
    SELECT 
        u.id,
        u.username,
        u.created_at as member_since,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total_price ELSE 0 END), 0) as total_spent,
        COUNT(DISTINCT CASE WHEN o.status = 'paid' THEN o.id END) as paid_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.id END) as pending_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.id END) as cancelled_orders,
        (SELECT COUNT(*) FROM game_keys WHERE sold_to_user_id = p_user_id) as total_keys_owned,
        (SELECT COUNT(*) FROM favorites WHERE user_id = p_user_id) as favorites_count
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.id = p_user_id
    GROUP BY u.id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_increment_product_view` (IN `p_product_id` INT)   BEGIN
    UPDATE products SET view_count = view_count + 1 WHERE id = p_product_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_login_attempt` (IN `p_username` VARCHAR(50), IN `p_login_success` BOOLEAN, OUT `p_is_locked` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_user_id INT;
    DECLARE v_failed_attempts INT;
    DECLARE v_locked_until TIMESTAMP;
    DECLARE v_max_attempts INT DEFAULT 5;
    DECLARE v_lock_duration INT DEFAULT 15; 
    
    SELECT id, failed_login_attempts, locked_until 
    INTO v_user_id, v_failed_attempts, v_locked_until
    FROM users WHERE username = p_username;
    
    IF v_user_id IS NULL THEN
        SET p_is_locked = FALSE;
        SET p_message = 'Felhasználó nem található';
    ELSEIF v_locked_until IS NOT NULL AND v_locked_until > NOW() THEN
        SET p_is_locked = TRUE;
        SET p_message = CONCAT('Fiók zárolva ', TIMESTAMPDIFF(MINUTE, NOW(), v_locked_until), ' percig');
    ELSEIF p_login_success THEN
        UPDATE users 
        SET failed_login_attempts = 0, 
            locked_until = NULL,
            last_login_at = NOW()
        WHERE id = v_user_id;
        SET p_is_locked = FALSE;
        SET p_message = 'Sikeres bejelentkezés';
    ELSE
        SET v_failed_attempts = v_failed_attempts + 1;
        
        IF v_failed_attempts >= v_max_attempts THEN
            UPDATE users 
            SET failed_login_attempts = v_failed_attempts,
                locked_until = DATE_ADD(NOW(), INTERVAL v_lock_duration MINUTE)
            WHERE id = v_user_id;
            SET p_is_locked = TRUE;
            SET p_message = CONCAT('Túl sok sikertelen próbálkozás. Fiók zárolva ', v_lock_duration, ' percre');
        ELSE
            UPDATE users 
            SET failed_login_attempts = v_failed_attempts
            WHERE id = v_user_id;
            SET p_is_locked = FALSE;
            SET p_message = CONCAT('Hibás jelszó. Még ', v_max_attempts - v_failed_attempts, ' próbálkozás');
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_merge_cart` (IN `p_user_id` INT, IN `p_session_id` VARCHAR(100))   BEGIN
    UPDATE cart c1
    JOIN cart c2 ON c1.product_id = c2.product_id
    SET c1.quantity = c1.quantity + c2.quantity
    WHERE c1.user_id = p_user_id AND c2.session_id = p_session_id AND c2.user_id IS NULL;
    
    UPDATE cart
    SET user_id = p_user_id, session_id = NULL
    WHERE session_id = p_session_id AND user_id IS NULL
      AND product_id NOT IN (SELECT product_id FROM (SELECT product_id FROM cart WHERE user_id = p_user_id) as tmp);
    
    DELETE FROM cart WHERE session_id = p_session_id AND user_id IS NULL;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_orders_create` (IN `p_user_id` INT, IN `p_payment_method` ENUM('online_card','bank_transfer','paypal'), IN `p_billing_name` VARCHAR(100), IN `p_billing_address` VARCHAR(255), IN `p_billing_city` VARCHAR(100), IN `p_billing_zip` VARCHAR(20), IN `p_billing_country` VARCHAR(100), IN `p_billing_tax_number` VARCHAR(50), IN `p_notes` TEXT, OUT `p_new_id` INT, OUT `p_order_number` VARCHAR(20), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a rendelés létrehozása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_user_id AND is_active = 1) THEN
        SET p_success = FALSE;
        SET p_message = 'Érvénytelen felhasználó';
        SET p_new_id = NULL;
    ELSE
        INSERT INTO orders (user_id, total_price, status, payment_method, 
                           billing_name, billing_address, billing_city, 
                           billing_zip, billing_country, billing_tax_number, notes)
        VALUES (p_user_id, 0, 'pending', p_payment_method,
                p_billing_name, p_billing_address, p_billing_city,
                p_billing_zip, p_billing_country, p_billing_tax_number, p_notes);
        
        SET p_new_id = LAST_INSERT_ID();
        SELECT order_number INTO p_order_number FROM orders WHERE id = p_new_id;
        SET p_success = TRUE;
        SET p_message = 'Rendelés sikeresen létrehozva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_orders_delete` (IN `p_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_status VARCHAR(20);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a rendelés törlése során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT status INTO v_status FROM orders WHERE id = p_id;
    
    IF v_status IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A rendelés nem található';
    ELSEIF v_status != 'pending' THEN
        SET p_success = FALSE;
        SET p_message = 'Csak függőben lévő rendelés törölhető';
    ELSE
        DELETE FROM orders WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Rendelés sikeresen törölve';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_orders_get_all` (IN `p_page` INT, IN `p_page_size` INT, IN `p_status` ENUM('pending','paid','cancelled','refunded'), IN `p_user_id` INT, IN `p_date_from` DATE, IN `p_date_to` DATE)   BEGIN
    DECLARE v_offset INT;
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_page_size, 20);
    SET v_offset = (IFNULL(p_page, 1) - 1) * v_limit;
    
    SELECT o.*, u.username, u.email as user_email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE (p_status IS NULL OR o.status = p_status)
      AND (p_user_id IS NULL OR o.user_id = p_user_id)
      AND (p_date_from IS NULL OR DATE(o.created_at) >= p_date_from)
      AND (p_date_to IS NULL OR DATE(o.created_at) <= p_date_to)
    ORDER BY o.created_at DESC
    LIMIT v_limit OFFSET v_offset;
    
    SELECT COUNT(*) as total_count,
           SUM(CASE WHEN o.status = 'paid' THEN o.total_price ELSE 0 END) as total_revenue
    FROM orders o
    WHERE (p_status IS NULL OR o.status = p_status)
      AND (p_user_id IS NULL OR o.user_id = p_user_id)
      AND (p_date_from IS NULL OR DATE(o.created_at) >= p_date_from)
      AND (p_date_to IS NULL OR DATE(o.created_at) <= p_date_to);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_orders_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT o.*, u.username, u.email as user_email
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.id = p_id;
    
    SELECT oi.*, p.name as product_name, p.platform
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_orders_get_by_user` (IN `p_user_id` INT, IN `p_page` INT, IN `p_page_size` INT)   BEGIN
    DECLARE v_offset INT;
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_page_size, 10);
    SET v_offset = (IFNULL(p_page, 1) - 1) * v_limit;
    
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    WHERE o.user_id = p_user_id
    ORDER BY o.created_at DESC
    LIMIT v_limit OFFSET v_offset;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_orders_update` (IN `p_id` INT, IN `p_status` ENUM('pending','paid','cancelled','refunded'), IN `p_payment_transaction_id` VARCHAR(100), IN `p_notes` TEXT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_current_status VARCHAR(20);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a rendelés módosítása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT status INTO v_current_status FROM orders WHERE id = p_id;
    
    IF v_current_status IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A rendelés nem található';
    ELSE
        UPDATE orders SET
            status = IFNULL(p_status, status),
            payment_transaction_id = IFNULL(p_payment_transaction_id, payment_transaction_id),
            notes = IFNULL(p_notes, notes),
            paid_at = CASE WHEN p_status = 'paid' AND paid_at IS NULL THEN CURRENT_TIMESTAMP ELSE paid_at END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_id;
        
        SET p_success = TRUE;
        SET p_message = 'Rendelés sikeresen módosítva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_order_items_create` (IN `p_order_id` INT, IN `p_product_id` INT, IN `p_quantity` INT, OUT `p_new_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_order_status VARCHAR(20);
    DECLARE v_unit_price INT;
    DECLARE v_available_keys INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a tétel hozzáadása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT status INTO v_order_status FROM orders WHERE id = p_order_id;
    SELECT price INTO v_unit_price FROM products WHERE id = p_product_id AND is_active = 1;
    SELECT COUNT(*) INTO v_available_keys FROM game_keys WHERE product_id = p_product_id AND is_sold = 0;
    
    IF v_order_status IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A rendelés nem található';
    ELSEIF v_order_status != 'pending' THEN
        SET p_success = FALSE;
        SET p_message = 'Csak függőben lévő rendeléshez adható tétel';
    ELSEIF v_unit_price IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A termék nem található vagy inaktív';
    ELSEIF v_available_keys < IFNULL(p_quantity, 1) THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Nincs elég készlet. Elérhető: ', v_available_keys);
    ELSE
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
        VALUES (p_order_id, p_product_id, IFNULL(p_quantity, 1), v_unit_price, v_unit_price * IFNULL(p_quantity, 1));
        
        SET p_new_id = LAST_INSERT_ID();
        
        UPDATE orders SET total_price = (
            SELECT SUM(total_price) FROM order_items WHERE order_id = p_order_id
        ) WHERE id = p_order_id;
        
        SET p_success = TRUE;
        SET p_message = 'Tétel sikeresen hozzáadva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_order_items_delete` (IN `p_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_order_id INT;
    DECLARE v_order_status VARCHAR(20);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a tétel törlése során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT oi.order_id, o.status
    INTO v_order_id, v_order_status
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.id = p_id;
    
    IF v_order_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A tétel nem található';
    ELSEIF v_order_status != 'pending' THEN
        SET p_success = FALSE;
        SET p_message = 'Csak függőben lévő rendelés tétele törölhető';
    ELSE
        DELETE FROM order_items WHERE id = p_id;
        
        UPDATE orders SET total_price = IFNULL((
            SELECT SUM(total_price) FROM order_items WHERE order_id = v_order_id
        ), 0) WHERE id = v_order_id;
        
        SET p_success = TRUE;
        SET p_message = 'Tétel sikeresen törölve';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_order_items_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT oi.*, p.name as product_name, p.platform, o.order_number, o.status as order_status
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_order_items_get_by_order` (IN `p_order_id` INT)   BEGIN
    SELECT oi.*, p.name as product_name, p.platform, p.image_url
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = p_order_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_order_items_update` (IN `p_id` INT, IN `p_quantity` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_order_id INT;
    DECLARE v_order_status VARCHAR(20);
    DECLARE v_product_id INT;
    DECLARE v_unit_price INT;
    DECLARE v_available_keys INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a tétel módosítása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT oi.order_id, o.status, oi.product_id, oi.unit_price
    INTO v_order_id, v_order_status, v_product_id, v_unit_price
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.id = p_id;
    
    SELECT COUNT(*) INTO v_available_keys FROM game_keys WHERE product_id = v_product_id AND is_sold = 0;
    
    IF v_order_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'A tétel nem található';
    ELSEIF v_order_status != 'pending' THEN
        SET p_success = FALSE;
        SET p_message = 'Csak függőben lévő rendelés tétele módosítható';
    ELSEIF p_quantity > v_available_keys THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Nincs elég készlet. Elérhető: ', v_available_keys);
    ELSE
        UPDATE order_items SET
            quantity = p_quantity,
            total_price = v_unit_price * p_quantity
        WHERE id = p_id;
        
        UPDATE orders SET total_price = (
            SELECT SUM(total_price) FROM order_items WHERE order_id = v_order_id
        ) WHERE id = v_order_id;
        
        SET p_success = TRUE;
        SET p_message = 'Tétel sikeresen módosítva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_products_create` (IN `p_name` VARCHAR(200), IN `p_short_description` TEXT, IN `p_long_description` TEXT, IN `p_platform` ENUM('pc','ps','xbox','switch'), IN `p_tag` ENUM('top','new','sale','normal'), IN `p_price` INT, IN `p_original_price` INT, IN `p_discount_percent` INT, IN `p_image_url` VARCHAR(255), OUT `p_new_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a termék létrehozása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    INSERT INTO products (name, short_description, long_description, platform, tag, 
                         price, original_price, discount_percent, image_url, is_active)
    VALUES (p_name, p_short_description, p_long_description, p_platform, 
            IFNULL(p_tag, 'normal'), p_price, p_original_price, 
            IFNULL(p_discount_percent, 0), p_image_url, 1);
    
    SET p_new_id = LAST_INSERT_ID();
    SET p_success = TRUE;
    SET p_message = 'Termék sikeresen létrehozva';
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_products_delete` (IN `p_id` INT, IN `p_hard_delete` BOOLEAN, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_sold_keys INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a termék törlése során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM products WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A termék nem található';
    ELSE
        SELECT COUNT(*) INTO v_sold_keys FROM game_keys WHERE product_id = p_id AND is_sold = 1;
        
        IF p_hard_delete = TRUE AND v_sold_keys > 0 THEN
            SET p_success = FALSE;
            SET p_message = 'Nem törölhető: vannak eladott kulcsok a termékhez';
        ELSEIF p_hard_delete = TRUE THEN
            DELETE FROM products WHERE id = p_id;
            SET p_success = TRUE;
            SET p_message = 'Termék véglegesen törölve';
        ELSE
            UPDATE products SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = p_id;
            SET p_success = TRUE;
            SET p_message = 'Termék inaktiválva';
        END IF;
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_products_get_all` (IN `p_page` INT, IN `p_page_size` INT, IN `p_platform` ENUM('pc','ps','xbox','switch'), IN `p_tag` ENUM('top','new','sale','normal'), IN `p_is_active` TINYINT, IN `p_min_price` INT, IN `p_max_price` INT, IN `p_order_by` VARCHAR(20), IN `p_order_dir` VARCHAR(4))   BEGIN
    DECLARE v_offset INT;
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_page_size, 20);
    SET v_offset = (IFNULL(p_page, 1) - 1) * v_limit;
    
    SELECT p.*, 
           COUNT(CASE WHEN gk.is_sold = 0 THEN 1 END) as available_keys
    FROM products p
    LEFT JOIN game_keys gk ON gk.product_id = p.id
    WHERE (p_platform IS NULL OR p.platform = p_platform)
      AND (p_tag IS NULL OR p.tag = p_tag)
      AND (p_is_active IS NULL OR p.is_active = p_is_active)
      AND (p_min_price IS NULL OR p.price >= p_min_price)
      AND (p_max_price IS NULL OR p.price <= p_max_price)
    GROUP BY p.id
    ORDER BY 
        CASE WHEN p_order_by = 'price' AND p_order_dir = 'ASC' THEN p.price END ASC,
        CASE WHEN p_order_by = 'price' AND p_order_dir = 'DESC' THEN p.price END DESC,
        CASE WHEN p_order_by = 'name' AND p_order_dir = 'ASC' THEN p.name END ASC,
        CASE WHEN p_order_by = 'name' AND p_order_dir = 'DESC' THEN p.name END DESC,
        CASE WHEN p_order_by = 'created_at' OR p_order_by IS NULL THEN p.created_at END DESC
    LIMIT v_limit OFFSET v_offset;
    
    SELECT COUNT(*) as total_count
    FROM products p
    WHERE (p_platform IS NULL OR p.platform = p_platform)
      AND (p_tag IS NULL OR p.tag = p_tag)
      AND (p_is_active IS NULL OR p.is_active = p_is_active)
      AND (p_min_price IS NULL OR p.price >= p_min_price)
      AND (p_max_price IS NULL OR p.price <= p_max_price);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_products_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT p.*, 
           COUNT(CASE WHEN gk.is_sold = 0 THEN 1 END) as available_keys,
           COUNT(gk.id) as total_keys
    FROM products p
    LEFT JOIN game_keys gk ON gk.product_id = p.id
    WHERE p.id = p_id
    GROUP BY p.id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_products_search` (IN `p_search_term` VARCHAR(100), IN `p_platform` ENUM('pc','ps','xbox','switch'), IN `p_limit` INT)   BEGIN
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_limit, 20);
    
    SELECT p.*, 
           COUNT(CASE WHEN gk.is_sold = 0 THEN 1 END) as available_keys
    FROM products p
    LEFT JOIN game_keys gk ON gk.product_id = p.id
    WHERE p.is_active = 1
      AND (p_platform IS NULL OR p.platform = p_platform)
      AND (p.name LIKE CONCAT('%', p_search_term, '%')
           OR p.short_description LIKE CONCAT('%', p_search_term, '%'))
    GROUP BY p.id
    ORDER BY 
        CASE WHEN p.name LIKE CONCAT(p_search_term, '%') THEN 0 ELSE 1 END,
        p.name
    LIMIT v_limit;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_products_update` (IN `p_id` INT, IN `p_name` VARCHAR(200), IN `p_short_description` TEXT, IN `p_long_description` TEXT, IN `p_platform` ENUM('pc','ps','xbox','switch'), IN `p_tag` ENUM('top','new','sale','normal'), IN `p_price` INT, IN `p_original_price` INT, IN `p_discount_percent` INT, IN `p_image_url` VARCHAR(255), IN `p_is_active` TINYINT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a termék módosítása során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM products WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A termék nem található';
    ELSE
        UPDATE products SET
            name = IFNULL(p_name, name),
            short_description = IFNULL(p_short_description, short_description),
            long_description = IFNULL(p_long_description, long_description),
            platform = IFNULL(p_platform, platform),
            tag = IFNULL(p_tag, tag),
            price = IFNULL(p_price, price),
            original_price = IFNULL(p_original_price, original_price),
            discount_percent = IFNULL(p_discount_percent, discount_percent),
            image_url = IFNULL(p_image_url, image_url),
            is_active = IFNULL(p_is_active, is_active),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_id;
        
        SET p_success = TRUE;
        SET p_message = 'Termék sikeresen módosítva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_register_user` (IN `p_username` VARCHAR(50), IN `p_email` VARCHAR(100), IN `p_password_hash` VARCHAR(255), IN `p_full_name` VARCHAR(100), IN `p_phone` VARCHAR(20), OUT `p_user_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_user_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt a regisztráció során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF LENGTH(p_username) < 3 THEN
        SET p_user_id = 0;
        SET p_success = FALSE;
        SET p_message = 'A felhasználónév legalább 3 karakter legyen';
        ROLLBACK;
    ELSEIF p_email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$' THEN
        SET p_user_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Érvénytelen email formátum';
        ROLLBACK;
    ELSEIF EXISTS (SELECT 1 FROM users WHERE username = p_username) THEN
        SET p_user_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Ez a felhasználónév már foglalt';
        ROLLBACK;
    ELSEIF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
        SET p_user_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Ez az email cím már használatban van';
        ROLLBACK;
    ELSE
        INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active)
        VALUES (p_username, p_email, p_password_hash, p_full_name, p_phone, 'user', 1);
        
        SET p_user_id = LAST_INSERT_ID();
        SET p_success = TRUE;
        SET p_message = 'Sikeres regisztráció';
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_search_products` (IN `p_search_term` VARCHAR(100), IN `p_platform` VARCHAR(10), IN `p_tag` VARCHAR(10), IN `p_min_price` INT, IN `p_max_price` INT, IN `p_in_stock_only` BOOLEAN, IN `p_sort_by` VARCHAR(20), IN `p_sort_order` VARCHAR(4), IN `p_limit` INT, IN `p_offset` INT)   BEGIN
    SET @sql = 'SELECT p.*, COUNT(gk.id) as available_keys FROM products p ';
    SET @sql = CONCAT(@sql, 'LEFT JOIN game_keys gk ON gk.product_id = p.id AND gk.is_sold = 0 ');
    SET @sql = CONCAT(@sql, 'WHERE p.is_active = 1 ');
    
    IF p_search_term IS NOT NULL AND p_search_term != '' THEN
        SET @sql = CONCAT(@sql, 'AND (p.name LIKE "%', p_search_term, '%" OR p.short_description LIKE "%', p_search_term, '%") ');
    END IF;
    
    IF p_platform IS NOT NULL AND p_platform != '' THEN
        SET @sql = CONCAT(@sql, 'AND p.platform = "', p_platform, '" ');
    END IF;
    
    IF p_tag IS NOT NULL AND p_tag != '' THEN
        SET @sql = CONCAT(@sql, 'AND p.tag = "', p_tag, '" ');
    END IF;
    
    IF p_min_price IS NOT NULL THEN
        SET @sql = CONCAT(@sql, 'AND p.price >= ', p_min_price, ' ');
    END IF;
    
    IF p_max_price IS NOT NULL THEN
        SET @sql = CONCAT(@sql, 'AND p.price <= ', p_max_price, ' ');
    END IF;
    
    SET @sql = CONCAT(@sql, 'GROUP BY p.id ');
    
    IF p_in_stock_only THEN
        SET @sql = CONCAT(@sql, 'HAVING available_keys > 0 ');
    END IF;
    
    IF p_sort_by IS NOT NULL THEN
        SET @sql = CONCAT(@sql, 'ORDER BY ', p_sort_by, ' ', COALESCE(p_sort_order, 'ASC'), ' ');
    ELSE
        SET @sql = CONCAT(@sql, 'ORDER BY p.name ASC ');
    END IF;
    
    IF p_limit IS NOT NULL THEN
        SET @sql = CONCAT(@sql, 'LIMIT ', p_limit, ' ');
        IF p_offset IS NOT NULL THEN
            SET @sql = CONCAT(@sql, 'OFFSET ', p_offset);
        END IF;
    END IF;
    
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_toggle_favorite` (IN `p_user_id` INT, IN `p_product_id` INT, OUT `p_is_favorite` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_exists INT;
    
    SELECT COUNT(*) INTO v_exists 
    FROM favorites 
    WHERE user_id = p_user_id AND product_id = p_product_id;
    
    IF v_exists > 0 THEN
        DELETE FROM favorites WHERE user_id = p_user_id AND product_id = p_product_id;
        SET p_is_favorite = FALSE;
        SET p_message = 'Eltávolítva a kedvencekből';
    ELSE
        INSERT INTO favorites (user_id, product_id) VALUES (p_user_id, p_product_id);
        SET p_is_favorite = TRUE;
        SET p_message = 'Hozzáadva a kedvencekhez';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_toggle_user_status` (IN `p_user_id` INT, IN `p_is_active` BOOLEAN, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    UPDATE users SET is_active = p_is_active WHERE id = p_user_id;
    
    IF ROW_COUNT() > 0 THEN
        SET p_success = TRUE;
        SET p_message = IF(p_is_active, 'Felhasználó aktiválva', 'Felhasználó deaktiválva');
    ELSE
        SET p_success = FALSE;
        SET p_message = 'Felhasználó nem található';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_cart_quantity` (IN `p_cart_id` INT, IN `p_quantity` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_product_id INT;
    DECLARE v_available INT;
    
    SELECT product_id INTO v_product_id FROM cart WHERE id = p_cart_id;
    
    IF v_product_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Kosárelem nem található';
    ELSE
        SELECT COUNT(*) INTO v_available 
        FROM game_keys 
        WHERE product_id = v_product_id AND is_sold = 0;
        
        IF p_quantity > v_available THEN
            SET p_success = FALSE;
            SET p_message = CONCAT('Maximum ', v_available, ' db rendelhető');
        ELSEIF p_quantity <= 0 THEN
            DELETE FROM cart WHERE id = p_cart_id;
            SET p_success = TRUE;
            SET p_message = 'Termék eltávolítva a kosárból';
        ELSE
            UPDATE cart SET quantity = p_quantity WHERE id = p_cart_id;
            SET p_success = TRUE;
            SET p_message = 'Kosár frissítve';
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_product` (IN `p_product_id` INT, IN `p_name` VARCHAR(200), IN `p_short_description` TEXT, IN `p_long_description` TEXT, IN `p_platform` ENUM('pc','ps','xbox','switch'), IN `p_tag` ENUM('top','new','sale','normal'), IN `p_price` INT, IN `p_original_price` INT, IN `p_image_url` VARCHAR(255), IN `p_is_active` BOOLEAN, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_discount INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
    END;
    
    IF p_original_price IS NOT NULL AND p_original_price > p_price THEN
        SET v_discount = ROUND((1 - p_price / p_original_price) * 100);
    END IF;
    
    UPDATE products SET
        name = COALESCE(p_name, name),
        short_description = COALESCE(p_short_description, short_description),
        long_description = COALESCE(p_long_description, long_description),
        platform = COALESCE(p_platform, platform),
        tag = COALESCE(p_tag, tag),
        price = COALESCE(p_price, price),
        original_price = p_original_price,
        discount_percent = v_discount,
        image_url = COALESCE(p_image_url, image_url),
        is_active = COALESCE(p_is_active, is_active)
    WHERE id = p_product_id;
    
    IF ROW_COUNT() > 0 THEN
        SET p_success = TRUE;
        SET p_message = 'Termék sikeresen frissítve';
    ELSE
        SET p_success = FALSE;
        SET p_message = 'Termék nem található';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_user_profile` (IN `p_user_id` INT, IN `p_full_name` VARCHAR(100), IN `p_phone` VARCHAR(20), IN `p_email` VARCHAR(100), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_existing_email INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    SELECT COUNT(*) INTO v_existing_email 
    FROM users 
    WHERE email = p_email AND id != p_user_id;
    
    IF v_existing_email > 0 THEN
        SET p_success = FALSE;
        SET p_message = 'Ez az email cím már használatban van';
        ROLLBACK;
    ELSE
        UPDATE users 
        SET full_name = COALESCE(p_full_name, full_name),
            phone = COALESCE(p_phone, phone),
            email = COALESCE(p_email, email)
        WHERE id = p_user_id;
        
        SET p_success = TRUE;
        SET p_message = 'Profil sikeresen frissítve';
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_create` (IN `p_username` VARCHAR(50), IN `p_email` VARCHAR(100), IN `p_password_hash` VARCHAR(255), IN `p_full_name` VARCHAR(100), IN `p_phone` VARCHAR(20), IN `p_role` ENUM('user','seller','admin'), OUT `p_new_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_new_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;

    START TRANSACTION;

    IF EXISTS (SELECT 1 FROM users WHERE username = p_username) THEN
        SET p_new_id = 0;
        SET p_success = FALSE;
        SET p_message = 'A felhasználónév már foglalt';
        ROLLBACK;
    ELSEIF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
        SET p_new_id = 0;
        SET p_success = FALSE;
        SET p_message = 'Az e-mail cím már foglalt';
        ROLLBACK;
    ELSE
        INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active)
        VALUES (p_username, p_email, p_password_hash, p_full_name, p_phone, IFNULL(p_role, 'user'), 1);

        SET p_new_id = LAST_INSERT_ID();
        SET p_success = TRUE;
        SET p_message = 'Felhasználó sikeresen létrehozva';
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_delete` (IN `p_id` INT, IN `p_hard_delete` BOOLEAN, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Hiba történt a felhasználó törlése során';
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A felhasználó nem található';
    ELSEIF p_hard_delete = TRUE THEN
        DELETE FROM users WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Felhasználó véglegesen törölve';
    ELSE
        UPDATE users SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = p_id;
        SET p_success = TRUE;
        SET p_message = 'Felhasználó inaktiválva';
    END IF;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_get_all` (IN `p_page` INT, IN `p_page_size` INT, IN `p_role` ENUM('user','seller','admin'), IN `p_is_active` TINYINT)   BEGIN
    DECLARE v_offset INT;

    IF p_page IS NOT NULL AND p_page_size IS NOT NULL THEN
        SET v_offset = (p_page - 1) * p_page_size;

        SELECT COUNT(*) AS total
        FROM users
        WHERE (p_role IS NULL OR role = p_role)
          AND (p_is_active IS NULL OR is_active = p_is_active);

        SELECT id, username, email, full_name, phone, role, is_active,
               created_at, last_login_at
        FROM users
        WHERE (p_role IS NULL OR role = p_role)
          AND (p_is_active IS NULL OR is_active = p_is_active)
        ORDER BY id ASC
        LIMIT v_offset, p_page_size;
    ELSE
        SELECT id, username, email, full_name, phone, role, is_active, created_at
        FROM users
        ORDER BY id ASC;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_get_by_id` (IN `p_id` INT)   BEGIN
    SELECT id, username, email, full_name, phone, role, is_active, 
           created_at, updated_at, last_login_at
    FROM users
    WHERE id = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_search` (IN `p_search_term` VARCHAR(100), IN `p_limit` INT)   BEGIN
    DECLARE v_limit INT;
    SET v_limit = IFNULL(p_limit, 20);
    
    SELECT id, username, email, full_name, phone, role, is_active, created_at
    FROM users
    WHERE username LIKE CONCAT('%', p_search_term, '%')
       OR email LIKE CONCAT('%', p_search_term, '%')
       OR full_name LIKE CONCAT('%', p_search_term, '%')
    ORDER BY 
        CASE WHEN username LIKE CONCAT(p_search_term, '%') THEN 0 ELSE 1 END,
        username
    LIMIT v_limit;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_users_update` (IN `p_id` INT, IN `p_username` VARCHAR(50), IN `p_email` VARCHAR(100), IN `p_full_name` VARCHAR(100), IN `p_phone` VARCHAR(20), IN `p_role` ENUM('user','seller','admin'), IN `p_is_active` TINYINT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = FALSE;
        SET p_message = 'Adatbázis hiba történt';
        ROLLBACK;
    END;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_id) THEN
        SET p_success = FALSE;
        SET p_message = 'A felhasználó nem található';
        ROLLBACK;
    ELSE
        UPDATE users SET
            username = IFNULL(p_username, username),
            email = IFNULL(p_email, email),
            full_name = IFNULL(p_full_name, full_name),
            phone = IFNULL(p_phone, phone),
            role = IFNULL(p_role, role),
            is_active = IFNULL(p_is_active, is_active)
        WHERE id = p_id;

        SET p_success = TRUE;
        SET p_message = 'Felhasználó frissítve';
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_validate_coupon` (IN `p_code` VARCHAR(50), IN `p_order_total` INT, OUT `p_discount_amount` INT, OUT `p_is_valid` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_discount_type VARCHAR(10);
    DECLARE v_discount_value INT;
    DECLARE v_min_order_value INT;
    DECLARE v_max_uses INT;
    DECLARE v_used_count INT;
    DECLARE v_valid_from TIMESTAMP;
    DECLARE v_valid_until TIMESTAMP;
    DECLARE v_is_active BOOLEAN;
    
    SELECT discount_type, discount_value, min_order_value, max_uses, used_count, valid_from, valid_until, is_active
    INTO v_discount_type, v_discount_value, v_min_order_value, v_max_uses, v_used_count, v_valid_from, v_valid_until, v_is_active
    FROM coupons
    WHERE code = p_code;
    
    IF v_discount_type IS NULL THEN
        SET p_discount_amount = 0;
        SET p_is_valid = FALSE;
        SET p_message = 'Érvénytelen kuponkód';
    ELSEIF NOT v_is_active THEN
        SET p_discount_amount = 0;
        SET p_is_valid = FALSE;
        SET p_message = 'Ez a kupon inaktív';
    ELSEIF NOW() < v_valid_from THEN
        SET p_discount_amount = 0;
        SET p_is_valid = FALSE;
        SET p_message = 'Ez a kupon még nem érvényes';
    ELSEIF NOW() > v_valid_until THEN
        SET p_discount_amount = 0;
        SET p_is_valid = FALSE;
        SET p_message = 'Ez a kupon lejárt';
    ELSEIF v_max_uses IS NOT NULL AND v_used_count >= v_max_uses THEN
        SET p_discount_amount = 0;
        SET p_is_valid = FALSE;
        SET p_message = 'Ez a kupon elérte a maximális felhasználási számot';
    ELSEIF p_order_total < v_min_order_value THEN
        SET p_discount_amount = 0;
        SET p_is_valid = FALSE;
        SET p_message = CONCAT('Minimum rendelési érték: ', v_min_order_value, ' Ft');
    ELSE
        IF v_discount_type = 'percent' THEN
            SET p_discount_amount = ROUND(p_order_total * v_discount_value / 100);
        ELSE
            SET p_discount_amount = v_discount_value;
        END IF;
        
        IF p_discount_amount > p_order_total THEN
            SET p_discount_amount = p_order_total;
        END IF;
        
        SET p_is_valid = TRUE;
        SET p_message = CONCAT('Kupon érvényes! Kedvezmény: ', p_discount_amount, ' Ft');
    END IF;
END$$

--
-- Függvények
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_generate_order_number` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
    DECLARE v_order_number VARCHAR(20);
    DECLARE v_exists INT DEFAULT 1;
    
    WHILE v_exists > 0 DO
        SET v_order_number = CONCAT('GC', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(FLOOR(RAND() * 10000), 4, '0'));
        SELECT COUNT(*) INTO v_exists FROM orders WHERE order_number = v_order_number;
    END WHILE;
    
    RETURN v_order_number;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int NOT NULL,
  `table_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `audit_log`
--

INSERT INTO `audit_log` (`id`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `user_id`, `ip_address`, `created_at`) VALUES
(1, 'users', 3, 'UPDATE', '{\"role\": \"user\", \"email\": \"tabikevin@icloud.com\", \"username\": \"tabikevin\", \"is_active\": 1}', '{\"role\": \"user\", \"email\": \"tabikevin@icloud.com\", \"username\": \"tabikevin\", \"is_active\": 1}', NULL, NULL, '2026-02-23 08:19:55');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `cart`
--

CREATE TABLE `cart` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `coupons`
--

CREATE TABLE `coupons` (
  `id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percent','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` int NOT NULL,
  `min_order_value` int DEFAULT '0',
  `max_uses` int DEFAULT NULL,
  `used_count` int DEFAULT '0',
  `valid_from` timestamp NOT NULL,
  `valid_until` timestamp NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order_value`, `max_uses`, `used_count`, `valid_from`, `valid_until`, `is_active`, `created_at`) VALUES
(1, 'WELCOME10', 'percent', 10, 5000, 100, 0, '2026-02-22 19:46:50', '2027-02-22 19:46:50', 1, '2026-02-22 19:46:50'),
(2, 'SUMMER2024', 'percent', 15, 10000, 50, 0, '2026-02-22 19:46:50', '2026-05-22 18:46:50', 1, '2026-02-22 19:46:50'),
(3, 'FLAT1000', 'fixed', 1000, 8000, NULL, 0, '2026-02-22 19:46:50', '2026-08-22 18:46:50', 1, '2026-02-22 19:46:50');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `favorites`
--

CREATE TABLE `favorites` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `game_keys`
--

CREATE TABLE `game_keys` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `key_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_sold` tinyint(1) DEFAULT '0',
  `sold_to_user_id` int DEFAULT NULL,
  `sold_at` timestamp NULL DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `seller_id` int DEFAULT NULL,
  `seller_price` int DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `game_keys`
--

INSERT INTO `game_keys` (`id`, `product_id`, `key_code`, `is_sold`, `sold_to_user_id`, `sold_at`, `order_id`, `seller_id`, `seller_price`, `is_approved`, `created_at`) VALUES
(1, 1, 'WAJHZ-X6CPA-GSAGW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(2, 1, 'THU3A-3YLE8-LCD5V', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(3, 1, '3ZT9N-T4V5U-YBHKC', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(4, 2, 'HDJWM-MGJ7W-VFZFN', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(5, 2, '9WTX4-2HBLJ-G86LW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(6, 2, 'N7QJH-TJU6N-H9SCB', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(7, 3, 'DW3PC-N9S95-AZXTJ', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(8, 3, 'WDPQ8-6JSF7-5KWVE', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(9, 4, '2T2SA-LA764-KB68C-RC22E-RTJ5P', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(10, 4, 'GTZGK-9WQSD-HLUHH-CWH65-CHXGE', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(11, 4, '7UR33-GDPPQ-ZX9DN-L5HGQ-PJH7Q', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(12, 5, '5TB94-975FR-GNBN9', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(13, 5, 'J3QP8-9TYEK-8UTZB', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(14, 5, 'US5F8-CFVXH-D6HVV', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(15, 6, 'PUS7G-YHNXK-L2CQ9', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(16, 6, '9CGJ7-66MF4-YV9WA', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(17, 7, '4KX8-EDDT-JVYG-WJR6', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(18, 7, '5CPJ-A2WJ-ZTPA-C66T', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(19, 8, '4UEE-K635-6GHD-26PV', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(20, 8, 'E759-36AZ-L735-3J3D', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(21, 9, '55H4Q-K3HAG-L58JW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(22, 9, 'N9T8D-9F8JD-PZLVS', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(23, 9, 'NUJYA-7TZZX-MCXLL', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(24, 10, '5ZKKP-NKEP8-X6VKT', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(25, 10, 'AKPUW-QQGR6-3FCSW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(26, 10, 'L42XH-GAHR5-CPWGY', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(27, 11, 'RHWA-22DP-F8SB-H5Q3', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(28, 11, 'X9V8-6VZS-4T6Z-RJHW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(29, 11, '2SWJ-CKJL-TEHY-YCPT', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(30, 12, 'PGP6U-Y52NA-M258P-ZTVHH-PALXN', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(31, 12, 'FQEVA-NUAWE-QBNGL-2N24J-CAT9M', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(32, 12, 'W2W28-GAEEX-UGY2F-VZD48-DAKX8', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(33, 13, 'NYCY-WD25-VD93-MPMS', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(34, 13, 'L54D-8W4Y-PZ8J-3TRP', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(35, 13, 'ZJ54-C6HQ-VNA5-FG3L', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(36, 14, '6YJA8-CZR2Y-WR3CH-YKURS-PZLYJ', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(37, 14, 'JDGDT-2FGRZ-9SKDG-HFATJ-9TYE5', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(38, 14, 'R5URQ-FB8R4-NCWBE-UK9D2-V3HSQ', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(39, 15, 'Q59K-VVZ8-GGXF-FCAQ', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(40, 15, 'VKHY-YQX7-3WX7-PTW7', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(41, 15, '4CFK-ZUKD-YY8S-RCBP', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(42, 16, 'LJC84-U89XJ-B2RW7-FUWRC-MLX5L', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(43, 16, 'YK84T-QLL9N-S9XTP-GM2W2-GT7QY', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(44, 16, 'D9VHF-TTC97-QD4RY-SJ49H-TN7S6', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(45, 17, '5J3P7-S4KKU-9RETM', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(46, 17, 'TTQLG-YUH2B-ZY3NX', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(47, 17, '49RBS-U55QS-QZPWE', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(48, 18, 'R9LY-W555-NLY2-5BCW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(49, 18, '79ZP-DW5Q-Y6B3-KMES', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(50, 18, 'U3FC-5VH7-EQJJ-A36X', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(51, 19, 'FQMUW-PJ5GN-RHUU9-K9XJU-XZBVR', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(52, 19, '6K3VM-WVXZX-4W6WK-QUZCK-ZFET9', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(53, 19, 'S74J4-R3ZLD-8E85Z-983M3-ATQXX', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(54, 20, 'U48DH-A6T7H-C47KP', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(55, 20, 'E8KRD-6EQXZ-8PZEP', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(56, 20, 'DRP8B-MQ85D-XT9MN', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(57, 21, 'GD5Q-XWVA-3HEU-C2GG', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(58, 21, '5LE6-A5EE-JFXA-A3HL', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(59, 21, 'FBZS-ZR2S-DS8B-35SQ', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(60, 22, '8BRNX-R7CCV-CJWTL-9SQVD-Y5W2Z', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(61, 22, 'GQHLQ-PDPXM-E7C4C-DZ3TU-TLDMX', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(62, 22, '25Z8V-MUGXH-D5M2U-3594V-WA74J', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(63, 23, 'FYKLA-5UNEW-AZGPL', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(64, 23, 'MZ2UC-BFVXJ-XRQJ2', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(65, 23, 'DPKXR-XBNVA-KGEJL', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(66, 24, 'AZFN-YS43-C5BA-75UU', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(67, 24, 'YPDA-L3V4-7C5H-USNK', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(68, 24, 'LLFS-DS6M-Z44H-EGV2', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(69, 25, 'WF2R2-2Q7UW-V5WEC-QKJBS-KQB4C', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(70, 25, '55VSQ-T36Q3-WLREB-D6LYS-FHQQS', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(71, 25, 'FMKPL-V3WCD-XKZX7-5LUEM-8YNV8', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(72, 26, 'KN34-X4ZX-SWPM', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(73, 26, 'MFG8-RHEC-5D24', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(74, 26, '6BL2-ENF4-23FQ', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(75, 27, 'PXHQK-ZQTK3-R4M89', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(76, 27, 'QKNS8-43EJW-5TD55', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(77, 27, 'K2J6E-P8HQM-ABSH9', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(78, 28, 'MA74W-3EK4Y-EYQAC-J4TZS-DKKBP', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(79, 28, '3QD6V-ASHEP-VHPXC-9CSS9-TNRW9', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(80, 28, 'JA3MZ-W2ZLB-T4F2Q-YW3C2-QKBGB', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(81, 29, '7S9TJ-2GM25-LDM6Z', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(82, 29, '6N9RR-7S3ZC-V4TLC', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(83, 29, 'XKVPY-MB5M9-J98CW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(84, 30, 'M3X8-EV3B-C2MN-UVX8', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(85, 30, 'M2US-NWHD-ZRNT-JJQ9', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(86, 30, 'K7RD-5Q6Q-CX5L-MCT6', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(87, 31, '5Y7W-3SDG-E3AT-M3TV', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(88, 31, 'CK9Z-S24C-9WQX-M62W', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(89, 31, 'F8P7-BDEF-LYM8-65JW', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(90, 32, 'TEKTS-F8EXZ-V4U4A-BWUW2-PWRWK', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(91, 32, '8R3XK-CBQRG-VLYZ5-9EMTL-QJHDK', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(92, 32, 'NVH5F-LUL2R-SRKA8-S8GMB-R6WRT', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(93, 33, '5P6Z-2MNU-GTCS', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(94, 33, '2J9X-D9N3-L82G', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(95, 33, 'SSBE-LRQV-SLVF', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(96, 34, '9ZKL4-TYK4M-X9U3Z', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(97, 34, 'YAR83-2UZUF-WV4EX', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(98, 34, 'B8DAL-EYP27-5ZBEG', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(99, 35, 'LXSLF-7LM2Z-WYVGH', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(100, 35, 'TH9KK-GYLM7-3JDXN', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(101, 35, '9483K-KYMQJ-RGT7Y', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(102, 36, 'EVUHX-SWBPY-D2AT7', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(103, 36, 'P76RG-8LW7C-UTZ77', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(104, 36, 'F6F79-NR787-6AUD9', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(105, 37, 'K9TR-85QG-DFQJ-FZ3U', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(106, 37, '2F56-4SCX-FELH-JH28', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(107, 37, 'KZAW-YBDK-NS6Y-KD7K', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(108, 38, 'VHEEZ-VPTRT-XGH25-CQYCU-BTSUE', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(109, 38, 'LSDGU-CS3QS-QBQ3Z-N4ZUB-MC97C', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(110, 38, '7BP22-9WP9N-57NXX-TWMFT-SZHAA', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(111, 39, 'KXTT4-HMJ7Z-D3P26', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(112, 39, 'AGDBV-YBZNH-G5BSK', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(113, 39, '4ZLVX-LKEXH-KB867', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(114, 40, 'WPR9-8X3L-PEN2-R5KY', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(115, 40, '37T8-TEHG-W75C-ZGJX', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(116, 40, 'V5G2-ZTR5-L8A7-8D9R', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(117, 41, 'HVG7T-2NTMJ-SRD9R-47MPU-HEJ7U', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(118, 41, 'PELN4-RWXJ4-8WRF3-FZR7T-WBB58', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50'),
(119, 41, 'X2P4H-YASMV-XVRRA-TASJ9-A4X37', 0, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-22 19:46:50');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `order_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_price` int NOT NULL,
  `status` enum('pending','paid','cancelled','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_method` enum('online_card','bank_transfer','paypal','cash') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_transaction_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_zip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_tax_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Eseményindítók `orders`
--
DELIMITER $$
CREATE TRIGGER `tr_before_order_insert` BEFORE INSERT ON `orders` FOR EACH ROW BEGIN
    IF NEW.order_number IS NULL THEN
        SET NEW.order_number = fn_generate_order_number();
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_orders_audit_update` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_log (table_name, record_id, action, old_values, new_values)
        VALUES ('orders', OLD.id, 'UPDATE',
                JSON_OBJECT('status', OLD.status),
                JSON_OBJECT('status', NEW.status));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` int NOT NULL,
  `total_price` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `long_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `platform` enum('pc','ps','xbox','switch') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'action',
  `tag` enum('top','new','sale','normal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `price` int NOT NULL,
  `original_price` int DEFAULT NULL,
  `discount_percent` int DEFAULT '0',
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `view_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `products`
--

INSERT INTO `products` (`id`, `name`, `short_description`, `long_description`, `platform`, `category`, `tag`, `price`, `original_price`, `discount_percent`, `image_url`, `is_active`, `view_count`, `created_at`, `updated_at`) VALUES
(1, 'Cyberpunk 2077', 'Nyílt világú akció RPG futurisztikus Night City-ben', 'A Cyberpunk 2077 egy nyílt világú akció-kalandjáték, amely a sötét jövő Night City nevű megalopoliszában játszódik.', 'pc', 'rpg', 'top', 8990, 14990, 40, 'cyberpunk2077_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(2, 'Elden Ring', 'Epikus fantasy akció RPG a Dark Souls alkotóitól', 'A FromSoftware és George R.R. Martin közös alkotása, egy hatalmas fantasy világban.', 'pc', 'rpg', 'top', 12990, NULL, 0, 'elden_ring_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(3, 'EA Sports FC 25', 'A legújabb EA Sports futball szimulátor', 'Az EA Sports FC 25 a világ legnépszerűbb futballszimulátorának legújabb része.', 'pc', 'sport', 'new', 15490, NULL, 0, 'ea_fc25_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(4, 'GTA V Premium Edition', 'Grand Theft Auto V teljes kiadás', 'A GTA V Premium Edition tartalmazza a teljes Grand Theft Auto V játékot és a GTA Online-t.', 'xbox', 'action', 'sale', 4990, 9990, 50, 'gta5_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(5, 'The Witcher 3: Wild Hunt', 'Legendás fantasy RPG Geralt kalandjaival', 'A The Witcher 3: Wild Hunt egy történetvezérelt, nyílt világú RPG.', 'pc', 'rpg', 'sale', 2990, 7990, 63, 'witcher3_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(6, 'Minecraft Java & Bedrock', 'A világ legnépszerűbb sandbox játéka', 'A Minecraft egy sandbox videojáték, amelyben gyakorlatilag bármit építhetsz.', 'pc', 'sandbox', 'top', 7490, NULL, 0, 'minecraft_java_bedrock_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(7, 'Red Dead Redemption 2', 'Epikus western kaland a Vadnyugaton', 'Az Arthur Morgan és a Van der Linde banda történetét mesélő epikus western.', 'ps', 'action', 'normal', 9990, 14990, 33, 'rdr2_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(8, 'God of War Ragnarök', 'Kratos és Atreus északi kalandja', 'A God of War folytatása, Kratos és Atreus Ragnarök előtti kalandja.', 'ps', 'action', 'top', 16990, NULL, 0, 'god_of_war_ragnarok_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(9, 'Hogwarts Legacy', 'Varázslatos kaland a Roxfort világában', 'Nyílt világú akció RPG a Harry Potter univerzumban.', 'pc', 'rpg', 'new', 13990, NULL, 0, 'hogwarts_legacy_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(10, 'Steam Wallet 10€', 'Steam egyenleg feltöltés', 'Steam egyenleg feltöltés 10 euró értékben.', 'pc', 'other', 'normal', 4490, NULL, 0, 'steam_wallet_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(11, 'Cyberpunk 2077 PS', 'Nyílt világú akció RPG futurisztikus Night City-ben', NULL, 'ps', 'rpg', 'normal', 8990, NULL, 0, 'cyberpunk2077_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(12, 'Cyberpunk 2077 Xbox', 'Nyílt világú akció RPG futurisztikus Night City-ben', NULL, 'xbox', 'rpg', 'normal', 9490, NULL, 0, 'cyberpunk2077_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(13, 'Elden Ring PS', 'Epikus fantasy akció RPG a Dark Souls alkotóitól', NULL, 'ps', 'rpg', 'normal', 12990, NULL, 0, 'elden_ring_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(14, 'Elden Ring Xbox', 'Epikus fantasy akció RPG a Dark Souls alkotóitól', NULL, 'xbox', 'rpg', 'normal', 13490, NULL, 0, 'elden_ring_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(15, 'EA Sports FC 25 PS', 'A legújabb EA Sports futball szimulátor', NULL, 'ps', 'sport', 'normal', 15990, NULL, 0, 'ea_fc25_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(16, 'EA Sports FC 25 Xbox', 'A legújabb EA Sports futball szimulátor', NULL, 'xbox', 'sport', 'normal', 15490, NULL, 0, 'ea_fc25_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(17, 'GTA V Premium Edition PC', 'Grand Theft Auto V teljes kiadás', NULL, 'pc', 'action', 'normal', 4990, NULL, 0, 'gta5_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(18, 'GTA V Premium Edition PS', 'Grand Theft Auto V teljes kiadás', NULL, 'ps', 'action', 'normal', 5490, NULL, 0, 'gta5_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(19, 'GTA V Premium Edition Xbox', 'Grand Theft Auto V teljes kiadás', NULL, 'xbox', 'action', 'normal', 4990, NULL, 0, 'gta5_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(20, 'The Witcher 3: Wild Hunt PC', 'Legendás fantasy RPG Geralt kalandjaival', NULL, 'pc', 'rpg', 'normal', 3490, NULL, 0, 'witcher3_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(21, 'The Witcher 3: Wild Hunt PS', 'Legendás fantasy RPG Geralt kalandjaival', NULL, 'ps', 'rpg', 'normal', 3490, NULL, 0, 'witcher3_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(22, 'The Witcher 3: Wild Hunt Xbox', 'Legendás fantasy RPG Geralt kalandjaival', NULL, 'xbox', 'rpg', 'normal', 3490, NULL, 0, 'witcher3_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(23, 'Minecraft PC', 'A világ legnépszerűbb sandbox játéka', NULL, 'pc', 'sandbox', 'normal', 7990, NULL, 0, 'minecraft_java_bedrock_cover.png', 0, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(24, 'Minecraft PS', 'A világ legnépszerűbb sandbox játéka', NULL, 'ps', 'sandbox', 'normal', 7990, NULL, 0, 'minecraft_java_bedrock_cover.png', 0, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(25, 'Minecraft Xbox', 'A világ legnépszerűbb sandbox játéka', NULL, 'xbox', 'sandbox', 'normal', 7990, NULL, 0, 'minecraft_java_bedrock_cover.png', 0, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(26, 'Minecraft Switch', 'A világ legnépszerűbb sandbox játéka', NULL, 'switch', 'sandbox', 'normal', 8490, NULL, 0, 'minecraft_java_bedrock_cover.png', 0, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(27, 'Red Dead Redemption 2 PC', 'Epikus western kaland a Vadnyugaton', NULL, 'pc', 'action', 'normal', 9990, NULL, 0, 'rdr2_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(28, 'Red Dead Redemption 2 Xbox', 'Epikus western kaland a Vadnyugaton', NULL, 'xbox', 'action', 'normal', 15990, NULL, 0, 'rdr2_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(29, 'God of War Ragnarök PC', 'Kratos és Atreus északi kalandja', NULL, 'pc', 'action', 'normal', 17490, NULL, 0, 'god_of_war_ragnarok_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(30, 'God of War Ragnarök PS', 'Kratos és Atreus északi kalandja', NULL, 'ps', 'action', 'normal', 16990, NULL, 0, 'god_of_war_ragnarok_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(31, 'Hogwarts Legacy PS', 'Varázslatos kaland a Roxfort világában', NULL, 'ps', 'rpg', 'normal', 14490, NULL, 0, 'hogwarts_legacy_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(32, 'Hogwarts Legacy Xbox', 'Varázslatos kaland a Roxfort világában', NULL, 'xbox', 'rpg', 'normal', 14490, NULL, 0, 'hogwarts_legacy_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(33, 'Hogwarts Legacy Switch', 'Varázslatos kaland a Roxfort világában', NULL, 'switch', 'rpg', 'normal', 12990, NULL, 0, 'hogwarts_legacy_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(34, 'Valorant Points 475', 'Valorant pont csomag', NULL, 'pc', 'other', 'normal', 12990, NULL, 0, 'valorant_points_cover.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(35, 'Steam Wallet 10€ PC', 'Steam egyenleg feltöltés 10 euró értékben', NULL, 'pc', 'other', 'normal', 3900, NULL, 0, 'steam_wallet_thumb.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(36, 'FIFA 25 PC', 'A legújabb FIFA fociszimulátoros játék', NULL, 'pc', 'sport', 'normal', 15490, NULL, 0, 'fifa25_thumb.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(37, 'FIFA 25 PS', 'A legújabb FIFA fociszimulátoros játék', NULL, 'ps', 'sport', 'normal', 15990, NULL, 0, 'fifa25_thumb.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(38, 'FIFA 25 Xbox', 'A legújabb FIFA fociszimulátoros játék', NULL, 'xbox', 'sport', 'normal', 15990, NULL, 0, 'fifa25_thumb.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(39, 'Assassin\'s Creed Valhalla PC', 'Viking kalandok az északi mitológia világában', NULL, 'pc', 'action', 'normal', 12990, NULL, 0, 'ac_valhalla_thumb.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(40, 'Assassin\'s Creed Valhalla PS', 'Viking kalandok az északi mitológia világában', NULL, 'ps', 'action', 'normal', 13990, NULL, 0, 'ac_valhalla_thumb.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50'),
(41, 'Assassin\'s Creed Valhalla Xbox', 'Viking kalandok az északi mitológia világában', NULL, 'xbox', 'action', 'normal', 13990, NULL, 0, 'ac_valhalla_thumb.png', 1, 0, '2026-02-22 19:46:50', '2026-02-22 19:46:50');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('user','seller','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT '1',
  `failed_login_attempts` int DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- A tábla adatainak kiíratása `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role`, `is_active`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `last_login_at`) VALUES
(1, 'admin', 'admin@gamecube.com', '$2b$10$Db/pHL549lYZ8Axb9cs9N.vPzGrZ72fE1/tApDS0/kbHtCxkfiRK.', 'Rendszergazda', NULL, 'admin', 1, 0, NULL, '2025-10-05 09:12:34', '2026-03-14 16:47:22', '2026-03-14 16:47:22'),
(2, 'testuser', 'test@example.com', '$2b$10$DGUGO4nky29Ka2NAyjTw9.bZbWCKE56n3f6ncNn0vBK2GnEXyWRYC', 'Teszt Felhasználó', '+36301234567', 'user', 1, 0, NULL, '2025-11-18 14:03:51', '2026-02-07 11:29:44', '2026-02-07 11:29:44');

--
-- Eseményindítók `users`
--
DELIMITER $$
CREATE TRIGGER `tr_users_audit_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    INSERT INTO audit_log (table_name, record_id, action, old_values, new_values)
    VALUES ('users', OLD.id, 'UPDATE', 
            JSON_OBJECT('username', OLD.username, 'email', OLD.email, 'role', OLD.role, 'is_active', OLD.is_active),
            JSON_OBJECT('username', NEW.username, 'email', NEW.email, 'role', NEW.role, 'is_active', NEW.is_active));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- A nézet helyettes szerkezete `v_active_products`
-- (Lásd alább az aktuális nézetet)
--
CREATE TABLE `v_active_products` (
`id` int
,`name` varchar(200)
,`short_description` text
,`long_description` text
,`platform` enum('pc','ps','xbox','switch')
,`category` varchar(50)
,`tag` enum('top','new','sale','normal')
,`price` int
,`original_price` int
,`discount_percent` int
,`image_url` varchar(255)
,`view_count` int
,`available_keys` bigint
,`in_stock` int
);

-- --------------------------------------------------------

--
-- A nézet helyettes szerkezete `v_admin_stats`
-- (Lásd alább az aktuális nézetet)
--
CREATE TABLE `v_admin_stats` (
`total_users` bigint
,`active_users` bigint
,`total_products` bigint
,`paid_orders` bigint
,`pending_orders` bigint
,`cancelled_orders` bigint
,`total_revenue` decimal(32,0)
,`available_keys` bigint
,`sold_keys` bigint
,`today_revenue` decimal(32,0)
,`today_orders` bigint
);

-- --------------------------------------------------------

--
-- A nézet helyettes szerkezete `v_daily_revenue`
-- (Lásd alább az aktuális nézetet)
--
CREATE TABLE `v_daily_revenue` (
`date` date
,`order_count` bigint
,`revenue` decimal(32,0)
,`avg_order_value` decimal(14,4)
);

-- --------------------------------------------------------

--
-- A nézet helyettes szerkezete `v_product_stats`
-- (Lásd alább az aktuális nézetet)
--
CREATE TABLE `v_product_stats` (
`id` int
,`name` varchar(200)
,`platform` enum('pc','ps','xbox','switch')
,`price` int
,`available_keys` bigint
,`sold_keys` bigint
,`total_sold` decimal(32,0)
,`total_revenue` decimal(32,0)
,`view_count` int
);

-- --------------------------------------------------------

--
-- A nézet helyettes szerkezete `v_user_orders`
-- (Lásd alább az aktuális nézetet)
--
CREATE TABLE `v_user_orders` (
`order_id` int
,`order_number` varchar(20)
,`user_id` int
,`username` varchar(50)
,`email` varchar(100)
,`total_price` int
,`status` enum('pending','paid','cancelled','refunded')
,`payment_method` enum('online_card','bank_transfer','paypal','cash')
,`created_at` timestamp
,`paid_at` timestamp
,`item_count` bigint
,`total_items` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Nézet szerkezete `v_active_products`
--
DROP TABLE IF EXISTS `v_active_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_products`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`short_description` AS `short_description`, `p`.`long_description` AS `long_description`, `p`.`platform` AS `platform`, `p`.`category` AS `category`, `p`.`tag` AS `tag`, `p`.`price` AS `price`, `p`.`original_price` AS `original_price`, `p`.`discount_percent` AS `discount_percent`, `p`.`image_url` AS `image_url`, `p`.`view_count` AS `view_count`, count(`gk`.`id`) AS `available_keys`, (case when (count(`gk`.`id`) > 0) then 1 else 0 end) AS `in_stock` FROM (`products` `p` left join `game_keys` `gk` on(((`gk`.`product_id` = `p`.`id`) and (`gk`.`is_sold` = 0)))) WHERE (`p`.`is_active` = 1) GROUP BY `p`.`id` ;

-- --------------------------------------------------------

--
-- Nézet szerkezete `v_admin_stats`
--
DROP TABLE IF EXISTS `v_admin_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_admin_stats`  AS SELECT (select count(0) from `users` where (`users`.`role` = 'user')) AS `total_users`, (select count(0) from `users` where ((`users`.`role` = 'user') and (`users`.`is_active` = 1))) AS `active_users`, (select count(0) from `products` where (`products`.`is_active` = 1)) AS `total_products`, (select count(0) from `orders` where (`orders`.`status` = 'paid')) AS `paid_orders`, (select count(0) from `orders` where (`orders`.`status` = 'pending')) AS `pending_orders`, (select count(0) from `orders` where (`orders`.`status` = 'cancelled')) AS `cancelled_orders`, (select coalesce(sum(`orders`.`total_price`),0) from `orders` where (`orders`.`status` = 'paid')) AS `total_revenue`, (select count(0) from `game_keys` where (`game_keys`.`is_sold` = 0)) AS `available_keys`, (select count(0) from `game_keys` where (`game_keys`.`is_sold` = 1)) AS `sold_keys`, (select coalesce(sum(`orders`.`total_price`),0) from `orders` where ((`orders`.`status` = 'paid') and (cast(`orders`.`created_at` as date) = curdate()))) AS `today_revenue`, (select count(0) from `orders` where ((`orders`.`status` = 'paid') and (cast(`orders`.`created_at` as date) = curdate()))) AS `today_orders` ;

-- --------------------------------------------------------

--
-- Nézet szerkezete `v_daily_revenue`
--
DROP TABLE IF EXISTS `v_daily_revenue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_daily_revenue`  AS SELECT cast(`orders`.`created_at` as date) AS `date`, count(0) AS `order_count`, sum(`orders`.`total_price`) AS `revenue`, avg(`orders`.`total_price`) AS `avg_order_value` FROM `orders` WHERE (`orders`.`status` = 'paid') GROUP BY cast(`orders`.`created_at` as date) ORDER BY `date` DESC ;

-- --------------------------------------------------------

--
-- Nézet szerkezete `v_product_stats`
--
DROP TABLE IF EXISTS `v_product_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_product_stats`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`platform` AS `platform`, `p`.`price` AS `price`, count(distinct `gk_available`.`id`) AS `available_keys`, count(distinct `gk_sold`.`id`) AS `sold_keys`, coalesce(sum(`oi`.`quantity`),0) AS `total_sold`, coalesce(sum(`oi`.`total_price`),0) AS `total_revenue`, `p`.`view_count` AS `view_count` FROM ((((`products` `p` left join `game_keys` `gk_available` on(((`gk_available`.`product_id` = `p`.`id`) and (`gk_available`.`is_sold` = 0)))) left join `game_keys` `gk_sold` on(((`gk_sold`.`product_id` = `p`.`id`) and (`gk_sold`.`is_sold` = 1)))) left join `order_items` `oi` on((`oi`.`product_id` = `p`.`id`))) left join `orders` `o` on(((`o`.`id` = `oi`.`order_id`) and (`o`.`status` = 'paid')))) GROUP BY `p`.`id` ;

-- --------------------------------------------------------

--
-- Nézet szerkezete `v_user_orders`
--
DROP TABLE IF EXISTS `v_user_orders`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_user_orders`  AS SELECT `o`.`id` AS `order_id`, `o`.`order_number` AS `order_number`, `o`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `o`.`total_price` AS `total_price`, `o`.`status` AS `status`, `o`.`payment_method` AS `payment_method`, `o`.`created_at` AS `created_at`, `o`.`paid_at` AS `paid_at`, count(`oi`.`id`) AS `item_count`, sum(`oi`.`quantity`) AS `total_items` FROM ((`orders` `o` join `users` `u` on((`u`.`id` = `o`.`user_id`))) left join `order_items` `oi` on((`oi`.`order_id` = `o`.`id`))) GROUP BY `o`.`id` ;

--
-- Indexek a kiírt táblákhoz
--

--
-- A tábla indexei `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_name` (`table_name`),
  ADD KEY `idx_record_id` (`record_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- A tábla indexei `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- A tábla indexei `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- A tábla indexei `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- A tábla indexei `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- A tábla indexei `game_keys`
--
ALTER TABLE `game_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_code` (`key_code`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_is_sold` (`is_sold`),
  ADD KEY `idx_sold_to_user` (`sold_to_user_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_seller_id` (`seller_id`);

--
-- A tábla indexei `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_order_number` (`order_number`);

--
-- A tábla indexei `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- A tábla indexei `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_platform` (`platform`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_tag` (`tag`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_name` (`name`);

--
-- A tábla indexei `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- A kiírt táblák AUTO_INCREMENT értéke
--

--
-- AUTO_INCREMENT a táblához `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT a táblához `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT a táblához `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `game_keys`
--
ALTER TABLE `game_keys`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT a táblához `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT a táblához `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Megkötések a kiírt táblákhoz
--

--
-- Megkötések a táblához `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `game_keys`
--
ALTER TABLE `game_keys`
  ADD CONSTRAINT `game_keys_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_keys_ibfk_2` FOREIGN KEY (`sold_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Megkötések a táblához `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Események
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_clean_old_carts` ON SCHEDULE EVERY 1 DAY STARTS '2026-02-22 20:46:50' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    DELETE FROM cart WHERE user_id IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
END$$

CREATE DEFINER=`root`@`localhost` EVENT `ev_unlock_users` ON SCHEDULE EVERY 5 MINUTE STARTS '2026-02-22 20:46:50' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE users SET locked_until = NULL, failed_login_attempts = 0 
    WHERE locked_until IS NOT NULL AND locked_until <= NOW();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
