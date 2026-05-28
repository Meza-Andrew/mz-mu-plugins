<?php

if (!function_exists('ds_build_user_email')) {
    function ds_build_user_email(array $form_data, string $footer = ''): array
    {
        $page_id  = isset($form_data['PageId']) ? (int) $form_data['PageId'] : 0;
        $form_cfg = (function_exists('get_field') && $page_id) ? get_field('section_form', $page_id) : null;
        $form_cfg = is_array($form_cfg) ? $form_cfg : [];
        $form_cfg = function_exists('mzf_normalize_temporal_config')
            ? mzf_normalize_temporal_config($form_cfg, $page_id)
            : $form_cfg;
        $site_domain = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $email_data = $form_data;
        if ($site_domain !== '') {
            $email_data['domain'] = $site_domain;
            $email_data['site_domain'] = $site_domain;
        }

        $sig_name = trim((string) ($form_cfg['email_signature'] ?? 'The Meza Team'));
        $subject  = ds_replace_tokens((string) ($form_cfg['email_subject'] ?? 'Thank you'), $email_data);

        $content  = ds_replace_tokens((string) ($form_cfg['email_content'] ?? ''), $email_data);
        $content  = ds_strip_outer_p($content);

        $first    = isset($form_data['FirstName']) ? trim((string) $form_data['FirstName']) : '';
        $greeting = $first !== '' ? '<p>Hey ' . esc_html($first) . ',</p>' : '<p>Hello,</p>';

        $body  = $greeting;
        $body .= $content !== '' ? $content : '<p>Thanks for reaching out — we’ll follow up shortly.</p>';
        $body .= '<p>Sincerely,<br><strong>' . esc_html($sig_name) . '</strong></p>';
        $body .= $footer;

        return ['subject' => $subject, 'body' => $body];
    }
}

if (!function_exists('mz_build_user_email')) {
    function mz_build_user_email(array $form_data, string $footer = ''): array
    {
        $cfg = mzf_resolve_form_config($form_data);
        $site_domain = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $email_data = $form_data;
        if ($site_domain !== '') {
            $email_data['domain'] = $site_domain;
            $email_data['site_domain'] = $site_domain;
        }

        $first = trim((string) ($form_data['FirstName'] ?? ''));
        $site_name_raw = (string) get_bloginfo('name');

        $subject = (string) mzf_form_config_value($cfg, ['email_user.subject', 'email_subject'], 'Thank you');
        $subject = ds_replace_tokens($subject, $email_data);

        $intro = (string) mzf_form_config_value($cfg, ['email_user.intro', 'email_intro'], '');
        $main = (string) mzf_form_config_value($cfg, ['email_user.body', 'email_content'], '');
        $salutation = (string) mzf_form_config_value($cfg, ['email_user.salutation', 'email_salutation'], '');
        $signature = (string) mzf_form_config_value($cfg, ['email_user.signature', 'email_signature'], ('The ' . $site_name_raw . ' Team'));
        $signature = str_replace('{site name}', $site_name_raw, $signature);
        $logo_url = function_exists('mzf_user_email_logo_url')
            ? mzf_user_email_logo_url($cfg)
            : '';

        $greeting_html = $intro !== ''
            ? '<p>' . esc_html($intro) . ' ' . esc_html($first) . ',</p>'
            : ($first !== '' ? '<p>Hey ' . esc_html($first) . ',</p>' : '<p>Hello,</p>');

        $main_html = ds_replace_tokens($main, $email_data);
        $main_html = ds_strip_outer_p($main_html);
        if ($main_html === '') {
            $main_html = '<p>Thanks for reaching out — we’ll follow up shortly.</p>';
        }
        $salutation_html = $salutation !== ''
            ? '<p>' . wp_kses_post($salutation) . '<br>'
            : '<p>Sincerely,<br>';
        $logo_html = $logo_url !== ''
            ? '<p style="margin:0 0 16px 0;"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name_raw) . '" style="max-width:240px;height:auto;display:block;"></p>'
            : '';

        $email = [
            'subject' => $subject,
            'body' => $logo_html . $greeting_html . $main_html . $salutation_html . '<strong>' . esc_html($signature) . '</strong></p>' . $footer,
        ];
        return (array) apply_filters('mzf_user_email', $email, $form_data, $footer);
    }
}

if (!function_exists('mzf_unlock_query_arg')) {
    function mzf_unlock_query_arg(): string
    {
        return (string) apply_filters('mzf_unlock_query_arg', 'mzf_reveal');
    }
}

if (!function_exists('mzf_submission_page_url')) {
    function mzf_submission_page_url(array $form_data): string
    {
        $page_id = isset($form_data['PageId']) ? (int) $form_data['PageId'] : 0;
        if ($page_id <= 0 || !function_exists('get_permalink')) {
            return '';
        }

        $page_url = get_permalink($page_id);
        return is_string($page_url) ? trim($page_url) : '';
    }
}

if (!function_exists('mzf_submission_unlock_url')) {
    function mzf_submission_unlock_url(array $form_data): string
    {
        $page_url = function_exists('mzf_submission_page_url')
            ? mzf_submission_page_url($form_data)
            : '';

        if ($page_url === '') {
            return '';
        }

        $slug = function_exists('mzf_get_form_slug')
            ? mzf_get_form_slug($form_data)
            : sanitize_key((string) ($form_data['FormSlug'] ?? ''));

        if (!function_exists('mzf_slug_matches_family') || !mzf_slug_matches_family($slug, 'lead-gen')) {
            return '';
        }

        return (string) add_query_arg(mzf_unlock_query_arg(), '1', $page_url);
    }
}

if (!function_exists('mzf_user_email_unlock_link_html')) {
    function mzf_user_email_unlock_link_html(array $form_data): string
    {
        $unlock_url = function_exists('mzf_submission_unlock_url')
            ? mzf_submission_unlock_url($form_data)
            : '';

        if ($unlock_url === '') {
            return '';
        }

        $slug = function_exists('mzf_get_form_slug')
            ? mzf_get_form_slug($form_data)
            : sanitize_key((string) ($form_data['FormSlug'] ?? ''));

        $link_text = (string) apply_filters(
            'mzf_user_email_unlock_link_text',
            (function_exists('mzf_slug_matches_family') && mzf_slug_matches_family($slug, 'lead-gen'))
                ? 'Access this page again'
                : 'View this page',
            $form_data,
            $unlock_url
        );
        $intro_text = (string) apply_filters(
            'mzf_user_email_unlock_link_intro',
            'You can come back to this page any time using the link below.',
            $form_data,
            $unlock_url
        );

        return '<p>' . esc_html($intro_text) . '</p><p><a href="' . esc_url($unlock_url) . '">' . esc_html($link_text) . '</a></p>';
    }
}

if (!function_exists('mzf_ensure_absolute_url')) {
    function mzf_ensure_absolute_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (strpos($url, '//') === 0) {
            $scheme = is_ssl() ? 'https:' : 'http:';
            return $scheme . $url;
        }
        if (strpos($url, '/') === 0) {
            return home_url($url);
        }
        return home_url('/' . ltrim($url, '/'));
    }
}

if (!function_exists('mzf_site_logo_absolute_url')) {
    function mzf_site_logo_absolute_url(): string
    {
        $url = '';
        if (function_exists('meza_get_custom_logo_url')) {
            $url = (string) meza_get_custom_logo_url();
        }
        if ($url === '' && function_exists('meza_get_custom_logo_id')) {
            $custom_logo_id = (int) meza_get_custom_logo_id();
            if ($custom_logo_id > 0) {
                $custom_logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                $url = is_string($custom_logo_url) ? $custom_logo_url : '';
            }
        }
        if ($url === '') {
            $custom_logo_id = (int) get_theme_mod('custom_logo');
            if ($custom_logo_id > 0) {
                $custom_logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
                $url = is_string($custom_logo_url) ? $custom_logo_url : '';
            }
        }
        if ($url === '' && function_exists('get_custom_logo')) {
            $custom_logo_html = (string) get_custom_logo();
            if ($custom_logo_html !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $custom_logo_html, $matches)) {
                $url = (string) ($matches[1] ?? '');
            }
        }
        if ($url === '' && function_exists('meza_get_footer_logo_html')) {
            $footer_logo_html = (string) meza_get_footer_logo_html();
            if ($footer_logo_html !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $footer_logo_html, $matches)) {
                $url = (string) ($matches[1] ?? '');
            }
        }
        return mzf_ensure_absolute_url((string) $url);
    }
}

