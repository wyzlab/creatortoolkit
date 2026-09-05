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
