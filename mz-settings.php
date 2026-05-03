<?php

/**
 * Plugin Name: MZ Settings
 * Description: Core site settings, defaults, and bootstrap configuration.
 * Author: Meza LLC
 * Author URI: https://meza.design
 * Version: 1.8.37
 */

/** ================================
 *  CONFIG
 *  ================================ */
const MEZA_ADMIN_EMAIL       = 'info@meza.design';
const MEZA_GMT_OFFSET        = null;   // auto-resolve from timezone string
const MEZA_TIMEZONE_STRING   = 'America/New_York';
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
const MEZA_ADMIN_ITEMS_PER_PAGE = 20;

/** Editor defaults */
const MEZA_DEFAULT_EDITOR      = 'classic';
const MEZA_ALLOW_EDITOR_SWITCH = 0;

/** Core pages */
const MEZA_FRONT_PAGE_TITLE = 'Homepage';
const MEZA_FRONT_PAGE_SLUGS = ['home', 'homepage'];
const MEZA_POSTS_PAGE_TITLE = 'Posts Page';
const MEZA_POSTS_PAGE_SLUGS = ['blog'];

const MEZA_PRIVACY_POLICY_TITLE = 'Privacy Policy';
const MEZA_PRIVACY_POLICY_SLUGS = ['privacy-policy', 'privacy'];

const MEZA_COOKIE_POLICY_TITLE  = 'Cookie Policy';
const MEZA_COOKIE_POLICY_SLUGS  = ['cookie-policy', 'cookies'];
const MEZA_COOKIE_POLICY_OPTION = 'meza_page_for_cookie_policy';

const MEZA_CONTACT_TITLE      = 'Contact';
const MEZA_CONTACT_SLUGS      = ['contact'];
const MEZA_CONTACT_OPTION     = 'meza_page_for_contact';
const MEZA_CONTACT_FORM_TITLE = 'Contact';
const MEZA_CONTACT_FORM_SLUG  = 'contact';

const MEZA_ABOUT_TITLE   = 'About';
const MEZA_ABOUT_SLUGS   = ['about'];
const MEZA_ABOUT_OPTION  = 'meza_page_for_about';

const MEZA_STYLE_GUIDE_TITLE   = 'Style Guide';
const MEZA_STYLE_GUIDE_SLUGS   = ['styles', 'style-guide'];
const MEZA_STYLE_GUIDE_TPL     = 'page-style.php';
const MEZA_STYLE_GUIDE_OPTION  = 'meza_page_for_style_guide';

const MEZA_DOCUMENTATION_TITLE  = 'Documentation';
const MEZA_DOCUMENTATION_SLUGS  = ['documentation', 'site-documentation'];
const MEZA_DOCUMENTATION_TPL    = 'page-documentation.php';
const MEZA_DOCUMENTATION_OPTION = 'meza_page_for_documentation';

const MEZA_FAQ_TITLE  = 'Frequently Asked Questions (FAQ)';
const MEZA_FAQ_SLUGS  = ['faq'];
const MEZA_FAQ_TPL    = 'page-faq.php';
const MEZA_FAQ_OPTION = 'meza_page_for_faq';

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