if (!function_exists('mzf_theme_email_logo_absolute_url')) {
    function mzf_theme_email_logo_absolute_url(): string
    {
        if (!function_exists('get_stylesheet') || !function_exists('get_stylesheet_directory') || !function_exists('get_stylesheet_directory_uri')) {
            return '';
        }

        $theme_slug = sanitize_file_name((string) get_stylesheet());
        $base_dir = trailingslashit((string) get_stylesheet_directory()) . 'public/';
        $base_uri = trailingslashit((string) get_stylesheet_directory_uri()) . 'public/';
        $candidates = [
            'email_logo_' . $theme_slug . '.jpg',
            'email_logo_' . $theme_slug . '.jpeg',
            'email_logo_' . $theme_slug . '.png',
        ];

        foreach ($candidates as $filename) {
            if (file_exists($base_dir . $filename)) {
                return mzf_ensure_absolute_url($base_uri . $filename);
            }
        }

        return '';
    }
}

if (!function_exists('mzf_user_email_logo_url')) {
    function mzf_user_email_logo_url(array $cfg = []): string
    {
        $url = mzf_theme_email_logo_absolute_url();
        if ($url === '') {
            $url = mzf_site_logo_absolute_url();
        }
        return $url;
    }
}

if (!function_exists('mzf_get_path')) {
    function mzf_get_path(array $source, string $path)
    {
        if ($path === '') {
            return null;
        }
        if (array_key_exists($path, $source)) {
            return $source[$path];
        }

        $segments = explode('.', $path);
        $cursor = $source;
        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }
}

if (!function_exists('mzf_normalize_temporal_config')) {
    function mzf_normalize_temporal_config($value, int $context_id = 0)
    {
        if (!is_array($value)) {
            return $value;
        }

        $temporal_keys = ['before', 'base', 'after', 'season_after'];
        $has_temporal_shape = false;

        foreach ($temporal_keys as $key) {
            if (array_key_exists($key, $value)) {
                $has_temporal_shape = true;
                break;
            }
        }

        if ($has_temporal_shape && function_exists('meza_resolve_section_field_value')) {
            $value = meza_resolve_section_field_value($value, $context_id);
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            $value[$key] = mzf_normalize_temporal_config($child, $context_id);
        }

        return $value;
    }
}

if (!function_exists('mzf_section_form_content')) {
    function mzf_section_form_content($section_form): array
    {
        if (!is_array($section_form)) {
            return [];
        }

        $resolve_form_post_fields = static function ($value): array {
            if (!function_exists('get_fields') || !function_exists('get_post_type')) {
                return [];
            }

            $post_id = 0;
            if ($value instanceof WP_Post) {
                $post_id = (int) $value->ID;
            } elseif (is_numeric($value)) {
                $post_id = (int) $value;
            } elseif (is_array($value)) {
                if (isset($value['ID']) || isset($value['id'])) {
                    $post_id = (int) ($value['ID'] ?? $value['id'] ?? 0);
                } else {
                    $first = reset($value);
                    if ($first instanceof WP_Post) {
                        $post_id = (int) $first->ID;
                    } elseif (is_numeric($first)) {
                        $post_id = (int) $first;
                    } elseif (is_array($first)) {
                        $post_id = (int) ($first['ID'] ?? $first['id'] ?? 0);
                    }
                }
            }

            if ($post_id <= 0 || get_post_type($post_id) !== 'form') {
                return [];
            }

            $fields = (array) get_fields($post_id);

            return mzf_normalize_temporal_config($fields, $post_id);
        };

        $form_ref = $section_form['form'] ?? null;
        if ($form_ref !== null) {
            return $resolve_form_post_fields($form_ref);
        }
        return [];
    }
}

if (!function_exists('mzf_has_meaningful_value')) {
    function mzf_has_meaningful_value($value): bool
    {
        if (is_array($value)) {
            return !empty($value);
        }
        if (is_bool($value) || is_numeric($value)) {
            return true;
        }
        return trim((string) $value) !== '';
    }
}

if (!function_exists('mzf_form_config_value')) {
    function mzf_form_config_value(array $cfg, array $keys, $fallback = '')
    {
        foreach ($keys as $key) {
            $candidate = mzf_get_path($cfg, (string) $key);
            if (mzf_has_meaningful_value($candidate)) {
                return $candidate;
            }
        }
        return $fallback;
    }
}

if (!function_exists('mz_resolve_office_email')) {
    function mz_resolve_office_email($store_raw = '', $ref_path = '')
    {
        return ds_resolve_office_email($store_raw, $ref_path);
    }
}

if (!function_exists('mzf_resolve_form_config')) {
    function mzf_resolve_form_config(array $data = []): array
    {
        $cfg = [];
        $slug = function_exists('mzf_get_form_slug') ? mzf_get_form_slug($data) : sanitize_key((string) ($data['FormSlug'] ?? ''));
        $slug = function_exists('mzf_normalize_form_slug') ? mzf_normalize_form_slug($slug) : $slug;

        if ($slug !== '' && function_exists('get_page_by_path') && function_exists('get_fields')) {
            $form_post = get_page_by_path($slug, OBJECT, 'form');
            if ($form_post instanceof WP_Post) {
                $form_post_id = (int) $form_post->ID;
                $from_form = (array) get_fields((int) $form_post->ID);
                $from_form_group = function_exists('get_field') ? get_field('section_form', $form_post_id) : null;
                $from_form_group = mzf_section_form_content($from_form_group);
                if (!empty($from_form_group)) {
                    // Support transitional storage where form config is nested in section_form.
                    $cfg = array_merge($cfg, $from_form_group);
                }
                if (!empty($from_form)) {
                    $cfg = array_merge($cfg, $from_form);
                }
                if (function_exists('get_post_meta')) {
                    $from_post_meta_group = get_post_meta($form_post_id, 'section_form', true);
                    $from_post_meta_group = mzf_section_form_content($from_post_meta_group);
                    if (!empty($from_post_meta_group)) {
                        $cfg = array_merge($cfg, $from_post_meta_group);
                    }
                }
            }
        }

        $page_id = isset($data['PageId']) ? (int) $data['PageId'] : 0;
        if ($page_id && function_exists('get_field')) {
            $from_page = mzf_section_form_content(get_field('section_form', $page_id));
            if (!empty($from_page)) {
                $cfg = array_merge($from_page, $cfg);
            }
        }

        if (function_exists('get_field')) {
            $from_option = mzf_section_form_content(get_field('section_form', 'option'));
            if (!empty($from_option)) {
                $cfg = array_merge($from_option, $cfg);
            }
        }

        return (array) apply_filters('mzf_form_config', $cfg, $data, $slug);
    }
}

if (!function_exists('mzf_parse_recipients')) {
    function mzf_parse_recipients($value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $row) {
                if (is_array($row)) {
                    $candidate = trim((string) ($row['email'] ?? ''));
                    if ($candidate !== '') {
                        $out[] = $candidate;
                    }
                } else {
                    $candidate = trim((string) $row);
                    if ($candidate !== '') {
                        $out[] = $candidate;
                    }
                }
            }
            return array_values(array_unique(array_filter($out, 'is_email')));
        }
        $s = trim((string) $value);
        if ($s === '') {
            return [];
        }
        $parts = preg_split('/[\s,;]+/', $s) ?: [];
        $parts = array_map('trim', $parts);
        return array_values(array_unique(array_filter($parts, 'is_email')));
    }
}

if (!function_exists('mzf_recipients_from_form_config')) {
    function mzf_recipients_from_form_config(array $data = [], ?array $cfg = null): array
    {
        $cfg = is_array($cfg) ? $cfg : mzf_resolve_form_config($data);
        $raw = mzf_form_config_value($cfg, ['email_admin.recipients', 'email_recipients'], []);
        $emails = mzf_parse_recipients($raw);
        return array_values(array_unique(array_filter($emails, 'is_email')));
    }
}

