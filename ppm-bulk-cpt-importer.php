<?php
/**
 * Plugin Name: PPM Bulk CPT Importer
 * Plugin URI: https://bringinghomebacon.com
 * Description: Internal PPM tool for bulk creating and updating CPT pages via CSV (URL-based images only).
 * Version: 1.0.6
 * Author: Purple Pig Marketing
 * Author URI: https://bringinghomebacon.com
 * License: Proprietary
 */

if (!defined('ABSPATH')) exit;

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
/* PREVIEW                                                                   */
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
/* SHORTCODES                                                                 */
/* -------------------------------------------------------------------------- */

add_shortcode('ppm_acf_image', function ($atts) {
    $atts = shortcode_atts([
        'field' => '',
        'class' => '',
        'alt'   => '',
    ], $atts);

    if (!$atts['field']) return '';

    $url = function_exists('get_field')
        ? get_field($atts['field'])
        : get_post_meta(get_the_ID(), $atts['field'], true);

    if (!$url) return '';

    $url = esc_url($url);
    $alt = esc_attr($atts['alt']);
    $class = esc_attr($atts['class']);

    return "<img src=\"$url\" class=\"$class\" alt=\"$alt\">";
});


/* -------------------------------------------------------------------------- */
/* IMPORT (URL-ONLY, NO MEDIA)                                                 */
/* -------------------------------------------------------------------------- */

function ppm_run_import($token, $cpt_slug) {
    $payload = get_transient("ppm_import_$token");
    delete_transient("ppm_import_$token");

    $created = $updated = $skipped = 0;

    foreach ($payload['rows'] as $row) {
        $row = array_combine($payload['header'], $row);
        if (empty($row['post_slug'])) { $skipped++; continue; }

        $existing = get_page_by_path($row['post_slug'], OBJECT, $cpt_slug);

        $post_args = [
            'post_type'   => $cpt_slug,
            'post_title'  => $row['post_title'],
            'post_name'   => $row['post_slug'],
            'post_status' => $row['post_status'] ?: 'publish'
        ];

        $post_id = $existing
            ? wp_update_post(array_merge($post_args, ['ID' => $existing->ID]), true)
            : wp_insert_post($post_args, true);

        if (is_wp_error($post_id)) { $skipped++; continue; }

        $existing ? $updated++ : $created++;

        foreach ($row as $key => $value) {
            if ($value === '' || in_array($key, ['post_title','post_slug','post_status'], true)) continue;

            /* YOAST */
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

            /* EVERYTHING ELSE = STRING (URL OR TEXT) */
            if (function_exists('update_field')) {
                update_field($key, $value, $post_id);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    echo "<div class='updated'><p><strong>Import complete.</strong> Created: $created | Updated: $updated | Skipped: $skipped</p></div>";
}
