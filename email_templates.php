<?php
function buildOrderReceivedEmail($orderData) {
    $orderId    = $orderData['order_id'];
    $userName   = htmlspecialchars($orderData['user_name'], ENT_QUOTES, 'UTF-8');
    $items      = $orderData['items'];
    $totalPrice = number_format($orderData['total_price'], 0, ',', ' ');
    $payment    = htmlspecialchars($orderData['payment_method_label'], ENT_QUOTES, 'UTF-8');
    $dateTime   = date('Y. m. d. H:i');

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemName  = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
        $itemQty   = (int)$item['quantity'];
        $itemPrice = number_format($item['price'] * $item['quantity'], 0, ',', ' ');
        $itemsHtml .= "
            <tr>
                <td style='padding: 10px 15px; color: #e2e8f0; font-size: 14px; border-bottom: 1px solid rgba(168,85,247,0.1);'>{$itemName}</td>
                <td style='padding: 10px 15px; color: #94a3b8; font-size: 14px; text-align: center; border-bottom: 1px solid rgba(168,85,247,0.1);'>{$itemQty} db</td>
                <td style='padding: 10px 15px; color: #06b6d4; font-size: 14px; text-align: right; font-weight: 600; border-bottom: 1px solid rgba(168,85,247,0.1);'>{$itemPrice} Ft</td>
            </tr>";
    }

    return buildEmailLayout(
        "Rendelés fogadva!",
        "🛒 Rendelés #{$orderId}",
        "
        <p style='color: #e2e8f0; font-size: 16px; margin: 0 0 20px;'>Kedves <strong>{$userName}</strong>!</p>
        <p style='color: #e2e8f0; font-size: 15px; line-height: 1.7; margin: 0 0 25px;'>
            Köszönjük a rendelésedet! Az alábbi rendelésed sikeresen beérkezett, és 
            <strong style='color: #a855f7;'>10 percen belül</strong> jóváhagyjuk.
        </p>

        <p style='color: #a855f7; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 12px; font-weight: 700;'>📦 Rendelés részletei</p>
        <table width='100%' cellpadding='0' cellspacing='0' style='background: rgba(255,255,255,0.03); border-radius: 10px; margin-bottom: 20px;'>
            <tr>
                <th style='padding: 12px 15px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; text-align: left; border-bottom: 1px solid rgba(168,85,247,0.2);'>Termék</th>
                <th style='padding: 12px 15px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; text-align: center; border-bottom: 1px solid rgba(168,85,247,0.2);'>Mennyiség</th>
                <th style='padding: 12px 15px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; text-align: right; border-bottom: 1px solid rgba(168,85,247,0.2);'>Ár</th>
            </tr>
            {$itemsHtml}
            <tr>
                <td colspan='2' style='padding: 14px 15px; color: #ffffff; font-size: 16px; font-weight: 700;'>Összesen:</td>
                <td style='padding: 14px 15px; color: #a855f7; font-size: 18px; text-align: right; font-weight: 800;'>{$totalPrice} Ft</td>
            </tr>
        </table>

        <table width='100%' cellpadding='0' cellspacing='0' style='background: rgba(168,85,247,0.08); border: 1px solid rgba(168,85,247,0.2); border-radius: 12px; padding: 5px;'>
            <tr><td style='padding: 15px 20px;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr><td style='padding: 6px 0;'>
                        <span style='color: #94a3b8; font-size: 12px;'>Rendelés szám:</span><br>
                        <span style='color: #f1f5f9; font-size: 15px; font-weight: 600;'>#{$orderId}</span>
                    </td></tr>
                    <tr><td style='padding: 6px 0; border-top: 1px solid rgba(168,85,247,0.15);'>
                        <span style='color: #94a3b8; font-size: 12px;'>Fizetési mód:</span><br>
                        <span style='color: #f1f5f9; font-size: 15px; font-weight: 600;'>{$payment}</span>
                    </td></tr>
                    <tr><td style='padding: 6px 0; border-top: 1px solid rgba(168,85,247,0.15);'>
                        <span style='color: #94a3b8; font-size: 12px;'>Dátum:</span><br>
                        <span style='color: #f1f5f9; font-size: 15px; font-weight: 600;'>{$dateTime}</span>
                    </td></tr>
                </table>
            </td></tr>
        </table>

        <div style='background: rgba(6,182,212,0.08); border-left: 3px solid #06b6d4; border-radius: 0 10px 10px 0; padding: 15px 20px; margin-top: 20px;'>
            <p style='color: #06b6d4; font-size: 14px; font-weight: 600; margin: 0 0 5px;'>⏱️ Mi történik most?</p>
            <p style='color: #94a3b8; font-size: 13px; line-height: 1.6; margin: 0;'>Rendelésedet feldolgozzuk és <strong style=\"color: #e2e8f0;\">10 percen belül jóváhagyjuk</strong>. A jóváhagyás után külön emailben értesítünk, és a játék kulcsokat megtalálod a profilodban.</p>
        </div>
        "
    );
}