if (!function_exists('mzf_build_footer_html')) {
    function mzf_build_footer_html(
        string $variant,
        ?string $org_addr_display,
        ?string $org_maps_url,
        ?string $org_phone,
        ?string $org_phone_href,
        ?string $org_email_display,
        ?string $site_url,
        ?string $domain
    ): string {
        $addr_display = trim((string) $org_addr_display);
        $maps_url = trim((string) $org_maps_url);
        $phone = trim((string) $org_phone);
        $phone_href = trim((string) $org_phone_href);
        $email_display = trim((string) $org_email_display);
        $site_url = trim((string) $site_url);
        $domain = trim((string) $domain);
        $domain_href = $domain !== '' ? ('https://' . $domain) : '';
        $site_href = mzf_ensure_absolute_url($site_url);
        $business_address = function_exists('meza_get_business_information_address')
            ? meza_get_business_information_address()
            : ['raw' => null, 'text' => function_exists('get_field') ? trim((string) get_field('address_text', 'option')) : ''];
        $option_addr_text = trim((string) ($business_address['text'] ?? ''));
        $address_uses_maps = ($maps_url !== '');
        $using_address_text_fallback = false;
        if ($site_href === '') {
            $site_href = $domain_href;
        }
        $site_name = trim((string) get_bloginfo('name'));
        if ($site_name === '') {
            $site_name = $site_href !== '' ? preg_replace('#^https?://#i', '', $site_href) : 'Website';
        }
        $option_addr_raw = $business_address['raw'] ?? null;
        if (is_array($option_addr_raw)) {
            $street = trim((string) (($option_addr_raw['street_number'] ?? '') . ' ' . ($option_addr_raw['street_name'] ?? '')));
            $suite = trim((string) (
                $option_addr_raw['subpremise'] ??
                $option_addr_raw['suite'] ??
                $option_addr_raw['unit'] ??
                $option_addr_raw['street_2'] ??
                ''
            ));
            $city   = trim((string) ($option_addr_raw['city'] ?? ''));
            $state  = trim((string) ($option_addr_raw['state'] ?? ''));
            $zip    = trim((string) ($option_addr_raw['post_code'] ?? $option_addr_raw['postal_code'] ?? ''));
            $option_addr = trim((string) ($option_addr_raw['address'] ?? $option_addr_raw['formatted_address'] ?? ''));
            // Preserve suite/unit fragments (for example "#100") from formatted address if structured keys omit them.
            if ($suite === '' && $option_addr !== '') {
                $first_chunk = trim((string) explode(',', $option_addr)[0]);
                if ($first_chunk !== '' && preg_match('/(?:#\s*[A-Za-z0-9\-]+|\b(?:suite|ste|unit)\s*[A-Za-z0-9\-]+)/i', $first_chunk, $m)) {
                    $suite = trim((string) ($m[0] ?? ''));
                }
            }
            if ($suite !== '' && stripos($street, $suite) === false) {
                $street = trim($street . ' ' . $suite);
            }
            $parts  = [];
            if ($street !== '') $parts[] = $street;
            if ($city !== '') $parts[] = $city;
            $state_zip = trim($state . ($zip !== '' ? ' ' . $zip : ''));
            if ($state_zip !== '') $parts[] = $state_zip;
            if (!empty($parts)) {
                $addr_display = implode(', ', $parts);
                $address_uses_maps = true;
            } else {
                if ($option_addr !== '') {
                    $chunks = array_values(array_filter(array_map('trim', explode(',', $option_addr)), static fn($v) => $v !== ''));
                    if (count($chunks) >= 4) {
                        array_shift($chunks); // drop place/business name
                    }
                    $addr_display = implode(', ', $chunks);
                    $address_uses_maps = true;
                }
            }
        } elseif (is_string($option_addr_raw)) {
            $option_addr = trim($option_addr_raw);
            if ($option_addr !== '') {
                $chunks = array_values(array_filter(array_map('trim', explode(',', $option_addr)), static fn($v) => $v !== ''));
                if (count($chunks) >= 4) {
                    array_shift($chunks); // drop place/business name
                }
                $addr_display = implode(', ', $chunks);
                $address_uses_maps = true;
            }
        }
        if ($addr_display === '' && $option_addr_text !== '') {
            $addr_display = preg_replace('/\s*\R\s*/', ', ', $option_addr_text);
            $addr_display = trim((string) $addr_display, " \t\n\r\0\x0B,");
            $address_uses_maps = false;
            $using_address_text_fallback = true;
        }
        $addr_label = preg_replace('/,\s*(US|USA|United States(?: of America)?)\s*$/i', '', $addr_display);
        $addr_label = trim((string) $addr_label);
        if ($addr_label === '') {
            $addr_label = $addr_display;
        }
        if ($maps_url === '' && $address_uses_maps && $addr_display !== '') {
            $maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr_display);
        }

        // Global footer template: always use FH-style footer across all clients.
        $footer = '<hr style="margin:24px 0;"><p><small>';
        if ($site_href !== '') {
            $footer .= '<a href="' . esc_url($site_href) . '" target="_blank" rel="noopener">' . esc_html($site_name) . '</a><br>';
        }
        if ($phone !== '') {
            $footer .= '<a href="tel:' . esc_attr($phone_href) . '" target="_blank">' . esc_html($phone) . '</a><br>';
        }
        if ($email_display !== '') {
            $footer .= '<a href="mailto:' . esc_attr($email_display) . '" target="_blank">' . esc_html($email_display) . '</a><br>';
        }
        if ($addr_display !== '' && $using_address_text_fallback) {
            $footer .= esc_html($addr_label) . '<br>';
        } elseif ($addr_display !== '' && $maps_url !== '') {
            $footer .= '<a href="' . esc_url($maps_url) . '" target="_blank" rel="noopener">' . esc_html($addr_label) . '</a><br>';
        } elseif ($addr_display !== '') {
            $footer .= esc_html($addr_label) . '<br>';
        }
        $footer .= '<br><strong>Powered by <a href="https://meza.design" target="_blank" rel="noopener">meza.</a></strong><br>';
        $footer .= '<em>This is an automated email. Please do not reply directly to this message.</em>';
        $footer .= '</small></p>';

        return $footer;
    }
}

if (!function_exists('mzf_default_body_layouts')) {
    function mzf_default_body_layouts(): array
    {
        return function_exists('mzf_registry_body_layouts')
            ? mzf_registry_body_layouts()
            : [];
    }
}

if (!function_exists('mzf_default_field_labels')) {
    function mzf_default_field_labels(): array
    {
        return function_exists('mzf_registry_field_labels')
            ? mzf_registry_field_labels()
            : [];
    }
}

