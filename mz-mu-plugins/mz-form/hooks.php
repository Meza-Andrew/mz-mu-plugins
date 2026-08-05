<?php

add_action('wp_mail_failed', function ($wp_error) {
    if (!function_exists('error_log')) return;
    error_log('wp_mail_failed: ' . $wp_error->get_error_message());
    $data = $wp_error->get_error_data();
    if ($data) {
        $ctx = [
            'to'          => $data['to'] ?? null,
            'subject'     => $data['subject'] ?? null,
            'headers'     => $data['headers'] ?? null,
            'attachments' => $data['attachments'] ?? null,
        ];
        error_log('wp_mail_failed context: ' . print_r($ctx, true));
    }
});