function buildOrderApprovedEmail($orderData) {
    $orderId    = $orderData['order_id'];
    $userName   = htmlspecialchars($orderData['user_name'], ENT_QUOTES, 'UTF-8');
    $items      = $orderData['items'];
    $totalPrice = number_format($orderData['total_price'], 0, ',', ' ');
    $dateTime   = date('Y. m. d. H:i');

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemName  = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
        $itemQty   = (int)$item['quantity'];
        $itemsHtml .= "
            <tr>
                <td style='padding: 10px 15px; color: #e2e8f0; font-size: 14px; border-bottom: 1px solid rgba(168,85,247,0.1);'>{$itemName}</td>
                <td style='padding: 10px 15px; color: #94a3b8; font-size: 14px; text-align: center; border-bottom: 1px solid rgba(168,85,247,0.1);'>{$itemQty} db</td>
            </tr>";
    }

    return buildEmailLayout(
        "Rendelés jóváhagyva! ✅",
        "✅ Rendelés #{$orderId} - Jóváhagyva",
        "
        <p style='color: #e2e8f0; font-size: 16px; margin: 0 0 20px;'>Kedves <strong>{$userName}</strong>!</p>
        
        <div style='background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 25px;'>
            <p style='color: #22c55e; font-size: 40px; margin: 0 0 10px;'>✅</p>
            <p style='color: #22c55e; font-size: 20px; font-weight: 700; margin: 0 0 5px;'>Rendelésed jóváhagyva!</p>
            <p style='color: #94a3b8; font-size: 14px; margin: 0;'>Rendelés #{$orderId} • {$dateTime}</p>
        </div>

        <p style='color: #e2e8f0; font-size: 15px; line-height: 1.7; margin: 0 0 25px;'>
            Örömmel értesítünk, hogy a rendelésedet <strong style='color: #22c55e;'>sikeresen jóváhagytuk</strong>! 
            A játék kulcsaidat mostantól megtalálod a <strong style='color: #a855f7;'>profilodban</strong>.
        </p>

        <p style='color: #a855f7; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 12px; font-weight: 700;'>🎮 Megrendelt termékek</p>
        <table width='100%' cellpadding='0' cellspacing='0' style='background: rgba(255,255,255,0.03); border-radius: 10px; margin-bottom: 20px;'>
            <tr>
                <th style='padding: 12px 15px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; text-align: left; border-bottom: 1px solid rgba(168,85,247,0.2);'>Termék</th>
                <th style='padding: 12px 15px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; text-align: center; border-bottom: 1px solid rgba(168,85,247,0.2);'>Mennyiség</th>
            </tr>
            {$itemsHtml}
            <tr>
                <td colspan='2' style='padding: 14px 15px; color: #ffffff; font-size: 16px; font-weight: 700; text-align: center;'>Összesen: <span style=\"color: #a855f7;\">{$totalPrice} Ft</span></td>
            </tr>
        </table>

        <div style='background: rgba(168,85,247,0.08); border-left: 3px solid #a855f7; border-radius: 0 10px 10px 0; padding: 15px 20px; margin-bottom: 20px;'>
            <p style='color: #a855f7; font-size: 14px; font-weight: 600; margin: 0 0 5px;'>🔑 Hol találod a kulcsokat?</p>
            <p style='color: #94a3b8; font-size: 13px; line-height: 1.6; margin: 0;'>Jelentkezz be a fiókodba, menj a <strong style=\"color: #e2e8f0;\">Profil</strong> oldalra, és ott megtalálod az összes megvásárolt játék kulcsot.</p>
        </div>
        ",
        true
    );
}

