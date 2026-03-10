<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=form',
        'Form Submission Log',
        'Submissions',
        'manage_options',
        'mzf-submissions',
        'mzf_render_submission_log_admin_page'
    );
});

if (!function_exists('mzf_bool_to_label')) {
    function mzf_bool_to_label(string $value): string
    {
        return ($value === '1') ? 'Yes' : 'No';
    }
}

if (!function_exists('mzf_render_submission_log_admin_page')) {
    function mzf_render_submission_log_admin_page(): void
    {
        if (!current_user_can('manage_options')) return;

        $export_url = wp_nonce_url(
            add_query_arg(['action' => 'mzf_export_submissions_csv'], admin_url('admin-post.php')),
            'mzf_export_submissions_csv'
        );

        echo '<div class="wrap">';
        echo '<h1>Form Submission Log</h1>';
        echo '<p>Review recent form submissions and delivery status.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($export_url) . '">Export CSV</a></p>';

        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $query = new WP_Query([
            'post_type' => 'mzf_submission',
            'post_status' => 'private',
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => 50,
            'paged' => $paged,
            'no_found_rows' => false,
        ]);

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Date/Time</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Phone</th><th>Page</th><th>Form</th><th>Admin</th><th>Status</th></tr></thead><tbody>';

        if (!$query->have_posts()) {
            echo '<tr><td colspan="9"><em>No submissions logged yet.</em></td></tr>';
        } else {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $first_name = (string) get_post_meta($id, '_mzf_first_name', true);
                $last_name = (string) get_post_meta($id, '_mzf_last_name', true);
                $form_slug = (string) get_post_meta($id, '_mzf_form_slug', true);
                $email = (string) get_post_meta($id, '_mzf_email', true);
                $phone = (string) get_post_meta($id, '_mzf_phone', true);
                $page_id = (int) get_post_meta($id, '_mzf_page_id', true);
                $page_link = ($page_id > 0) ? get_permalink($page_id) : '';
                $page_title = ($page_id > 0) ? get_the_title($page_id) : '';
                $form_post_id = (int) get_post_meta($id, '_mzf_form_post_id', true);
                if ($form_post_id <= 0 && $form_slug !== '') {
                    $form_post = get_page_by_path($form_slug, OBJECT, 'form');
                    if ($form_post instanceof WP_Post) $form_post_id = (int) $form_post->ID;
                }
                $form_edit_link = ($form_post_id > 0) ? get_edit_post_link($form_post_id) : '';
                $form_label = ($form_post_id > 0) ? ((string) get_the_title($form_post_id) ?: $form_slug) : $form_slug;
                $admin_to = (string) get_post_meta($id, '_mzf_admin_to', true);
                $raw_status = (string) get_post_meta($id, '_mzf_delivery_status', true);
                $status = ($raw_status === 'success') ? 'Success' : 'Failure';

                echo '<tr>';
                echo '<td>' . esc_html(get_the_date('Y-m-d g:i:s A')) . '</td>';
                echo '<td>' . esc_html($first_name) . '</td>';
                echo '<td>' . esc_html($last_name) . '</td>';
                echo '<td>';
                if ($email !== '' && is_email($email)) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo ($email !== '') ? esc_html($email) : '&mdash;';
                }
                echo '</td>';
                echo '<td>';
                $tel = preg_replace('/[^\d\+]/', '', (string) $phone);
                if ($phone !== '' && $tel !== '') {
                    echo '<a href="tel:' . esc_attr($tel) . '">' . esc_html($phone) . '</a>';
                } else {
                    echo ($phone !== '') ? esc_html($phone) : '&mdash;';
                }
                echo '</td>';
                echo '<td>';
                if ($page_link !== '') {
                    $link_text = ($page_title !== '') ? $page_title : $page_link;
                    echo '<a href="' . esc_url($page_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link_text) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                echo '<td>';
                if ($form_edit_link !== '') {
                    echo '<a href="' . esc_url($form_edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($form_label !== '' ? $form_label : 'Form') . '</a>';
                } else {
                    echo ($form_slug !== '') ? '<code>' . esc_html($form_slug) . '</code>' : '&mdash;';
                }
                echo '</td>';
                echo '<td>' . esc_html($admin_to !== '' ? $admin_to : '—') . '</td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }
        echo '</tbody></table>';

        if ((int) $query->max_num_pages > 1) {
            $base_url = add_query_arg('page', 'mzf-submissions', admin_url('edit.php?post_type=form'));
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin-top:12px;">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%', $base_url),
                'format' => '',
                'current' => $paged,
                'total' => (int) $query->max_num_pages,
            ]);
            echo '</div></div>';
        }

        echo '</div>';
    }
}

add_action('admin_post_mzf_export_submissions_csv', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }
    check_admin_referer('mzf_export_submissions_csv');

    $q = new WP_Query([
        'post_type' => 'mzf_submission',
        'post_status' => 'private',
        'orderby' => 'date',
        'order' => 'DESC',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ]);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mzf-submissions-' . gmdate('Ymd-His') . '.csv"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        wp_die('Failed to open export stream.');
    }

    fputcsv($out, [
        'Date/Time',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Page URL',
        'Form Name',
        'Form Edit URL',
        'Admin',
        'Status',
    ]);

    foreach ((array) $q->posts as $id) {
        $id = (int) $id;
        $entry = get_post($id);
        if (!($entry instanceof WP_Post)) continue;

        $first_name = (string) get_post_meta($id, '_mzf_first_name', true);
        $last_name = (string) get_post_meta($id, '_mzf_last_name', true);
        $email = (string) get_post_meta($id, '_mzf_email', true);
        $phone = (string) get_post_meta($id, '_mzf_phone', true);
        $page_id = (int) get_post_meta($id, '_mzf_page_id', true);
        $page_url = ($page_id > 0) ? (string) get_permalink($page_id) : '';
        $form_post_id = (int) get_post_meta($id, '_mzf_form_post_id', true);
        $form_name = ($form_post_id > 0) ? ((string) get_the_title($form_post_id)) : (string) get_post_meta($id, '_mzf_form_slug', true);
        $form_edit_url = ($form_post_id > 0) ? (string) get_edit_post_link($form_post_id) : '';
        $admin_to = (string) get_post_meta($id, '_mzf_admin_to', true);
        $raw_status = (string) get_post_meta($id, '_mzf_delivery_status', true);
        $status = ($raw_status === 'success') ? 'Success' : 'Failure';

        $date_time = get_date_from_gmt((string) $entry->post_date_gmt, 'Y-m-d g:i:s A');

        fputcsv($out, [
            $date_time,
            $first_name,
            $last_name,
            $email,
            $phone,
            $page_url,
            $form_name,
            $form_edit_url,
            $admin_to,
            $status,
        ]);
    }

    fclose($out);
    exit;
});
