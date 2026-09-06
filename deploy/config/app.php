<?php
/**
 * Non-secret application settings.  ABOVE public_html.
 * Safe to read, holds no credentials.
 */

return [
    // The toolkit lives on its own subdomain. Confirmed: toolkit.wyzcore.com
    'app_url'       => 'https://toolkit.wyzcore.com',
    'app_name'      => 'DIY Creator Starter Toolkit',
    'brand_line'    => 'WyzLab Studio Originals',
    'academy_line'  => 'WyzCore Academy',
    'contact_email' => 'hello@wyzcore.com',

    // Where to send someone who reaches the claim/thank-you page without a
    // matching purchase (so we can invite them to buy).
    'purchase_url'  => 'https://www.wyzcore.com/en/checkout/?product_id=14644&product_type=digital_product&price_id=308265',

    // The set-password ("you're in") email is a backup for buyers who do not
    // finish on the thank-you page. It is sent this many minutes after the
    // purchase, and ONLY if the account is still unclaimed (no password set).
    // Sent by tools/send-claim-reminders.php on a schedule (Hostinger cron).
    'claim_reminder_delay_minutes' => 30,

    // wyzcore.com sells many products; the purchase webhook grants toolkit
    // access ONLY when the sale matches the toolkit. It matches if the item's
    // title contains one of these (case-insensitive) phrases, OR the sale's
    // product id is in toolkit_product_ids below.
    'toolkit_product_match' => [
        'diy creator',
        'creator starter toolkit',
        'diy-creator-starter-toolkit',
    ],
    // The toolkit's product id in wyzcore (more robust than the title, which can
    // be renamed or translated). From the product URL /.../14644/.
    'toolkit_product_ids' => ['14644'],

    // WyzAI floating widget (the blue chat button, bottom-right). Embedded in
    // the footer of every page. This is the live WyzQuest agency for the toolkit.
    'wyzai_widget_src'    => 'https://agents.wyzquestpro.com/role/widget.js',
    'wyzai_agency_id'     => 'agency-b5b9d9b09d0ecf0a',

    // Absolute path (above web root) to the ten source PDFs. Adjust if your
    // Hostinger layout differs. download-pdf.php is the only route to these.
    'pdf_dir'       => __DIR__ . '/../private/assets',

    'session_idle_days' => 30,   // people return to this tool over weeks
    'environment'   => 'production',  // 'production' hides internal error detail
];
