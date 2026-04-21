<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mzf_registry_field_labels')) {
    function mzf_registry_field_labels(): array
    {
        return [
            'FullName' => 'Name',
            'PreferredName' => 'Preferred Name',
            'Pronouns' => 'Pronouns',
            'Company' => 'Organization/Company',
            'Email' => 'Email',
            'Phone' => 'Phone',
            'Interests' => 'Interests',
            'AvailabilityMode' => 'Availability Type',
            'GeneralAvailability' => 'General Availability',
            'GeneralWeekdayAvailability' => 'Weekdays Availability',
            'GeneralWeekendAvailability' => 'Weekends Availability',
            'Days' => 'Weekly Schedule Days',
            'MondaysAvailability' => 'Mondays Availability',
            'TuesdaysAvailability' => 'Tuesdays Availability',
            'WednesdaysAvailability' => 'Wednesdays Availability',
            'ThursdaysAvailability' => 'Thursdays Availability',
            'FridaysAvailability' => 'Fridays Availability',
            'SaturdaysAvailability' => 'Saturdays Availability',
            'SundaysAvailability' => 'Sundays Availability',
            'Father' => 'Young Father',
            'Age' => 'Age 15-21',
            'County' => 'In County',
            'Zip' => 'ZIP Code',
            'Comments' => 'Comments',
            'Vocals' => 'Vocals',
            'ItemType' => 'Item Type',
            'ItemName' => 'Item',
            'DateNeeded' => 'Date Needed',
            'Duration' => 'Duration',
            'Quantity' => 'Quantity',
            'PrintColor' => 'Color',
            'PickupContact' => 'Pickup Contact',
            'Dimensions' => 'Dimensions',
            'FilesLink' => 'Files Link',
            'FleetSize' => 'Fleet Size',
            'Vehicle' => 'Vehicle',
            'WallSurface' => 'Wall Surface',
            'InstalledGraphics' => 'Previously Installed Graphics',
            'LocationDisplay' => 'Shipping Address',
            'Explosive' => 'Explosive Materials',
            'Weight' => 'Weight',
            'CondomCount' => 'Quantity',
            'Training' => 'Training Acknowledged',
            'Conduct' => 'Volunteer Conduct Acknowledged',
            'Confidentiality' => 'Confidentiality Acknowledged',
            'Applicant' => 'Applicant Statement Acknowledged',
            'NewsletterSignup' => 'Signed Up for Newsletter',
        ];
    }
}

if (!function_exists('mzf_slug_registry')) {
    function mzf_slug_registry(): array
    {
        $contact_layout = ['FullName', 'Company', 'Email', 'Phone', 'Interests', 'Comments', 'NewsletterSignup'];
        $contact_required = ['FirstName', 'LastName', 'Email', 'PageId'];

        $registry = [
            'contact' => [
                'subject' => 'New message from {{name_company}}',
                'required' => $contact_required,
                'layout' => $contact_layout,
            ],
            'lead-gen' => [
                'subject' => 'An {{lead_gen_role}} just viewed the {{page_title}} [{{site_domain}}]',
                'required' => ['FirstName', 'Email', 'PageId', 'FormSlug'],
                'layout' => ['FullName', 'Email'],
            ],
            'co-lender' => [
                'subject' => '{{co_lender_subject_prefix}}co-lender is interested in working with you [{{site_domain}}]',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug'],
                'layout' => ['FullName', 'Email', 'Phone', 'Experience', 'Comments'],
            ],
            'volunteer' => [
                'subject' => 'New volunteer interest from {{name_company}}',
                'required' => array_merge($contact_required, ['Date', 'Training', 'Conduct', 'Confidentiality', 'Applicant']),
                'layout' => [
                    'FullName',
                    'PreferredName',
                    'Pronouns',
                    'Date',
                    'Company',
                    'Email',
                    'Phone',
                    'Interests',
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
                    'Training',
                    'Conduct',
                    'Confidentiality',
                    'Applicant',
                    'Comments',
                ],
            ],
            'sponsor' => [
                'subject' => 'New sponsorship interest from {{name_company}}',
                'required' => $contact_required,
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'Interests', 'Comments'],
            ],
            'program-application' => [
                'subject' => 'New program application from {{name_company}}',
                'required' => $contact_required,
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'Father', 'Age', 'County', 'Zip', 'Interests', 'Comments'],
            ],
            'board-member' => [
                'subject' => 'New board member interest from {{name_company}}',
                'required' => $contact_required,
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'Interests', 'Comments'],
            ],
            'staff-member' => [
                'subject' => 'New staff member interest from {{name_company}}',
                'required' => $contact_required,
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'Interests', 'Comments'],
            ],
            'coach-mentor' => [
                'subject' => 'New coach/mentor interest from {{name_company}}',
                'required' => $contact_required,
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'Interests', 'Comments'],
            ],
            'medical' => [
                'subject' => 'New medical services request from {{name_company}}',
                'required' => ['FirstName', 'Email'],
                'layout' => ['FullName', 'Email', 'Phone', 'Company', 'LocationDisplay', 'Interests', 'Comments'],
            ],
            'condoms' => [
                'subject' => 'New condom order request ({{condom_count}}) for {{state}}',
                'required' => ['FirstName', 'Email'],
                'layout' => ['FullName', 'Email', 'Phone', 'Company', 'LocationDisplay', 'CondomCount', 'Interests', 'Comments'],
            ],
            'audition' => [
                'subject' => 'New {{vocals_or_vocalist}} requesting an audition',
                'required' => $contact_required,
                'layout' => ['FullName', 'Email', 'Phone', 'Vocals', 'Comments', 'NewsletterSignup'],
            ],
            'quote' => [
                'subject' => 'New quote request from {{name_company}}',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'ItemType', 'ItemName', 'DateNeeded', 'LocationDisplay', 'Quantity', 'Dimensions', 'FilesLink', 'Comments'],
            ],
            'upload-files' => [
                'subject' => 'New quote request from {{name_company}}',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'ItemType', 'ItemName', 'DateNeeded', 'LocationDisplay', 'Quantity', 'Dimensions', 'FilesLink', 'Comments'],
            ],
            'rent' => [
                'subject' => 'New rental request from {{name_company}}',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug', 'DateNeeded'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'ItemName', 'DateNeeded', 'Duration', 'Quantity', 'LocationDisplay', 'Comments'],
            ],
            'buy' => [
                'subject' => 'New purchase request from {{name_company}}',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug', 'DateNeeded'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'ItemName', 'DateNeeded', 'Quantity', 'LocationDisplay', 'Comments'],
            ],
            'consulting' => [
                'subject' => 'New consulting request from {{name_company}}',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug', 'Explosive', 'Weight'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'Explosive', 'Weight', 'LocationDisplay', 'Comments'],
            ],
            'sign-quote' => [
                'subject' => 'New sign quote request from {{name_company}}',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'DateNeeded', 'LocationDisplay', 'ItemType', 'Dimensions', 'FilesLink', 'Comments'],
            ],

            'vehicle-wraps' => [
                'subject' => '{{name_company}} is interested in vehicle wraps',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug', 'Company', 'FleetSize', 'VehicleYear', 'VehicleMake', 'VehicleModel'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'DateNeeded', 'FleetSize', 'Vehicle', 'ItemType', 'Dimensions', 'FilesLink', 'Comments'],
            ],
            'wall-graphics' => [
                'subject' => '{{name_company}} is interested in wall graphics',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'DateNeeded', 'Dimensions', 'WallSurface', 'InstalledGraphics', 'FilesLink', 'Comments'],
            ],
            
            'print-quote' => [
                'subject' => 'New print quote request from {{name_company}}',
                'required' => ['FirstName', 'LastName', 'Email', 'PageId', 'FormSlug'],
                'layout' => ['FullName', 'Company', 'Email', 'Phone', 'ItemType', 'DateNeeded', 'LocationDisplay', 'PickupContact', 'PrintColor', 'Quantity', 'Dimensions', 'FilesLink', 'Comments'],
            ],
        ];

        return (array) apply_filters('mzf_slug_registry', $registry);
    }
}

