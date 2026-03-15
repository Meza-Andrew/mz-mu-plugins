<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mzf_default_config')) {
    function mzf_default_config(): array
    {
        return [
            'admin_bcc_email' => 'info@meza.design',
            'default_env'     => 'production',
            'profile'         => 'auto', // auto|ds|fh
            'marketing_provider' => 'none', // none|mailchimp|constant_contact
            'strict_mode' => false, // enforce canonical payload/slug only
            'strict_log_deprecations' => true, // log legacy key usage while transitioning
            'reject_unknown_fields' => true, // reject POST keys outside canonical allowlist
            'debug_enabled' => false, // enable diagnostic mode
            'debug_log' => true, // write debug events to error_log
            'debug_response' => false, // include debug payload in JSON responses
            'debug_key' => '', // optional shared secret for nopriv debug access
        ];
    }
}

if (!function_exists('mzf_get_config')) {
    function mzf_get_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = (array) apply_filters('mzf_config', mzf_default_config());
        return $config;
    }
}

if (!function_exists('mzf_get')) {
    function mzf_get(string $key, $fallback = null)
    {
        $config = mzf_get_config();
        return array_key_exists($key, $config) ? $config[$key] : $fallback;
    }
}

if (!function_exists('mzf_get_crm_group')) {
    function mzf_get_crm_group(): array
    {
        $crm = [];

        if (function_exists('get_field')) {
            $acf_crm = get_field('crm', 'option');
            if (is_array($acf_crm)) {
                $crm = $acf_crm;
            } else {
                // Backward/fallback read for environments using reversed args.
                $acf_crm_fallback = get_field('option', 'crm');
                if (is_array($acf_crm_fallback)) {
                    $crm = $acf_crm_fallback;
                }
            }
        }

        if (empty($crm)) {
            $opt_crm = get_option('crm');
            if (is_array($opt_crm)) {
                $crm = $opt_crm;
            }
        }

        return is_array($crm) ? $crm : [];
    }
}

