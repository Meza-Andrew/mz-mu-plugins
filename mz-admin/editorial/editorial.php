<?php

/** ================================
 *  THEME-AGNOSTIC EDITORIAL BEHAVIOR
 *  ================================ */

/** Media policy: allow SVG uploads, but only for administrators who can manage site-wide settings. */
function meza_allow_svg_uploads(array $mimes): array
{
    if (current_user_can('manage_options')) {
        $mimes['svg'] = 'image/svg+xml';
    }

    return $mimes;
}
add_filter('upload_mimes', 'meza_allow_svg_uploads');

/** ACF setup: pull the Google API key from the shared theme filter so Maps fields work in the editor. */
function meza_extend_acf_init(): void
{
    $gcloud_key = (string) apply_filters('theme_gcloud_key', '');
    if ($gcloud_key === '') return;

    acf_update_setting('google_api_key', $gcloud_key);
}
add_action('acf/init', 'meza_extend_acf_init');

/** ACF setup: center new map fields on a sensible default location so editors start from the right region. */
add_filter('acf/fields/google_map/api', function ($api) {
    $api['center_lat'] = 38.3032;
    $api['center_lng'] = -77.4605;
    $api['zoom'] = 14;

    return $api;
});

/** Media hygiene: prevent WordPress from auto-filling image titles from filenames on first upload. */
add_filter('wp_insert_attachment_data', function ($data, $postarr) {
    if (
        empty($postarr['ID'])
        && isset($postarr['post_mime_type'])
        && wp_match_mime_types('image', $postarr['post_mime_type'])
    ) {
        $data['post_title'] = '';
    }

    return $data;
}, 10, 2);

