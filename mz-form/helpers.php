<?php

if (!function_exists('ds_build_user_email')) {
    function ds_build_user_email(array $form_data, string $footer = ''): array
    {
        $page_id  = isset($form_data['PageId']) ? (int) $form_data['PageId'] : 0;
        $form_cfg = (function_exists('get_field') && $page_id) ? get_field('section_form', $page_id) : null;
        $form_cfg = is_array($form_cfg) ? $form_cfg : [];

        $sig_name = trim((string) ($form_cfg['email_signature'] ?? 'The Distinct Sign Solutions Team'));
        $subject  = ds_replace_tokens((string) ($form_cfg['email_subject'] ?? 'Thank you'), $form_data);

        $content  = ds_replace_tokens((string) ($form_cfg['email_content'] ?? ''), $form_data);
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

        $first = trim((string) ($form_data['FirstName'] ?? ''));
        $site_name_raw = (string) get_bloginfo('name');

        $subject = (string) mzf_form_config_value($cfg, ['email_user.subject', 'email_subject'], 'Thank you');
        $subject = ds_replace_tokens($subject, $form_data);

        $intro = (string) mzf_form_config_value($cfg, ['email_user.intro', 'email_intro'], '');
        $main = (string) mzf_form_config_value($cfg, ['email_user.body', 'email_content'], '');
        $salutation = (string) mzf_form_config_value($cfg, ['email_user.salutation', 'email_salutation'], '');
        $signature = (string) mzf_form_config_value($cfg, ['email_user.signature', 'email_signature'], ('The ' . $site_name_raw . ' Team'));
        $signature = str_replace('{site name}', $site_name_raw, $signature);

        $greeting_html = $intro !== ''
            ? '<p>' . esc_html($intro) . ' ' . esc_html($first) . ',</p>'
            : ($first !== '' ? '<p>Hey ' . esc_html($first) . ',</p>' : '<p>Hello,</p>');

        $main_html = ds_replace_tokens($main, $form_data);
        $main_html = ds_strip_outer_p($main_html);
        if ($main_html === '') {
            $main_html = '<p>Thanks for reaching out — we’ll follow up shortly.</p>';
        }

        $salutation_html = $salutation !== ''
            ? '<p>' . wp_kses_post($salutation) . '<br>'
            : '<p>Sincerely,<br>';

        $email = [
            'subject' => $subject,
            'body' => $greeting_html . $main_html . $salutation_html . '<strong>' . esc_html($signature) . '</strong></p>' . $footer,
        ];
        return (array) apply_filters('mzf_user_email', $email, $form_data, $footer);
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

            return (array) get_fields($post_id);
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
        $variant = strtolower(trim((string) $variant));
        $addr_display = trim((string) $org_addr_display);
        $maps_url = trim((string) $org_maps_url);
        $phone = trim((string) $org_phone);
        $phone_href = trim((string) $org_phone_href);
        $email_display = trim((string) $org_email_display);
        $site_url = trim((string) $site_url);
        $domain = trim((string) $domain);

        // FH-style admin footer: full site URL in footer link.
        if ($variant === 'admin') {
            return '<hr><p><small>'
                . ($addr_display !== '' && $maps_url !== '' ? '<a href="' . esc_url($maps_url) . '" target="_blank" rel="noopener">' . esc_html($addr_display) . '</a><br>' : '')
                . ($phone !== '' ? '<a href="tel:' . esc_attr($phone_href) . '" target="_blank">' . esc_html($phone) . '</a>' : '')
                . '<br>'
                . ($email_display !== '' ? '<a href="mailto:' . esc_attr($email_display) . '" target="_blank">' . esc_html($email_display) . '</a><br>' : '')
                . ($site_url !== '' ? '<a href="' . esc_url($site_url) . '" target="_blank">' . $site_url . '</a>' : '')
                . '</small></p>';
        }

        // FH-style user footer: canonical domain link.
        $domain_href = $domain !== '' ? ('https://' . $domain) : '';
        return '<hr><p><small>'
            . ($addr_display !== '' && $maps_url !== '' ? '<a href="' . esc_url($maps_url) . '" target="_blank" rel="noopener">' . esc_html($addr_display) . '</a><br>' : '')
            . ($phone !== '' ? '<a href="tel:' . esc_attr($phone_href) . '" target="_blank">' . esc_html($phone) . '</a>' : '')
            . '<br>'
            . ($email_display !== '' ? '<a href="mailto:' . esc_attr($email_display) . '" target="_blank">' . esc_html($email_display) . '</a><br>' : '')
            . ($domain_href !== '' ? '<a href="' . esc_url($domain) . '" target="_blank">' . $domain_href . '</a>' : '')
            . '</small></p>';
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
        if (empty($layout)) {
            return $fallback_body;
        }

        $labels = (array) apply_filters('mzf_field_labels', mzf_default_field_labels(), $data, $context);
        // Dynamic label overrides preserve original client wording without profile switches.
        if (trim((string) ($data['Organization'] ?? '')) !== '') {
            $labels['Company'] = 'Company/Organization';
        }
        if (!empty($data['Interests'])) {
            $labels['Interest'] = 'Interests';
        }
        if (trim((string) ($data['Service'] ?? '')) !== '') {
            $labels['ItemType'] = 'Service';
        }
        if (
            trim((string) ($data['RentalDate'] ?? '')) !== ''
            || trim((string) ($data['Deadline'] ?? '')) !== ''
            || trim((string) ($data['Date'] ?? '')) !== ''
        ) {
            $labels['DateNeeded'] = 'Rental Date';
        }
        if (trim((string) ($data['RentalDuration'] ?? '')) !== '' || trim((string) ($data['Days'] ?? '')) !== '') {
            $labels['Duration'] = 'Rental Duration';
        }
        if (
            in_array($slug, ['rent', 'buy'], true)
            || trim((string) ($data['ReceivingAddress'] ?? '')) !== ''
            || trim((string) ($data['ReceivingAddressDisplay'] ?? '')) !== ''
        ) {
            $labels['LocationDisplay'] = 'Shipping Address';
        }
        $full_name = trim((string) ($data['FirstName'] ?? '') . ' ' . (string) ($data['LastName'] ?? ''));
        $footer = (string) ($context['footer_html'] ?? '');

        $value_for = static function (string $key) use ($data, $full_name) {
            if ($key === 'FullName') return $full_name;
            if ($key === 'Email') return (string) ($data['Email'] ?? '');
            if ($key === 'Phone') return (string) ($data['Phone'] ?? '');
            if ($key === 'Comments') return (string) ($data['Comments'] ?? '');
            if ($key === 'Vehicle') {
                return trim((string) ($data['VehicleYear'] ?? '') . ' ' . (string) ($data['VehicleMake'] ?? '') . ' ' . (string) ($data['VehicleModel'] ?? ''));
            }
            if ($key === 'NewsletterSignup') {
                $raw = $data['NewsletterSignup'] ?? '';
                if (is_bool($raw)) return $raw ? 'Yes' : 'No';
                $s = strtolower(trim((string) $raw));
                return in_array($s, ['1', 'true', 'yes', 'on', 'y'], true) ? 'Yes' : 'No';
            }
            if ($key === 'LocationDisplay') {
                $loc = trim((string) ($data['LocationDisplay'] ?? ''));
                if ($loc !== '') return $loc;
                return trim((string) ($data['Location'] ?? ''));
            }
            if ($key === 'Company') {
                $company = trim((string) ($data['Company'] ?? ''));
                if ($company !== '') return $company;
                return trim((string) ($data['Organization'] ?? ''));
            }
            return isset($data[$key]) ? $data[$key] : '';
        };

        $body = '';
        foreach ($layout as $field_key) {
            $label = (string) ($labels[$field_key] ?? $field_key);
            $raw = $value_for((string) $field_key);
            if ($raw === '' || $raw === null || $raw === []) continue;

            if (is_array($raw)) {
                $vals = array_filter(array_map(static fn($v) => trim((string) $v), $raw), static fn($v) => $v !== '');
                if (empty($vals)) continue;
                $display = implode(', ', array_map('esc_html', $vals));
            } else {
                $text = trim((string) $raw);
                if ($text === '') continue;
                if ($field_key === 'Comments' || $field_key === 'Reason') {
                    $display = nl2br(esc_html($text));
                } elseif ($field_key === 'Email') {
                    $display = '<a href="mailto:' . esc_attr($text) . '">' . esc_html($text) . '</a>';
                } elseif ($field_key === 'Phone') {
                    $display = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $text)) . '">' . esc_html($text) . '</a>';
                } else {
                    $display = esc_html($text);
                }
            }

            $body .= '<p><strong>' . esc_html($label) . ':</strong><br>' . $display . '</p>';
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
        if (!in_array($slug, ['contact', 'volunteer', 'medical', 'condoms'], true)) {
            return false;
        }
        if (trim((string) ($data['FirstName'] ?? '')) === '' || trim((string) ($data['Email'] ?? '')) === '') {
            return false;
        }
        if ($slug === 'condoms' && trim((string) ($data['CondomCount'] ?? '')) === '') {
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
            if (!is_array($val) && !is_object($val)) $replacements['{{' . $key . '}}'] = (string) $val;
        }
        if (!isset($replacements['{{Name}}'])) {
            $first = isset($data['FirstName']) ? trim((string) $data['FirstName']) : '';
            $last  = isset($data['LastName'])  ? trim((string) $data['LastName'])  : '';
            $full  = trim($first . ' ' . $last);
            if ($full !== '') $replacements['{{Name}}'] = $full;
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
        $opt_email = sanitize_email((string) get_field('email', 'option'));
        if (is_email($opt_email)) return $opt_email;

        return ''; // last resort
    }
}
