<?php

/**
 * Client-only compatibility shims for third-party plugins that are not part of
 * the normal `mz-starter` baseline.
 *
 * Keep these fixes narrowly scoped, easy to remove, and targeted at the exact
 * plugin/version behavior we need to smooth over on a given project.
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

if (!function_exists('mz_plugin_compat_enabled')) {
    function mz_plugin_compat_enabled(): bool
    {
        return !(defined('MZ_DISABLE_PLUGIN_COMPAT') && MZ_DISABLE_PLUGIN_COMPAT);
    }
}

if (!mz_plugin_compat_enabled()) {
    return;
}

if (!function_exists('mz_plugin_compat_normalize_path')) {
    function mz_plugin_compat_normalize_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('mz_plugin_compat_get_eol')) {
    function mz_plugin_compat_get_eol(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }
}

if (!function_exists('mz_plugin_compat_resolve_source_patch_target')) {
    function mz_plugin_compat_resolve_source_patch_target(string $relative_file): string
    {
        $relative_file = ltrim($relative_file, '/\\');
        if ($relative_file === '') {
            return '';
        }

        if (str_starts_with($relative_file, 'wp-content/')) {
            return rtrim(ABSPATH, '/\\') . '/' . $relative_file;
        }

        $plugin_dir = defined('WP_PLUGIN_DIR')
            ? rtrim((string) WP_PLUGIN_DIR, '/\\')
            : rtrim(ABSPATH, '/\\') . '/wp-content/plugins';

        return $plugin_dir . '/' . $relative_file;
    }
}

if (!function_exists('mz_plugin_compat_get_source_patch_rules')) {
    function mz_plugin_compat_get_source_patch_rules(): array
    {
        $rules = [
            'divi_booster_dynamic_package_slug' => [
                'file' => '/divi-booster/core/wtfplugin_1_0.class.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'var $package_slug;')) return $contents;

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*var \$error_handler;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1] . "\tvar \$package_slug;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'divi_booster_contact_form_email_blacklist_runtime_properties' => [
                'file' => '/divi-booster/core/features/contactFormEmailBlacklist/dbdb-contactform-emailblacklist.php',
                'apply' => static function (string $contents): string {
                    if (
                        str_contains($contents, 'protected $current_blacklist = array();')
                        && str_contains($contents, 'protected $current_contact_form = 0;')
                    ) {
                        return $contents;
                    }

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*protected \$email_blacklist_key = \'dbdb_email_blacklist\';\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1]
                                . "\tprotected \$current_blacklist = array();" . $eol
                                . "\tprotected \$current_contact_form = 0;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'monsterinsights_site_health_tracking_property' => [
                'file' => '/google-analytics-for-wordpress/lite/includes/admin/wp-site-health.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'private $is_tracking;')) {
                        return $contents;
                    }

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*private \$ecommerce;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1] . "\tprivate \$is_tracking;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'divi_booster_early_textdomain_constants' => [
                'file' => '/divi-booster/divi-booster.php',
                'apply' => static function (string $contents): string {
                    $updated = preg_replace(
                        "/define\\('BOOSTER_NAME',\\s*__\\('Divi Booster',\\s*BOOSTER_SLUG\\)\\);/",
                        "define('BOOSTER_NAME', 'Divi Booster');",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'divi_booster_early_textdomain_config' => [
                'file' => '/divi-booster/divi-booster.php',
                'apply' => static function (string $contents): string {
                    $updated = preg_replace(
                        "/'plugin_name'\\s*=>\\s*__\\('Divi Booster',\\s*'divi-booster'\\),/",
                        "'plugin_name' => 'Divi Booster',",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'divi_theme_metabox_post_id_guard' => [
                'file' => '/wp-content/themes/Divi/functions.php',
                'apply' => static function (string $contents): string {
                    $replace = '$enabled = ( is_object( $post ) && isset( $post->ID ) ) ? et_builder_enabled_for_post( $post->ID ) : et_builder_enabled_for_post_type( $post_type );';
                    if (str_contains($contents, $replace)) {
                        return $contents;
                    }

                    $search = '$enabled = $post ? et_builder_enabled_for_post( $post->ID ) : et_builder_enabled_for_post_type( $post_type );';
                    $updated = str_replace($search, $replace, $contents, $count);

                    return $count > 0 ? $updated : $contents;
                },
            ],
            'divi_builder_metabox_post_id_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/functions.php',
                'apply' => static function (string $contents): string {
                    $updated = $contents;

                    $first_replace = 'if ( et_builder_bfb_enabled() && ( ! is_object( $post ) || ! isset( $post->ID ) || ! et_pb_is_pagebuilder_used( $post->ID ) ) ) {';
                    if (!str_contains($updated, $first_replace)) {
                        $updated = str_replace(
                            'if ( et_builder_bfb_enabled() && ! et_pb_is_pagebuilder_used( $post->ID ) ) {',
                            $first_replace,
                            $updated
                        );
                    }

                    $second_replace = 'if ( ! $add && is_object( $post ) && isset( $post->ID ) && et_builder_enabled_for_post( $post->ID ) ) {';
                    if (!str_contains($updated, $second_replace)) {
                        $updated = str_replace(
                            'if ( ! $add && ! empty( $post ) && et_builder_enabled_for_post( $post->ID ) ) {',
                            $second_replace,
                            $updated
                        );
                    }

                    return $updated;
                },
            ],
            'divi_theme_builder_request_archive_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/frontend-builder/theme-builder/ThemeBuilderRequest.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'if ( $object instanceof WP_Term && isset( $object->taxonomy ) ) {')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
		if ( is_category() || is_tag() || is_tax() ) {
			return new self( self::TYPE_TERM, $object->taxonomy, $id );
		}

		if ( is_post_type_archive() ) {
			return new self( self::TYPE_POST_TYPE_ARCHIVE, $object->name, $id );
		}
PHP;

                    $replace = <<<'PHP'
		if ( is_category() || is_tag() || is_tax() ) {
			if ( $object instanceof WP_Term && isset( $object->taxonomy ) ) {
				return new self( self::TYPE_TERM, $object->taxonomy, $id );
			}

			if ( $object instanceof WP_Post_Type && isset( $object->name ) ) {
				return new self( self::TYPE_POST_TYPE_ARCHIVE, $object->name, $id );
			}
		}

		if ( is_post_type_archive() ) {
			$post_type = $object instanceof WP_Post_Type && isset( $object->name ) ? $object->name : get_query_var( 'post_type' );

			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}

			return new self( self::TYPE_POST_TYPE_ARCHIVE, (string) $post_type, $id );
		}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'divi_dynamic_assets_taxonomy_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/feature/dynamic-assets/class-dynamic-assets.php',
                'apply' => static function (string $contents): string {
                    if (
                        str_contains($contents, '$queried_object   = get_queried_object();')
                        && str_contains($contents, '$queried instanceof WP_Term ? sanitize_key( $queried->taxonomy ) : \'\'')
                    ) {
                        return $contents;
                    }

                    $updated = str_replace(
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$this->_object_id = intval( get_queried_object()->term_id );",
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$queried_object   = get_queried_object();\n\t\t\t\$this->_object_id = \$queried_object instanceof WP_Term ? intval( \$queried_object->term_id ) : -1;",
                        $contents
                    );

                    $updated = str_replace(
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$queried     = get_queried_object();\n\t\t\t\$taxonomy    = sanitize_key( \$queried->taxonomy );\n\t\t\t\$folder_name = \"taxonomy/{\$taxonomy}/\" . \$this->_object_id;",
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$queried  = get_queried_object();\n\t\t\t\$taxonomy = \$queried instanceof WP_Term ? sanitize_key( \$queried->taxonomy ) : '';\n\n\t\t\tif ( '' === \$taxonomy ) {\n\t\t\t\treturn 'archive';\n\t\t\t}\n\n\t\t\t\$folder_name = \"taxonomy/{\$taxonomy}/\" . \$this->_object_id;",
                        $updated
                    );

                    return $updated;
                },
            ],
            'divi_postbased_term_id_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/module/type/PostBased.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object = get_queried_object();')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
					if ( $is_category || $is_tag || $is_tax ) {
						$term_ids[] = get_queried_object()->term_id;
					}
PHP;

                    $replace = <<<'PHP'
					if ( $is_category || $is_tag || $is_tax ) {
						$queried_object = get_queried_object();

						if ( $queried_object instanceof WP_Term ) {
							$term_ids[] = (int) $queried_object->term_id;
						}
					}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'toolset_content_template_taxonomy_guard' => [
                'file' => '/wp-views/vendor/toolset/toolset-common/user-editors/medium/screen/content-template/frontend.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$term instanceof WP_Term')) {
                        return $contents;
                    }

                    $updated = preg_replace(
                        "/if\\( \\$term && array_key_exists\\( 'views_template_loop_' \\. \\$term->taxonomy, \\$wpv_options \\) \\) \\{/",
                        "if (\n\t\t\t\t\t\$term instanceof WP_Term\n\t\t\t\t\t&& array_key_exists( 'views_template_loop_' . \$term->taxonomy, \$wpv_options )\n\t\t\t\t) {",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'toolset_archive_title_taxonomy_guard' => [
                'file' => '/wp-views/embedded/inc/functions-core-embedded.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object = get_queried_object();')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
    } elseif ( is_tax() ) {
        $tax = get_taxonomy( get_queried_object()->taxonomy );
        /* translators: 1: Taxonomy singular name, 2: Current taxonomy term */
        $title = sprintf( __( '%1$s: %2$s' ), $tax->labels->singular_name, single_term_title( '', false ) );
    } else {
PHP;

                    $replace = <<<'PHP'
    } elseif ( is_tax() ) {
        $queried_object = get_queried_object();
        $tax = $queried_object instanceof WP_Term ? get_taxonomy( $queried_object->taxonomy ) : false;

        if ( $tax && isset( $tax->labels->singular_name ) ) {
            /* translators: 1: Taxonomy singular name, 2: Current taxonomy term */
            $title = sprintf( __( '%1$s: %2$s' ), $tax->labels->singular_name, single_term_title( '', false ) );
        } else {
            $title = __( 'Archives' );
        }
    } else {
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'toolset_views_template_archive_term_guard' => [
                'file' => '/wp-views/embedded/inc/views-templates/wpv-template.class.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$kind = $term instanceof WP_Term ? \'archive-\' . $term->taxonomy : \'archive\';')) {
                        return $contents;
                    }

                    $updated = str_replace(
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$kind = 'archive-' . \$term->taxonomy;",
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$kind = \$term instanceof WP_Term ? 'archive-' . \$term->taxonomy : 'archive';",
                        $contents
                    );

                    $updated = str_replace(
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$archive_loop = 'views_template_loop_' . \$term->taxonomy;",
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$archive_loop = \$term instanceof WP_Term ? 'views_template_loop_' . \$term->taxonomy : null;",
                        $updated
                    );

                    $updated = str_replace(
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\tif( \$term ) {",
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\tif( \$term instanceof WP_Term ) {",
                        $updated
                    );

                    return $updated;
                },
            ],
            'toolset_parent_filter_term_guard' => [
                'file' => '/wp-views/embedded/inc/filters/wpv-filter-parent-embedded.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$parent_id = $queried_object instanceof WP_Term ? $queried_object->term_id : $parent_id;')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
						$queried_object = get_queried_object();
						$parent_id = $queried_object->term_id;
