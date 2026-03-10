<?php

/**
 * Plugin Name: DS Settings
 * Description: Core site settings, defaults, and bootstrap configuration.
 * Author: Meza LLC
 * Author URI: https://meza.design
 * Version: 1.8.0
 */

/** ================================
 *  CONFIG
 *  ================================ */
const MEZA_ADMIN_EMAIL       = 'info@meza.design';
const MEZA_GMT_OFFSET        = -5;     // fixed UTC-5
const MEZA_TIMEZONE_STRING   = '';
const MEZA_RSS_USE_EXCERPT   = 1;

/** Decide whether search engines should be allowed to index the site for the current environment. */
function meza_target_blog_public_value(): int
{
    $env = defined('WP_ENV') ? strtolower(WP_ENV) : '';
    return ($env === 'production') ? 1 : 0;
}

const MEZA_THUMB_W    = 300;
const MEZA_THUMB_H    = 300;
const MEZA_THUMB_CROP = 0;
const MEZA_MED_W      = 600;
const MEZA_MED_H      = 600;
const MEZA_LG_W       = 1200;
const MEZA_LG_H       = 1200;

/** General defaults (leave blank to “freeze current live value”) */
const MEZA_SITE_TITLE      = '';
const MEZA_HOME_URL        = '';
const MEZA_SITE_URL        = '';
const MEZA_WP_LANG         = '';
const MEZA_DATE_FORMAT     = 'F j, Y';
const MEZA_TIME_FORMAT     = 'g:i a';
const MEZA_START_OF_WEEK   = 0;

/** Post via e-mail defaults */
const MEZA_MAILSERVER_URL          = '';
const MEZA_MAILSERVER_PORT         = 110;
const MEZA_MAILSERVER_LOGIN        = '';
const MEZA_MAILSERVER_PASS         = '';
const MEZA_DEFAULT_EMAIL_CATEGORY  = 0;

/** Additional defaults */
const MEZA_USERS_CAN_REGISTER  = 0;
const MEZA_DEFAULT_ROLE        = 'subscriber';
const MEZA_DEFAULT_POST_FORMAT = 'standard';
const MEZA_POSTS_PER_PAGE      = 10;
const MEZA_POSTS_PER_RSS       = 10;
const MEZA_UPLOADS_YM_FOLDERS  = 1;
const MEZA_PERMALINK_STRUCTURE = '/%postname%/';
const MEZA_ADMIN_ITEMS_PER_PAGE = 100;

/** Editor defaults */
const MEZA_DEFAULT_EDITOR      = 'classic';
const MEZA_ALLOW_EDITOR_SWITCH = 0;

/** Core pages */
const MEZA_FRONT_PAGE_TITLE = 'Homepage';
const MEZA_FRONT_PAGE_SLUG  = 'home';
const MEZA_POSTS_PAGE_TITLE = 'Posts Page';
const MEZA_POSTS_PAGE_SLUG  = 'blog';

const MEZA_PRIVACY_POLICY_TITLE = 'Privacy Policy';
const MEZA_PRIVACY_POLICY_SLUG  = 'privacy-policy';

const MEZA_COOKIE_POLICY_TITLE  = 'Cookie Policy';
const MEZA_COOKIE_POLICY_SLUG   = 'cookie-policy';

const MEZA_CONTACT_TITLE = 'Contact';
const MEZA_CONTACT_SLUG  = 'contact';
const MEZA_CONTACT_TPL   = 'page-form.php';

const MEZA_STYLE_GUIDE_TITLE = 'Style Guide';
const MEZA_STYLE_GUIDE_SLUG  = 'styles';
const MEZA_STYLE_GUIDE_TPL   = 'page-style.php';

/** Menu locations (theme should register these) */
const MEZA_MENU_LOCATIONS = [
    'Primary' => 'primary',
    'Legal'   => 'legal',
    'Social'  => 'social',
];

/** ================================
 *  HELPERS
 *  ================================ */

/** Force a WordPress option to always read as a specific value, no matter what is stored in the database. */
function meza_force_option_read($opt, $value)
{
    add_filter("pre_option_{$opt}", function () use ($value) {
        return $value;
    }, 9999);
}

/** Intercept writes to an option so this plugin can allow, reject, or normalize attempted changes. */
function meza_guard_option_write($opt, callable $allow_fn)
{
    add_filter("pre_update_option_{$opt}", function ($new, $old) use ($allow_fn) {
        $allowed = $allow_fn($new, $old);
        return $allowed === null ? $old : $allowed;
    }, 9999, 2);
}