if (!function_exists('mzf_render_admin_body')) {
    function mzf_render_admin_body(string $fallback_body, array $data, array $context = []): string
    {
        $slug = function_exists('mzf_get_form_slug') ? mzf_get_form_slug($data) : sanitize_key((string) ($data['FormSlug'] ?? ''));
        $slug = function_exists('mzf_normalize_form_slug') ? mzf_normalize_form_slug($slug) : $slug;
        if ($slug === '') {
            return $fallback_body;
        }

        $layouts = (array) apply_filters('mzf_body_layouts', mzf_default_body_layouts(), $data, $context);
        $layout = isset($layouts[$slug]) && is_array($layouts[$slug]) ? $layouts[$slug] : null;
        if (empty($layout) && function_exists('mzf_slug_profile')) {
            $profile = mzf_slug_profile($slug);
            $layout = isset($profile['layout']) && is_array($profile['layout']) ? $profile['layout'] : null;
        }
        if (empty($layout)) {
            return $fallback_body;
        }

        $labels = (array) apply_filters('mzf_field_labels', mzf_default_field_labels(), $data, $context);
        // Dynamic label overrides preserve original client wording without profile switches.
        if (!empty($data['Interests'])) {
            $labels['Interests'] = 'Interests';
        }
        if (trim((string) ($data['Service'] ?? '')) !== '') {
            $labels['ItemType'] = 'Service';
        }
        $labels['NewsletterSignup'] = 'Signed Up for Newsletter';
        $is_lead_gen = function_exists('mzf_slug_matches_family') && mzf_slug_matches_family($slug, 'lead-gen');
        $receiving_option = strtolower(trim((string) ($data['ReceivingOption'] ?? '')));
        if ($receiving_option === 'delivery' || $receiving_option === 'deliver') {
            $labels['LocationDisplay'] = 'Deliver to Address';
        } elseif ($receiving_option === 'ship' || $receiving_option === 'shipping') {
            $labels['LocationDisplay'] = 'Ship to Address';
        }
        if ($slug === 'rent') {
            $labels['DateNeeded'] = 'Rental Date';
        }
        if (trim((string) ($data['RentalDuration'] ?? '')) !== '' || trim((string) ($data['Days'] ?? '')) !== '') {
            $labels['Duration'] = 'Rental Duration';
        }
        if ($slug === 'volunteer') {
            $labels['Date'] = 'Date of Birth';
            $labels['Comments'] = 'Skills and Experience';
        }
        $full_name = trim((string) ($data['FirstName'] ?? '') . ' ' . (string) ($data['LastName'] ?? ''));
        $footer = (string) ($context['footer_html'] ?? '');
        $lead_gen_page_row = '';
        if ($is_lead_gen) {
            $page_id = isset($data['PageId']) ? (int) $data['PageId'] : 0;
            $page_title = $page_id > 0 ? trim((string) get_the_title($page_id)) : '';
            $page_url = $page_id > 0 ? trim((string) get_permalink($page_id)) : '';
            if ($page_title !== '' && $page_url !== '') {
                $lead_gen_page_row = '<p><strong>Page:</strong><br><a href="' . esc_url($page_url) . '" target="_blank" rel="noopener">' . esc_html($page_title) . '</a></p>';
            } elseif ($page_title !== '') {
                $lead_gen_page_row = '<p><strong>Page:</strong><br>' . esc_html($page_title) . '</p>';
            }
        }

        $value_for = static function (string $key) use ($data, $full_name) {
            if ($key === 'FullName') return $full_name;
            if ($key === 'Email') return (string) ($data['Email'] ?? '');
            if ($key === 'Phone') return (string) ($data['Phone'] ?? '');
            if ($key === 'ContactEmail') return (string) ($data['ContactEmail'] ?? '');
            if ($key === 'Comments') return (string) ($data['Comments'] ?? '');
            if ($key === 'PickupContact') {
                $contact_first = trim((string) ($data['ContactFirstName'] ?? ''));
                $contact_last = trim((string) ($data['ContactLastName'] ?? ''));
                $contact_phone = trim((string) ($data['ContactPhone'] ?? ''));
                $contact_email = trim((string) ($data['ContactEmail'] ?? ''));
                return [
                    'name' => trim($contact_first . ' ' . $contact_last),
                    'phone' => $contact_phone,
                    'email' => $contact_email,
                ];
            }
            if ($key === 'Vehicle') {
                return trim((string) ($data['VehicleYear'] ?? '') . ' ' . (string) ($data['VehicleMake'] ?? '') . ' ' . (string) ($data['VehicleModel'] ?? ''));
            }
            if ($key === 'NewsletterSignup') {
                $raw = $data['NewsletterSignup'] ?? '';
                if (is_bool($raw)) return $raw ? 'Yes' : 'No';
                $s = strtolower(trim((string) $raw));
                return in_array($s, ['1', 'true', 'yes', 'on', 'y'], true) ? 'Yes' : 'No';
            }
            if (in_array($key, ['Consent', 'Training', 'Conduct', 'Confidentiality', 'Applicant'], true)) {
                $raw = $data[$key] ?? '';
                if (is_bool($raw)) return $raw ? 'Yes' : 'No';
                $s = strtolower(trim((string) $raw));
                return in_array($s, ['1', 'true', 'yes', 'on', 'y'], true) ? 'Yes' : 'No';
            }
            if ($key === 'LocationDisplay') {
                $loc = trim((string) ($data['LocationDisplay'] ?? ''));
                if ($loc !== '') return $loc;
                $loc = trim((string) ($data['Location'] ?? ''));
                if ($loc !== '') return $loc;
                $loc = trim((string) ($data['ReceivingAddressDisplay'] ?? ''));
                if ($loc !== '') return $loc;
                return trim((string) ($data['ReceivingAddress'] ?? ''));
            }
            if ($key === 'Company') {
                return trim((string) ($data['Company'] ?? ''));
            }
            if ($key === 'PrintColor') {
                $color = trim((string) ($data['PrintColor'] ?? ''));
                if (strcasecmp($color, 'Color') === 0) {
                    return 'Full Color';
                }
                return $color;
            }
            return isset($data[$key]) ? $data[$key] : '';
        };

        $build_uploaded_files = static function () use ($data): array {
            $uploaded_files = [];
            if (!empty($data['UploadedFiles']) && is_array($data['UploadedFiles'])) {
                foreach ((array) $data['UploadedFiles'] as $file_entry) {
                    if (is_array($file_entry)) {
                        $file_name = trim((string) ($file_entry['name'] ?? ''));
                        $file_url = esc_url_raw((string) ($file_entry['url'] ?? ''));
                        if ($file_name === '' && $file_url !== '') {
                            $parsed_path = (string) wp_parse_url($file_url, PHP_URL_PATH);
                            $file_name = basename($parsed_path);
                        }
                        if ($file_name !== '' && $file_url !== '') {
                            $uploaded_files[] = '<a href="' . esc_url($file_url) . '" target="_blank" rel="noopener">' . esc_html($file_name) . '</a>';
                        }
                    } else {
                        $file_url = esc_url_raw((string) $file_entry);
                        if ($file_url !== '') {
                            $parsed_path = (string) wp_parse_url($file_url, PHP_URL_PATH);
                            $file_name = basename($parsed_path);
                            $uploaded_files[] = '<a href="' . esc_url($file_url) . '" target="_blank" rel="noopener">' . esc_html($file_name) . '</a>';
                        }
                    }
                }
            }
            return $uploaded_files;
        };
        $uploaded_files = $build_uploaded_files();

        $raw_contact = $value_for('PickupContact');
        $contact_name = trim((string) ($raw_contact['name'] ?? ''));
        $contact_phone = trim((string) ($raw_contact['phone'] ?? ''));
        $contact_email = trim((string) ($raw_contact['email'] ?? ''));
        $receiving_contact_raw = strtolower(trim((string) ($data['ReceivingContact'] ?? '')));
        $receiving_contact_enabled = in_array($receiving_contact_raw, ['1', 'true', 'yes', 'on', 'y'], true);
        $has_pickup_contact = $receiving_contact_enabled && ($contact_name !== '' || $contact_phone !== '' || $contact_email !== '');
        $interest_source = $data['Interests'] ?? [];
        $interest_values = [];
        if (is_array($interest_source)) {
            foreach ($interest_source as $interest_item) {
                $interest_item = trim((string) $interest_item);
                if ($interest_item !== '') {
                    $interest_values[] = $interest_item;
                }
            }
        } else {
            $interest_text = trim((string) $interest_source);
            if ($interest_text !== '') {
                foreach (preg_split('/\s*,\s*/', $interest_text) as $interest_item) {
                    $interest_item = trim((string) $interest_item);
                    if ($interest_item !== '') {
                        $interest_values[] = $interest_item;
                    }
                }
            }
        }
        $interest_values = array_values(array_unique($interest_values));
        $has_multi_interest = count($interest_values) > 1;
        if ($has_multi_interest && !in_array('Interests', $layout, true)) {
            $insert_before = array_search('FilesLink', $layout, true);
            if ($insert_before === false) {
                $insert_before = array_search('Comments', $layout, true);
            }
            if ($insert_before !== false) {
                array_splice($layout, (int) $insert_before, 0, ['Interests']);
            } else {
                $layout[] = 'Interests';
            }
        }
        if ($has_pickup_contact && !in_array('PickupContact', $layout, true)) {
            $insert_after = array_search('LocationDisplay', $layout, true);
            if ($insert_after !== false) {
                array_splice($layout, (int) $insert_after + 1, 0, ['PickupContact']);
            } else {
                $insert_before = array_search('FilesLink', $layout, true);
                if ($insert_before === false) {
                    $insert_before = array_search('Comments', $layout, true);
                }
                if ($insert_before !== false) {
                    array_splice($layout, (int) $insert_before, 0, ['PickupContact']);
                } else {
                    $layout[] = 'PickupContact';
                }
            }
        }
        $print_color_value = trim((string) $value_for('PrintColor'));
        if ($print_color_value !== '' && !in_array('PrintColor', $layout, true)) {
            $insert_before = array_search('Quantity', $layout, true);
            if ($insert_before === false) {
                $insert_before = array_search('Dimensions', $layout, true);
            }
            if ($insert_before === false) {
                $insert_before = array_search('FilesLink', $layout, true);
            }
            if ($insert_before === false) {
                $insert_before = array_search('Comments', $layout, true);
            }
            if ($insert_before !== false) {
                array_splice($layout, (int) $insert_before, 0, ['PrintColor']);
            } else {
                $layout[] = 'PrintColor';
            }
        }

        $body = '';
        $contact_rows = [];
        $request_rows = [];
        $marketing_rows = [];
        $label_name = (string) ($labels['FullName'] ?? 'Name');
        $label_company = (string) ($labels['Company'] ?? 'Company');
        $label_email = (string) ($labels['Email'] ?? 'Email');
        $label_phone = (string) ($labels['Phone'] ?? 'Phone');
        $label_pronouns = (string) ($labels['Pronouns'] ?? 'Pronouns');
        $contact_name_value = trim((string) $value_for('FullName'));
        if ($slug === 'volunteer') {
            $preferred_name = trim((string) ($data['PreferredName'] ?? ''));
            if ($preferred_name !== '') {
                $contact_name_value = $contact_name_value !== ''
                    ? ($preferred_name . ' (' . $contact_name_value . ')')
                    : $preferred_name;
            }
        }
        if ($contact_name_value !== '') {
            $contact_rows[] = '<p><strong>' . esc_html($label_name) . ':</strong><br>' . esc_html($contact_name_value) . '</p>';
        }
        if ($slug === 'volunteer') {
            $contact_pronouns_value = trim((string) ($data['Pronouns'] ?? ''));
            if ($contact_pronouns_value !== '') {
                $contact_rows[] = '<p><strong>' . esc_html($label_pronouns) . ':</strong><br>' . esc_html($contact_pronouns_value) . '</p>';
            }
        }
        $contact_company_value = trim((string) $value_for('Company'));
        if ($contact_company_value !== '') {
            $contact_rows[] = '<p><strong>' . esc_html($label_company) . ':</strong><br>' . esc_html($contact_company_value) . '</p>';
        }
        $contact_email_value = trim((string) $value_for('Email'));
        if ($contact_email_value !== '') {
            $contact_email_display = '<a href="mailto:' . esc_attr($contact_email_value) . '">' . esc_html($contact_email_value) . '</a>';
            $contact_email_display = apply_filters('mzf_admin_field_display', $contact_email_display, 'Email', $contact_email_value, $data, $context, $label_email);
            $contact_rows[] = '<p><strong>' . esc_html($label_email) . ':</strong><br>' . $contact_email_display . '</p>';
        }
        $contact_phone_value = trim((string) $value_for('Phone'));
        if ($contact_phone_value !== '') {
            $contact_phone_display = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $contact_phone_value)) . '">' . esc_html($contact_phone_value) . '</a>';
            $contact_phone_display = apply_filters('mzf_admin_field_display', $contact_phone_display, 'Phone', $contact_phone_value, $data, $context, $label_phone);
            $contact_rows[] = '<p><strong>' . esc_html($label_phone) . ':</strong><br>' . $contact_phone_display . '</p>';
        }
        $newsletter_value = '';
        $deferred_comments = '';
        $deferred_files_link = '';
        $receiving_option = strtolower(trim((string) ($data['ReceivingOption'] ?? '')));
        $is_pickup = ($receiving_option === 'pickup' || $receiving_option === 'pick up');
        $service_value = strtolower(trim((string) ($data['Service'] ?? '')));
        $allow_dimensions = ($slug === 'print-quote' || $service_value === 'printing');
        $availability_field_keys = [
            'AvailabilityMode',
            'GeneralAvailability',
            'GeneralWeekdayAvailability',
            'GeneralWeekendAvailability',
            'Days',
            'MondaysAvailability',
            'TuesdaysAvailability',
            'WednesdaysAvailability',
            'ThursdaysAvailability',
            'FridaysAvailability',
            'SaturdaysAvailability',
            'SundaysAvailability',
        ];
        $acknowledgment_field_keys = ['Applicant', 'Conduct', 'Confidentiality', 'Training', 'Consent'];
        $normalize_selection = static function ($raw): array {
            if (is_array($raw)) {
                $values = $raw;
            } else {
                $text = trim((string) $raw);
                $values = $text === '' ? [] : preg_split('/\s*,\s*/', $text);
            }
            $values = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $values), static fn($v) => $v !== ''));
            return array_values(array_unique($values));
        };
        $contains_ci = static function (array $haystack, string $needle): bool {
            foreach ($haystack as $item) {
                if (strcasecmp(trim((string) $item), $needle) === 0) {
                    return true;
                }
            }
            return false;
        };
        $format_availability_line = static function (string $label, array $times) use ($contains_ci): string {
            $times = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $times), static fn($v) => $v !== ''));
            if (empty($times) || $contains_ci($times, 'Anytime')) {
                return $label;
            }
            $day_label_map = [
                'Mondays' => 'Monday',
                'Tuesdays' => 'Tuesday',
                'Wednesdays' => 'Wednesday',
                'Thursdays' => 'Thursday',
                'Fridays' => 'Friday',
                'Saturdays' => 'Saturday',
                'Sundays' => 'Sunday',
            ];
            $display_label = (string) ($day_label_map[$label] ?? $label);
            $times_lc = array_map(static fn($v) => strtolower((string) $v), $times);
            if (count($times_lc) === 1) {
                $times_text = $times_lc[0];
            } elseif (count($times_lc) === 2) {
                $times_text = $times_lc[0] . ' and ' . $times_lc[1];
            } else {
                $last = array_pop($times_lc);
                $times_text = implode(', ', $times_lc) . ', and ' . $last;
            }
            return $display_label . ' ' . $times_text;
        };
        $availability_lines = [];
        if ($slug === 'volunteer') {
            $general_groups = $normalize_selection($data['GeneralAvailability'] ?? []);
            $weekday_times = $normalize_selection($data['GeneralWeekdayAvailability'] ?? []);
            $weekend_times = $normalize_selection($data['GeneralWeekendAvailability'] ?? []);
            $selected_days = $normalize_selection($data['Days'] ?? []);

            $is_general_mode = !empty($general_groups) || (!empty($weekday_times) || !empty($weekend_times));
            if ($is_general_mode) {
                if ($contains_ci($general_groups, 'Weekdays') || !empty($weekday_times)) {
                    $availability_lines[] = $format_availability_line('Weekdays', $weekday_times);
                }
                if ($contains_ci($general_groups, 'Weekends') || !empty($weekend_times)) {
                    $availability_lines[] = $format_availability_line('Weekends', $weekend_times);
                }
            } else {
                $day_map = [
                    'Mondays' => 'MondaysAvailability',
                    'Tuesdays' => 'TuesdaysAvailability',
                    'Wednesdays' => 'WednesdaysAvailability',
                    'Thursdays' => 'ThursdaysAvailability',
                    'Fridays' => 'FridaysAvailability',
                    'Saturdays' => 'SaturdaysAvailability',
                    'Sundays' => 'SundaysAvailability',
                ];
                foreach ($day_map as $day_label => $field_name) {
                    $day_times = $normalize_selection($data[$field_name] ?? []);
                    $day_selected = $contains_ci($selected_days, $day_label) || !empty($day_times);
                    if (!$day_selected) {
                        continue;
                    }
                    $availability_lines[] = $format_availability_line($day_label, $day_times);
                }
            }
        }
        $availability_row_added = false;
        $availability_html = implode('<br>', array_map('esc_html', $availability_lines));
        $ack_label_map = [
            'Applicant' => 'Applicant Statement',
            'Conduct' => 'Volunteer Conduct',
            'Confidentiality' => 'Confidentiality',
            'Training' => 'Training',
            'Consent' => 'Consent',
        ];
        $is_truthy = static function ($raw): bool {
            if (is_bool($raw)) {
                return $raw;
            }
            $value = strtolower(trim((string) $raw));
            return in_array($value, ['1', 'true', 'yes', 'on', 'y'], true);
        };
        $acknowledgment_lines = [];
        if ($slug === 'volunteer') {
            foreach ($acknowledgment_field_keys as $ack_key) {
                if (!$is_truthy($data[$ack_key] ?? '')) {
                    continue;
                }
                $acknowledgment_lines[] = (string) ($ack_label_map[$ack_key] ?? ($labels[$ack_key] ?? $ack_key));
            }
        }
        $acknowledgment_row_added = false;
        $acknowledgment_html = implode('<br>', array_map('esc_html', $acknowledgment_lines));
        foreach ($layout as $field_key) {
            if ((string) $field_key === 'NewsletterSignup') {
                $newsletter_value = (string) $value_for('NewsletterSignup');
                continue;
            }
            if ((string) $field_key === 'Comments') {
                $deferred_comments = (string) $value_for('Comments');
                continue;
            }
            if ((string) $field_key === 'FilesLink') {
                $deferred_files_link = (string) $value_for('FilesLink');
                continue;
            }
            if ((string) $field_key === 'Dimensions' && !$allow_dimensions) {
                continue;
            }
            if ((string) $field_key === 'ItemType' && trim((string) ($data['Service'] ?? '')) !== '') {
                continue;
            }
            if ((string) $field_key === 'LocationDisplay' && $is_pickup) {
                continue;
            }
            if ($slug === 'volunteer' && in_array((string) $field_key, $availability_field_keys, true)) {
                if (!$availability_row_added && $availability_html !== '') {
                    $request_rows[] = '<p><strong>Availability:</strong><br>' . $availability_html . '</p>';
                    $availability_row_added = true;
                }
                continue;
            }
            if ($slug === 'volunteer' && in_array((string) $field_key, $acknowledgment_field_keys, true)) {
                if (!$acknowledgment_row_added && $acknowledgment_html !== '') {
                    $request_rows[] = '<p><strong>Acknowledgment(s):</strong><br>' . $acknowledgment_html . '</p>';
                    $acknowledgment_row_added = true;
                }
                continue;
            }
            if (in_array((string) $field_key, ['FullName', 'Company', 'Email', 'Phone'], true)) {
                continue;
            }
            $label = (string) ($labels[$field_key] ?? $field_key);
            $raw = $value_for((string) $field_key);
            if ($raw === '' || $raw === null || $raw === []) continue;

            if ($field_key === 'PickupContact') {
                $name = trim((string) ($raw['name'] ?? ''));
                $phone = trim((string) ($raw['phone'] ?? ''));
                $email = trim((string) ($raw['email'] ?? ''));
                if ($name === '' && $phone === '' && $email === '') {
                    continue;
                }
                if ($name !== '' && $phone !== '') {
                    $display = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($name) . '</a>';
                } elseif ($name !== '') {
                    $display = esc_html($name);
                } elseif ($phone !== '') {
                    $display = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>';
                } else {
                    $display = '';
                }
                if ($email !== '') {
                    $email_html = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                    $display = $display !== '' ? ($display . '<br>' . $email_html) : $email_html;
                }
            } elseif (is_array($raw)) {
                $vals = array_filter(array_map(static fn($v) => trim((string) $v), $raw), static fn($v) => $v !== '');
                if (empty($vals)) continue;
                if ($field_key === 'Interests') {
                    $display = implode('<br>', array_map('esc_html', $vals));
                } else {
                    $display = implode(', ', array_map('esc_html', $vals));
                }
            } else {
                $text = trim((string) $raw);
                if ($text === '') continue;
                if ($field_key === 'Date') {
                    $ts = strtotime($text);
                    if ($ts) {
                        $display = esc_html(date_i18n('F j, Y', $ts));
                        $timezone = function_exists('wp_timezone')
                            ? wp_timezone()
                            : new DateTimeZone(date_default_timezone_get());
                        $dob = (new DateTimeImmutable('now', $timezone))->setTimestamp((int) $ts);
                        $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0);
                        $age = $dob->diff($today)->y;
                        if ($age >= 0) {
                            $display .= ' ' . esc_html('(' . $age . ' years old)');
                        }
                    } else {
                        $display = esc_html($text);
                    }
                } elseif ($field_key === 'DateNeeded') {
                    $ts = strtotime($text);
                    $display = $ts ? esc_html(date_i18n('l, F, j, Y', $ts)) : esc_html($text);
                } elseif ($field_key === 'Duration') {
                    $days = (int) preg_replace('/\D+/', '', $text);
                    if ($days > 0) {
                        $weeks = intdiv($days, 7);
                        $rem = $days % 7;
                        $parts = [];
                        if ($weeks > 0) {
                            $parts[] = $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks');
                        }
                        if ($rem > 0) {
                            $parts[] = $rem . ' ' . ($rem === 1 ? 'day' : 'days');
                        }
                        $display = esc_html(!empty($parts) ? implode(' ', $parts) : '0 days');
                    } else {
                        $display = esc_html($text);
                    }
                } elseif ($field_key === 'Comments') {
                    $display = nl2br(esc_html($text));
                } elseif ($field_key === 'Email') {
                    $display = '<a href="mailto:' . esc_attr($text) . '">' . esc_html($text) . '</a>';
                } elseif ($field_key === 'ContactEmail') {
                    $display = '<a href="mailto:' . esc_attr($text) . '">' . esc_html($text) . '</a>';
                } elseif ($field_key === 'Phone') {
                    $display = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $text)) . '">' . esc_html($text) . '</a>';
                } elseif ($field_key === 'LocationDisplay') {
                    $addr_display = preg_replace('/,\s*(US|USA|United States(?: of America)?)\s*$/i', '', $text);
                    $addr_display = trim((string) $addr_display);
                    if ($addr_display === '') {
                        $addr_display = $text;
                    }
                    $place_id = trim((string) ($data['ReceivingPlaceID'] ?? ''));
                    $receiving_lat = trim((string) ($data['ReceivingLatitude'] ?? ''));
                    $receiving_lng = trim((string) ($data['ReceivingLongitude'] ?? ''));
                    $base_lat = trim((string) ($data['Latitude'] ?? ''));
                    $base_lng = trim((string) ($data['Longitude'] ?? ''));
                    $coords = '';
                    if ($receiving_lat !== '' && $receiving_lng !== '') {
                        $coords = $receiving_lat . ',' . $receiving_lng;
                    } elseif ($base_lat !== '' && $base_lng !== '') {
                        $coords = $base_lat . ',' . $base_lng;
                    }
                    $receiving_option = strtolower(trim((string) ($data['ReceivingOption'] ?? '')));
                    $is_delivery = ($receiving_option === 'delivery' || $receiving_option === 'deliver');
                    if ($is_delivery) {
                        $destination = $coords !== '' ? $coords : $addr_display;
                        $maps_url = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode('My Location')
                            . '&destination=' . rawurlencode($destination);
                        if ($place_id !== '') {
                            $maps_url .= '&destination_place_id=' . rawurlencode($place_id);
                        }
                    } else {
                        $query = $coords !== '' ? $coords : $addr_display;
                        $maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
                        if ($place_id !== '') {
                            $maps_url .= '&query_place_id=' . rawurlencode($place_id);
                        }
                    }
                    $display = '<a href="' . esc_url($maps_url) . '" target="_blank" rel="noopener">' . esc_html($addr_display) . '</a>';
                } else {
                    $display = esc_html($text);
                }
            }

            $display = apply_filters('mzf_admin_field_display', $display, (string) $field_key, $raw, $data, $context, $label);

            if ($slug === 'volunteer' && in_array((string) $field_key, ['PreferredName', 'Pronouns'], true)) {
                continue;
            }
            if ($is_lead_gen) {
                $contact_rows[] = '<p><strong>' . esc_html($label) . ':</strong><br>' . $display . '</p>';
            } elseif ($slug === 'volunteer' && (string) $field_key === 'Date') {
                $contact_rows[] = '<p><strong>' . esc_html($label) . ':</strong><br>' . $display . '</p>';
            } else {
                $request_rows[] = '<p><strong>' . esc_html($label) . ':</strong><br>' . $display . '</p>';
            }
        }
        if ($slug === 'volunteer' && !$availability_row_added && $availability_html !== '') {
            $request_rows[] = '<p><strong>Availability:</strong><br>' . $availability_html . '</p>';
        }
        if ($slug === 'volunteer' && !$acknowledgment_row_added && $acknowledgment_html !== '') {
            $request_rows[] = '<p><strong>Acknowledgment(s):</strong><br>' . $acknowledgment_html . '</p>';
        }
        if (!empty($uploaded_files) && $slug !== 'volunteer') {
            $uploaded_files_label = ($slug === 'volunteer') ? 'Photo ID' : 'Files';
            $request_rows[] = '<p><strong>' . esc_html($uploaded_files_label) . ':</strong><br>' . implode('<br>', $uploaded_files) . '</p>';
        }
        if ($deferred_files_link === '') {
            $deferred_files_link = (string) $value_for('FilesLink');
        }
        $files_link_text = trim($deferred_files_link);
        if ($files_link_text !== '') {
            $sl = esc_url_raw($files_link_text);
            if ($sl !== '') {
                $files_link_label = (string) ($labels['FilesLink'] ?? 'Files Link');
                $file_row = '<p><strong>' . esc_html($files_link_label) . ':</strong><br><a href="' . esc_url($sl) . '" target="_blank" rel="noopener noreferrer">' . esc_html($sl) . '</a></p>';
                if ($is_lead_gen) {
                    $contact_rows[] = $file_row;
                } else {
                    $request_rows[] = $file_row;
                }
            }
        }
        if ($deferred_comments === '') {
            $deferred_comments = (string) $value_for('Comments');
        }
        $comments_text = trim((string) $deferred_comments);
        if ($comments_text !== '') {
            $comments_label = (string) ($labels['Comments'] ?? 'Comments');
            $comment_row = '<p><strong>' . esc_html($comments_label) . ':</strong><br>' . nl2br(esc_html($comments_text)) . '</p>';
            if ($is_lead_gen) {
                $contact_rows[] = $comment_row;
            } else {
                $request_rows[] = $comment_row;
            }
        }
        if ($slug === 'volunteer' && !empty($uploaded_files)) {
            $request_rows[] = '<p><strong>Photo ID:</strong><br>' . implode('<br>', $uploaded_files) . '</p>';
        }
        if ($newsletter_value === '') {
            $newsletter_value = (string) $value_for('NewsletterSignup');
        }
        $newsletter_raw = strtolower(trim((string) $newsletter_value));
        $newsletter_yes = in_array($newsletter_raw, ['yes', '1', 'true', 'on', 'y'], true);
        $marketing_sync = isset($context['marketing_sync']) && is_array($context['marketing_sync']) ? $context['marketing_sync'] : [];
        $crm_platform = function_exists('mzf_crm_platform') ? mzf_crm_platform() : '';
        $crm_label = function_exists('mzf_crm_platform_label') ? mzf_crm_platform_label($crm_platform) : '';
        $crm_url = ($crm_platform !== '' && function_exists('mzf_crm_click_url')) ? mzf_crm_click_url($crm_platform) : '';
        $env = function_exists('wp_get_environment_type') ? strtolower((string) wp_get_environment_type()) : 'production';
        $is_live_env = in_array($env, ['production', 'qa'], true);
        if ($crm_platform !== '' && $crm_url === '' && function_exists('mzf_crm_dashboard_url')) {
            $crm_url = mzf_crm_dashboard_url($crm_platform);
        }

        if ($newsletter_yes && !$is_lead_gen) {
            $newsletter_line = 'Yes';
            $sync_ok = !empty($marketing_sync['ok']);
            $sync_label = trim((string) ($marketing_sync['label'] ?? ''));
            $sync_url = trim((string) ($marketing_sync['contact_url'] ?? ''));
            $sync_link_text = trim((string) ($marketing_sync['link_text'] ?? ''));
            $sync_action = strtolower(trim((string) ($marketing_sync['action'] ?? '')));
            if ($sync_ok && $sync_label !== '') {
                if ($is_live_env && $sync_url !== '') {
                    if ($sync_link_text === '') {
                        if (in_array($sync_action, ['update', 'updated'], true)) {
                            $sync_link_text = 'Updated in ' . $sync_label;
                        } else {
                            $sync_link_text = 'Added to ' . $sync_label;
                        }
                    }
                    $newsletter_line .= ' (<a href="' . esc_url($sync_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($sync_link_text) . '</a>)';
                }
            } elseif ($is_live_env && $crm_platform !== '' && $crm_url !== '') {
                $newsletter_line .= ' (<a href="' . esc_url($crm_url) . '" target="_blank" rel="noopener noreferrer">Click to add to ' . esc_html($crm_label) . '</a>)';
            } elseif ($is_live_env && $crm_platform !== '' && $crm_label !== '') {
                $newsletter_line .= ' (Click to add to ' . esc_html($crm_label) . ')';
            }
            $marketing_rows[] = '<p><strong>' . esc_html((string) ($labels['NewsletterSignup'] ?? 'Signed Up for Newsletter')) . ':</strong><br>' . $newsletter_line . '</p>';
        }
        if ($is_lead_gen && $lead_gen_page_row !== '') {
            $marketing_rows[] = $lead_gen_page_row;
        }

        if (!empty($contact_rows)) {
            $body .= '<hr><h3 style="margin:1em 0 .5em 0;">Contact Details</h3>';
            $body .= implode('', $contact_rows);
        }
        if (!empty($request_rows) && !$is_lead_gen) {
            $request_heading = (string) apply_filters('mzf_admin_request_heading', 'Request Details', $data, $context);
            $body .= '<hr><h3 style="margin:1em 0 .5em 0;">' . esc_html($request_heading) . '</h3>';
            $body .= implode('', $request_rows);
        }
        if (!empty($marketing_rows)) {
            $body .= '<hr><h3 style="margin:1em 0 .5em 0;">Marketing Details</h3>';
            $body .= implode('', $marketing_rows);
        }

        if ($footer !== '') {
            $body .= $footer;
        }
        return $body !== '' ? $body : $fallback_body;
    }
}