if (!function_exists('mzf_maybe_migrate_mailchimp_to_crm')) {
    function mzf_maybe_migrate_mailchimp_to_crm(): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        $crm = mzf_get_crm_group();
        if (!is_array($crm)) {
            $crm = [];
        }

        $legacy_crm = get_option('crm');
        if (!is_array($legacy_crm)) {
            $legacy_crm = [];
        }
        $options_crm = get_option('options_crm');
        if (!is_array($options_crm)) {
            $options_crm = [];
        }
        $mc4wp = get_option('mc4wp');
        if (!is_array($mc4wp)) {
            $mc4wp = [];
        }
        $mc4wp_mailchimp = get_option('mc4wp_mailchimp');
        if (!is_array($mc4wp_mailchimp)) {
            $mc4wp_mailchimp = [];
        }

        $legacy_api_key_candidates = [
            trim((string) ($crm['api_mailchimp'] ?? '')),
            trim((string) ($options_crm['api_mailchimp'] ?? '')),
            trim((string) ($legacy_crm['api_mailchimp'] ?? '')),
            trim((string) get_option('options_crm_api_mailchimp', '')),
            trim((string) get_option('options_mz_mc_api_key', '')),
            trim((string) get_option('options_mailchimp_api_key', '')),
            trim((string) get_option('mz_mc_api_key', '')),
            trim((string) get_option('mailchimp_api_key', '')),
            trim((string) ($mc4wp_mailchimp['api_key'] ?? '')),
            trim((string) ($mc4wp['api_key'] ?? '')),
            trim((string) ($mc4wp['mailchimp']['api_key'] ?? '')),
            defined('MAILCHIMP_API_KEY') ? trim((string) MAILCHIMP_API_KEY) : '',
        ];
        $legacy_list_id_candidates = [
            trim((string) ($crm['list_mailchimp'] ?? '')),
            trim((string) ($options_crm['list_mailchimp'] ?? '')),
            trim((string) ($legacy_crm['list_mailchimp'] ?? '')),
            trim((string) get_option('options_crm_list_mailchimp', '')),
            trim((string) get_option('options_mz_mc_list_id', '')),
            trim((string) get_option('options_mailchimp_list_id', '')),
            trim((string) get_option('mz_mc_list_id', '')),
            trim((string) get_option('mailchimp_list_id', '')),
            trim((string) ($mc4wp_mailchimp['list_id'] ?? '')),
            trim((string) ($mc4wp_mailchimp['default_list'] ?? '')),
            trim((string) ($mc4wp['mailchimp']['list_id'] ?? '')),
            trim((string) ($mc4wp['mailchimp']['default_list'] ?? '')),
            defined('MAILCHIMP_LIST_ID') ? trim((string) MAILCHIMP_LIST_ID) : '',
        ];

        $legacy_api_key = '';
        foreach ($legacy_api_key_candidates as $candidate) {
            if ($candidate !== '') {
                $legacy_api_key = $candidate;
                break;
            }
        }

        $legacy_list_id = '';
        foreach ($legacy_list_id_candidates as $candidate) {
            if ($candidate !== '') {
                $legacy_list_id = $candidate;
                break;
            }
        }

        if ($legacy_api_key === '' && $legacy_list_id === '') {
            return;
        }

        $dirty = false;
        if ($legacy_api_key !== '' && trim((string) ($crm['api_mailchimp'] ?? '')) === '') {
            $crm['api_mailchimp'] = $legacy_api_key;
            $dirty = true;
        }
        if ($legacy_list_id !== '' && trim((string) ($crm['list_mailchimp'] ?? '')) === '') {
            $crm['list_mailchimp'] = $legacy_list_id;
            $dirty = true;
        }
        $mailchimp_group = isset($crm['mailchimp']) && is_array($crm['mailchimp']) ? $crm['mailchimp'] : [];
        if ($legacy_api_key !== '' && trim((string) ($mailchimp_group['api'] ?? '')) === '') {
            $mailchimp_group['api'] = $legacy_api_key;
            $crm['mailchimp'] = $mailchimp_group;
            $dirty = true;
        }
        if ($legacy_list_id !== '' && trim((string) ($mailchimp_group['list'] ?? '')) === '') {
            $mailchimp_group['list'] = $legacy_list_id;
            $crm['mailchimp'] = $mailchimp_group;
            $dirty = true;
        }
        $platform_raw = trim((string) ($crm['platform'] ?? ''));
        if ($legacy_api_key !== '' && $platform_raw === '') {
            // Match ACF select choice values so conditional sub-fields show in admin UI.
            $crm['platform'] = 'MailChimp';
            $dirty = true;
        } elseif (strtolower($platform_raw) === 'mailchimp' && $platform_raw !== 'MailChimp') {
            // Repair previously stored variants so conditional sub-fields show in admin UI.
            $crm['platform'] = 'MailChimp';
            $dirty = true;
        }

        if (!$dirty) {
            return;
        }

        if (function_exists('update_field')) {
            $saved = update_field('crm', $crm, 'option');
            if ($saved !== false) {
                // Keep explicit sub-field options in sync for ACF UIs reading direct option keys.
                if (trim((string) ($crm['api_mailchimp'] ?? '')) !== '') {
                    update_option('options_crm_api_mailchimp', (string) $crm['api_mailchimp'], false);
                }
                if (trim((string) ($crm['list_mailchimp'] ?? '')) !== '') {
                    update_option('options_crm_list_mailchimp', (string) $crm['list_mailchimp'], false);
                }
                if (!empty($crm['mailchimp']) && is_array($crm['mailchimp'])) {
                    update_option('options_crm_mailchimp', $crm['mailchimp'], false);
                    if (trim((string) ($crm['mailchimp']['api'] ?? '')) !== '') {
                        update_option('options_crm_mailchimp_api', (string) $crm['mailchimp']['api'], false);
                    }
                    if (trim((string) ($crm['mailchimp']['list'] ?? '')) !== '') {
                        update_option('options_crm_mailchimp_list', (string) $crm['mailchimp']['list'], false);
                    }
                }
                if (trim((string) ($crm['platform'] ?? '')) !== '') {
                    update_option('options_crm_platform', (string) $crm['platform'], false);
                }
                return;
            }
        }

        update_option('crm', $crm, false);
        update_option('options_crm', $crm, false);
        if (trim((string) ($crm['api_mailchimp'] ?? '')) !== '') {
            update_option('options_crm_api_mailchimp', (string) $crm['api_mailchimp'], false);
        }
        if (trim((string) ($crm['list_mailchimp'] ?? '')) !== '') {
            update_option('options_crm_list_mailchimp', (string) $crm['list_mailchimp'], false);
        }
        if (!empty($crm['mailchimp']) && is_array($crm['mailchimp'])) {
            update_option('options_crm_mailchimp', $crm['mailchimp'], false);
            if (trim((string) ($crm['mailchimp']['api'] ?? '')) !== '') {
                update_option('options_crm_mailchimp_api', (string) $crm['mailchimp']['api'], false);
            }
            if (trim((string) ($crm['mailchimp']['list'] ?? '')) !== '') {
                update_option('options_crm_mailchimp_list', (string) $crm['mailchimp']['list'], false);
            }
        }
        if (trim((string) ($crm['platform'] ?? '')) !== '') {
            update_option('options_crm_platform', (string) $crm['platform'], false);
        }
    }
}