/** Use the configured constant when present; otherwise fall back to a callback or literal default value. */
function meza_resolve_target($const, $fallback_cb)
{
    if ($const !== '' && $const !== null) return $const;
    return is_callable($fallback_cb) ? (string) $fallback_cb() : (string) $fallback_cb;
}

/** Check whether WordPress already has both a front page and posts page assigned. */
function meza_has_assigned_reading_pages(): bool
{
    return ((int)get_option('page_on_front', 0) > 0 && (int)get_option('page_for_posts', 0) > 0);
}

/** Convert common "truthy" config values like 1/true/yes/on into a real boolean. */
function meza_truthy($v): bool
{
    if (is_bool($v)) return $v;
    if (is_numeric($v)) return ((int)$v) !== 0;
    $s = strtolower(trim((string)$v));
    return in_array($s, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Should we (re)run destructive/authoritative tasks?
 * - Constant MZ_FORCE_RERUN (preferred), or
 * - Option 'mz_force_rerun' (bool-ish)
 */
function meza_should_rerun(): bool
{
    if (defined('MZ_FORCE_RERUN')) return meza_truthy(MZ_FORCE_RERUN);
    return meza_truthy(get_option('mz_force_rerun', 0));
}

/** Assign a page template and refresh post caches even if the template file is being deployed later. */
function meza_assign_template(int $page_id, string $template_file): bool
{
    if ($page_id <= 0 || $template_file === '') return false;

    // Check child & parent theme for the file
    $child = trailingslashit(get_stylesheet_directory()) . ltrim($template_file, '/');
    $parent = trailingslashit(get_template_directory()) . ltrim($template_file, '/');

    $exists = file_exists($child) || file_exists($parent);
    // Still set the meta even if file isn't found yet (e.g., about to deploy it)
    update_post_meta($page_id, '_wp_page_template', $template_file);
    wp_update_post(['ID' => $page_id, 'page_template' => $template_file]);
    clean_post_cache($page_id);

    return $exists;
}

/**
 * Ensure a page exists at $slug:
 * - Creates it when missing
 * - Leaves existing pages untouched
 * - Applies the template only on newly created pages
 * Returns the page ID (0 on failure).
 */
function meza_ensure_page_state(string $title, string $slug, string $template_file = ''): int
{
    $page = get_page_by_path($slug);
    $created = false;

    if (!$page || $page->post_type !== 'page') {
        $id = wp_insert_post([
            'post_type'   => 'page',
            'post_title'  => $title,
            'post_name'   => sanitize_title($slug),
            'post_status' => 'publish',
        ]);
        if (is_wp_error($id)) return 0;
        $page = get_post($id);
        $created = true;
    }

    $id = (int)$page->ID;

    if ($created && $template_file) {
        meza_assign_template($id, $template_file);
    }

    return $id;
}

/** Build the encoded payload ACF expects for storing a license key against this site's URL. */
function meza_acf_encode_license(string $key): string
{
    $payload = ['key' => trim($key), 'url' => home_url()];
    return base64_encode(maybe_serialize($payload));
}

/** Activate meza-theme theme when rerun flag is true */
add_action('admin_init', function () {
    if (!meza_should_rerun()) return;

    require_once ABSPATH . 'wp-admin/includes/theme.php';

    $target  = 'meza-theme';
    $current = wp_get_theme();

    // Only switch if different and the target exists
    $candidate = wp_get_theme($target);
    if ($current->stylesheet !== $target && $candidate && $candidate->exists()) {
        switch_theme($target);
    }
}, 1);

/**
 * Return a sortable "year index" for a Twenty Twenty* theme slug.
 * twentytwenty => 20, twentytwentyone => 21, ... twentytwentyfive => 25
 */
function meza_twenty_score(string $slug): int
{
    if (strpos($slug, 'twentytwenty') !== 0) return -1;

    // Exact "twentytwenty"
    if ($slug === 'twentytwenty') return 20;

    // Map word suffix to number
    $map = [
        'one' => 1,
        'two' => 2,
        'three' => 3,
        'four' => 4,
        'five' => 5,
        'six' => 6,
        'seven' => 7,
        'eight' => 8,
        'nine' => 9
    ];

    // strip "twentytwenty" prefix and normalize
    $suffix = strtolower(substr($slug, strlen('twentytwenty')));
    // e.g. "one", "two", "three", "four", "five"
    return 20 + ($map[$suffix] ?? 0);
}

/** Find the newest installed Twenty Twenty* theme so cleanup can leave one fallback theme in place. */
function meza_latest_twenty_slug(): string
{
    $latest_slug = '';
    $latest_score = -1;
    foreach (wp_get_themes() as $slug => $theme_obj) {
        $score = meza_twenty_score($slug);
        if ($score > $latest_score) {
            $latest_score = $score;
            $latest_slug = $slug;
        }
    }
    return $latest_slug;
}

/** ================================
 *  OPTION FORCING (reads + writes)
 *  ================================ */
add_action('muplugins_loaded', function () {
    $target_blogname = meza_resolve_target(MEZA_SITE_TITLE, function () {
        return get_option('blogname');
    });
    $target_home     = meza_resolve_target(MEZA_HOME_URL, function () {
        return get_option('home');
    });
    $target_siteurl  = meza_resolve_target(MEZA_SITE_URL, function () {
        return get_option('siteurl');
    });
    $target_lang     = meza_resolve_target(MEZA_WP_LANG, function () {
        return get_option('WPLANG');
    });

    meza_force_option_read('admin_email',     MEZA_ADMIN_EMAIL);
    meza_force_option_read('blogname',        $target_blogname);
    meza_force_option_read('home',            $target_home);
    meza_force_option_read('siteurl',         $target_siteurl);
    meza_force_option_read('WPLANG',          $target_lang);
    meza_force_option_read('date_format',     MEZA_DATE_FORMAT);
    meza_force_option_read('time_format',     MEZA_TIME_FORMAT);
    meza_force_option_read('start_of_week',   MEZA_START_OF_WEEK);
    meza_force_option_read('users_can_register', MEZA_USERS_CAN_REGISTER);
    meza_force_option_read('default_role',    MEZA_DEFAULT_ROLE);
    meza_force_option_read('default_post_format', MEZA_DEFAULT_POST_FORMAT);

    if (class_exists('Classic_Editor') || defined('CLASSIC_EDITOR_VERSION')) {
        $replace  = (MEZA_DEFAULT_EDITOR === 'classic') ? 'classic' : 'block';
        $allow    = MEZA_ALLOW_EDITOR_SWITCH ? 'allow' : 'disallow';
        meza_force_option_read('classic-editor-replace',      $replace);
        meza_force_option_read('classic-editor-allow-users',  $allow);
        meza_guard_option_write('classic-editor-replace', function ($n, $o) use ($replace) {
            return $n === $replace ? $replace : null;
        });
        meza_guard_option_write('classic-editor-allow-users', function ($n, $o) use ($allow) {
            return $n === $allow ? $allow : null;
        });
    }

    meza_force_option_read('rss_use_excerpt', MEZA_RSS_USE_EXCERPT);
    meza_force_option_read('blog_public',     meza_target_blog_public_value());
    meza_force_option_read('posts_per_page',  MEZA_POSTS_PER_PAGE);
    meza_force_option_read('posts_per_rss',   MEZA_POSTS_PER_RSS);
    if (meza_has_assigned_reading_pages()) {
        meza_force_option_read('show_on_front', 'page');
    }

    meza_force_option_read('thumbnail_size_w', MEZA_THUMB_W);
    meza_force_option_read('thumbnail_size_h', MEZA_THUMB_H);
    meza_force_option_read('thumbnail_crop',   MEZA_THUMB_CROP);
    meza_force_option_read('medium_size_w',    MEZA_MED_W);
    meza_force_option_read('medium_size_h',    MEZA_MED_H);
    meza_force_option_read('large_size_w',     MEZA_LG_W);
    meza_force_option_read('large_size_h',     MEZA_LG_H);
    meza_force_option_read('uploads_use_yearmonth_folders', MEZA_UPLOADS_YM_FOLDERS);

    meza_force_option_read('permalink_structure', MEZA_PERMALINK_STRUCTURE);

    meza_force_option_read('mailserver_url',         MEZA_MAILSERVER_URL);
    meza_force_option_read('mailserver_port',        MEZA_MAILSERVER_PORT);
    meza_force_option_read('mailserver_login',       MEZA_MAILSERVER_LOGIN);
    meza_force_option_read('mailserver_pass',        MEZA_MAILSERVER_PASS);
    meza_force_option_read('default_email_category', MEZA_DEFAULT_EMAIL_CATEGORY);

    foreach (
        [
            'admin_email'        => MEZA_ADMIN_EMAIL,
            'blogname'           => $target_blogname,
            'home'               => $target_home,
            'siteurl'            => $target_siteurl,
            'WPLANG'             => $target_lang,
            'date_format'        => MEZA_DATE_FORMAT,
            'time_format'        => MEZA_TIME_FORMAT,
            'start_of_week'      => MEZA_START_OF_WEEK,
            'users_can_register' => MEZA_USERS_CAN_REGISTER,
            'default_role'       => MEZA_DEFAULT_ROLE,
            'default_post_format' => MEZA_DEFAULT_POST_FORMAT,
            'rss_use_excerpt'    => MEZA_RSS_USE_EXCERPT,
            'blog_public'        => meza_target_blog_public_value(),
            'posts_per_page'     => MEZA_POSTS_PER_PAGE,
            'posts_per_rss'      => MEZA_POSTS_PER_RSS,
            'thumbnail_size_w'   => MEZA_THUMB_W,
            'thumbnail_size_h'   => MEZA_THUMB_H,
            'thumbnail_crop'     => MEZA_THUMB_CROP,
            'medium_size_w'      => MEZA_MED_W,
            'medium_size_h'      => MEZA_MED_H,
            'large_size_w'       => MEZA_LG_W,
            'large_size_h'       => MEZA_LG_H,
            'uploads_use_yearmonth_folders' => MEZA_UPLOADS_YM_FOLDERS,
            'permalink_structure' => MEZA_PERMALINK_STRUCTURE,
            'mailserver_url'     => MEZA_MAILSERVER_URL,
            'mailserver_port'    => MEZA_MAILSERVER_PORT,
            'mailserver_login'   => MEZA_MAILSERVER_LOGIN,
            'mailserver_pass'    => MEZA_MAILSERVER_PASS,
            'default_email_category' => MEZA_DEFAULT_EMAIL_CATEGORY,
        ] as $opt => $target
    ) {
        meza_guard_option_write($opt, function ($n, $o) use ($target) {
            return ((string) $n == (string) $target) ? $target : null;
        });
    }

    if (meza_has_assigned_reading_pages()) {
        meza_guard_option_write('show_on_front', function ($n, $o) {
            return ((string) $n === 'page') ? 'page' : null;
        });
    }

    meza_guard_option_write('admin_email', function ($n, $o) {
        return (strcasecmp((string) $n, (string) MEZA_ADMIN_EMAIL) === 0) ? $n : null;
    });
}, 0);

/** =========================================
 *  ENFORCE PAGES + READING SETTINGS once, unless rerun is explicitly enabled
 *  ========================================= */
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;

    $has_run = (int)get_option('meza_pages_initialized', 0) === 1;
    if ($has_run && !meza_should_rerun()) return;

    $current_front_page = (int)get_option('page_on_front', 0);
    $current_posts_page = (int)get_option('page_for_posts', 0);
    $can_seed_pages = ($current_front_page <= 0 && $current_posts_page <= 0);

    if (!$can_seed_pages) {
        update_option('meza_pages_initialized', 1);
        return;
    }

    // Home & Posts pages
    $home_id  = meza_ensure_page_state(MEZA_FRONT_PAGE_TITLE,  MEZA_FRONT_PAGE_SLUG);
    $posts_id = meza_ensure_page_state(MEZA_POSTS_PAGE_TITLE,  MEZA_POSTS_PAGE_SLUG);

    if ($home_id && $posts_id && $home_id !== $posts_id) {
        update_option('show_on_front', 'page');
        update_option('page_on_front',  (int)$home_id);
        update_option('page_for_posts', (int)$posts_id);

        meza_force_option_read('page_on_front',  (int)$home_id);
        meza_force_option_read('page_for_posts', (int)$posts_id);
        meza_guard_option_write('page_on_front', function ($n, $o) use ($home_id) {
            return ((int) $n === (int) $home_id) ? $home_id : null;
        });
        meza_guard_option_write('page_for_posts', function ($n, $o) use ($posts_id) {
            return ((int) $n === (int) $posts_id) ? $posts_id : null;
        });
    }

    // Privacy Policy
    $privacy_id = meza_ensure_page_state(MEZA_PRIVACY_POLICY_TITLE, MEZA_PRIVACY_POLICY_SLUG);
    if ($privacy_id) {
        if (function_exists('set_privacy_policy_page')) set_privacy_policy_page($privacy_id);
        else update_option('wp_page_for_privacy_policy', $privacy_id);
    }

    // Cookie Policy
    meza_ensure_page_state(MEZA_COOKIE_POLICY_TITLE, MEZA_COOKIE_POLICY_SLUG);

    // Style Guide + template
    meza_ensure_page_state(MEZA_STYLE_GUIDE_TITLE, MEZA_STYLE_GUIDE_SLUG, MEZA_STYLE_GUIDE_TPL);

    // Contact + template
    meza_ensure_page_state(MEZA_CONTACT_TITLE, MEZA_CONTACT_SLUG, MEZA_CONTACT_TPL);

    update_option('meza_pages_initialized', 1);
}, 9);