/** Resolve the desired GMT offset; when timezone string is set, use the current DST-aware offset for that zone. */
function meza_resolve_gmt_offset()
{
    if (is_string(MEZA_TIMEZONE_STRING) && trim(MEZA_TIMEZONE_STRING) !== '') {
        try {
            $tz = new DateTimeZone(MEZA_TIMEZONE_STRING);
            $now = new DateTimeImmutable('now', $tz);
            return $tz->getOffset($now) / HOUR_IN_SECONDS;
        } catch (Throwable $e) {
            // Fall back to explicit constant below.
        }
    }

    return MEZA_GMT_OFFSET;
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

/** Build the standard SEO title used for seeded utility pages. */
function meza_build_page_meta_title(string $page_title): string
{
    $page_title = trim(wp_strip_all_tags($page_title));
    $site_name = trim((string) get_option('blogname', ''));

    if ($page_title === '') return $site_name;
    if ($site_name === '') return $page_title;

    return sprintf('%s | %s', $page_title, $site_name);
}

/** Store the standard Yoast meta title for a page. */
function meza_assign_page_meta_title(int $page_id, string $page_title = ''): void
{
    if ($page_id <= 0) return;

    if ($page_title === '') {
        $page_title = (string) get_the_title($page_id);
    }

    $meta_title = meza_build_page_meta_title($page_title);
    if ($meta_title === '') return;

    update_post_meta($page_id, '_yoast_wpseo_title', $meta_title);
}

/** Ask Yoast to rebuild its indexable record for a post after manual SEO meta changes. */
function meza_refresh_yoast_indexable(int $post_id): void
{
    if ($post_id <= 0 || !function_exists('YoastSEO')) {
        return;
    }

    if (!class_exists('\Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher')) {
        return;
    }

    try {
        $watcher = YoastSEO()->classes->get(\Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher::class);
        if (is_object($watcher) && method_exists($watcher, 'build_indexable')) {
            $watcher->build_indexable($post_id);
        }
    } catch (Throwable $e) {
    }
}

/** Apply Yoast Advanced tab robots defaults for a page. */
function meza_assign_yoast_robots(int $page_id, bool $allow_indexing = true, bool $allow_follow = true): void
{
    if ($page_id <= 0) return;

    update_post_meta($page_id, '_yoast_wpseo_meta-robots-noindex', $allow_indexing ? '2' : '1');
    update_post_meta($page_id, '_yoast_wpseo_meta-robots-nofollow', $allow_follow ? '0' : '1');
}

/** Normalize and de-duplicate page slug candidates while preserving order. */
function meza_normalize_slug_candidates(array $slugs): array
{
    $normalized = [];

    foreach ($slugs as $slug) {
        $slug = sanitize_title((string) $slug);
        if ($slug === '' || in_array($slug, $normalized, true)) continue;
        $normalized[] = $slug;
    }

    return $normalized;
}

/** Return the option name that stores manually deleted seeded pages. */
function meza_get_deleted_seeded_pages_option_name(): string
{
    return 'meza_deleted_seeded_pages_v1';
}

/** Build the list of auto-seeded pages that should respect manual deletion. */
function meza_get_seeded_page_definitions(): array
{
    return [
        [
            'slugs' => MEZA_FRONT_PAGE_SLUGS,
            'option_keys' => ['page_on_front'],
        ],
        [
            'slugs' => MEZA_POSTS_PAGE_SLUGS,
            'option_keys' => ['page_for_posts'],
        ],
        [
            'slugs' => MEZA_PRIVACY_POLICY_SLUGS,
            'option_keys' => ['wp_page_for_privacy_policy'],
        ],
        [
            'slugs' => MEZA_COOKIE_POLICY_SLUGS,
            'option_keys' => [MEZA_COOKIE_POLICY_OPTION],
        ],
        [
            'slugs' => MEZA_CONTACT_SLUGS,
            'option_keys' => [MEZA_CONTACT_OPTION],
        ],
        [
            'slugs' => MEZA_ABOUT_SLUGS,
            'option_keys' => [MEZA_ABOUT_OPTION],
        ],
        [
            'slugs' => MEZA_STYLE_GUIDE_SLUGS,
            'option_keys' => [MEZA_STYLE_GUIDE_OPTION],
        ],
        [
            'slugs' => MEZA_DOCUMENTATION_SLUGS,
            'option_keys' => [MEZA_DOCUMENTATION_OPTION],
        ],
        [
            'slugs' => MEZA_FAQ_SLUGS,
            'option_keys' => [MEZA_FAQ_OPTION],
        ],
    ];
}

/** Normalize the stored list of seeded page deletions. */
function meza_normalize_deleted_seeded_pages($value): array
{
    return array_values(array_unique(array_filter(array_map(
        static function ($slug): string {
            return sanitize_title((string) $slug);
        },
        is_array($value) ? $value : []
    ))));
}

/** Return the primary identifier for a seeded page definition. */
function meza_get_seeded_page_definition_identifier(array $definition): string
{
    $slugs = meza_normalize_slug_candidates((array) ($definition['slugs'] ?? []));

    return $slugs[0] ?? '';
}

/** Check whether a seeded page slug has been manually trashed or deleted. */
function meza_is_seeded_page_manually_deleted(array $slugs): bool
{
    $deleted_slugs = meza_normalize_deleted_seeded_pages(
        get_option(meza_get_deleted_seeded_pages_option_name(), [])
    );

    foreach (meza_normalize_slug_candidates($slugs) as $slug) {
        if (in_array($slug, $deleted_slugs, true)) {
            return true;
        }
    }

    return false;
}

/** Persist a manual deletion marker for a seeded page and clear stale option references. */
function meza_mark_seeded_page_deleted(array $definition): void
{
    $identifier = meza_get_seeded_page_definition_identifier($definition);
    if ($identifier === '') {
        return;
    }

    $deleted_slugs = meza_normalize_deleted_seeded_pages(
        get_option(meza_get_deleted_seeded_pages_option_name(), [])
    );

    if (!in_array($identifier, $deleted_slugs, true)) {
        $deleted_slugs[] = $identifier;
        update_option(meza_get_deleted_seeded_pages_option_name(), $deleted_slugs, false);
    }

    foreach ((array) ($definition['option_keys'] ?? []) as $option_key) {
        if ($option_key === '') {
            continue;
        }

        update_option((string) $option_key, 0);
    }
}

/** Remove a manual deletion marker when a seeded page is restored from the trash. */
function meza_clear_seeded_page_deleted(array $definition): void
{
    $identifier = meza_get_seeded_page_definition_identifier($definition);
    if ($identifier === '') {
        return;
    }

    $deleted_slugs = array_values(array_filter(
        meza_normalize_deleted_seeded_pages(get_option(meza_get_deleted_seeded_pages_option_name(), [])),
        static function (string $slug) use ($identifier): bool {
            return $slug !== $identifier;
        }
    ));

    update_option(meza_get_deleted_seeded_pages_option_name(), $deleted_slugs, false);
}

/** Match a page post to one of the auto-seeded page definitions. */
function meza_get_seeded_page_definition_for_post(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!($post instanceof WP_Post) || $post->post_type !== 'page') {
        return null;
    }

    $post_slug = sanitize_title((string) $post->post_name);
    if ($post_slug === '') {
        return null;
    }

    foreach (meza_get_seeded_page_definitions() as $definition) {
        $slugs = meza_normalize_slug_candidates((array) ($definition['slugs'] ?? []));
        if (in_array($post_slug, $slugs, true)) {
            return $definition;
        }
    }

    return null;
}