add_action('acf/init', function () {
    mzf_maybe_migrate_mailchimp_to_crm();
}, 20);

add_action('init', function () {
    if (!did_action('acf/init')) {
        mzf_maybe_migrate_mailchimp_to_crm();
    }
}, 25);

if (!function_exists('mzf_normalize_crm_platform')) {
    function mzf_normalize_crm_platform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        $platform = str_replace(['_', ' '], '-', $platform);
        $platform = preg_replace('/-+/', '-', $platform);

        if (in_array($platform, ['constant-contact', 'constantcontact'], true)) {
            return 'constant-contact';
        }
        if ($platform === 'mailchimp') {
            return 'mailchimp';
        }
        if ($platform === 'zeffy') {
            return 'zeffy';
        }
        return '';
    }
}

if (!function_exists('mzf_crm_platform')) {
    function mzf_crm_platform(): string
    {
        $crm = mzf_get_crm_group();
        $platform = (string) ($crm['platform'] ?? '');
        return mzf_normalize_crm_platform($platform);
    }
}

if (!function_exists('mzf_crm_platform_label')) {
    function mzf_crm_platform_label(?string $platform = null): string
    {
        $slug = mzf_normalize_crm_platform((string) ($platform ?? mzf_crm_platform()));
        if ($slug === 'constant-contact') {
            return 'Constant Contact';
        }
        if ($slug === 'mailchimp') {
            return 'Mailchimp';
        }
        if ($slug === 'zeffy') {
            return 'Zeffy';
        }
        return '';
    }
}

if (!function_exists('mzf_crm_group_aliases')) {
    function mzf_crm_group_aliases(string $platform): array
    {
        $slug = mzf_normalize_crm_platform($platform);
        if ($slug === '') {
            return [];
        }

        $aliases = [$slug];
        $underscore = str_replace('-', '_', $slug);
        if (!in_array($underscore, $aliases, true)) {
            $aliases[] = $underscore;
        }

        return $aliases;
    }
}