PHP;

                    $replace = <<<'PHP'
						$queried_object = get_queried_object();
						$parent_id = $queried_object instanceof WP_Term ? $queried_object->term_id : $parent_id;
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_indexable_hierarchy_term_guard' => [
                'file' => '/wordpress-seo/src/builders/indexable-hierarchy-builder.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, "if ( ! ( \$term instanceof \\WP_Term ) ) {")) {
                        return $contents;
                    }

                    $updated = preg_replace(
                        "/private function get_term_parents\\( \\$term \\) \\{\n\t\t/",
                        "private function get_term_parents( \$term ) {\n\t\tif ( ! ( \$term instanceof \\\\WP_Term ) ) {\n\t\t\treturn [];\n\t\t}\n\n\t\t",
                        $contents,
                        1
                    );

                    if (!is_string($updated)) {
                        return $contents;
                    }

                    $updated = preg_replace(
                        "/\t\t\t\\$term      = \\\\get_term\\( \\$term->parent, \\$tax \\ );\n\t\t\t\\$parents\\[\\] = \\$term;/",
                        "\t\t\t\$term      = \\\\get_term( \$term->parent, \$tax );\n\n\t\t\tif ( ! ( \$term instanceof \\\\WP_Term ) ) {\n\t\t\t\tbreak;\n\t\t\t}\n\n\t\t\t\$parents[] = \$term;",
                        $updated,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'yoast_current_page_term_id_guard' => [
                'file' => '/wordpress-seo/src/helpers/current-page-helper.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object instanceof \WP_Term')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
		if ( $wp_query->is_tax() || $wp_query->is_tag() || $wp_query->is_category() ) {
			$queried_object = $wp_query->get_queried_object();
			if ( $queried_object && ! \is_wp_error( $queried_object ) ) {
				return $queried_object->term_id;
			}
		}
PHP;

                    $replace = <<<'PHP'
		if ( $wp_query->is_tax() || $wp_query->is_tag() || $wp_query->is_category() ) {
			$queried_object = $wp_query->get_queried_object();
			if ( $queried_object instanceof \WP_Term && ! \is_wp_error( $queried_object ) ) {
				return $queried_object->term_id;
			}
		}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_term_archive_presentation_guard' => [
                'file' => '/wordpress-seo/src/presentations/indexable-term-archive-presentation.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object = \get_queried_object();')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
	public function generate_source() {
		if ( ! empty( $this->model->object_id ) || \get_queried_object() === null ) {
			return \get_term( $this->model->object_id, $this->model->object_sub_type );
		}

		return \get_term( \get_queried_object()->term_id, \get_queried_object()->taxonomy );
	}
