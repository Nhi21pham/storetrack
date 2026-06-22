<?php

return [
    'chrome_path' => env('CHROME_PATH', '/usr/bin/chromium'),
    'node_module_path' => env('NODE_MODULE_PATH', '/opt/puppeteer/node_modules/'),
    'render_script' => resource_path('browser/render-invoices.cjs'),
];