if (!function_exists('mzf_crm_group_sub_value')) {
    function mzf_crm_group_sub_value(array $crm, string $platform, string $field): string
    {
        foreach (mzf_crm_group_aliases($platform) as $group_key) {
            $group = $crm[$group_key] ?? null;
            if (!is_array($group)) {
                continue;
            }

            $value = trim((string) ($group[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('mzf_crm_api_key')) {
    function mzf_crm_api_key(?string $platform = null): string
    {
        $slug = mzf_normalize_crm_platform((string) ($platform ?? mzf_crm_platform()));
        if ($slug === '') {
            return '';
        }
        $crm = mzf_get_crm_group();
        $nested = mzf_crm_group_sub_value($crm, $slug, 'api');
        if ($nested !== '') {
            return $nested;
        }

        return trim((string) ($crm['api_' . $slug] ?? ''));
    }
}

if (!function_exists('mzf_crm_list_id')) {
    function mzf_crm_list_id(?string $platform = null): string
    {
        $slug = mzf_normalize_crm_platform((string) ($platform ?? mzf_crm_platform()));
        if ($slug === '') {
            return '';
        }

        $crm = mzf_get_crm_group();
        $nested = mzf_crm_group_sub_value($crm, $slug, 'list');
        if ($nested !== '') {
            return $nested;
        }

        return trim((string) ($crm['list_' . $slug] ?? ''));
    }
}

if (!function_exists('mzf_crm_list_url')) {
    function mzf_crm_list_url(?string $platform = null): string
    {
        $slug = mzf_normalize_crm_platform((string) ($platform ?? mzf_crm_platform()));
        if ($slug === '') {
            return '';
        }

        $crm = mzf_get_crm_group();
        $url = trim((string) ($crm['list_' . $slug] ?? ''));
        if ($url === '') {
            foreach (mzf_crm_group_aliases($slug) as $group_key) {
                $group = $crm[$group_key] ?? null;
                if (!is_array($group)) {
                    continue;
                }

                $candidate = trim((string) ($group['url'] ?? ''));
                if ($candidate !== '') {
                    $url = $candidate;
                    break;
                }
            }
        }

        if ($url !== '') {
            if (!preg_match('#^https?://#i', $url)) {
                return '';
            }
            return (string) esc_url_raw($url);
        }

        $list_id = mzf_crm_list_id($slug);
        if ($list_id === '') {
            return '';
        }

        if ($slug === 'mailchimp') {
            return 'https://admin.mailchimp.com/lists/members/?id=' . rawurlencode($list_id);
        }
        if ($slug === 'constant-contact') {
            return 'https://app.constantcontact.com/pages/contacts/ui/lists?list=' . rawurlencode($list_id);
        }

        return '';
    }
}

if (!function_exists('mzf_crm_dashboard_url')) {
    function mzf_crm_dashboard_url(?string $platform = null): string
    {
        $slug = mzf_normalize_crm_platform((string) ($platform ?? mzf_crm_platform()));
        if ($slug === 'constant-contact') {
            return 'https://app.constantcontact.com/pages/contacts/ui/contacts';
        }
        if ($slug === 'mailchimp') {
            return 'https://admin.mailchimp.com/audience/contacts/';
        }
        if ($slug === 'zeffy') {
            return 'https://www.zeffy.com/login';
        }
        return '';
    }
}

if (!function_exists('mzf_crm_click_url')) {
    function mzf_crm_click_url(?string $platform = null): string
    {
        $slug = mzf_normalize_crm_platform((string) ($platform ?? mzf_crm_platform()));
        if ($slug === '') {
            return '';
        }

        $list_url = mzf_crm_list_url($slug);
        if ($list_url !== '') {
            return $list_url;
        }

        return mzf_crm_dashboard_url($slug);
    }
}

if (!function_exists('mzf_default_fields')) {
    function mzf_default_fields(): array
    {
        return [
            'FirstName',
            'LastName',
            'PreferredName',
            'Pronouns',
            'Email',
            'Phone',
            'WorkPhone',
            'Work Phone',
            'work_phone',
            'phone_number',
            'PhoneNumber',
            'WorkPhoneNumber',
            'workPhone',
            'ContactFirstName',
            'ContactLastName',
            'ContactPhone',
            'ContactEmail',
            'Website',
            'Honeypot',
            'Company',
            'OtherInterest',
            'Interests',
            'DateNeeded',
            'Date',
            'RentalDate',
            'Deadline',
            'Duration',
            'RentalDuration',
            'Days',
            'AvailabilityMode',
            'GeneralAvailability',
            'GeneralWeekdayAvailability',
            'GeneralWeekendAvailability',
            'MondaysAvailability',
            'TuesdaysAvailability',
            'WednesdaysAvailability',
            'ThursdaysAvailability',
            'FridaysAvailability',
            'SaturdaysAvailability',
            'SundaysAvailability',
            'ItemType',
            'Service',
            'SignType',
            'ApparelType',
            'PrintType',
            'PrintColor',
            'WrapType',
            'ItemName',
            'Products',
            'ProductName',
            'Dimensions',
            'PrintSize',
            'Size',
            'Quantity',
            'PrintCount',
            'PrintQuantity',
            'Count',
            'Amount',
            'ProductId',
            'VariationId',
            'Roadcase',
            'Explosive',
            'Weight',
            'LocationDisplay',
            'Location',
            'Place',
            'Latitude',
            'Longitude',
            'ReceivingOption',
            'ReceivingAddress',
            'ReceivingPlaceID',
            'ReceivingLatitude',
            'ReceivingLongitude',
            'ReceivingAddressStreet',
            'ReceivingAddressStreet2',
            'ReceivingAddressCity',
            'ReceivingAddressState',
            'ReceivingAddressPostal',
            'ReceivingAddressZip',
            'ReceivingAddressCountry',
            'ReceivingAddressDisplay',
            'ReceivingContact',
            'Store',
            'FleetSize',
            'VehicleYear',
            'VehicleMake',
            'VehicleModel',
            'WallSurface',
            'InstalledGraphics',
            'Vocals',
            'Father',
            'Age',
            'County',
            'Zip',
            'ZipCode',
            'Zip code',
            'zip_code',
            'zipcode',
            'Zipcode',
            'zipCode',
            'PostalCode',
            'Postal Code',
            'postal_code',
            'postal',
            'CondomCount',
            'MondayTimes',
            'TuesdayTimes',
            'WednesdayTimes',
            'ThursdayTimes',
            'FridayTimes',
            'SaturdayTimes',
            'SundayTimes',
            'FilesLink',
            'Comments',
            'Consent',
            'Training',
            'Conduct',
            'Confidentiality',
            'Applicant',
            'NewsletterSignup',
            'PageId',
            'FormSlug',
        ];
    }
}

if (!function_exists('mzf_fields')) {
    function mzf_fields(): array
    {
        return array_values((array) apply_filters('mzf_fields', mzf_default_fields()));
    }
}

if (!function_exists('mzf_core_fields')) {
    function mzf_core_fields(): array
    {
        $core = ['FirstName', 'LastName', 'Email', 'Phone', 'Company', 'PageId', 'FormSlug', 'Comments'];
        return array_values((array) apply_filters('mzf_core_fields', $core));
    }
}

if (!function_exists('mzf_is_strict_mode')) {
    function mzf_is_strict_mode(): bool
    {
        $strict = defined('MZF_STRICT_MODE')
            ? (bool) MZF_STRICT_MODE
            : (bool) mzf_get('strict_mode', false);
        return (bool) apply_filters('mzf_strict_mode', $strict);
    }
}

if (!function_exists('mzf_reject_unknown_fields')) {
    function mzf_reject_unknown_fields(): bool
    {
        $reject = (bool) mzf_get('reject_unknown_fields', true);
        return (bool) apply_filters('mzf_reject_unknown_fields', $reject);
    }
}

if (!function_exists('mzf_debug_allowed')) {
    function mzf_debug_allowed(array $src = []): bool
    {
        // Debug is controlled only via wp-config constants.
        $enabled = defined('MZF_DEBUG_ENABLED') ? (bool) MZF_DEBUG_ENABLED : false;
        $enabled = (bool) apply_filters('mzf_debug_enabled', $enabled, $src);

        if (is_user_logged_in() && current_user_can('manage_options') && $enabled) {
            return true;
        }

        if (!$enabled) {
            return false;
        }

        $configured_key = defined('MZF_DEBUG_KEY') ? (string) MZF_DEBUG_KEY : '';
        $configured_key = trim($configured_key);
        $provided_key = isset($src['debug_key']) ? trim((string) $src['debug_key']) : '';

        if ($configured_key !== '' && $provided_key !== '' && hash_equals($configured_key, $provided_key)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('mzf_debug_session_enabled')) {
    function mzf_debug_session_enabled(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        $uid = get_current_user_id();
        if ($uid <= 0) {
            return false;
        }
        return get_user_meta($uid, 'mzf_debug_session', true) === '1';
    }
}

if (!function_exists('mzf_marketing_dry_run_enabled')) {
    function mzf_marketing_dry_run_enabled(string $provider = ''): bool
    {
        $env = function_exists('wp_get_environment_type')
            ? strtolower((string) wp_get_environment_type())
            : 'production';

        $default_enabled = in_array($env, ['local', 'development', 'staging'], true);

        if (defined('MZF_MARKETING_DRY_RUN')) {
            $raw = MZF_MARKETING_DRY_RUN;
            if (is_bool($raw)) {
                $enabled = $raw;
            } else {
                $raw_s = strtolower(trim((string) $raw));
                $enabled = in_array($raw_s, ['1', 'true', 'yes', 'on', 'y'], true);
            }
        } else {
            $enabled = $default_enabled;
        }

        return (bool) apply_filters('mzf_marketing_dry_run_enabled', $enabled, $provider, $env);
    }
}

if (!function_exists('mzf_profile')) {
    function mzf_profile(): string
    {
        $profile = defined('MZF_PROFILE')
            ? (string) MZF_PROFILE
            : (string) mzf_get('profile', 'auto');
        $profile = strtolower(trim((string) apply_filters('mzf_profile', $profile)));
        if (in_array($profile, ['ds', 'fh'], true)) {
            return $profile;
        }

        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $host = strtolower($host);
        if (strpos($host, 'fahass') !== false) {
            return 'fh';
        }
        return 'ds';
    }
}

if (!function_exists('mzf_get_form_slug')) {
    function mzf_get_form_slug(array $data = []): string
    {
        $src = !empty($data) ? $data : ($_POST ?? []);
        $slug = isset($src['FormSlug']) ? sanitize_key((string) $src['FormSlug']) : '';
        return (string) apply_filters('mzf_form_slug', $slug, $src);
    }
}

if (!function_exists('mzf_slug_in')) {
    function mzf_slug_in(string $slug, array $allowed): bool
    {
        if ($slug === '' || empty($allowed)) {
            return false;
        }
        $allowed = array_values(array_filter(array_map(static fn($v) => sanitize_key((string) $v), $allowed)));
        return in_array($slug, $allowed, true);
    }
}

if (!function_exists('mzf_default_form_slug_map')) {
    function mzf_default_form_slug_map(): array
    {
        // Legacy alias map kept only for migration messaging/rejection.
        return [
            'rent-form'              => 'rent',
            'buy-form'               => 'buy',
            'consulting-form'        => 'consulting',
            'auditions-rehearsals'   => 'audition',
            'ccfbg-audition'         => 'audition',
            'ccfbg-contact'          => 'contact',
            'fahass-contact'         => 'contact',
            'fahass-volunteer'       => 'volunteer',
            'fahass-condoms'         => 'condoms',
            'fahass-medical'         => 'medical',
            'afl-contact'            => 'contact',
            'afl-volunteer'          => 'volunteer',
            'afl-sponsor'            => 'sponsor',
            'afl-program'            => 'program-application',
            'afl-board'              => 'board-member',
            'afl-staff'              => 'staff-member',
            'afl-coach'              => 'coach-mentor',
            'printing'               => 'quote',
            'quote-request'          => 'quote',
        ];
    }
}

if (!function_exists('mzf_normalize_form_slug')) {
    function mzf_normalize_form_slug(string $slug): string
    {
        return sanitize_key($slug);
    }
}

if (!function_exists('mzf_legacy_slug_target')) {
    function mzf_legacy_slug_target(string $slug): string
    {
        $slug = sanitize_key($slug);
        if ($slug === '') {
            return '';
        }
        $map = (array) apply_filters('mzf_form_slug_map', mzf_default_form_slug_map());
        $map = array_change_key_case(array_map(static fn($v) => sanitize_key((string) $v), $map), CASE_LOWER);
        $target = $map[strtolower($slug)] ?? '';
        if ($target === '' || $target === $slug) {
            return '';
        }
        return $target;
    }
}

if (!function_exists('mzf_to_list')) {
    function mzf_to_list($value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $v) {
                $s = trim((string) $v);
                if ($s !== '') $out[] = $s;
            }
            return array_values($out);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        if (strpos($value, ',') !== false) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), static fn($v) => $v !== ''));
        }
        return [$value];
    }
}

if (!function_exists('mzf_default_interest_reason_map')) {
    function mzf_default_interest_reason_map(): array
    {
        return [
            'volunteer'         => 'volunteer',
            'sponsor'           => 'sponsor',
            'partner'           => 'partner',
            'events'            => 'events',
            'event'             => 'events',
            'board member'      => 'board-member',
            'staff member'      => 'staff-member',
            'coach/mentor'      => 'coach-mentor',
            'coach mentor'      => 'coach-mentor',
            'medical'           => 'medical',
            'hiv testing'       => 'medical',
            'appointments'      => 'medical',
            'free condoms'      => 'condoms',
            'condoms'           => 'condoms',
            'apply'             => 'program-application',
            'program application' => 'program-application',
            'contact'           => 'contact',
            'quote'             => 'quote',
        ];
    }
}

if (!function_exists('mzf_normalize_terms')) {
    function mzf_normalize_terms($value): array
    {
        $items = mzf_to_list($value);
        $map = (array) apply_filters('mzf_interest_reason_map', mzf_default_interest_reason_map());
        $norm = [];
        foreach ($items as $item) {
            $k = strtolower(trim((string) $item));
            $k = preg_replace('/\s+/', ' ', (string) $k);
            $canonical = $map[$k] ?? sanitize_key(str_replace('/', '-', $k));
            if ($canonical !== '') {
                $norm[] = $canonical;
            }
        }
        return array_values(array_unique(array_filter($norm)));
    }
}

if (!function_exists('mzf_default_subject_templates')) {
    function mzf_default_subject_templates(): array
    {
        return function_exists('mzf_registry_subject_templates')
            ? mzf_registry_subject_templates()
            : [];
    }
}

if (!function_exists('mzf_render_template')) {
    function mzf_render_template(string $template, array $tokens): string
    {
        if ($template === '') {
            return '';
        }
        $replacements = [];
        foreach ($tokens as $k => $v) {
            $replacements['{{' . $k . '}}'] = (string) $v;
            $replacements['[[' . $k . ']]'] = (string) $v;
        }
        return trim(strtr($template, $replacements));
    }
}

if (!function_exists('mzf_apply_subject_templates')) {
    function mzf_apply_subject_templates(string $default_subject, array $data = [], array $context = []): string
    {
        $slug = mzf_get_form_slug($data);
        $slug = mzf_normalize_form_slug($slug);
        if ($slug === '') {
            return $default_subject;
        }

        $templates = (array) apply_filters('mzf_subject_templates', mzf_default_subject_templates(), $data, $context);
        $template = isset($templates[$slug]) ? (string) $templates[$slug] : '';
        if ($template === '') {
            return $default_subject;
        }

        $first = trim((string) ($data['FirstName'] ?? ''));
        $last = trim((string) ($data['LastName'] ?? ''));
        $name = trim($first . ' ' . $last);
        $company = trim((string) ($data['Company'] ?? ''));
        $name_company = $name !== '' ? $name : 'A visitor';
        if ($company !== '') {
            $name_company .= ' at ' . $company;
        }

        $vocals_value = trim((string) ($data['Vocals'] ?? ''));
        $vocals_subject = $vocals_value !== ''
            ? (function_exists('mb_strtolower') ? mb_strtolower($vocals_value, 'UTF-8') : strtolower($vocals_value))
            : '';
        $vocals_or_vocalist = $vocals_subject;
        if ($vocals_or_vocalist === '' || $vocals_or_vocalist === 'flexible') {
            $vocals_or_vocalist = 'vocalist';
        }

        $state = trim((string) (
            $data['ReceivingAddressState']
            ?? $data['State']
            ?? $data['state']
            ?? ''
        ));
        if ($state === '') {
            $address_sources = [
                (string) ($data['LocationDisplay'] ?? ''),
                (string) ($data['ReceivingAddressDisplay'] ?? ''),
                (string) ($data['ReceivingAddress'] ?? ''),
                (string) ($data['Address'] ?? ''),
                (string) ($data['Location'] ?? ''),
            ];
            foreach ($address_sources as $address_source) {
                $address_source = trim($address_source);
                if ($address_source === '') {
                    continue;
                }
                if (preg_match('/,\s*([A-Z]{2})\s+\d{5}(?:-\d{4})?(?:,|$)/', $address_source, $state_match)) {
                    $state = (string) ($state_match[1] ?? '');
                    break;
                }
            }
        }
        if ($state !== '') {
            $state_map = [
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
                'MN' => 'Minnesota',
                'MS' => 'Mississippi',
                'MO' => 'Missouri',
                'MT' => 'Montana',
                'NE' => 'Nebraska',
                'NV' => 'Nevada',
                'NH' => 'New Hampshire',
                'NJ' => 'New Jersey',
                'NM' => 'New Mexico',
                'NY' => 'New York',
                'NC' => 'North Carolina',
                'ND' => 'North Dakota',
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming',
                'DC' => 'District of Columbia',
            ];
            $state_key = strtoupper($state);
            if (isset($state_map[$state_key])) {
                $state = $state_map[$state_key];
            }
        }

        $tokens = [
            'name'         => $name !== '' ? $name : 'A visitor',
            'company'      => $company,
            'name_company' => $name_company,
            'form_slug'    => $slug,
            'domain'       => (string) ($context['domain'] ?? ''),
            'site_domain'  => (string) ($context['domain'] ?? ''),
            'vocals'       => $vocals_subject,
            'vocals_or_vocalist' => $vocals_or_vocalist,
            'condom_count' => trim((string) ($data['CondomCount'] ?? '')),
            'state'        => $state,
            'state_clause' => (trim((string) ($data['LocationDisplay'] ?? '')) !== '' ? (' for ' . trim((string) ($data['LocationDisplay'] ?? ''))) : ''),
        ];
        $rendered = mzf_render_template($template, $tokens);
        if ($slug === 'condoms') {
            $rendered = preg_replace('/\(\)\s*/', '', (string) $rendered);
            $rendered = preg_replace('/\s+for\s*$/i', '', (string) $rendered);
        }
        return $rendered;
    }
}

if (!function_exists('mzf_default_required_rules')) {
    function mzf_default_required_rules(): array
    {
        return function_exists('mzf_registry_required_rules')
            ? mzf_registry_required_rules()
            : [];
    }
}

if (!function_exists('mzf_apply_required_rules')) {
    function mzf_apply_required_rules(array $required_fields, array $data = []): array
    {
        $slug = mzf_get_form_slug($data);
        $slug = mzf_normalize_form_slug($slug);
        if ($slug === '') {
            return $required_fields;
        }
        $rules = (array) apply_filters('mzf_required_rules', mzf_default_required_rules(), $data);
        $extra = isset($rules[$slug]) && is_array($rules[$slug]) ? $rules[$slug] : [];
        return array_values(array_unique(array_merge($required_fields, $extra)));
    }
}

if (!function_exists('mzf_infer_form_slug')) {
    function mzf_infer_form_slug(array $data = []): string
    {
        $slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        if (!empty($data['Vocals'])) {
            return 'audition';
        }
        if (!empty($data['CondomCount'])) {
            return 'condoms';
        }
        if (!empty($data['MondayTimes']) || !empty($data['TuesdayTimes']) || !empty($data['WednesdayTimes']) || !empty($data['ThursdayTimes']) || !empty($data['FridayTimes']) || !empty($data['SaturdayTimes']) || !empty($data['SundayTimes'])) {
            return 'volunteer';
        }
        if (!empty($data['Explosive']) || !empty($data['Weight'])) {
            return 'consulting';
        }
        if (!empty($data['ProductId']) || !empty($data['VariationId']) || !empty($data['Roadcase']) || !empty($data['ItemName']) || !empty($data['ItemType'])) {
            if (!empty($data['Duration'])) {
                return 'rent';
            }
            if (!empty($data['DateNeeded'])) {
                return 'buy';
            }
            return 'quote';
        }
        if (!empty($data['FleetSize']) || !empty($data['VehicleMake']) || !empty($data['VehicleModel']) || !empty($data['VehicleYear']) || !empty($data['WallSurface'])) {
            return 'quote';
        }

        $interests = isset($data['InterestsNorm']) && is_array($data['InterestsNorm']) ? $data['InterestsNorm'] : [];
        foreach ($interests as $term) {
            $term = sanitize_key((string) $term);
            if ($term !== '' && $term !== 'quote') {
                return $term;
            }
        }
        if (in_array('quote', $interests, true)) {
            return 'quote';
        }

        return '';
    }
}
