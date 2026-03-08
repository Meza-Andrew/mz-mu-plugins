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

if (!function_exists('mzf_default_fields')) {
    function mzf_default_fields(): array
    {
        return [
            'FirstName',
            'LastName',
            'Email',
            'Phone',
            'ContactFirstName',
            'ContactLastName',
            'ContactPhone',
            'Website',
            'Honeypot',
            'Company',
            'Organization',
            'Interest',
            'Interests',
            'Reason',
            'DateNeeded',
            'Date',
            'RentalDate',
            'Deadline',
            'Duration',
            'RentalDuration',
            'Days',
            'ItemType',
            'Service',
            'SignType',
            'ApparelType',
            'PrintType',
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
            'CondomCount',
            'SkillsExperience',
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
        $enabled = defined('MZF_DEBUG_ENABLED')
            ? (bool) MZF_DEBUG_ENABLED
            : (bool) mzf_get('debug_enabled', false);
        $enabled = (bool) apply_filters('mzf_debug_enabled', $enabled, $src);

        $session_enabled = function_exists('mzf_debug_session_enabled')
            ? mzf_debug_session_enabled()
            : false;

        if (is_user_logged_in() && current_user_can('manage_options') && ($enabled || $session_enabled)) {
            return true;
        }

        if (!$enabled) {
            return false;
        }

        $configured_key = defined('MZF_DEBUG_KEY')
            ? (string) MZF_DEBUG_KEY
            : (string) mzf_get('debug_key', '');
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

        $tokens = [
            'name'         => $name !== '' ? $name : 'A visitor',
            'company'      => $company,
            'name_company' => $name_company,
            'form_slug'    => $slug,
            'domain'       => (string) ($context['domain'] ?? ''),
            'vocals'       => trim((string) ($data['Vocals'] ?? '')),
            'vocals_or_vocalist' => (trim((string) ($data['Vocals'] ?? '')) !== '' ? trim((string) ($data['Vocals'] ?? '')) : 'vocalist'),
            'condom_count' => trim((string) ($data['CondomCount'] ?? '')),
            'state_clause' => (trim((string) ($data['LocationDisplay'] ?? '')) !== '' ? (' for ' . trim((string) ($data['LocationDisplay'] ?? ''))) : ''),
        ];
        $rendered = mzf_render_template($template, $tokens);
        if ($slug === 'condoms') {
            $rendered = preg_replace('/\(\)\s*/', '', (string) $rendered);
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
        if (!empty($data['SkillsExperience']) || !empty($data['MondayTimes']) || !empty($data['TuesdayTimes']) || !empty($data['WednesdayTimes']) || !empty($data['ThursdayTimes']) || !empty($data['FridayTimes']) || !empty($data['SaturdayTimes']) || !empty($data['SundayTimes'])) {
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

        $reasons = isset($data['ReasonNorm']) && is_array($data['ReasonNorm']) ? $data['ReasonNorm'] : [];
        $interests = isset($data['InterestNorm']) && is_array($data['InterestNorm']) ? $data['InterestNorm'] : [];
        foreach (array_merge($reasons, $interests) as $term) {
            $term = sanitize_key((string) $term);
            if ($term !== '' && $term !== 'quote') {
                return $term;
            }
        }
        if (in_array('quote', array_merge($reasons, $interests), true)) {
            return 'quote';
        }

        return '';
    }
}