/** Dashboard: replace the default "At a Glance" list with counts for the admin-editable post types editors can manage. */
if (!function_exists('meza_get_site_overview_dashboard_post_type_items')) {
    function meza_get_site_overview_dashboard_post_type_items(bool $include_zero_counts = false): array
    {
        $items = [];
        $post_types = get_post_types(['show_ui' => true], 'objects');

        foreach ($post_types as $post_type) {
            if (!($post_type instanceof WP_Post_Type)) {
                continue;
            }

            $post_type_name = (string) ($post_type->name ?? '');
            if ($post_type_name === '') {
                continue;
            }

            if (
                $post_type_name === 'post'
                && function_exists('meza_are_posts_enabled')
                && !meza_are_posts_enabled()
            ) {
                continue;
            }

            if (
                in_array($post_type_name, ['attachment', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request'], true)
                || str_starts_with($post_type_name, 'wp_')
                || (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type_name))
            ) {
                continue;
            }

            if (empty($post_type->show_ui) || empty($post_type->cap->edit_posts) || !current_user_can($post_type->cap->edit_posts)) {
                continue;
            }

            $is_hidden_menu_post_type = $post_type_name !== 'post'
                && $post_type_name !== 'page'
                && $post_type->show_in_menu === false;

            if (
                $is_hidden_menu_post_type
                && !(
                    function_exists('meza_admin_bar_allows_hidden_post_type_new_node')
                    && meza_admin_bar_allows_hidden_post_type_new_node($post_type_name)
                )
            ) {
                continue;
            }

            $published = meza_get_published_post_type_count($post_type_name);
            if (!$include_zero_counts && $published < 1) {
                continue;
            }

            $items[] = [
                'post_type' => $post_type_name,
                'count' => $published,
                'label' => $published === 1 ? $post_type->labels->singular_name : $post_type->labels->name,
                'singular_label' => (string) ($post_type->labels->singular_name ?? $post_type->labels->name ?? $post_type_name),
                'url' => $post_type_name === 'post'
                    ? admin_url('edit.php')
                    : admin_url('edit.php?post_type=' . $post_type_name),
                'icon' => meza_get_post_type_dashicon_class($post_type_name),
            ];
        }

        return $items;
    }
}

function meza_replace_glance_items()
{
    $custom_items = [];

    foreach (meza_get_site_overview_dashboard_post_type_items() as $item) {
        if (!is_array($item)) {
            continue;
        }

        $post_type_name = (string) ($item['post_type'] ?? '');
        if ($post_type_name === '') {
            continue;
        }

        $custom_items[] = [
            'name' => 'meza-' . $post_type_name,
            'count' => (int) ($item['count'] ?? 0),
            'label' => (string) ($item['label'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'icon' => (string) ($item['icon'] ?? 'dashicons-admin-post'),
        ];
    }

    $custom_items = array_merge($custom_items, meza_admin_get_dashboard_provider_items());

    usort($custom_items, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    foreach ($custom_items as $item) {
        $label = sprintf('%s %s', number_format_i18n($item['count']), $item['label']);
        printf(
            '<li class="%s"><a href="%s"><span class="dashicons %s"></span> %s</a></li>',
            esc_attr($item['name']),
            esc_url($item['url']),
            esc_attr($item['icon']),
            esc_html($label)
        );
    }
}
add_action('dashboard_glance_items', 'meza_replace_glance_items');

if (!function_exists('meza_admin_can_view_gravity_forms_dashboard_item')) {
    function meza_admin_can_view_gravity_forms_dashboard_item(): bool
    {
        return current_user_can('gravityforms_edit_forms')
            || current_user_can('gform_full_access')
            || (class_exists('GFCommon') && method_exists('GFCommon', 'current_user_can_any') && GFCommon::current_user_can_any('gravityforms_edit_forms'));
    }
}

if (!function_exists('meza_admin_get_gravity_forms_count')) {
    function meza_admin_get_gravity_forms_count(): int
    {
        static $cached_form_count = null;

        if ($cached_form_count !== null) {
            return $cached_form_count;
        }

        $transient_key = 'meza_admin_gravity_forms_count';
        $cached_transient = get_transient($transient_key);
        if ($cached_transient !== false) {
            $cached_form_count = max(0, (int) $cached_transient);
            return $cached_form_count;
        }

        $form_count = 0;

        if (class_exists('GFFormsModel') && method_exists('GFFormsModel', 'get_forms')) {
            $forms = GFFormsModel::get_forms(null, 'title', 'ASC', false);
            $form_count = is_array($forms) ? count($forms) : 0;
        } elseif (class_exists('GFAPI') && method_exists('GFAPI', 'get_forms')) {
            $forms = GFAPI::get_forms(true, false, 'title', 'ASC');
            $form_count = is_array($forms) ? count($forms) : 0;
        }

        if ($form_count === 0) {
            global $wpdb;

            $table_name = $wpdb->prefix . 'gf_form';
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($table_exists === $table_name) {
                $form_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE is_trash = 0");
            }
        }

        $cached_form_count = max(0, $form_count);
        set_transient($transient_key, $cached_form_count, 120);

        return $cached_form_count;
    }
}

if (!function_exists('meza_admin_flush_gravity_forms_count_cache')) {
    function meza_admin_flush_gravity_forms_count_cache(): void
    {
        delete_transient('meza_admin_gravity_forms_count');
    }
}

add_action('gform_after_save_form', 'meza_admin_flush_gravity_forms_count_cache');
add_action('gform_after_delete_form', 'meza_admin_flush_gravity_forms_count_cache');
add_action('gform_post_form_trashed', 'meza_admin_flush_gravity_forms_count_cache');
add_action('gform_post_form_restored', 'meza_admin_flush_gravity_forms_count_cache');

if (!function_exists('meza_admin_get_gravity_forms_dashboard_items')) {
    function meza_admin_get_gravity_forms_dashboard_items(): array
    {
        $form_count = meza_admin_get_gravity_forms_count();
        if ($form_count < 1) {
            return [];
        }

        return [[
            'name' => 'meza-gravityforms',
            'count' => $form_count,
            'label' => $form_count === 1 ? 'Form' : 'Forms',
            'url' => admin_url('admin.php?page=gf_edit_forms'),
            'icon' => meza_get_keyword_dashicon_for_top_level_menu_item('Forms', 'gf_edit_forms'),
            'new_node_id' => 'new-gravityforms',
            'new_label' => 'Form',
            'new_url' => admin_url('admin.php?page=gf_new_form'),
            'new_capability' => 'gravityforms_create_form',
        ]];
    }
}

if (!function_exists('meza_admin_get_dashboard_item_providers')) {
    function meza_admin_get_dashboard_item_providers(): array
    {
        $defaults = [
            [
                'id' => 'gravityforms',
                'is_available_callback' => 'meza_admin_can_view_gravity_forms_dashboard_item',
                'items_callback' => 'meza_admin_get_gravity_forms_dashboard_items',
            ],
        ];

        $providers = apply_filters('meza_admin_dashboard_item_providers', $defaults);

        return is_array($providers) ? $providers : $defaults;
    }
}

if (!function_exists('meza_admin_get_dashboard_provider_items')) {
    function meza_admin_get_dashboard_provider_items(): array
    {
        $items = [];

        foreach (meza_admin_get_dashboard_item_providers() as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $availability_callback = $provider['is_available_callback'] ?? null;
            if (is_callable($availability_callback) && !call_user_func($availability_callback, $provider)) {
                continue;
            }

            $items_callback = $provider['items_callback'] ?? null;
            if (!is_callable($items_callback)) {
                continue;
            }

            $provider_items = call_user_func($items_callback, $provider);
            if (!is_array($provider_items)) {
                continue;
            }

            foreach ($provider_items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $name = trim((string) ($item['name'] ?? ''));
                $label = trim((string) ($item['label'] ?? ''));
                $url = trim((string) ($item['url'] ?? ''));
                $icon = trim((string) ($item['icon'] ?? ''));
                $count = isset($item['count']) ? (int) $item['count'] : 0;
                $new_node_id = sanitize_key((string) ($item['new_node_id'] ?? ''));
                $new_label = trim((string) ($item['new_label'] ?? ''));
                $new_url = trim((string) ($item['new_url'] ?? ''));
                $new_capability = trim((string) ($item['new_capability'] ?? ''));

                if ($name === '' || $label === '' || $url === '' || $icon === '' || $count < 1) {
                    continue;
                }

                $normalized_item = [
                    'name' => $name,
                    'count' => $count,
                    'label' => $label,
                    'url' => $url,
                    'icon' => $icon,
                ];

                if ($new_node_id !== '' && $new_label !== '' && $new_url !== '') {
                    $normalized_item['new_node_id'] = $new_node_id;
                    $normalized_item['new_label'] = $new_label;
                    $normalized_item['new_url'] = $new_url;

                    if ($new_capability !== '') {
                        $normalized_item['new_capability'] = $new_capability;
                    }
                }

                $items[] = $normalized_item;
            }
        }

        return $items;
    }
}

/** Dashboard: hide the stock core counters so they do not duplicate the custom editorial counts above. */
function meza_hide_default_glance_items_css(): void
{
    echo '<style>
        #dashboard_right_now .post-count,
        #dashboard_right_now .page-count,
        #dashboard_right_now .comment-count,
        #dashboard_right_now .comment-mod-count {
            display: none !important;
        }
        #dashboard_right_now .search-engines-info:before, #dashboard_right_now li a:before, #dashboard_right_now li>span:before {
            content: none;
        }
    </style>';
}
add_action('admin_head-index.php', 'meza_hide_default_glance_items_css');

/** ================================
 *  WYSIWYG NORMALIZATION
 *  ================================ */

/**
 * Normalize editor HTML by removing inline styling noise and converting simple presentational tags to semantic ones.
 * This keeps stored content cleaner and makes front-end output more predictable.
 */
function meza_normalize_wysiwyg_markup(string $html): string
{
    $html = preg_replace('/<(li|span)([^>]*) style="[^"]*"([^>]*)>/i', '<$1$2$3>', $html);
    $html = preg_replace('/<span[^>]*>\s*<\/span>/i', '', $html);
    $html = preg_replace('/<span[^>]*>\s*(<[^>]+>)\s*<\/span>/i', '$1', $html);
    $html = str_replace(['<b>', '</b>', '<i>', '</i>'], ['<strong>', '</strong>', '<em>', '</em>'], $html);

    return $html;
}

/** TinyMCE defaults: reduce extra wrappers and formatting noise before editors even save the field. */
function meza_acf_tinymce_custom_settings($init)
{
    $init['forced_root_block'] = false;
    $init['force_p_newlines'] = true;
    $init['removeformat'] = 'span';
    $init['valid_elements'] = '*[*]';
    return $init;
}
add_filter('tiny_mce_before_init', 'meza_acf_tinymce_custom_settings');

/** Save-time cleanup for ACF WYSIWYG fields so the database stores the normalized version of the content. */
function meza_acf_strip_inline_styles($value, $post_id, $field)
{
    return meza_normalize_wysiwyg_markup((string) $value);
}
add_filter('acf/update_value/type=wysiwyg', 'meza_acf_strip_inline_styles', 10, 3);

/** Extra save-time cleanup for span wrappers that can survive the broader normalization pass. */
function meza_acf_strip_spans_around_elements($value, $post_id, $field)
{
    return preg_replace('/<span[^>]*>\s*(<[^>]+>)\s*<\/span>/i', '$1', $value);
}
add_filter('acf/update_value/type=wysiwyg', 'meza_acf_strip_spans_around_elements', 10, 3);

/** Render-time cleanup so existing legacy content is cleaned up even if it was saved before these rules existed. */
function meza_acf_clean_wysiwyg_output($content)
{
    return meza_normalize_wysiwyg_markup((string) $content);
}
add_filter('acf_the_content', 'meza_acf_clean_wysiwyg_output');
add_filter('the_content', 'meza_acf_clean_wysiwyg_output');

/**
 * Default the Yoast SEO panel to collapsed on post edit screens,
 * while still persisting whatever state the editor chooses afterward.
 */
add_action('admin_footer-post.php', 'meza_render_yoast_panel_state_script');
add_action('admin_footer-post-new.php', 'meza_render_yoast_panel_state_script');
add_action('admin_footer-post.php', 'meza_render_post_panel_label_sync_script', 1001);
add_action('admin_footer-post-new.php', 'meza_render_post_panel_label_sync_script', 1001);

function meza_render_yoast_panel_state_script(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || (string) ($screen->base ?? '') !== 'post') {
        return;
    }

    $post_type = (string) ($screen->post_type ?? '');
    $taxonomy_labels = [];

    if ($post_type !== '') {
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        if (is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (!($taxonomy instanceof WP_Taxonomy) || empty($taxonomy->show_ui)) continue;

                $taxonomy_labels[] = strtolower(trim(wp_strip_all_tags((string) ($taxonomy->labels->name ?? ''))));
                $taxonomy_labels[] = strtolower(trim(wp_strip_all_tags((string) ($taxonomy->labels->singular_name ?? ''))));
            }
        }
    }

    $taxonomy_labels = array_values(array_unique(array_filter($taxonomy_labels, static function ($label): bool {
        return $label !== '';
    })));
?>
    <script id="meza-yoast-panel-state">
        (() => {
            const storageKey = 'mezaYoastPanelOpen';
            const defaultOpen = false;
            const boundTargets = new WeakSet();
            const postType = <?php echo wp_json_encode($post_type); ?> || '';
            const taxonomyLabels = <?php echo wp_json_encode($taxonomy_labels); ?> || [];

            const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const isElement = (value) => value instanceof HTMLElement;
            const getBlockEditorPanel = (element) => element?.closest?.('.components-panel__body, .editor-post-panel, .plugin-document-setting-panel') ||
                element?.parentElement ||
                null;
            const getSidebarButtons = () => {
                const selectors = [
                    '.edit-post-sidebar button[aria-expanded]',
                    '.editor-sidebar button[aria-expanded]',
                    '.interface-complementary-area button[aria-expanded]',
                    '.interface-interface-skeleton__sidebar button[aria-expanded]',
                ];

                return selectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)));
            };
            const getButtonText = (button) => normalize(button?.textContent);
            const getButtonControls = (button) => normalize(button?.getAttribute('aria-controls'));
            const getButtonLabelledBy = (button) => normalize(button?.getAttribute('aria-labelledby'));
            const matchesPanelButton = (button, matches) => {
                if (!isElement(button)) return false;

                return matches({
                    button,
                    label: getButtonText(button),
                    controls: getButtonControls(button),
                    labelledBy: getButtonLabelledBy(button),
                });
            };
            const findBlockEditorPanelByButton = (matches) => {
                const button = getSidebarButtons().find((candidate) => matchesPanelButton(candidate, matches));
                return button ? getBlockEditorPanel(button) : null;
            };
            const getBlockEditorPanelSiblings = (container) => Array.from(container?.children || []).filter(isElement);
            const isTaxonomyPanelButton = ({
                label,
                controls,
                labelledBy
            }) => {
                if (controls.includes('taxonomy') || labelledBy.includes('taxonomy')) return true;

                return taxonomyLabels.some((taxonomyLabel) => taxonomyLabel !== '' && label === taxonomyLabel);
            };
            const getBlockEditorTaxonomyPanels = (container) => {
                const panels = getSidebarButtons()
                    .filter((button) => matchesPanelButton(button, isTaxonomyPanelButton))
                    .map((button) => ({
                        button,
                        label: getButtonText(button),
                        panel: getBlockEditorPanel(button),
                    }))
                    .filter((entry) => isElement(entry.panel) && (!isElement(container) || entry.panel.parentElement === container));

                panels.sort((left, right) => left.label.localeCompare(right.label, undefined, {
                    sensitivity: 'base',
                    numeric: true,
                }));

                const seenPanels = new WeakSet();

                return panels
                    .map((entry) => entry.panel)
                    .filter((panel) => {
                        if (!isElement(panel) || seenPanels.has(panel)) return false;
                        seenPanels.add(panel);
                        return true;
                    });
            };

            const readPreference = () => {
                try {
                    const value = window.localStorage.getItem(storageKey);
                    if (value === 'open') return true;
                    if (value === 'closed') return false;
                } catch (error) {}

                return null;
            };

            const writePreference = (isOpen) => {
                try {
                    window.localStorage.setItem(storageKey, isOpen ? 'open' : 'closed');
                } catch (error) {}
            };

            const isAcfMetabox = (element) => {
                if (!isElement(element) || element.id === 'wpseo_meta') return false;

                const id = normalize(element.id);
                return id.startsWith('acf-') ||
                    element.classList.contains('acf-postbox') ||
                    element.querySelector('.acf-fields, .acf-postbox, [data-acf]');
            };

            const moveClassicYoastToBottom = () => {
                const metabox = document.getElementById('wpseo_meta');
                if (!isElement(metabox) || !isElement(metabox.parentElement)) return;

                const container = metabox.parentElement;
                const siblings = Array.from(container.children).filter(isElement);
                const acfBoxes = siblings.filter(isAcfMetabox);
                const anchor = acfBoxes.length > 0 ?
                    acfBoxes[acfBoxes.length - 1] :
                    siblings.filter((element) => element !== metabox && element.classList.contains('postbox')).at(-1) || null;

                if (anchor) {
                    if (anchor.nextElementSibling !== metabox) {
                        anchor.after(metabox);
                    }
                    return;
                }

                if (container.lastElementChild !== metabox) {
                    container.appendChild(metabox);
                }
            };

            const moveBlockEditorYoastToBottom = () => {
                const target = getBlockEditorTarget();
                if (!target || !isElement(target.key)) return;

                const panel = getBlockEditorPanel(target.key);

                if (!isElement(panel) || !isElement(panel.parentElement)) return;

                const container = panel.parentElement;
                if (container.lastElementChild !== panel) {
                    container.appendChild(panel);
                }
            };

            const moveBlockEditorFeaturedImageUnderPublish = () => {
                const featuredImagePanel = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label.includes('featured image') ||
                    controls.includes('featured-image') ||
                    labelledBy.includes('featured-image')
                ));

                if (!isElement(featuredImagePanel) || !isElement(featuredImagePanel.parentElement)) return;

                const container = featuredImagePanel.parentElement;
                const anchor = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label === 'summary' ||
                    label === 'excerpt' ||
                    label === 'subhead' ||
                    label.includes('publish') ||
                    label.includes('status') ||
                    controls.includes('post-status') ||
                    labelledBy.includes('post-status')
                ));
                const taxonomyAnchor = findBlockEditorPanelByButton(isTaxonomyPanelButton);
                const siblings = getBlockEditorPanelSiblings(container);
                const anchorIndex = (isElement(anchor) && anchor.parentElement === container) ?
                    siblings.indexOf(anchor) :
                    -1;
                const taxonomyIndex = (
                        isElement(taxonomyAnchor) &&
                        taxonomyAnchor.parentElement === container &&
                        taxonomyAnchor !== featuredImagePanel
                    ) ?
                    siblings.indexOf(taxonomyAnchor) :
                    -1;

                if (anchorIndex >= 0) {
                    if (anchor.nextElementSibling !== featuredImagePanel) {
                        anchor.after(featuredImagePanel);
                    }
                    return;
                }

                if (taxonomyIndex >= 0) {
                    if (taxonomyAnchor.previousElementSibling !== featuredImagePanel) {
                        container.insertBefore(featuredImagePanel, taxonomyAnchor);
                    }
                    return;
                }

                const fallbackAnchor = siblings.find((panel) => panel !== featuredImagePanel) || null;
                const targetAnchor = (isElement(anchor) && anchor.parentElement === container && anchor !== featuredImagePanel) ?
                    anchor :
                    fallbackAnchor;

                if (!isElement(targetAnchor)) return;
                if (targetAnchor.nextElementSibling === featuredImagePanel) return;

                targetAnchor.after(featuredImagePanel);
            };

            const moveBlockEditorTaxonomiesAndAttributes = () => {
                if (postType === 'page') {
                    return;
                }

                const featuredImagePanel = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label.includes('featured image') ||
                    controls.includes('featured-image') ||
                    labelledBy.includes('featured-image')
                ));
                const attributesPanel = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label === 'attributes' ||
                    controls.includes('page-attributes') ||
                    labelledBy.includes('page-attributes')
                ));

                const basePanel = isElement(featuredImagePanel) ? featuredImagePanel : attributesPanel;
                if (!isElement(basePanel) || !isElement(basePanel.parentElement)) {
                    return;
                }

                const container = basePanel.parentElement;
                const taxonomyPanels = getBlockEditorTaxonomyPanels(container).filter((panel) => panel !== attributesPanel);
                const publishAnchor = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label === 'summary' ||
                    label === 'excerpt' ||
                    label === 'subhead' ||
                    label.includes('publish') ||
                    label.includes('status') ||
                    controls.includes('post-status') ||
                    labelledBy.includes('post-status')
                ));
                const anchor = (
                        isElement(featuredImagePanel) &&
                        featuredImagePanel.parentElement === container
                    ) ?
                    featuredImagePanel :
                    (
                        isElement(publishAnchor) &&
                        publishAnchor.parentElement === container ?
                        publishAnchor :
                        null
                    );

                let insertionAnchor = isElement(anchor) ? anchor : null;

                for (const taxonomyPanel of taxonomyPanels) {
                    if (!isElement(taxonomyPanel) || taxonomyPanel.parentElement !== container) {
                        continue;
                    }

                    if (isElement(insertionAnchor)) {
                        if (insertionAnchor.nextElementSibling !== taxonomyPanel) {
                            insertionAnchor.after(taxonomyPanel);
                        }
                        insertionAnchor = taxonomyPanel;
                        continue;
                    }

                    if (container.firstElementChild !== taxonomyPanel) {
                        container.insertBefore(taxonomyPanel, container.firstElementChild);
                    }

                    insertionAnchor = taxonomyPanel;
                }

                if (!isElement(attributesPanel) || attributesPanel.parentElement !== container) {
                    return;
                }

                if (isElement(insertionAnchor)) {
                    if (insertionAnchor.nextElementSibling !== attributesPanel) {
                        insertionAnchor.after(attributesPanel);
                    }
                    return;
                }

                if (container.firstElementChild !== attributesPanel) {
                    container.insertBefore(attributesPanel, container.firstElementChild);
                }
            };

            const moveYoastToBottom = () => {
                moveBlockEditorFeaturedImageUnderPublish();
                moveBlockEditorTaxonomiesAndAttributes();

                if (postType === 'event') {
                    return;
                }

                moveClassicYoastToBottom();
                moveBlockEditorYoastToBottom();
            };

            const getClassicTarget = () => {
                const metabox = document.getElementById('wpseo_meta');
                if (!metabox) return null;

                return {
                    key: metabox,
                    isOpen: () => !metabox.classList.contains('closed'),
                    setOpen: (shouldOpen) => {
                        const toggle = metabox.querySelector('.handlediv, .postbox-header button.handlediv, .hndle');
                        if (toggle && !toggle.disabled) {
                            toggle.click();
                        }
                    },
                    bind: () => {
                        const observer = new MutationObserver(() => {
                            writePreference(!metabox.classList.contains('closed'));
                        });

                        observer.observe(metabox, {
                            attributes: true,
                            attributeFilter: ['class'],
                        });
                    },
                };
            };

            const getBlockEditorTarget = () => {
                for (const button of getSidebarButtons()) {
                    const label = getButtonText(button);
                    const controls = getButtonControls(button);
                    const labelledBy = getButtonLabelledBy(button);
                    const isYoastButton = label.includes('yoast seo') ||
                        controls.includes('yoast') ||
                        labelledBy.includes('yoast');

                    if (!isYoastButton) continue;

                    return {
                        key: button,
                        isOpen: () => button.getAttribute('aria-expanded') === 'true',
                        setOpen: (shouldOpen) => {
                            if (!button.disabled) {
                                button.click();
                            }
                        },
                        bind: () => {
                            const observer = new MutationObserver(() => {
                                writePreference(button.getAttribute('aria-expanded') === 'true');
                            });

                            observer.observe(button, {
                                attributes: true,
                                attributeFilter: ['aria-expanded'],
                            });
                        },
                    };
                }

                return null;
            };

            const getTarget = () => getBlockEditorTarget() || getClassicTarget();

            const syncTarget = () => {
                moveYoastToBottom();

                const target = getTarget();
                if (!target) return;

                if (!boundTargets.has(target.key)) {
                    boundTargets.add(target.key);
                    target.bind();
                }

                const preference = readPreference();
                const desiredOpen = preference === null ? defaultOpen : preference;
                const isOpen = target.isOpen();

                if (isOpen !== desiredOpen) {
                    target.setOpen(desiredOpen);
                    return;
                }

                if (preference === null) {
                    writePreference(desiredOpen);
                }
            };

            const start = () => {
                syncTarget();

                const observer = new MutationObserver(() => {
                    window.requestAnimationFrame(syncTarget);
                });

                if (document.body instanceof HTMLBodyElement) {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true,
                    });
                }

                window.setTimeout(syncTarget, 300);
                window.setTimeout(syncTarget, 1000);
                window.setTimeout(syncTarget, 2000);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, {
                    once: true
                });
            } else {
                start();
            }
        })();
    </script>
