<?php

return [
    // Mock checkout is deliberately limited to local/testing environments in code.
    'allow_demo_payments' => (bool) env('CARGOTRACE_ALLOW_DEMO_PAYMENTS', true),
    'email_notifications' => (bool) env('CARGOTRACE_EMAIL_NOTIFICATIONS', false),
];