/** Remember when an auto-seeded page is manually trashed or deleted. */
function meza_track_seeded_page_deletion(int $post_id): void
{
    $definition = meza_get_seeded_page_definition_for_post($post_id);
    if (is_array($definition)) {
        meza_mark_seeded_page_deleted($definition);
    }
}

/** Remove the manual deletion marker when a seeded page is restored. */
function meza_track_seeded_page_restoration(int $post_id): void
{
    $definition = meza_get_seeded_page_definition_for_post($post_id);
    if (is_array($definition)) {
        meza_clear_seeded_page_deleted($definition);
    }
}

add_action('wp_trash_post', 'meza_track_seeded_page_deletion', 5);
add_action('before_delete_post', 'meza_track_seeded_page_deletion', 5);
add_action('untrashed_post', 'meza_track_seeded_page_restoration', 5);

/** Return the first existing page that matches one of the candidate slugs. */
function meza_get_page_by_candidate_slugs(array $slugs): ?WP_Post
{
    foreach (meza_normalize_slug_candidates($slugs) as $slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if (
            $page instanceof WP_Post
            && $page->post_type === 'page'
            && !in_array((string) $page->post_status, ['auto-draft', 'trash'], true)
        ) {
            return $page;
        }
    }

    return null;
}

/**
 * Ensure a page exists at $slug:
 * - Creates it when missing
 * - Supports alias slugs
 * - Can optionally enforce status and template updates
 * Returns the page ID (0 on failure).
 */
function meza_ensure_page_state(string $title, array $slugs, array $args = []): int
{
    $args = wp_parse_args($args, [
        'status' => 'publish',
        'force_status' => false,
        'force_title' => false,
        'template' => '',
        'always_apply_template' => false,
        'meta_title' => true,
        'yoast_allow_indexing' => true,
        'yoast_allow_follow' => true,
    ]);

    $slugs = meza_normalize_slug_candidates($slugs);
    if ($slugs === []) return 0;

    $page = meza_get_page_by_candidate_slugs($slugs);
    $created = false;

    if (!$page instanceof WP_Post && meza_is_seeded_page_manually_deleted($slugs)) {
        return 0;
    }

    if (!$page instanceof WP_Post) {
        $id = wp_insert_post([
            'post_type'   => 'page',
            'post_title'  => $title,
            'post_name'   => $slugs[0],
            'post_status' => (string) $args['status'],
        ]);
        if (is_wp_error($id)) return 0;
        $page = get_post($id);
        $created = true;
    }

    if (!$page instanceof WP_Post) return 0;

    $id = (int)$page->ID;

    if (!empty($args['force_status']) && $page->post_status !== (string) $args['status']) {
        wp_update_post([
            'ID' => $id,
            'post_status' => (string) $args['status'],
        ]);
    }

    if (!empty($args['force_title']) && $page->post_title !== $title) {
        wp_update_post([
            'ID' => $id,
            'post_title' => $title,
        ]);
    }

    $template_file = (string) ($args['template'] ?? '');
    if (
        $template_file !== ''
        && ($created || !empty($args['always_apply_template']))
    ) {
        meza_assign_template($id, $template_file);
    }

    if (!empty($args['meta_title'])) {
        meza_assign_page_meta_title($id, $title);
    }

    meza_assign_yoast_robots(
        $id,
        !empty($args['yoast_allow_indexing']),
        !empty($args['yoast_allow_follow'])
    );

    meza_refresh_yoast_indexable($id);

    return $id;
}

