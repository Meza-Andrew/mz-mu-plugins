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

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['mzf_debug'])) {
        return;
    }

    $raw = strtolower(trim((string) $_GET['mzf_debug']));
    $enable = in_array($raw, ['1', 'on', 'true', 'yes'], true);
    $uid = get_current_user_id();
    if ($uid > 0) {
        update_user_meta($uid, 'mzf_debug_session', $enable ? '1' : '0');
    }

    $redirect = remove_query_arg(['mzf_debug', '_wpnonce']);
    wp_safe_redirect($redirect ?: admin_url());
    exit;
});

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return;
    }

    $enabled = function_exists('mzf_debug_session_enabled') && mzf_debug_session_enabled();
    $toggle_to = $enabled ? '0' : '1';
    $label = $enabled ? 'MZF Debug: ON' : 'MZF Debug: OFF';
    $title = $enabled ? 'Disable MZF Debug' : 'Enable MZF Debug';

    $href = add_query_arg(['mzf_debug' => $toggle_to], admin_url());
    $href = wp_nonce_url($href, 'mzf_debug_toggle');

    $wp_admin_bar->add_node([
        'id'    => 'mzf-debug-toggle',
        'title' => esc_html($label),
        'href'  => esc_url($href),
        'meta'  => ['title' => esc_attr($title)],
    ]);
}, 100);

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!function_exists('mzf_debug_session_enabled') || !mzf_debug_session_enabled()) {
        return;
    }
    echo '<div class="notice notice-warning"><p><strong>MZ Form debug is enabled for your admin session.</strong></p></div>';
});