/** =========================================
 *  SEED OTHER OPTIONS (idempotent)
 *  ========================================= */
add_action('admin_init', function () {
    $target_blogname = meza_resolve_target(MEZA_SITE_TITLE, function () {
        return get_option('blogname');
    });
    $target_home     = meza_resolve_target(MEZA_HOME_URL, function () {
        return get_option('home');
    });
    $target_siteurl  = meza_resolve_target(MEZA_SITE_URL, function () {
        return get_option('siteurl');
    });
    $target_lang     = meza_resolve_target(MEZA_WP_LANG, function () {
        return get_option('WPLANG');
    });

    update_option('blogname',           $target_blogname);
    update_option('home',               $target_home);
    update_option('siteurl',            $target_siteurl);
    update_option('WPLANG',             $target_lang);
    update_option('date_format',        MEZA_DATE_FORMAT);
    update_option('time_format',        MEZA_TIME_FORMAT);
    update_option('start_of_week',      MEZA_START_OF_WEEK);
    update_option('users_can_register', MEZA_USERS_CAN_REGISTER);
    update_option('default_role',       MEZA_DEFAULT_ROLE);
    update_option('default_post_format', MEZA_DEFAULT_POST_FORMAT);
    update_option('mailserver_url',         MEZA_MAILSERVER_URL);
    update_option('mailserver_port',        MEZA_MAILSERVER_PORT);
    update_option('mailserver_login',       MEZA_MAILSERVER_LOGIN);
    update_option('mailserver_pass',        MEZA_MAILSERVER_PASS);
    update_option('default_email_category', MEZA_DEFAULT_EMAIL_CATEGORY);
    update_option('rss_use_excerpt', MEZA_RSS_USE_EXCERPT);
    update_option('blog_public',     meza_target_blog_public_value());
    update_option('posts_per_page',  MEZA_POSTS_PER_PAGE);
    update_option('posts_per_rss',   MEZA_POSTS_PER_RSS);
    if (meza_has_assigned_reading_pages()) {
        update_option('show_on_front', 'page');
    }
    update_option('timezone_string', MEZA_TIMEZONE_STRING);
    update_option('gmt_offset',      MEZA_GMT_OFFSET);
    update_option('thumbnail_size_w', MEZA_THUMB_W);
    update_option('thumbnail_size_h', MEZA_THUMB_H);
    update_option('thumbnail_crop',   MEZA_THUMB_CROP);
    update_option('medium_size_w',    MEZA_MED_W);
    update_option('medium_size_h',    MEZA_MED_H);
    update_option('large_size_w',     MEZA_LG_W);
    update_option('large_size_h',     MEZA_LG_H);
    update_option('uploads_use_yearmonth_folders', MEZA_UPLOADS_YM_FOLDERS);

    $current_structure = get_option('permalink_structure');
    if ($current_structure !== MEZA_PERMALINK_STRUCTURE) {
        update_option('permalink_structure', MEZA_PERMALINK_STRUCTURE);
        set_transient('meza_flush_rewrite_needed', 1, 5 * MINUTE_IN_SECONDS);
    }
}, 10);

