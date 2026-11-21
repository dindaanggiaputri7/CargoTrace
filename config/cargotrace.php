<?php

return [
    // Kept only for backward-compatible configuration. Payment confirmation now requires a verified gateway webhook.
    'allow_demo_payments' => false,
    'email_notifications' => (bool) env('CARGOTRACE_EMAIL_NOTIFICATIONS', false),
];
