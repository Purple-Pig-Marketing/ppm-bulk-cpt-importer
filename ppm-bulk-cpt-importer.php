<?php
/**
 * Plugin Name: PPM Bulk CPT Importer
 * Plugin URI: https://bringinghomebacon.com
 * Description: Internal PPM tool for bulk creating and updating CPT pages via CSV (URL-based images only).
 * Version: 1.0.12
 * Author: Purple Pig Marketing
 * Author URI: https://bringinghomebacon.com
 * License: Proprietary
 * Update URI: https://github.com/Purple-Pig-Marketing/ppm-bulk-cpt-importer
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------- */
/* SAFE GITHUB UPDATE CHECKER                                                 */
/* -------------------------------------------------------------------------- */

$ppm_update_checker_path = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

if (file_exists($ppm_update_checker_path)) {

    require_once $ppm_update_checker_path;

    if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {

        $ppm_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/Purple-Pig-Marketing/ppm-bulk-cpt-importer/',
            __FILE__,
            'ppm-bulk-cpt-importer'
        );

        $ppm_update_checker->setAuthentication('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');
    }
}

/* -------------------------------------------------------------------------- */
/* CUSTOM PLUGIN ICON                                                         */
/* -------------------------------------------------------------------------- */

add_action('admin_head', function () {

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'plugins') return;

    $icon_url = plugins_url('assets/ppm-plugin-icon.png', __FILE__);
    $plugin_slug = plugin_basename(__FILE__);

    echo "<style>
        tr[data-plugin='{$plugin_slug}'] .plugin-icon img {
            content: url('{$icon_url}');
        }
    </style>";
});

/* -------------------------------------------------------------------------- */
/* ADMIN MENU                                                                 */
/* -------------------------------------------------------------------------- */

add_action('admin_menu', function () {
    add_menu_page(
        'PPM Bulk CPT Import',
        'PPM Import',
        'manage_options',
        'ppm-bulk-import',
        'ppm_bulk_import_page',
        'dashicons-database-import'
    );
});

add_action('admin_init', function () {
    if (
        isset($_GET['page'], $_GET['download_template']) &&
        $_GET['page'] === 'ppm-bulk-import' &&
        current_user_can('manage_options')
    ) {
        ppm_download_template();
        exit;
    }
});

/* -------------------------------------------------------------------------- */
/* MAIN PAGE                                                                  */
/* -------------------------------------------------------------------------- */

function ppm_bulk_import_page() {
    ?>
    <div class="wrap">
        <h1>PPM Bulk CPT Import</h1>

        <p>
            <a class="button button-secondary" href="?page=ppm-bulk-import&download_template=1">
                Download CSV Template
            </a>
        </p>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('ppm_bulk_import'); ?>

            <p>
                <label><strong>CPT Slug</strong></label><br>
                <input type="text" name="cpt_slug" required value="<?php echo esc_attr($_POST['cpt_slug'] ?? ''); ?>">
            </p>

            <p>
                <label><strong>CSV File</strong></label><br>
                <input type="file" name="csv_file" accept=".csv" required>
            </p>

            <p>
                <input type="submit" name="preview_csv" class="button button-primary" value="Preview Import">
            </p>
        </form>
    </div>
    <?php

    if (isset($_POST['preview_csv']) && check_admin_referer('ppm_bulk_import')) {
        ppm_preview_import($_FILES['csv_file'], sanitize_text_field($_POST['cpt_slug']));
    }

    if (isset($_POST['run_import']) && check_admin_referer('ppm_bulk_import')) {
        ppm_run_import(
            sanitize_text_field($_POST['import_token']),
            sanitize_text_field($_POST['cpt_slug'])
        );
    }
}

/* -------------------------------------------------------------------------- */
/* CSV TEMPLATE                                                               */
/* -------------------------------------------------------------------------- */