/** Flush rewrites once if we changed permalinks */
add_action('admin_init', function () {
    if (get_transient('meza_flush_rewrite_needed')) {
        delete_transient('meza_flush_rewrite_needed');
        if (function_exists('flush_rewrite_rules')) flush_rewrite_rules(false);
    }
}, 20);

/** ACF PRO license: set & activate if key is present and ACF is active. */
add_action('acf/init', function () {
    if (!defined('MZ_ACF_PRO_KEY') || !MZ_ACF_PRO_KEY) return;

    if (function_exists('acf_pro_update_license')) {
        @acf_pro_update_license(trim(MZ_ACF_PRO_KEY));
        return;
    }
    update_option('acf_pro_license', meza_acf_encode_license(MZ_ACF_PRO_KEY));
});
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (!defined('MZ_ACF_PRO_KEY') || !MZ_ACF_PRO_KEY) return;
    if (!class_exists('ACF') && !function_exists('acf')) return;

    if (function_exists('acf_pro_update_license')) {
        @acf_pro_update_license(trim(MZ_ACF_PRO_KEY));
    } else {
        update_option('acf_pro_license', meza_acf_encode_license(MZ_ACF_PRO_KEY));
    }
}, 12);

/** Admin email confirmation auto-trigger */
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;

    $current = (string)get_option('admin_email');
    $target  = (string)MEZA_ADMIN_EMAIL;

    if (strcasecmp($current, $target) === 0) {
        delete_option('meza_admin_email_sent_hash');
        return;
    }
    $pending = get_option('new_admin_email');
    if (is_array($pending) && !empty($pending['newemail']) && strcasecmp($pending['newemail'], $target) === 0) return;

    $already = get_option('meza_admin_email_sent_hash');
    if (!empty($already) && is_array($already) && strcasecmp($already['newemail'] ?? '', $target) === 0) return;

    if (!function_exists('wp_generate_password')) require_once ABSPATH . 'wp-includes/pluggable.php';
    $hash = wp_generate_password(20, false);
    $payload = ['hash' => $hash, 'newemail' => $target];

    update_option('new_admin_email', $payload);
    update_option('meza_admin_email_sent_hash', $payload);

    $confirm_url = add_query_arg('adminhash', rawurlencode($hash), admin_url('options.php'));
    $sitename = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $subject  = sprintf('[%s] Confirm your admin email change', $sitename);

    $message = implode("\n", [
        sprintf('A request was made to change the site admin email for "%s".', $sitename),
        '',
        'To confirm this change, click the link below:',
        $confirm_url,
        '',
        'If you did not request this change, you can ignore this email. The admin email will not be updated without confirmation.',
    ]);

    wp_mail($target, $subject, $message);
}, 5);