if (!function_exists('mzf_slug_profile')) {
    function mzf_slug_profile(string $slug): array
    {
        $slug = sanitize_key($slug);
        if ($slug === '') {
            return [];
        }
        $registry = mzf_slug_registry();
        if (!empty($registry[$slug]) && is_array($registry[$slug])) {
            return $registry[$slug];
        }

        if (preg_match('/^[a-z0-9]+-(.+)$/', $slug, $m)) {
            $base = sanitize_key((string) ($m[1] ?? ''));
            if ($base !== '' && !empty($registry[$base]) && is_array($registry[$base])) {
                return $registry[$base];
            }
        }

        foreach ($registry as $base_slug => $profile) {
            $base_slug = sanitize_key((string) $base_slug);
            if ($base_slug === '' || !is_array($profile)) {
                continue;
            }

            if (function_exists('mzf_slug_matches_family') && mzf_slug_matches_family($slug, $base_slug)) {
                return $profile;
            }
        }

        return [];
    }
}

if (!function_exists('mzf_slug_matches_family')) {
    function mzf_slug_matches_family(string $slug, string $family): bool
    {
        $slug = sanitize_key($slug);
        $family = sanitize_key($family);

        if ($slug === '' || $family === '') {
            return false;
        }

        return $slug === $family
            || str_starts_with($slug, $family . '-')
            || str_ends_with($slug, '-' . $family);
    }
}

if (!function_exists('mzf_is_registered_slug')) {
    function mzf_is_registered_slug(string $slug): bool
    {
        return !empty(mzf_slug_profile($slug));
    }
}

if (!function_exists('mzf_registry_subject_templates')) {
    function mzf_registry_subject_templates(): array
    {
        $templates = [];
        foreach (mzf_slug_registry() as $slug => $profile) {
            if (!empty($profile['subject'])) {
                $templates[sanitize_key((string) $slug)] = (string) $profile['subject'];
            }
        }
        return $templates;
    }
}

if (!function_exists('mzf_registry_required_rules')) {
    function mzf_registry_required_rules(): array
    {
        $rules = [];
        foreach (mzf_slug_registry() as $slug => $profile) {
            $req = isset($profile['required']) && is_array($profile['required']) ? $profile['required'] : [];
            if (!empty($req)) {
                $rules[sanitize_key((string) $slug)] = array_values($req);
            }
        }
        return $rules;
    }
}

if (!function_exists('mzf_registry_body_layouts')) {
    function mzf_registry_body_layouts(): array
    {
        $layouts = [];
        foreach (mzf_slug_registry() as $slug => $profile) {
            $layout = isset($profile['layout']) && is_array($profile['layout']) ? $profile['layout'] : [];
            if (!empty($layout)) {
                $layouts[sanitize_key((string) $slug)] = array_values($layout);
            }
        }
        return $layouts;
    }
}