function buildEmailLayout($headerSubtitle, $bannerText, $bodyContent, $showProfileButton = false) {
    $profileBtn = '';
    if ($showProfileButton) {
        $profileBtn = "
            <table width='100%' cellpadding='0' cellspacing='0' style='margin-top: 28px;'>
                <tr>
                    <td align='center'>
                        <a href='#' style='display: inline-block; background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%); color: #ffffff; text-decoration: none; padding: 16px 44px; border-radius: 50px; font-size: 15px; font-weight: 700; letter-spacing: 0.5px; box-shadow: 0 8px 24px rgba(168,85,247,0.45);'>
                            🎮 &nbsp; Profilom megnyitása
                        </a>
                    </td>
                </tr>
            </table>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameCube</title>
    <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#09051a;font-family:'Segoe UI',Arial,sans-serif;-webkit-text-size-adjust:100%;">

<!-- Outer wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#09051a;padding:32px 16px;">
<tr><td align="center">

    <!-- Email card -->
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,0.6);">

        <!-- ── HEADER ── -->
        <tr>
            <td style="background:linear-gradient(135deg,#1a0b35 0%,#2a1258 100%);padding:36px 44px 28px;text-align:center;border-bottom:2px solid rgba(168,85,247,0.6);">
                <!-- Logo mark -->
                <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 16px;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#a855f7,#06b6d4);width:52px;height:52px;border-radius:14px;text-align:center;vertical-align:middle;font-size:26px;line-height:52px;">
                            🎮
                        </td>
                    </tr>
                </table>
                <h1 style="margin:0 0 6px;color:#ffffff;font-size:26px;font-weight:800;letter-spacing:3px;text-transform:uppercase;">GameCube</h1>
                <p style="margin:0;color:#a855f7;font-size:11px;text-transform:uppercase;letter-spacing:4px;font-weight:600;">{$headerSubtitle}</p>
            </td>
        </tr>

        <!-- ── BANNER BAR ── -->
        <tr>
            <td style="background:linear-gradient(90deg,#7c3aed 0%,#a855f7 50%,#06b6d4 100%);padding:14px 44px;text-align:center;">
                <p style="margin:0;color:#ffffff;font-size:15px;font-weight:700;letter-spacing:0.3px;">{$bannerText}</p>
            </td>
        </tr>

        <!-- ── BODY ── -->
        <tr>
            <td style="background-color:#120828;padding:36px 44px;">
                {$bodyContent}
                {$profileBtn}
            </td>
        </tr>

        <!-- ── DIVIDER ── -->
        <tr>
            <td style="background-color:#0d0620;padding:0 44px;">
                <div style="height:1px;background:linear-gradient(90deg,transparent,rgba(168,85,247,0.4),transparent);"></div>
            </td>
        </tr>

        <!-- ── FOOTER ── -->
        <tr>
            <td style="background-color:#0d0620;padding:24px 44px 28px;border-radius:0 0 20px 20px;">
                <!-- Social / info row -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="padding-bottom:14px;">
                            <span style="display:inline-block;background:rgba(168,85,247,0.12);border:1px solid rgba(168,85,247,0.25);border-radius:50px;padding:5px 16px;color:#a855f7;font-size:12px;font-weight:600;">✉️ &nbsp; support@gamecube.hu</span>
                        </td>
                    </tr>
                    <tr>
                        <td align="center">
                            <p style="margin:0 0 4px;color:#475569;font-size:11px;line-height:1.6;">Ez egy automatikusan generált értesítés — kérjük, ne válaszolj rá.</p>
                            <p style="margin:0;color:#334155;font-size:11px;">© 2026 GameCube &nbsp;·&nbsp; Digitális Játék Kulcsok &nbsp;·&nbsp; Minden jog fenntartva.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>
    <!-- / Email card -->

</td></tr>
</table>
<!-- / Outer wrapper -->

</body>
</html>
HTML;
}