if (!function_exists('mzf_first_nonempty_value')) {
    function mzf_first_nonempty_value(array $data, array $keys, bool $join_arrays = true): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $val = $data[$key];
            if (is_array($val)) {
                $parts = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $val), static fn($v) => $v !== ''));
                if (!empty($parts)) {
                    return $join_arrays ? implode(', ', $parts) : (string) reset($parts);
                }
                continue;
            }
            $text = trim((string) $val);
            if ($text !== '') {
                return $text;
            }
        }
        return '';
    }
}

if (!function_exists('mzf_has_base_contract')) {
    function mzf_has_base_contract(array $data = []): bool
    {
        return !empty($data['FormSlug'])
            && !empty($data['FirstName'])
            && !empty($data['LastName'])
            && !empty($data['Email'])
            && !empty($data['PageId']);
    }
}

if (!function_exists('mzf_has_commercial_contract')) {
    function mzf_has_commercial_contract(array $data = []): bool
    {
        if (!mzf_has_base_contract($data)) {
            return false;
        }
        $has_item = !empty($data['ItemType']) || !empty($data['ItemName']) || !empty($data['ProductId']);
        $has_date = !empty($data['DateNeeded']) || !empty($data['Date']) || !empty($data['RentalDate']);
        return $has_item && $has_date;
    }
}

