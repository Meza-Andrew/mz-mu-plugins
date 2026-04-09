<?php

/** ================================
 *  ADMIN LIST ACTIONS
 *  ================================ */

function meza_get_post_type_singular_label($post): string
{
    $post_obj = null;
    if ($post instanceof WP_Post) $post_obj = $post;
    if (is_numeric($post) && (int) $post > 0) $post_obj = get_post((int) $post);
    if (!($post_obj instanceof WP_Post)) return 'Post';

    $post_type_obj = get_post_type_object((string) $post_obj->post_type);
    if (is_object($post_type_obj) && isset($post_type_obj->labels->singular_name)) {
        $label = trim((string) $post_type_obj->labels->singular_name);
        if ($label !== '') return $label;
    }

    $fallback = trim(str_replace(['-', '_'], ' ', (string) $post_obj->post_type));
    return $fallback !== '' ? ucwords($fallback) : 'Post';
}

function meza_get_view_post_label($post): string
{
    return sprintf(__('View %s'), meza_get_post_type_singular_label($post));
}

function meza_get_preview_post_label($post): string
{
    return sprintf(__('Preview %s'), meza_get_post_type_singular_label($post));
}

function meza_strip_post_type_from_action_label(string $label, $post = null): string
{
    $normalized = trim(wp_strip_all_tags($label));
    if ($normalized === '') return $normalized;

    $candidates = [];

    if ($post instanceof WP_Post) {
        $post_type_obj = get_post_type_object((string) $post->post_type);
        if (is_object($post_type_obj) && isset($post_type_obj->labels)) {
            $singular = trim((string) ($post_type_obj->labels->singular_name ?? ''));
            $name = trim((string) ($post_type_obj->labels->name ?? ''));
            if ($singular !== '') $candidates[] = $singular;
            if ($name !== '') $candidates[] = $name;
        }

        $slug_label = trim(str_replace(['-', '_'], ' ', (string) $post->post_type));
        if ($slug_label !== '') $candidates[] = ucwords($slug_label);
    }

    $candidates = array_values(array_unique(array_filter($candidates, static fn($candidate) => is_string($candidate) && trim($candidate) !== '')));

    foreach ($candidates as $candidate) {
        $updated = preg_replace('/\s+' . preg_quote($candidate, '/') . '$/i', '', $normalized);
        if ($updated === null) continue;

        $updated = trim(preg_replace('/\s+/', ' ', $updated) ?? $updated);
        if ($updated !== '' && $updated !== $normalized) return $updated;
    }

    return $normalized;
}

function meza_update_admin_action_link(string $html, $post = null, string $label = ''): string
{
    if (trim($html) === '') return $html;

    return preg_replace_callback('/<a\b([^>]*)>(.*?)<\/a>/is', static function ($matches) use ($label, $post) {
        $attrs = (string) ($matches[1] ?? '');
        $text = (string) ($matches[2] ?? '');

        $href = '';
        if (preg_match('/\bhref\s*=\s*([\'"])(.*?)\1/i', $attrs, $href_matches)) {
            $href = html_entity_decode((string) ($href_matches[2] ?? ''), ENT_QUOTES, 'UTF-8');
        }

        $text_plain = strtolower(trim(wp_strip_all_tags($text)));
        $is_edit_or_view = (
            str_contains($href, 'post.php?')
            || str_contains($href, 'action=edit')
            || str_contains($text_plain, 'edit')
            || str_contains($text_plain, 'view')
            || str_contains($text_plain, 'preview')
        );

        if ($is_edit_or_view) {
            if (!preg_match('/\btarget\s*=/i', $attrs)) {
                $attrs .= ' target="_blank"';
            }
            if (!preg_match('/\brel\s*=/i', $attrs)) {
                $attrs .= ' rel="noopener noreferrer"';
            }
        } else {
            $attrs = preg_replace('/\s*\btarget\s*=\s*([\'"]).*?\1/i', '', $attrs) ?? $attrs;
            $attrs = preg_replace('/\s*\brel\s*=\s*([\'"]).*?\1/i', '', $attrs) ?? $attrs;
        }

        $new_label = $label !== '' ? $label : meza_strip_post_type_from_action_label($text, $post);
        if ($new_label !== '') $text = esc_html($new_label);
        return '<a' . $attrs . '>' . $text . '</a>';
    }, $html, 1) ?? $html;
}