/** Create any missing core menus and assign them to registered theme locations when those slots are empty. */
function meza_ensure_menus_exist_and_assigned(): void
{
    $locations  = get_theme_mod('nav_menu_locations', []);
    $registered = get_registered_nav_menus();
    $changed    = false;

    foreach (MEZA_MENU_LOCATIONS as $menu_name => $loc) {
        if (!isset($registered[$loc])) continue;
        if ((int)($locations[$loc] ?? 0) > 0) continue;

        $existing = wp_get_nav_menu_object($menu_name);
        if ($existing && !is_wp_error($existing)) {
            $term_id = (int)$existing->term_id;
        } else {
            $new_id = wp_create_nav_menu($menu_name);
            if (is_wp_error($new_id)) continue;
            $term_id = (int)$new_id;
        }

        if ($term_id <= 0) continue;

        $locations[$loc] = $term_id;
        $changed = true;
    }

    if ($changed) set_theme_mod('nav_menu_locations', $locations);
}

/** Ensure menus exist + assigned once, unless rerun is explicitly enabled. */
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;

    $has_run = (int)get_option('meza_menus_initialized', 0) === 1;
    if ($has_run && !meza_should_rerun()) return;

    meza_ensure_menus_exist_and_assigned();
    update_option('meza_menus_initialized', 1);
}, 15);