if (!function_exists('mzf_has_fp_contract')) {
    function mzf_has_fp_contract(array $data = []): bool
    {
        if (!mzf_has_base_contract($data)) {
            return false;
        }
        $slug = function_exists('mzf_get_form_slug') ? mzf_get_form_slug($data) : sanitize_key((string) ($data['FormSlug'] ?? ''));
        $slug = function_exists('mzf_normalize_form_slug') ? mzf_normalize_form_slug($slug) : $slug;
        if (!in_array($slug, ['rent', 'buy', 'consulting'], true)) {
            return false;
        }

        if ($slug === 'consulting') {
            return !empty($data['Explosive']) && !empty($data['Weight']);
        }

        $has_item = !empty($data['ItemName']) || !empty($data['ItemType']) || !empty($data['ProductId']);
        $has_date = !empty($data['DateNeeded']);
        if (!$has_item || !$has_date) {
            return false;
        }
        if ($slug === 'rent' && empty($data['Duration'])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('mzf_has_ccfbg_contract')) {
    function mzf_has_ccfbg_contract(array $data = []): bool
    {
        if (!mzf_has_base_contract($data)) {
            return false;
        }
        $slug = function_exists('mzf_get_form_slug') ? mzf_get_form_slug($data) : sanitize_key((string) ($data['FormSlug'] ?? ''));
        $slug = function_exists('mzf_normalize_form_slug') ? mzf_normalize_form_slug($slug) : $slug;
        if (!in_array($slug, ['contact', 'audition'], true)) {
            return false;
        }
        if ($slug === 'audition' && trim((string) ($data['Vocals'] ?? '')) === '') {
            return false;
        }
        return true;
    }
}

if (!function_exists('mzf_has_fh_contract')) {
    function mzf_has_fh_contract(array $data = []): bool
    {
        $slug = function_exists('mzf_get_form_slug') ? mzf_get_form_slug($data) : sanitize_key((string) ($data['FormSlug'] ?? ''));
        $slug = function_exists('mzf_normalize_form_slug') ? mzf_normalize_form_slug($slug) : $slug;
        if (!in_array($slug, ['contact', 'volunteer', 'medical'], true)) {
            return false;
        }
        if (trim((string) ($data['FirstName'] ?? '')) === '' || trim((string) ($data['Email'] ?? '')) === '') {
            return false;
        }
        return true;
    }
}

if (!function_exists('mzf_resolve_product_id')) {
    function mzf_resolve_product_id(array $data): int
    {
        $product_id = !empty($data['ProductId']) ? absint($data['ProductId']) : 0;
        $page_id = !empty($data['PageId']) ? absint($data['PageId']) : 0;
        if (!$product_id && $page_id && get_post_type($page_id) === 'product') {
            $product_id = $page_id;
        }
        if (!$product_id && isset($_POST['product_id'])) {
            $product_id = absint($_POST['product_id']);
        }
        if (!$product_id && isset($_POST['add-to-cart'])) {
            $product_id = absint($_POST['add-to-cart']);
        }
        return $product_id;
    }
}

if (!function_exists('mzf_is_machine_product')) {
    function mzf_is_machine_product(int $product_id): bool
    {
        if ($product_id <= 0 || !function_exists('get_term_by') || !function_exists('wp_get_post_terms')) {
            return false;
        }
        $machines_term = get_term_by('slug', 'machines', 'product_cat');
        if (!$machines_term || is_wp_error($machines_term)) {
            return false;
        }
        $machine_ids = array_merge(
            [(int) $machines_term->term_id],
            array_map('intval', (array) get_term_children((int) $machines_term->term_id, 'product_cat'))
        );
        $product_terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (is_wp_error($product_terms) || empty($product_terms)) {
            return false;
        }
        return (bool) array_intersect($machine_ids, $product_terms);
    }
}

if (!function_exists('mzf_validate_commercial_submission')) {
    function mzf_validate_commercial_submission(array $data)
    {
        $slug = function_exists('mzf_get_form_slug') ? mzf_get_form_slug($data) : sanitize_key((string) ($data['FormSlug'] ?? ''));
        $slug = function_exists('mzf_normalize_form_slug') ? mzf_normalize_form_slug($slug) : $slug;
        if (!in_array($slug, ['rent', 'buy', 'consulting'], true)) {
            return true;
        }

        if ($slug === 'consulting') {
            if (trim((string) ($data['Explosive'] ?? '')) === '') {
                return new WP_Error('mz_consulting_explosive_missing', 'Explosive field is required.');
            }
            if (trim((string) ($data['Weight'] ?? '')) === '') {
                return new WP_Error('mz_consulting_weight_missing', 'Weight field is required.');
            }
            return true;
        }

        $date_needed = trim((string) ($data['DateNeeded'] ?? ''));
        if ($date_needed === '') {
            return new WP_Error('mz_date_needed_missing', 'Date needed is required.');
        }

        if ($slug === 'rent') {
            $duration = (int) ($data['Duration'] ?? 0);
            if ($duration < 1) {
                return new WP_Error('mz_duration_invalid', 'Duration must be at least 1 week.');
            }
        }

        $product_id = mzf_resolve_product_id($data);
        $is_machine = mzf_is_machine_product($product_id);
        $qty = (int) ($data['Quantity'] ?? 0);
        if ($is_machine && $qty < 1) {
            return new WP_Error('mz_qty_invalid', 'Quantity must be at least 1.');
        }

        return true;
    }
}

if (!function_exists('mzf_validate_volunteer_submission')) {
    function mzf_validate_volunteer_submission(array $data)
    {
        $slug = function_exists('mzf_get_form_slug') ? mzf_get_form_slug($data) : sanitize_key((string) ($data['FormSlug'] ?? ''));
        $slug = function_exists('mzf_normalize_form_slug') ? mzf_normalize_form_slug($slug) : $slug;
        if ($slug !== 'volunteer') {
            return true;
        }

        $dob_raw = trim((string) ($data['Date'] ?? ''));
        if ($dob_raw === '') {
            return new WP_Error('mz_dob_missing', 'Date of birth is required.');
        }

        $timezone = function_exists('wp_timezone')
            ? wp_timezone()
            : new DateTimeZone(date_default_timezone_get());
        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $dob_raw, $timezone);
        if (!$dob) {
            $ts = strtotime($dob_raw);
            if ($ts === false) {
                return new WP_Error('mz_dob_invalid', 'Please enter a valid date of birth.');
            }
            $dob = (new DateTimeImmutable('now', $timezone))->setTimestamp((int) $ts);
        }

        $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0);
        $max_dob = $today->modify('-18 years');
        if ($dob > $max_dob) {
            return new WP_Error('mz_dob_underage', 'Volunteers must be age 18 or older.');
        }

        $photo_field = $_FILES['PhotoID'] ?? null;
        $has_photo_id = false;
        $photo_file_count = 0;
        if (is_array($photo_field) && array_key_exists('name', $photo_field)) {
            if (is_array($photo_field['name'])) {
                foreach ((array) $photo_field['name'] as $idx => $name) {
                    $name = trim((string) $name);
                    $err = isset($photo_field['error'][$idx]) ? (int) $photo_field['error'][$idx] : UPLOAD_ERR_NO_FILE;
                    if ($name !== '' && $err !== UPLOAD_ERR_NO_FILE) {
                        $photo_file_count++;
                        $has_photo_id = true;
                    }
                }
            } else {
                $name = trim((string) ($photo_field['name'] ?? ''));
                $err = isset($photo_field['error']) ? (int) $photo_field['error'] : UPLOAD_ERR_NO_FILE;
                $has_photo_id = ($name !== '' && $err !== UPLOAD_ERR_NO_FILE);
                if ($has_photo_id) {
                    $photo_file_count = 1;
                }
            }
        }
        if (!$has_photo_id) {
            return new WP_Error('mz_photo_id_missing', 'Photo ID upload is required.');
        }
        if ($photo_file_count > 1) {
            return new WP_Error('mz_photo_id_too_many', 'Please upload exactly one Photo ID file.');
        }

        return true;
    }
}