function meza_get_title_permalink_display_text(WP_Post $post): string
{
    if (!meza_post_has_permalink((int) $post->ID)) return '';

    $url = (string) get_permalink((int) $post->ID);
    if ($url === '') return '';

    return meza_get_admin_link_column_display_text($url);
}

function meza_get_copy_url_action_link(WP_Post $post): string
{
    if (!meza_post_has_permalink((int) $post->ID)) return '';

    $url = (string) get_permalink((int) $post->ID);
    if ($url === '') return '';

    $display = meza_get_title_permalink_display_text($post);
    if ($display === '') return '';

    $page_template_html = meza_post_type_has_permalink((string) $post->post_type)
        ? meza_get_page_template_title_link((int) $post->ID)
        : '';

    return '<a href="#" class="mz-copy-link" data-copy-text="' . esc_attr($url) . '" data-permalink-url="' . esc_attr($url) . '" data-permalink-display="' . esc_attr($display) . '" data-page-template-html="' . esc_attr($page_template_html) . '">' . esc_html__('Copy URL') . '</a>';
}

function meza_is_duplicate_row_action($key, $action): bool
{
    $key = strtolower(trim((string) $key));
    if (in_array($key, ['duplicate', 'duplicate_post'], true)) return true;
    if (!is_string($action)) return false;

    $normalized_action = strtolower($action);
    return str_contains($normalized_action, 'duplicate');
}

function meza_remove_quick_edit_action(array $actions, $post = null): array
{
    if (isset($actions['inline hide-if-no-js'])) unset($actions['inline hide-if-no-js']);
    if (isset($actions['inline'])) unset($actions['inline']);

    if ($post instanceof WP_Post && $post->post_type === 'product') {
        if (isset($actions['duplicate_post'])) unset($actions['duplicate_post']);

        foreach ($actions as $key => $action) {
            if (!is_string($action)) continue;
            if (str_contains($action, 'class="m4c-duplicate-post"') || str_contains($action, "class='m4c-duplicate-post'")) {
                unset($actions[$key]);
            }
        }
    }

    foreach ($actions as $key => $action) {
        if (!is_string($action)) continue;
        $label = '';
        if (in_array((string) $key, ['trash', 'delete'], true)) {
            $label = __('Delete');
        } elseif ((string) $key === 'edit') {
            $label = __('Edit');
        } elseif ((string) $key === 'view') {
            $label = __('View');
        }

        $actions[$key] = meza_update_admin_action_link($action, $post, $label);
    }

    if (!($post instanceof WP_Post)) {
        return $actions;
    }

    $copy_url_action = meza_get_copy_url_action_link($post);
    if ($copy_url_action !== '') {
        $actions['meza_copy_url'] = $copy_url_action;
    }

    $ordered = [];
    foreach (['edit', 'trash', 'delete'] as $key) {
        if (isset($actions[$key])) {
            $ordered[$key] = $actions[$key];
            unset($actions[$key]);
        }
    }

    foreach ($actions as $key => $action) {
        if (!meza_is_duplicate_row_action($key, $action)) continue;
        $ordered[$key] = meza_update_admin_action_link((string) $action, $post, __('Duplicate'));
        unset($actions[$key]);
    }

    foreach (['view', 'preview'] as $key) {
        if (isset($actions[$key])) {
            $ordered[$key] = $actions[$key];
            unset($actions[$key]);
        }
    }

    if (isset($actions['meza_copy_url'])) {
        $ordered['meza_copy_url'] = $actions['meza_copy_url'];
        unset($actions['meza_copy_url']);
    }

    foreach ($actions as $key => $action) {
        $ordered[$key] = $action;
    }

    return $ordered;
}

add_filter('post_row_actions', 'meza_remove_quick_edit_action', 1000, 2);
add_filter('page_row_actions', 'meza_remove_quick_edit_action', 1000, 2);