function ppm_download_template() {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ppm-cpt-import-template.csv"');

    echo implode(',', [
        'post_title','post_slug','post_status',
        'section_1_title','section_1_paragraph','section_1_image',
        'section_2_title','section_2_paragraph','section_2_image',
        'section_3_title','section_3_paragraph','section_3_image',
        'section_4_title','section_4_paragraph','section_4_image',
        'yoast_title','yoast_meta_description','yoast_focus_keyphrase'
    ]);
    exit;
}

/* -------------------------------------------------------------------------- */
/* PREVIEW                                                                    */
/* -------------------------------------------------------------------------- */

function ppm_preview_import($file, $cpt_slug) {
    $lines  = file($file['tmp_name'], FILE_IGNORE_NEW_LINES);
    $rows   = array_map('str_getcsv', $lines);
    $header = array_map('trim', array_shift($rows));

    $token = wp_generate_uuid4();
    set_transient("ppm_import_$token", compact('header','rows'), 1800);
    ?>
    <h2>Confirm Import Mapping</h2>
    <form method="post">
        <?php wp_nonce_field('ppm_bulk_import'); ?>
        <input type="hidden" name="cpt_slug" value="<?php echo esc_attr($cpt_slug); ?>">
        <input type="hidden" name="import_token" value="<?php echo esc_attr($token); ?>">

        <table class="widefat striped">
            <thead><tr><th>CSV Column</th><th>Destination</th></tr></thead>
            <tbody>
                <?php foreach ($header as $col): ?>
                    <tr>
                        <td><?php echo esc_html($col); ?></td>
                        <td><?php echo esc_html(ppm_detect_destination($col)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p><input type="submit" name="run_import" class="button button-primary" value="Run Import"></p>
    </form>
    <?php
}

/* -------------------------------------------------------------------------- */
/* DESTINATION LABELS                                                         */
/* -------------------------------------------------------------------------- */

function ppm_detect_destination($column) {
    if (in_array($column, ['post_title','post_slug','post_status'], true)) return 'WordPress Post Field';
    if (strpos($column, 'yoast_') === 0) return 'Yoast SEO Field';
    if (strpos($column, 'section_') !== false && strpos($column, '_image') !== false) return 'Image URL (string)';
    return 'ACF / Post Meta';
}

/* -------------------------------------------------------------------------- */
/* ACF HELPERS                                                                */
/* -------------------------------------------------------------------------- */

function ppm_prepare_csv_value($value) {
    if (!is_string($value)) {
        return $value;
    }

    // Remove UTF-8 BOM if present
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

    // Trim whitespace/newlines
    $value = trim($value);

    // Strip wrapping quotes only if the whole string is wrapped
    if (strlen($value) >= 2) {
        $first = substr($value, 0, 1);
        $last  = substr($value, -1);

        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
            $value = trim($value);
        }
    }

    return $value;
}

function ppm_collect_acf_fields_recursive($fields, &$map) {
    if (!is_array($fields)) return;

    foreach ($fields as $field) {
        if (!is_array($field)) continue;

        if (!empty($field['name']) && !empty($field['key'])) {
            $map[$field['name']] = $field['key'];
        }

        if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            ppm_collect_acf_fields_recursive($field['sub_fields'], $map);
        }
    }
}

function ppm_get_acf_field_key_map($cpt_slug) {
    static $cache = [];

    if (isset($cache[$cpt_slug])) {
        return $cache[$cpt_slug];
    }

    $map = [];

    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        $cache[$cpt_slug] = $map;
        return $map;
    }

    $groups = acf_get_field_groups(['post_type' => $cpt_slug]);

    if (!is_array($groups) || empty($groups)) {
        $groups = acf_get_field_groups();
    }

    if (is_array($groups)) {
        foreach ($groups as $group) {
            $fields = acf_get_fields($group);
            ppm_collect_acf_fields_recursive($fields, $map);
        }
    }

    $cache[$cpt_slug] = $map;
    return $map;
}