/** Force Classic Editor */
add_action('muplugins_loaded', function () {
    if (MEZA_DEFAULT_EDITOR === 'classic') {
        add_filter('use_block_editor_for_post',      '__return_false', 100);
        add_filter('use_block_editor_for_post_type', '__return_false', 100);
        add_filter('gutenberg_use_widgets_block_editor', '__return_false', 100);
        add_filter('use_widgets_block_editor',           '__return_false', 100);
    }
}, 1);

/** Build the list of screen-option keys used by posts, taxonomies, media, users, and network tables. */
function meza_admin_per_page_option_keys(): array
{
    $options = [
        'upload_per_page',
        'users_per_page',
        'edit_comments_per_page',
        'sites_network_per_page',
        'users_network_per_page',
        'themes_network_per_page',
        'plugins_network_per_page',
        'site_themes_network_per_page',
    ];

    foreach (get_post_types(['show_ui' => true], 'names') as $post_type) {
        $options[] = 'edit_' . $post_type . '_per_page';
    }

    foreach (get_taxonomies(['show_ui' => true], 'names') as $taxonomy) {
        $options[] = 'edit_' . $taxonomy . '_per_page';
    }

    return array_values(array_unique($options));
}

/** Force admin "items per page" to one value across post types/taxonomies/list tables. */
add_action('admin_init', function () {
    foreach (meza_admin_per_page_option_keys() as $option_key) {
        add_filter("get_user_option_{$option_key}", function ($value, $option, $user) {
            return MEZA_ADMIN_ITEMS_PER_PAGE;
        }, 9999, 3);
    }
}, 1);