/** Return the first existing form post that matches the target slug. */
function meza_get_form_by_slug(string $slug): ?WP_Post
{
    $slug = sanitize_title($slug);
    if ($slug === '') return null;

    $forms = get_posts([
        'post_type' => 'form',
        'name' => $slug,
        'post_status' => ['draft', 'publish', 'pending', 'private', 'future'],
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'all',
        'suppress_filters' => true,
        'no_found_rows' => true,
    ]);

    $form = $forms[0] ?? null;

    return $form instanceof WP_Post ? $form : null;
}

/** Ensure the default Contact form exists as a draft form post. */
function meza_ensure_form_state(string $title, string $slug, string $status = 'draft'): int
{
    $form = meza_get_form_by_slug($slug);

    if (!$form instanceof WP_Post) {
        $id = wp_insert_post([
            'post_type' => 'form',
            'post_title' => $title,
            'post_name' => sanitize_title($slug),
            'post_status' => $status,
        ]);

        if (is_wp_error($id)) return 0;
        $form = get_post($id);
    }

    if (!$form instanceof WP_Post) return 0;

    if ($form->post_status !== $status) {
        wp_update_post([
            'ID' => (int) $form->ID,
            'post_status' => $status,
        ]);
    }

    return (int) $form->ID;
}

/** Attach a form to a page's form section while keeping the section hidden by default. */
function meza_assign_contact_form_to_page(int $page_id, int $form_id): void
{
    if ($page_id <= 0 || $form_id <= 0) return;

    update_post_meta($page_id, 'show_form', 0);
    update_post_meta($page_id, 'section_form_form', $form_id);
    update_post_meta($page_id, 'section_form', ['form' => $form_id]);
}