if (!function_exists('mzf_legacy_key_map')) {
    function mzf_legacy_key_map(): array
    {
        return [
            'Comment' => 'Comments',
            'Message' => 'Comments',
            'message' => 'Comments',
            'Notes' => 'Comments',
            'Note' => 'Comments',
            'Inquiry' => 'Comments',
            'Details' => 'Comments',
            'Description' => 'Comments',
            'form_slug' => 'FormSlug',
        ];
    }
}

if (!function_exists('mzf_find_legacy_keys')) {
    function mzf_find_legacy_keys(array $src = []): array
    {
        $legacy_map = (array) apply_filters('mzf_legacy_key_map', mzf_legacy_key_map());
        $found = [];
        foreach ($legacy_map as $legacy_key => $canonical_key) {
            if (!array_key_exists((string) $legacy_key, $src)) {
                continue;
            }
            $raw = $src[(string) $legacy_key];
            $is_empty = is_array($raw)
                ? count(array_filter(array_map(static fn($v) => trim((string) $v), $raw), static fn($v) => $v !== '')) === 0
                : trim((string) $raw) === '';
            if ($is_empty) {
                continue;
            }
            $found[(string) $legacy_key] = (string) $canonical_key;
        }
        return $found;
    }
}

if (!function_exists('ds_replace_tokens')) {
    function ds_replace_tokens(string $text, array $data): string
    {
        if ($text === '' || empty($data)) return $text;
        $replacements = [];
        foreach ($data as $key => $val) {
            if (!is_array($val) && !is_object($val)) {
                $replacements['{{' . $key . '}}'] = (string) $val;
                $replacements['[[' . $key . ']]'] = (string) $val;
            }
        }
        if (!isset($replacements['{{Name}}'])) {
            $first = isset($data['FirstName']) ? trim((string) $data['FirstName']) : '';
            $last  = isset($data['LastName'])  ? trim((string) $data['LastName'])  : '';
            $full  = trim($first . ' ' . $last);
            if ($full !== '') {
                $replacements['{{Name}}'] = $full;
                $replacements['[[Name]]'] = $full;
            }
        }
        return strtr($text, $replacements);
    }
}

