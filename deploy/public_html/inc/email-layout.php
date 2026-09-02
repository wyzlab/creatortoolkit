<?php
/**
 * email-layout.php — wraps email content in the WyzCore branded shell:
 * a navy header, a clean white body, and the standard footer with the legal
 * lines, links, unsubscribe, and address. Table-based for email-client support.
 */

declare(strict_types=1);

/** A per-recipient unsubscribe URL (signed so it cannot be forged). */
function unsubscribe_url(string $email): string
{
    $secrets = require CONFIG_DIR . '/secrets.php';
    $t = substr(hash_hmac('sha256', strtolower(trim($email)), $secrets['csrf_salt']), 0, 32);
    return APP['app_url'] . '/unsubscribe.php?e=' . rawurlencode($email) . '&t=' . $t;
}

/** Wrap inner HTML content in the branded email shell. */
function email_html_wrap(string $innerHtml, string $unsubUrl): string
{
    $navy = '#003D7A';
    $blue = '#1C8BF5';
    $ink  = '#2D3748';
    $muted = '#8090a5';
    return '<!DOCTYPE html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#eef2f7;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:24px 0;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Inter,Arial,Helvetica,sans-serif;">'
        // Header: WyzCore Academy logo on white. The <img> loads once the logo
        // file is uploaded to /images/email-logo.png; until then the styled
        // wordmark alt/fallback shows.
        . '<tr><td style="background:#ffffff;padding:24px 32px 16px;text-align:center;border-bottom:1px solid #eef2f7;">'
        . '<img src="' . e(APP['app_url']) . '/images/email-logo.png" alt="WyzCore Academy" width="190" '
        . 'style="display:inline-block;max-width:190px;height:auto;border:0;outline:none;text-decoration:none;">'
        . '</td></tr>'
        // Body
        . '<tr><td style="padding:32px;color:' . $ink . ';font-size:15px;line-height:1.6;">'
        . $innerHtml
        . '</td></tr>'
        // Footer
        . '<tr><td style="background:' . $navy . ';padding:24px 32px;color:#cdd8e6;font-size:12px;line-height:1.6;text-align:center;">'
        . '<p style="margin:0 0 10px;">You received this email because you made a purchase on WyzCore.<br>If this was not you, please contact us immediately at <a href="mailto:support@wyzcore.com" style="color:#9fc3f0;">support@wyzcore.com</a>.</p>'
        . '<p style="margin:0 0 10px;"><a href="' . e($unsubUrl) . '" style="color:#9fc3f0;">Unsubscribe</a> &nbsp;|&nbsp; <a href="https://www.wyzcore.com/privacy" style="color:#9fc3f0;">Privacy Policy</a> &nbsp;|&nbsp; <a href="https://www.wyzcore.com/terms" style="color:#9fc3f0;">Terms of Service</a></p>'
        . '<p style="margin:0 0 10px;"><a href="https://www.wyzcore.com" style="color:#9fc3f0;">www.wyzcore.com</a> &nbsp;|&nbsp; support@wyzcore.com</p>'
        . '<p style="margin:0 0 4px;color:#ffffff;font-weight:600;">WyzCore by WyzLab Solutions</p>'
        . '<p style="margin:0;color:' . $muted . ';">Unit 1015, 10F, Parkway Corporate Center, Corporate Ave,<br>Filinvest, Alabang, Muntinlupa, Metro Manila</p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Wrap inner plain text with the footer lines. */
function email_text_wrap(string $innerText, string $unsubUrl): string
{
    return rtrim($innerText) . "\n\n"
        . "----------------------------------------\n"
        . "You received this email because you made a purchase on WyzCore.\n"
        . "If this was not you, please contact us immediately at support@wyzcore.com.\n\n"
        . "Unsubscribe: " . $unsubUrl . "\n"
        . "Privacy Policy: https://www.wyzcore.com/privacy\n"
        . "Terms of Service: https://www.wyzcore.com/terms\n"
        . "www.wyzcore.com | support@wyzcore.com\n\n"
        . "WyzCore by WyzLab Solutions\n"
        . "Unit 1015, 10F, Parkway Corporate Center, Corporate Ave, Filinvest, Alabang, Muntinlupa, Metro Manila\n";
}