/** Update one or more option keys that reference a special page ID. */
function meza_update_page_reference_options(string $filter_name, int $page_id, array $option_keys): void
{
    if ($page_id <= 0) return;

    $option_keys = apply_filters($filter_name, $option_keys, $page_id);
    if (!is_array($option_keys)) return;

    foreach ($option_keys as $option_key) {
        $option_key = sanitize_key((string) $option_key);
        if ($option_key === '') continue;
        update_option($option_key, $page_id);
    }
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

    if ($can_seed_pages) {
        $home_id  = meza_ensure_page_state(MEZA_FRONT_PAGE_TITLE, MEZA_FRONT_PAGE_SLUGS);
        $posts_id = meza_ensure_page_state(MEZA_POSTS_PAGE_TITLE, MEZA_POSTS_PAGE_SLUGS);

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
    }

    // Privacy Policy
    $privacy_id = meza_ensure_page_state(MEZA_PRIVACY_POLICY_TITLE, MEZA_PRIVACY_POLICY_SLUGS, [
        'status' => 'draft',
        'force_status' => true,
        'yoast_allow_indexing' => false,
        'yoast_allow_follow' => false,
    ]);
    if ($privacy_id) {
        if (function_exists('set_privacy_policy_page')) set_privacy_policy_page($privacy_id);
        else update_option('wp_page_for_privacy_policy', $privacy_id);
    }

    // Cookie Policy
    $cookie_id = meza_ensure_page_state(MEZA_COOKIE_POLICY_TITLE, MEZA_COOKIE_POLICY_SLUGS, [
        'yoast_allow_indexing' => false,
        'yoast_allow_follow' => false,
    ]);
    meza_update_page_reference_options('meza_cookie_policy_page_option_keys', $cookie_id, [MEZA_COOKIE_POLICY_OPTION]);

    // Style Guide + template
    $style_guide_id = meza_ensure_page_state(MEZA_STYLE_GUIDE_TITLE, MEZA_STYLE_GUIDE_SLUGS, [
        'template' => MEZA_STYLE_GUIDE_TPL,
        'always_apply_template' => true,
        'yoast_allow_indexing' => false,
        'yoast_allow_follow' => false,
    ]);
    meza_update_page_reference_options('meza_style_guide_page_option_keys', $style_guide_id, [MEZA_STYLE_GUIDE_OPTION]);

    // Contact page + draft form attachment
    $contact_page_id = meza_ensure_page_state(MEZA_CONTACT_TITLE, MEZA_CONTACT_SLUGS, [
        'status' => 'publish',
        'force_status' => true,
        'force_title' => true,
    ]);
    meza_update_page_reference_options('meza_contact_page_option_keys', $contact_page_id, [MEZA_CONTACT_OPTION]);
    $contact_form_id = meza_ensure_form_state(MEZA_CONTACT_FORM_TITLE, MEZA_CONTACT_FORM_SLUG, 'draft');
    meza_assign_contact_form_to_page($contact_page_id, $contact_form_id);

    // About page
    $about_page_id = meza_ensure_page_state(MEZA_ABOUT_TITLE, MEZA_ABOUT_SLUGS, [
        'status' => 'publish',
        'force_status' => true,
        'force_title' => true,
    ]);
    meza_update_page_reference_options('meza_about_page_option_keys', $about_page_id, [MEZA_ABOUT_OPTION]);

    // Documentation + template
    $documentation_id = meza_ensure_page_state(MEZA_DOCUMENTATION_TITLE, MEZA_DOCUMENTATION_SLUGS, [
        'status' => 'private',
        'force_status' => true,
        'force_title' => true,
        'template' => MEZA_DOCUMENTATION_TPL,
        'always_apply_template' => true,
    ]);
    meza_update_page_reference_options('meza_documentation_page_option_keys', $documentation_id, [MEZA_DOCUMENTATION_OPTION]);

    // FAQ + template
    $faq_page_id = meza_ensure_page_state(MEZA_FAQ_TITLE, MEZA_FAQ_SLUGS, [
        'status' => 'publish',
        'force_status' => true,
        'force_title' => true,
        'template' => MEZA_FAQ_TPL,
        'always_apply_template' => true,
    ]);
    meza_update_page_reference_options('meza_faq_page_option_keys', $faq_page_id, [MEZA_FAQ_OPTION]);

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
    $resolved_gmt_offset = meza_resolve_gmt_offset();
    if ($resolved_gmt_offset !== null && $resolved_gmt_offset !== '') {
        update_option('gmt_offset', (float) $resolved_gmt_offset);
    }
    update_option('thumbnail_size_w', MEZA_THUMB_W);
    update_option('thumbnail_size_h', MEZA_THUMB_H);
    update_option('thumbnail_crop',   MEZA_THUMB_CROP);
    update_option('medium_size_w',    MEZA_MED_W);
    update_option('medium_size_h',    MEZA_MED_H);
    update_option('large_size_w',     MEZA_LG_W);
    update_option('large_size_h',     MEZA_LG_H);
    update_option('uploads_use_yearmonth_folders', MEZA_UPLOADS_YM_FOLDERS);

    $current_structure = (string) get_option('permalink_structure');
    if ($current_structure === '' && MEZA_PERMALINK_STRUCTURE !== '') {
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
function meza_admin_items_per_page_target(): int
{
    $target = (int) apply_filters('meza_admin_items_per_page_target', (int) MEZA_ADMIN_ITEMS_PER_PAGE);

    return $target > 0 ? $target : 20;
}

/** Build the list of screen-option keys used by posts, taxonomies, media, users, and network tables. */
function meza_admin_per_page_option_keys(): array
{
    $options = [
        'plugins_per_page',
        'upload_per_page',
        'users_per_page',
        'tools_page_action_scheduler_per_page',
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

/** Count admin-visible items for a post type so pagination UI only appears when needed. */
function meza_post_type_admin_item_count(string $post_type): int
{
    $counts = wp_count_posts($post_type, 'readable');
    if (!is_object($counts)) {
        return 0;
    }

    $total = 0;
    foreach (get_object_vars($counts) as $status => $count) {
        if ($status === 'auto-draft') {
            continue;
        }

        $total += (int) $count;
    }

    return $total;
}

/** Count admin-visible terms for a taxonomy so pagination UI only appears when needed. */
function meza_taxonomy_admin_item_count(string $taxonomy): int
{
    $count = wp_count_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    if (is_wp_error($count)) {
        return 0;
    }

    return (int) $count;
}

/** Count installed plugins so plugin-list pagination UI only appears when needed. */
function meza_plugins_admin_item_count(): int
{
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();
    return is_array($plugins) ? count($plugins) : 0;
}

/** Count admin-visible users so user-list pagination UI only appears when needed. */
function meza_users_admin_item_count(): int
{
    $counts = function_exists('meza_get_cached_user_counts')
        ? meza_get_cached_user_counts()
        : count_users();

    if (!is_array($counts)) {
        return 0;
    }

    return (int) ($counts['total_users'] ?? 0);
}

/** Count admin-visible media items so media-list pagination UI only appears when needed. */
function meza_media_admin_item_count(): int
{
    $counts = wp_count_posts('attachment', 'readable');
    if (!is_object($counts)) {
        return 0;
    }

    $total = 0;
    foreach (get_object_vars($counts) as $status => $count) {
        if ($status === 'trash') {
            continue;
        }

        $total += (int) $count;
    }

    return $total;
}

/** Count scheduled actions so the Action Scheduler pagination UI only appears when needed. */
function meza_action_scheduler_admin_item_count(): int
{
    if (!class_exists('ActionScheduler_Store')) {
        return 0;
    }

    try {
        return (int) ActionScheduler_Store::instance()->query_actions([], 'count');
    } catch (Throwable $e) {
        return 0;
    }
}

/** Default admin "items per page" to one value without overriding saved user choices. */
add_action('admin_init', function () {
    foreach (meza_admin_per_page_option_keys() as $option_key) {
        add_filter("default_user_option_{$option_key}", function ($default_value) {
            return meza_admin_items_per_page_target();
        }, 9999, 1);

        add_filter("get_user_option_{$option_key}", function ($value, $option, $user) {
            $value = (int) $value;
            return $value > 0 ? $value : meza_admin_items_per_page_target();
        }, 9999, 3);
    }
}, 1);

/** Hide the Pagination screen option on post and taxonomy lists that do not exceed the forced page size. */
add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) return;
    $target = meza_admin_items_per_page_target();

    if ($screen->base === 'edit') {
        $post_type = (string) ($screen->post_type ?? '');
        if ($post_type === '') return;

        if (meza_post_type_admin_item_count($post_type) > $target) return;
    } elseif ($screen->base === 'edit-tags') {
        $taxonomy = (string) ($screen->taxonomy ?? '');
        if ($taxonomy === '') return;

        if (meza_taxonomy_admin_item_count($taxonomy) > $target) return;
    } elseif ($screen->base === 'plugins') {
        if (meza_plugins_admin_item_count() > $target) return;
    } elseif ($screen->base === 'upload') {
        if (meza_media_admin_item_count() > $target) return;
    } elseif ($screen->base === 'users') {
        if (meza_users_admin_item_count() > $target) return;
    } elseif ($screen->id === 'tools_page_action-scheduler') {
        if (meza_action_scheduler_admin_item_count() > $target) return;
    } else {
        return;
    }

    $screen->remove_option('per_page');
}, 1);

/** Force compact view mode for all post-type list tables. */
add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    if (headers_sent()) return;

    set_user_setting('posts_list_mode', 'list');
}, 1);

/** Remove the View mode screen option for all post-type list tables. */
add_filter('view_mode_post_types', '__return_empty_array', 9999);

/**
 * Screen Options defaults:
 * - Keep "SEO Title" + "Meta Desc." checked by default on post list screens.
 * - Force-hide Yoast internal links columns.
 */
add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    if (!is_array($hidden)) return $hidden;
    if (!($screen instanceof WP_Screen)) return $hidden;
    // Respect user Screen Options changes after first save/load.
    if (!$use_defaults) return $hidden;

    if ($screen->base !== 'edit') return $hidden;

    // Force-hide known Content Permissions column ids.
    foreach (['content_permissions', 'content-permissions'] as $column_id) {
        if (!in_array($column_id, $hidden, true)) $hidden[] = $column_id;
    }

    // Force-hide Yoast link-related and keyphrase columns.
    foreach (['wpseo-links', 'wpseo-linked', 'wpseo-focuskw'] as $column_id) {
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
