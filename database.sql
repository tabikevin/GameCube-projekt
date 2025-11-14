CREATE DATABASE IF NOT EXISTS gamecube
CHARACTER SET utf8mb4
COLLATE utf8mb4_hungarian_ci;

USE gamecube;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    platform ENUM('pc','ps','xbox','switch') NOT NULL,
    short_description VARCHAR(255) NULL,
    price INT UNSIGNED NOT NULL,
    tag ENUM('top','new','sale') NOT NULL DEFAULT 'top',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO products (name, platform, short_description, price, tag, is_active) VALUES
('Cyberpunk 2077', 'pc', 'Steam kulcs • HU/EU aktiválás', 8990, 'top', 1),
('Elden Ring', 'pc', 'Steam kulcs • Global', 12990, 'top', 1),
('EA Sports FC 25', 'ps', 'PS5 kulcs • EU', 15490, 'new', 1),
('GTA V Premium Edition', 'xbox', 'Xbox One / Series X|S kulcs', 5990, 'sale', 1),
('The Witcher 3: Wild Hunt', 'pc', 'GOG / PC kulcs', 3990, 'sale', 1),
('Minecraft Java & Bedrock', 'pc', 'PC kulcs • Microsoft', 7990, 'top', 1),
('Valorant Points 4750', 'pc', 'Riot balance feltöltő kód', 6490, 'new', 1),
('Steam Wallet 10€', 'pc', 'Digitális ajándékkártya', 2500, 'sale', 1);

CREATE TABLE IF NOT EXISTS game_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    key_code VARCHAR(80) NOT NULL UNIQUE,
    is_sold TINYINT(1) NOT NULL DEFAULT 0,
    sold_to_user_id INT UNSIGNED NULL,
    sold_at DATETIME NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (sold_to_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    total_price INT UNSIGNED NOT NULL,
    status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price INT UNSIGNED NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