<?php
}

function meza_render_post_panel_label_sync_script(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || (string) ($screen->base ?? '') !== 'post') {
        return;
    }

    $post_type = (string) ($screen->post_type ?? '');
    $panel_labels = meza_get_post_edit_panel_admin_label_map($post_type);
    if (empty($panel_labels)) {
        return;
    }

    $replacements = [];

    if (isset($panel_labels['postimagediv'])) {
        $replacements[] = [
            'from' => ['featured image'],
            'to' => (string) $panel_labels['postimagediv'],
        ];
    }

    if (isset($panel_labels['postexcerpt'])) {
        $replacements[] = [
            'from' => ['excerpt', 'summary', 'subhead'],
            'to' => (string) $panel_labels['postexcerpt'],
        ];
    }

    if (isset($panel_labels['slugdiv'])) {
        $replacements[] = [
            'from' => ['slug'],
            'to' => (string) $panel_labels['slugdiv'],
        ];
    }

    if (empty($replacements)) {
        return;
    }

?>
    <script id="meza-post-panel-label-sync">
        (() => {
            const replacements = <?php echo wp_json_encode($replacements); ?>;
            const scopeSelectors = [
                '.edit-post-sidebar',
                '.editor-sidebar',
                '.interface-complementary-area',
                '.preferences-modal',
                '.edit-post-preferences-modal',
                '.components-modal__frame',
            ];
            const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const isElement = (value) => value instanceof HTMLElement;
            const shouldReplaceText = (nodeValue, replacement) => replacement.from.includes(normalize(nodeValue));

            const syncScope = (scope) => {
                if (!isElement(scope)) return;

                const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
                let node = walker.nextNode();

                while (node) {
                    const parent = node.parentElement;
                    if (parent && !parent.closest('[contenteditable="true"], .block-editor-block-list__layout, .editor-styles-wrapper')) {
                        const text = normalize(node.nodeValue);

                        for (const replacement of replacements) {
                            if (!shouldReplaceText(text, replacement)) continue;
                            node.nodeValue = replacement.to;
                            break;
                        }
                    }

                    node = walker.nextNode();
                }
            };

            const syncLabels = () => {
                scopeSelectors.forEach((selector) => {
                    document.querySelectorAll(selector).forEach(syncScope);
                });
            };

            const start = () => {
                syncLabels();

                const observer = new MutationObserver(() => {
                    window.requestAnimationFrame(syncLabels);
                });

                if (document.body instanceof HTMLBodyElement) {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true,
                    });
                }

                window.setTimeout(syncLabels, 300);
                window.setTimeout(syncLabels, 1000);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, {
                    once: true
                });
            } else {
                start();
            }
        })();
    </script>
<?php
}

/**
 * Form editor UX: when ACF field groups include a duplicate "Slug" field,
 * hide it so editors use the core permalink/slug UI under the title.
 */
add_filter('acf/prepare_field', function ($field) {
    if (!is_admin()) return $field;
    if (!($field instanceof ArrayAccess) && !is_array($field)) return $field;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) return $field;
    if ((string) ($screen->post_type ?? '') !== 'form') return $field;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $field;

    $name = strtolower((string) ($field['name'] ?? ''));
    $label = strtolower(trim(wp_strip_all_tags((string) ($field['label'] ?? ''))));

    $is_slug_name = in_array($name, ['slug', 'form_slug', 'post_slug'], true);
    $is_slug_label = in_array($label, ['slug', 'form slug', 'post slug'], true);

    if ($is_slug_name || $is_slug_label) {
        return false;
    }

    return $field;
}, 20, 1);