function ppm_update_acf_or_meta($post_id, $cpt_slug, $key, $value) {
    $value = ppm_prepare_csv_value($value);

    if (function_exists('update_field')) {
        $field_key_map = ppm_get_acf_field_key_map($cpt_slug);

        if (!empty($field_key_map[$key])) {
            update_field($field_key_map[$key], $value, $post_id);
            return;
        }

        // Fallback: try by field name
        $updated = update_field($key, $value, $post_id);

        if ($updated !== false) {
            return;
        }
    }

    // Final fallback
    update_post_meta($post_id, $key, $value);
}

/* -------------------------------------------------------------------------- */
/* SHORTCODES                                                                 */
/* -------------------------------------------------------------------------- */

add_shortcode('ppm_acf_image', function ($atts) {
    $atts = shortcode_atts([
        'field' => '',
        'class' => '',
        'alt'   => '',
        'style' => '',
    ], $atts);

    if (!$atts['field']) return '';

    $url = function_exists('get_field')
        ? get_field($atts['field'])
        : get_post_meta(get_the_ID(), $atts['field'], true);

    if (!$url) return '';

    $url   = esc_url($url);
    $alt   = esc_attr($atts['alt']);
    $class = esc_attr($atts['class']);
    $style = esc_attr($atts['style']);

    return "<img src=\"$url\" class=\"$class\" alt=\"$alt\" style=\"$style\">";
});

/* -------------------------------------------------------------------------- */
/* IMPORT                                                                     */
/* -------------------------------------------------------------------------- */

function ppm_run_import($token, $cpt_slug) {

    // Force ACF field groups to initialize in this admin context
    if (function_exists('acf_get_field_groups')) {
        acf_get_field_groups();
    }

    $payload = get_transient("ppm_import_$token");
    delete_transient("ppm_import_$token");

    if (!$payload || empty($payload['rows'])) {
        echo "<div class='error'><p>Import session expired.</p></div>";
        return;
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($payload['rows'] as $row) {
        if (!is_array($row)) {
            $skipped++;
            continue;
        }

        if (count($row) < count($payload['header'])) {
            $row = array_pad($row, count($payload['header']), '');
        }

        $row = array_combine($payload['header'], $row);

        if ($row === false || empty($row['post_slug'])) {
            $skipped++;
            continue;
        }

        $post_slug = ppm_prepare_csv_value($row['post_slug']);
        $post_title = isset($row['post_title']) ? ppm_prepare_csv_value($row['post_title']) : '';
        $post_status = !empty($row['post_status']) ? ppm_prepare_csv_value($row['post_status']) : 'publish';

        $existing = get_page_by_path($post_slug, OBJECT, $cpt_slug);

        $post_args = [
            'post_type'   => $cpt_slug,
            'post_title'  => $post_title,
            'post_name'   => $post_slug,
            'post_status' => $post_status,
        ];

        $post_id = $existing
            ? wp_update_post(array_merge($post_args, ['ID' => $existing->ID]), true)
            : wp_insert_post($post_args, true);

        if (is_wp_error($post_id)) {
            $skipped++;
            continue;
        }

        if ($existing) {
            $updated++;
        } else {
            $created++;
        }

        foreach ($row as $key => $value) {
            if (in_array($key, ['post_title', 'post_slug', 'post_status'], true)) {
                continue;
            }

            $value = ppm_prepare_csv_value($value);

            if ($value === '') {
                continue;
            }

            if ($key === 'yoast_title') {
                update_post_meta($post_id, '_yoast_wpseo_title', $value);
                continue;
            }

            if ($key === 'yoast_meta_description') {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $value);
                continue;
            }

            if ($key === 'yoast_focus_keyphrase') {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $value);
                continue;
            }

            ppm_update_acf_or_meta($post_id, $cpt_slug, $key, $value);
        }
    }

    echo "<div class='updated'><p><strong>Import complete.</strong> Created: {$created} | Updated: {$updated} | Skipped: {$skipped}</p></div>";
}