PHP;

                    $replace = <<<'PHP'
	public function generate_source() {
		$queried_object = \get_queried_object();

		if ( ! empty( $this->model->object_id ) || ! ( $queried_object instanceof \WP_Term ) ) {
			return \get_term( $this->model->object_id, $this->model->object_sub_type );
		}

		return \get_term( $queried_object->term_id, $queried_object->taxonomy );
	}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'gravity_wiz_submit_access_dynamic_args' => [
                'file' => '/gw-submit-to-access/gw-submit-to-access.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'private $_args = array();')) return $contents;

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*private static \$instance = null;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1] . "\tprivate \$_args = array();" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'gravity_wiz_submit_access_null_post_guard' => [
                'file' => '/gw-submit-to-access/gw-submit-to-access.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, "if ( ! \$post || ! isset( \$post->ID ) ) {\n\t\t\treturn \$content;\n\t\t}")) {
                        return $contents;
                    }

                    $updated = preg_replace(
                        '/public function maybe_hide_the_content\( \$content \) \{\R\t\tglobal \$post;\R/',
                        "public function maybe_hide_the_content( \$content ) {\n\t\tglobal \$post;\n\n\t\tif ( ! \$post || ! isset( \$post->ID ) ) {\n\t\t\treturn \$content;\n\t\t}\n",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'custom_widget_area_declared_properties' => [
                'file' => '/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
                'apply' => static function (string $contents): string {
                    if (
                        str_contains($contents, 'private $view;')
                        && str_contains($contents, 'private $menuView;')
                        && str_contains($contents, 'private $table_name;')
                    ) {
                        return $contents;
                    }

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*private \$version;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1]
                                . "\tprivate \$view;" . $eol
                                . "\tprivate \$menuView;" . $eol
                                . "\tprivate \$table_name;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'toolset_divi_view_render_signature' => [
                'file' => '/wp-views.deactivated/vendor/toolset/divi/includes/modules/View/View.php',
                'apply' => static function (string $contents): string {
                    $updated = preg_replace(
                        '/public function render\(\s*\$attrs,\s*\$content\s*=\s*null,\s*\$render_slug\s*\)/',
                        'public function render( $attrs, $content = null, $render_slug = \'\' )',
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'aios_googlebot_prefixes_guard' => [
                'file' => '/all-in-one-wp-security-and-firewall/classes/wp-security-utility.php',
                'apply' => static function (string $contents): string {
                    $search = 'foreach ($json_array[\'prefixes\'] as $prefix) {';
                    $replace = 'foreach ((is_array($json_array[\'prefixes\'] ?? null) ? $json_array[\'prefixes\'] : []) as $prefix) {';

                    if (str_contains($contents, $replace)) {
                        return $contents;
                    }

                    $updated = str_replace($search, $replace, $contents, $count);

                    if ($count < 1) {
                        return $contents;
                    }

                    return $updated;
                },
            ],
        ];

        return apply_filters('mz_plugin_compat_source_patch_rules', $rules);
    }
}

if (!function_exists('mz_plugin_compat_apply_source_patches')) {
    function mz_plugin_compat_apply_source_patches(): void
    {
        foreach (mz_plugin_compat_get_source_patch_rules() as $rule) {
            if (!is_array($rule)) continue;

            $relative_file = (string) ($rule['file'] ?? '');
            $apply = $rule['apply'] ?? null;
            if ($relative_file === '' || !is_callable($apply)) continue;

            $target = mz_plugin_compat_resolve_source_patch_target($relative_file);
            if (!is_file($target) || !is_readable($target) || !is_writable($target)) continue;

            $contents = file_get_contents($target);
            if (!is_string($contents) || $contents === '') continue;

            $updated = $apply($contents);
            if (!is_string($updated) || $updated === $contents) continue;

            file_put_contents($target, $updated);
        }
    }
}

mz_plugin_compat_apply_source_patches();

add_filter('cron_schedules', static function (array $schedules): array {
    if (!isset($schedules['fifteen_minutes'])) {
        $schedules['fifteen_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'Every Fifteen Minutes',
        ];
    }

    return $schedules;
});

if (!function_exists('mz_plugin_compat_get_deprecated_notice_rules')) {
    function mz_plugin_compat_get_deprecated_notice_rules(): array
    {
        $rules = [
            'divi_booster_dynamic_package_slug' => [
                'message' => 'Creation of dynamic property wtfplugin_1_0::$package_slug is deprecated',
                'file' => '/wp-content/plugins/divi-booster/core/wtfplugin_1_0.class.php',
            ],
            'divi_booster_contact_form_email_blacklist_current_blacklist' => [
                'message' => 'Creation of dynamic property DBDB_ContactForm_EmailBlacklist::$current_blacklist is deprecated',
                'file' => '/wp-content/plugins/divi-booster/core/features/contactFormEmailBlacklist/dbdb-contactform-emailblacklist.php',
            ],
            'divi_booster_contact_form_email_blacklist_current_contact_form' => [
                'message' => 'Creation of dynamic property DBDB_ContactForm_EmailBlacklist::$current_contact_form is deprecated',
                'file' => '/wp-content/plugins/divi-booster/core/features/contactFormEmailBlacklist/dbdb-contactform-emailblacklist.php',
            ],
            'monsterinsights_site_health_tracking_property' => [
                'message' => 'Creation of dynamic property MonsterInsights_WP_Site_Health_Lite::$is_tracking is deprecated',
                'file' => '/wp-content/plugins/google-analytics-for-wordpress/lite/includes/admin/wp-site-health.php',
            ],
            'gravity_wiz_submit_access_dynamic_args' => [
                'message' => 'Creation of dynamic property GW_Submit_Access::$_args is deprecated',
                'file' => '/wp-content/plugins/gw-submit-to-access/gw-submit-to-access.php',
            ],
            'custom_widget_area_dynamic_view' => [
                'message' => 'Creation of dynamic property Custom_Widget_Area_Admin::$view is deprecated',
                'file' => '/wp-content/plugins/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
            ],
            'custom_widget_area_dynamic_menu_view' => [
                'message' => 'Creation of dynamic property Custom_Widget_Area_Admin::$menuView is deprecated',
                'file' => '/wp-content/plugins/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
            ],
            'custom_widget_area_dynamic_table_name' => [
                'message' => 'Creation of dynamic property Custom_Widget_Area_Admin::$table_name is deprecated',
                'file' => '/wp-content/plugins/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
            ],
        ];

        return apply_filters('mz_plugin_compat_deprecated_notice_rules', $rules);
    }
}

if (!function_exists('mz_plugin_compat_matches_deprecated_notice')) {
    function mz_plugin_compat_matches_deprecated_notice(string $message, string $file, array $rule): bool
    {
        $expected_message = trim((string) ($rule['message'] ?? ''));
        $expected_file = mz_plugin_compat_normalize_path((string) ($rule['file'] ?? ''));
        $normalized_file = mz_plugin_compat_normalize_path($file);

        if ($expected_message === '' || stripos($message, $expected_message) === false) {
            return false;
        }

        if ($expected_file === '' || stripos($normalized_file, $expected_file) === false) {
            return false;
        }

        return true;
    }
}

if (!function_exists('mz_plugin_compat_is_known_nullable_param_deprecation')) {
    function mz_plugin_compat_is_known_nullable_param_deprecation(string $message, string $file): bool
    {
        $normalized_message = strtolower(trim($message));
        if (
            !str_contains($normalized_message, 'implicitly marking parameter')
            || !str_contains($normalized_message, 'nullable is deprecated')
        ) {
            return false;
        }

        $normalized_file = mz_plugin_compat_normalize_path($file);
        $known_prefixes = [
            '/wp-content/plugins/all-in-one-seo-pack/',
            '/wp-content/plugins/cred-frontend-editor/',
            '/wp-content/plugins/divi-booster/',
            '/wp-content/plugins/the-events-calendar/',
            '/wp-content/plugins/wp-views/',
            '/wp-content/plugins/types.deactivated/',
            '/wp-content/plugins/wp-views.deactivated/',
        ];

        foreach ($known_prefixes as $prefix) {
            if (str_contains($normalized_file, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mz_plugin_compat_should_suppress_deprecated_notice')) {
    function mz_plugin_compat_should_suppress_deprecated_notice(string $message, string $file): bool
    {
        if (mz_plugin_compat_is_known_nullable_param_deprecation($message, $file)) {
            return true;
        }

        foreach (mz_plugin_compat_get_deprecated_notice_rules() as $rule) {
            if (!is_array($rule)) continue;
            if (mz_plugin_compat_matches_deprecated_notice($message, $file, $rule)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mz_plugin_compat_should_suppress_runtime_warning')) {
    function mz_plugin_compat_should_suppress_runtime_warning(string $message, string $file): bool
    {
        $normalized_file = mz_plugin_compat_normalize_path($file);

        if (!str_contains($normalized_file, '/wp-content/plugins/all-in-one-wp-security-and-firewall/classes/wp-security-utility.php')) {
            return false;
        }

        $normalized_message = trim($message);

        return str_contains($normalized_message, 'Undefined array key "prefixes"')
            || str_contains($normalized_message, 'foreach() argument must be of type array|object, null given');
    }
}

if (!function_exists('mz_plugin_compat_register_error_handler')) {
    function mz_plugin_compat_register_error_handler(): void
    {
        static $registered = false;

        if ($registered) return;
        $registered = true;

        $previous_handler = set_error_handler(
            static function ($errno, $errstr, $errfile = '', $errline = 0) use (&$previous_handler) {
                $is_deprecated = ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED);
                $is_warning = in_array($errno, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true);

                if (
                    $is_deprecated
                    && mz_plugin_compat_should_suppress_deprecated_notice((string) $errstr, (string) $errfile)
                ) {
                    return true;
                }

                if (
                    $is_warning
                    && mz_plugin_compat_should_suppress_runtime_warning((string) $errstr, (string) $errfile)
                ) {
                    return true;
                }

                if (is_callable($previous_handler)) {
                    return (bool) call_user_func($previous_handler, $errno, $errstr, $errfile, $errline);
                }

                return false;
            },
            E_DEPRECATED | E_USER_DEPRECATED | E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE
        );
    }
}

mz_plugin_compat_register_error_handler();

add_filter('doing_it_wrong_trigger_error', function ($trigger, $function_name, $message, $version) {
    if (!$trigger) return $trigger;
    if ((string) $function_name !== '_load_textdomain_just_in_time') return $trigger;

    $message = strtolower(strip_tags((string) $message));
    if (strpos($message, 'divi-booster') === false) return $trigger;

    return false;
}, 10, 4);

if (!function_exists('mz_plugin_compat_override_single_event_template_include')) {
    function mz_plugin_compat_override_single_event_template_include($template)
    {
        if (is_admin() || !is_singular('tribe_events')) {
            return $template;
        }

        $override = __DIR__ . '/overrides/plugin-compat/the-events-calendar/single-tribe_events.php';

        return is_file($override) ? $override : $template;
    }
}

if (!function_exists('mz_plugin_compat_disable_divi_theme_builder_for_single_events')) {
    function mz_plugin_compat_disable_divi_theme_builder_for_single_events($layouts)
    {
        if (is_admin() || !is_singular('tribe_events') || !is_array($layouts)) {
            return $layouts;
        }

        $layout_types = [];

        if (defined('ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE')) {
            $layout_types[] = ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE;
        }

        if (defined('ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE')) {
            $layout_types[] = ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE;
        }

        if (defined('ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE')) {
            $layout_types[] = ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE;
        }

        foreach ($layout_types as $layout_type) {
            if (!isset($layouts[$layout_type]) || !is_array($layouts[$layout_type])) {
                continue;
            }

            $layouts[$layout_type]['id'] = 0;
            $layouts[$layout_type]['enabled'] = false;
            $layouts[$layout_type]['override'] = false;
        }

        return $layouts;
    }
}

if (!function_exists('mz_plugin_compat_strip_divi_builder_body_classes_for_single_events')) {
    function mz_plugin_compat_strip_divi_builder_body_classes_for_single_events(array $classes): array
    {
        if (is_admin() || !is_singular('tribe_events')) {
            return $classes;
        }

        $remove = [
            'et-db',
            'et-tb-has-template',
            'et-tb-has-footer',
            'et-tb-has-header',
            'et_pb_pagebuilder_layout',
        ];

        return array_values(array_diff($classes, $remove));
    }
}

if (!function_exists('mz_plugin_compat_dequeue_divi_builder_assets_for_single_events')) {
    function mz_plugin_compat_dequeue_divi_builder_assets_for_single_events(): void
    {
        if (is_admin() || !is_singular('tribe_events')) {
            return;
        }

        $script_handles = [
            'et-builder-cpt-modules-wrapper-js',
            'fitvids-js',
        ];

        foreach ($script_handles as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }

        global $wp_styles;

        if (!($wp_styles instanceof WP_Styles)) {
            return;
        }

        foreach ((array) $wp_styles->queue as $handle) {
            if (strpos((string) $handle, 'et-builder-module-design-') !== 0) {
                continue;
            }

            wp_dequeue_style($handle);
        }
    }
}

add_filter('template_include', 'mz_plugin_compat_override_single_event_template_include', 999);
add_filter('et_theme_builder_template_layouts', 'mz_plugin_compat_disable_divi_theme_builder_for_single_events', 20);
add_filter('body_class', 'mz_plugin_compat_strip_divi_builder_body_classes_for_single_events', 20);
add_action('wp_enqueue_scripts', 'mz_plugin_compat_dequeue_divi_builder_assets_for_single_events', 999);