/** Keep saved screen-option values locked to MEZA_ADMIN_ITEMS_PER_PAGE. */
add_filter('set-screen-option', function ($status, $option, $value) {
    if (!in_array((string)$option, meza_admin_per_page_option_keys(), true)) return $status;
    return MEZA_ADMIN_ITEMS_PER_PAGE;
}, 9999, 3);

/**
 * Yoast SEO columns:
 * - Keep "SEO Title" + "Meta Desc." checked by default on post list screens.
 * - Force-hide Yoast internal links columns.
 */
add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    if (!is_array($hidden)) return $hidden;
    if (!($screen instanceof WP_Screen)) return $hidden;
    if ($screen->base !== 'edit') return $hidden;
    // Respect user Screen Options changes after first save/load.
    if (!$use_defaults) return $hidden;

    // Force-hide known Content Permissions column ids.
    foreach (['content_permissions', 'content-permissions'] as $column_id) {
        if (!in_array($column_id, $hidden, true)) $hidden[] = $column_id;
    }

    // Force-hide Yoast link-related columns.
    foreach (['wpseo-links', 'wpseo-linked'] as $column_id) {
        if (!in_array($column_id, $hidden, true)) $hidden[] = $column_id;
    }

    $post_type = (string) ($screen->post_type ?? '');
    $always_visible = ['wpseo-title', 'wpseo-metadesc', 'mz_page_link', 'categories', 'taxonomy-category'];

    if ($post_type === 'page') {
        // Keep page-focused columns checked by default on Pages.
        $always_visible[] = 'mz_page_headline';
        $always_visible[] = 'mz_page_cta';
    } else {
        // Keep these columns available in Screen Options, but unchecked by default on non-Pages.
        foreach (['mz_page_headline', 'mz_page_cta'] as $column_id) {
            if (!in_array($column_id, $hidden, true)) $hidden[] = $column_id;
        }
    }

    if (in_array($post_type, ['cta', 'review', 'reviews'], true) && !in_array('mz_thumbnail', $hidden, true)) {
        $hidden[] = 'mz_thumbnail';
    }

    $hidden = array_values(array_diff($hidden, $always_visible));
    return $hidden;
}, 20, 3);

/** Theme cleanup (only when mz_force_rerun = true); keep latest Twenty Twenty* */
add_action('admin_init', function () {
    if (!meza_should_rerun()) return;
    if (!current_user_can('delete_themes')) return;

    require_once ABSPATH . 'wp-admin/includes/theme.php';

    $active_stylesheet = get_option('stylesheet');
    $active_template   = get_option('template');
    $keep_twenty_slug  = meza_latest_twenty_slug(); // e.g. "twentytwentyfive" if installed

    foreach (wp_get_themes() as $slug => $theme_obj) {
        // Always keep the active theme and its parent
        if ($slug === $active_stylesheet || $slug === $active_template) continue;

        // Keep any Meza themes
        if (stripos($slug, 'meza') !== false) continue;

        // Keep the newest Twenty Twenty* for debugging
        if ($keep_twenty_slug && $slug === $keep_twenty_slug) continue;

        // Best-effort delete; ignore failures
        @delete_theme($slug);
    }
}, 40);

/** Keep menu assignments after theme switch only when rerun is explicitly enabled. */
add_action('after_switch_theme', function () {
    if (!meza_should_rerun()) return;
    meza_ensure_menus_exist_and_assigned();
});