add_filter('user_row_actions', function (array $actions, WP_User $user): array {
    if (!function_exists('meza_is_site_manager_user') || !meza_is_site_manager_user(wp_get_current_user())) {
        return $actions;
    }

    unset($actions['view']);

    foreach ($actions as $key => $action) {
        $normalized_key = strtolower(trim((string) $key));
        $normalized_action = strtolower(trim(wp_strip_all_tags((string) $action)));

        if ($normalized_key === 'view' || $normalized_action === 'view') {
            unset($actions[$key]);
        }
    }

    return $actions;
}, 1000, 2);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') {
        return;
    }

    $screen_id = (string) ($screen->id ?? '');
    $post_type = (string) ($screen->post_type ?? '');
    if ($screen_id === '' || $post_type === '') {
        return;
    }

    add_filter("bulk_actions-{$screen_id}", function ($actions) use ($post_type) {
        if (!is_array($actions)) {
            return $actions;
        }

        $post_type_object = get_post_type_object($post_type);
        $singular = '';
        $plural = '';

        if ($post_type_object instanceof WP_Post_Type) {
            $singular = trim((string) ($post_type_object->labels->singular_name ?? ''));
            $plural = trim((string) ($post_type_object->labels->name ?? ''));
        }

        foreach ($actions as $key => $label) {
            if (!is_string($label)) {
                continue;
            }

            $normalized_key = strtolower(trim((string) $key));
            $normalized_label = strtolower(trim(wp_strip_all_tags($label)));
            if (
                !str_contains($normalized_key, 'duplicate')
                && !str_contains($normalized_label, 'duplicate')
            ) {
                continue;
            }

            $updated = meza_strip_post_type_from_action_label($label, get_post_type_object($post_type) instanceof WP_Post_Type ? (object) ['post_type' => $post_type] : null);

            if ($updated === $label || $updated === '') {
                $updated = $label;
                foreach (array_filter([$singular, $plural]) as $candidate) {
                    $candidate = trim((string) $candidate);
                    if ($candidate === '') continue;
                    $pattern = '/\s+' . preg_quote($candidate, '/') . 's?$/i';
                    $next = preg_replace($pattern, '', $updated);
                    if ($next !== null && trim($next) !== '' && trim($next) !== trim($updated)) {
                        $updated = trim(preg_replace('/\s+/', ' ', $next) ?? $next);
                        break;
                    }
                }
            }

            $actions[$key] = ($updated !== '') ? $updated : __('Duplicate');
        }

        return $actions;
    }, 1000);
}, 1000);

add_filter('post_updated_messages', function (array $messages): array {
    global $post;
    if (!($post instanceof WP_Post)) return $messages;

    $post_type = (string) $post->post_type;
    if ($post_type === '' || !isset($messages[$post_type]) || !is_array($messages[$post_type])) return $messages;
    if (meza_is_acf_admin_post_type($post_type)) return $messages;

    $view_label = meza_get_view_post_label($post);
    $preview_label = meza_get_preview_post_label($post);

    foreach ($messages[$post_type] as $index => $message) {
        if (!is_string($message) || $message === '') continue;

        $messages[$post_type][$index] = preg_replace_callback('/<a\b([^>]*)>(.*?)<\/a>/is', static function ($matches) use ($view_label, $preview_label) {
            $attrs = (string) ($matches[1] ?? '');
            $text_html = (string) ($matches[2] ?? '');
            $text_plain = strtolower(trim(wp_strip_all_tags($text_html)));
            $label = str_contains($text_plain, 'preview') ? $preview_label : $view_label;

            if (!preg_match('/\btarget\s*=/i', $attrs)) {
                $attrs .= ' target="_blank"';
            }
            if (!preg_match('/\brel\s*=/i', $attrs)) {
                $attrs .= ' rel="noopener noreferrer"';
            }

            return '<a' . $attrs . '>' . esc_html($label) . '</a>';
        }, $message) ?? $message;
    }

    return $messages;
}, 1000);

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $view_node = $wp_admin_bar->get_node('view');
    if (!is_object($view_node)) return;

    $post = get_post();
    if (!($post instanceof WP_Post)) return;

    $meta = is_array($view_node->meta ?? null) ? $view_node->meta : [];
    $meta['target'] = '_blank';

    $rel = trim((string) ($meta['rel'] ?? ''));
    if ($rel === '') {
        $meta['rel'] = 'noopener noreferrer';
    } else {
        if (!preg_match('/\bnoopener\b/i', $rel)) $rel .= ' noopener';
        if (!preg_match('/\bnoreferrer\b/i', $rel)) $rel .= ' noreferrer';
        $meta['rel'] = trim($rel);
    }

    $wp_admin_bar->add_node([
        'id' => (string) $view_node->id,
        'parent' => $view_node->parent ?? false,
        'title' => esc_html(meza_get_view_post_label($post)),
        'href' => $view_node->href ?? false,
        'group' => !empty($view_node->group),
        'meta' => $meta,
    ]);
}, 100001);