if (!function_exists('ds_strip_outer_p')) {
    function ds_strip_outer_p(string $html): string
    {
        $t = trim($html);
        if ($t === '' || stripos($t, '<p') !== 0) return $html;
        $t = preg_replace('/^\x{FEFF}|\x{00A0}+$/u', '', $t);
        if (preg_match('#^<p\b[^>]*>(.*)</p>\s*$#is', $t, $m)) return $m[1];
        return $html;
    }
}

if (!function_exists('ds_resolve_office_email')) {
    /**
     * Resolve an office email based on Store selection or location term in referrer,
     * falling back to option email when Store is empty.
     */
    function ds_resolve_office_email($store_raw = '', $ref_path = '')
    {
        if (!function_exists('get_field')) {
            return '';
        }

        // Helper: best-match office by slug similarity
        $find_office_by_store = function (string $store_raw) {
            $store_slug = $store_raw ? sanitize_title($store_raw) : '';
            if ($store_slug === '') return 0;

            $q = new WP_Query([
                'post_type'      => 'office',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
            ]);

            $best_id = 0;
            $best_score = -1;
            if ($q->have_posts()) {
                foreach ($q->posts as $pid) {
                    $slug  = (string) get_post_field('post_name', $pid);
                    $score = ($slug === $store_slug) ? 3 : ((strpos($slug, $store_slug) === 0) ? 2 : ((strpos($slug, $store_slug) !== false) ? 1 : 0));
                    if ($score > $best_score) {
                        $best_score = $score;
                        $best_id = (int) $pid;
                        if ($score === 3) break;
                    }
                }
                wp_reset_postdata();
            } else {
                wp_reset_postdata();
            }
            return $best_id;
        };

        // Helper: find first office that has a specific location term slug
        $find_office_by_location_slug = function (string $loc_slug) {
            if ($loc_slug === '') return 0;
            $q = new WP_Query([
                'post_type'      => 'office',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'tax_query'      => [[
                    'taxonomy' => 'location',
                    'field'    => 'slug',
                    'terms'    => $loc_slug,
                ]],
            ]);
            if ($q->have_posts()) {
                $pid = (int) $q->posts[0];
                wp_reset_postdata();
                return $pid;
            }
            wp_reset_postdata();
            return 0;
        };

        // 1) If Store provided → try Store match first
        $email = '';
        $office_id = 0;
        if (!empty($store_raw)) {
            $office_id = $find_office_by_store((string) $store_raw);
            if ($office_id) {
                $email = sanitize_email((string) get_field('email', $office_id));
                if (is_email($email)) return $email;
            }
        }

        // 2) If we’re on a location term page → use office that has that term (overrides default)
        $loc_slug = '';
        if ($ref_path && preg_match('#/(location)/([^/]+)/?#i', $ref_path, $m)) {
            $loc_slug = sanitize_title($m[2]);
        }
        if ($loc_slug !== '') {
            $loc_office = $find_office_by_location_slug($loc_slug);
            if ($loc_office) {
                $email = sanitize_email((string) get_field('email', $loc_office));
                if (is_email($email)) return $email;
            }
        }

        // 3) Fallbacks
        // If no Store value (or none matched), default to option email
        $opt_email = function_exists('meza_get_business_information_email')
            ? sanitize_email(meza_get_business_information_email())
            : sanitize_email((string) get_field('email', 'option'));
        if (is_email($opt_email)) return $opt_email;

        return ''; // last resort
    }
}
