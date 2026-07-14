<?php
/**
 * Plugin Name: PPM Bulk CPT Importer
 * Plugin URI: https://bringinghomebacon.com
 * Description: Internal PPM tool for bulk creating and updating CPT pages via CSV (URL-based images only).
 * Version: 1.0.15
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
/* EXPORT HANDLER                                                             */
/* -------------------------------------------------------------------------- */

add_action('admin_post_ppm_export_cpt_csv', 'ppm_export_cpt_csv');

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

        <hr style="margin: 30px 0;">

        <h2>Export Existing CPT Pages</h2>
        <p>
            Exports every page in the selected CPT using the exact CSV column structure
            expected by this plugin's importer.
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ppm_export_cpt_csv'); ?>
            <input type="hidden" name="action" value="ppm_export_cpt_csv">

            <p>
                <label><strong>CPT Slug</strong></label><br>
                <input type="text" name="cpt_slug" required>
            </p>

            <p>
                <label><strong>Source Field Structure</strong></label><br>
                <select name="field_profile">
                    <option value="auto" selected>Auto-detect per page (recommended)</option>
                    <option value="standard">Standard PPM importer fields only</option>
                    <option value="legacy">Legacy heading/section fields only</option>
                </select>
            </p>

            <p class="description">
                Auto-detect uses the standard importer field when it contains a value,
                then falls back to the legacy field used by older city pages.
            </p>

            <p>
                <input type="submit" class="button button-secondary" value="Export Existing Pages">
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
        'post_title','post_slug','post_status','city_name',
        'section_1_title','section_1_sub_title','section_1_paragraph','section_1_button_text','section_1_button_url','section_1_image',
        'section_2_title','section_2_sub_title','section_2_paragraph','section_2_button_text','section_2_button_url','section_2_image',
        'section_3_title','section_3_sub_title','section_3_paragraph','section_3_button_text','section_3_button_url','section_3_image',
        'section_4_title','section_4_sub_title','section_4_paragraph','section_4_button_text','section_4_button_url','section_4_image',
        'city_featured_image',
        'yoast_title','yoast_meta_description','yoast_focus_keyphrase'
    ]);
    exit;
}

/* -------------------------------------------------------------------------- */
/* PREVIEW                                                                    */
/* -------------------------------------------------------------------------- */

function ppm_parse_csv_file($file_path) {
    $handle = fopen($file_path, 'r');

    if ($handle === false) {
        return new WP_Error('ppm_csv_open_failed', 'Could not open the uploaded CSV file.');
    }

    $header = fgetcsv($handle);

    if ($header === false || !is_array($header)) {
        fclose($handle);
        return new WP_Error('ppm_csv_missing_header', 'The CSV file does not contain a valid header row.');
    }

    // Normalize every header, including removal of the UTF-8 BOM from post_title.
    $header = array_map('ppm_prepare_csv_value', $header);

    $rows = [];

    // fgetcsv must read directly from the stream so quoted WYSIWYG content may
    // safely contain commas, quotes, and line breaks without splitting a page
    // into multiple broken rows.
    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null] || $row === []) {
            continue;
        }

        $rows[] = $row;
    }

    fclose($handle);

    return compact('header', 'rows');
}

function ppm_preview_import($file, $cpt_slug) {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        echo "<div class='error'><p>CSV upload failed.</p></div>";
        return;
    }

    $payload = ppm_parse_csv_file($file['tmp_name']);

    if (is_wp_error($payload)) {
        echo "<div class='error'><p>" . esc_html($payload->get_error_message()) . "</p></div>";
        return;
    }

    $header = $payload['header'];
    $rows   = $payload['rows'];

    if (!in_array('post_slug', $header, true)) {
        echo "<div class='error'><p>The CSV is missing the required post_slug column.</p></div>";
        return;
    }

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

        // Keep the first matching field name from the location-matched groups.
        // This prevents a duplicate field name in an unrelated group from
        // replacing the correct key later in the scan.
        if (
            !empty($field['name']) &&
            !empty($field['key']) &&
            empty($map[$field['name']])
        ) {
            $map[$field['name']] = $field['key'];
        }

        if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            ppm_collect_acf_fields_recursive($field['sub_fields'], $map);
        }
    }
}

function ppm_get_acf_field_key_map($post_id, $cpt_slug) {
    static $cache = [];

    $cache_key = $cpt_slug . ':' . (int) $post_id;

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $map = [];

    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        $cache[$cache_key] = $map;
        return $map;
    }

    // First use only groups whose location rules match this actual post.
    $groups = acf_get_field_groups(['post_id' => $post_id]);

    // Fallback for admin/import contexts where ACF cannot resolve post_id.
    if (!is_array($groups) || empty($groups)) {
        $groups = acf_get_field_groups(['post_type' => $cpt_slug]);
    }

    if (is_array($groups)) {
        foreach ($groups as $group) {
            if (isset($group['active']) && !$group['active']) {
                continue;
            }

            $fields = acf_get_fields($group);
            ppm_collect_acf_fields_recursive($fields, $map);
        }
    }

    $cache[$cache_key] = $map;
    return $map;
}

function ppm_update_acf_or_meta($post_id, $cpt_slug, $key, $value) {
    $value = ppm_prepare_csv_value($value);
    $field_key = '';

    if (function_exists('get_field_object')) {
        // Ask ACF to resolve the field in the context of the actual post first.
        $field_object = get_field_object($key, $post_id, false, false);

        if (
            is_array($field_object) &&
            !empty($field_object['key']) &&
            !empty($field_object['name']) &&
            $field_object['name'] === $key
        ) {
            $field_key = $field_object['key'];
        }
    }

    if ($field_key === '') {
        $field_key_map = ppm_get_acf_field_key_map($post_id, $cpt_slug);
        $field_key = !empty($field_key_map[$key]) ? $field_key_map[$key] : '';
    }

    // Save the actual value directly under the field name.
    update_post_meta($post_id, $key, $value);

    // Save ACF's hidden field-name-to-field-key reference explicitly. This is
    // what lets ACF and Elementor consistently resolve imported values.
    if ($field_key !== '') {
        update_post_meta($post_id, '_' . $key, $field_key);
    }
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
/* EXPORT HELPERS                                                             */
/* -------------------------------------------------------------------------- */

function ppm_export_get_field_value($post_id, $field_name) {
    if (function_exists('get_field')) {
        $value = get_field($field_name, $post_id, false);

        if ($value !== null && $value !== false && $value !== '') {
            return $value;
        }
    }

    return get_post_meta($post_id, $field_name, true);
}

function ppm_export_value_is_populated($value) {
    if (is_array($value)) {
        return !empty($value);
    }

    return $value !== null && $value !== false && $value !== '';
}

function ppm_export_image_url($value) {
    if (is_array($value)) {
        if (!empty($value['url'])) {
            return esc_url_raw($value['url']);
        }

        if (!empty($value['ID'])) {
            $url = wp_get_attachment_url((int) $value['ID']);
            return $url ? esc_url_raw($url) : '';
        }

        if (!empty($value['id'])) {
            $url = wp_get_attachment_url((int) $value['id']);
            return $url ? esc_url_raw($url) : '';
        }

        return '';
    }

    if (is_numeric($value)) {
        $url = wp_get_attachment_url((int) $value);
        return $url ? esc_url_raw($url) : '';
    }

    return is_string($value) ? esc_url_raw($value) : '';
}

function ppm_export_pick_field($post_id, $standard_field, $legacy_field, $profile = 'auto', $is_image = false) {
    $profile = in_array($profile, ['auto', 'standard', 'legacy'], true)
        ? $profile
        : 'auto';

    if ($profile === 'standard') {
        $value = ppm_export_get_field_value($post_id, $standard_field);
    } elseif ($profile === 'legacy') {
        $value = ppm_export_get_field_value($post_id, $legacy_field);
    } else {
        $standard_value = ppm_export_get_field_value($post_id, $standard_field);

        if (ppm_export_value_is_populated($standard_value)) {
            $value = $standard_value;
        } else {
            $value = ppm_export_get_field_value($post_id, $legacy_field);
        }
    }

    return $is_image ? ppm_export_image_url($value) : $value;
}

/* -------------------------------------------------------------------------- */
/* EXPORT                                                                     */
/* -------------------------------------------------------------------------- */

function ppm_export_cpt_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to export this data.');
    }

    check_admin_referer('ppm_export_cpt_csv');

    $cpt_slug = isset($_POST['cpt_slug'])
        ? sanitize_key(wp_unslash($_POST['cpt_slug']))
        : '';

    $profile = isset($_POST['field_profile'])
        ? sanitize_key(wp_unslash($_POST['field_profile']))
        : 'auto';

    if (!in_array($profile, ['auto', 'standard', 'legacy'], true)) {
        $profile = 'auto';
    }

    if (!$cpt_slug || !post_type_exists($cpt_slug)) {
        wp_die('Invalid CPT slug.');
    }

    $posts = get_posts([
        'post_type'        => $cpt_slug,
        'post_status'      => ['publish', 'draft', 'pending', 'private', 'future'],
        'posts_per_page'   => -1,
        'orderby'          => 'title',
        'order'            => 'ASC',
        'suppress_filters' => false,
    ]);

    $headers = [
        'post_title','post_slug','post_status','city_name',
        'section_1_title','section_1_sub_title','section_1_paragraph','section_1_button_text','section_1_button_url','section_1_image',
        'section_2_title','section_2_sub_title','section_2_paragraph','section_2_button_text','section_2_button_url','section_2_image',
        'section_3_title','section_3_sub_title','section_3_paragraph','section_3_button_text','section_3_button_url','section_3_image',
        'section_4_title','section_4_sub_title','section_4_paragraph','section_4_button_text','section_4_button_url','section_4_image',
        'city_featured_image',
        'yoast_title','yoast_meta_description','yoast_focus_keyphrase'
    ];

    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = sprintf(
        'ppm-cpt-export-%s-%s.csv',
        sanitize_file_name($cpt_slug),
        gmdate('Y-m-d')
    );

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if ($output === false) {
        wp_die('Could not open the CSV output stream.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);

    foreach ($posts as $post) {
        $row = [
            $post->post_title,
            $post->post_name,
            $post->post_status,
            ppm_export_get_field_value($post->ID, 'city_name'),

            ppm_export_pick_field($post->ID, 'section_1_title', 'heading_1', $profile),
            ppm_export_get_field_value($post->ID, 'section_1_sub_title'),
            ppm_export_pick_field($post->ID, 'section_1_paragraph', 'section_1', $profile),
            ppm_export_get_field_value($post->ID, 'section_1_button_text'),
            ppm_export_get_field_value($post->ID, 'section_1_button_url'),
            ppm_export_pick_field($post->ID, 'section_1_image', 'image_1', $profile, true),

            ppm_export_pick_field($post->ID, 'section_2_title', 'heading_2', $profile),
            ppm_export_get_field_value($post->ID, 'section_2_sub_title'),
            ppm_export_pick_field($post->ID, 'section_2_paragraph', 'section_2', $profile),
            ppm_export_get_field_value($post->ID, 'section_2_button_text'),
            ppm_export_get_field_value($post->ID, 'section_2_button_url'),
            ppm_export_pick_field($post->ID, 'section_2_image', 'image_2', $profile, true),

            ppm_export_pick_field($post->ID, 'section_3_title', 'heading_3', $profile),
            ppm_export_get_field_value($post->ID, 'section_3_sub_title'),
            ppm_export_pick_field($post->ID, 'section_3_paragraph', 'section_3', $profile),
            ppm_export_get_field_value($post->ID, 'section_3_button_text'),
            ppm_export_get_field_value($post->ID, 'section_3_button_url'),
            ppm_export_pick_field($post->ID, 'section_3_image', 'image_3', $profile, true),

            ppm_export_pick_field($post->ID, 'section_4_title', 'heading_4', $profile),
            ppm_export_get_field_value($post->ID, 'section_4_sub_title'),
            ppm_export_pick_field($post->ID, 'section_4_paragraph', 'section_4', $profile),
            ppm_export_get_field_value($post->ID, 'section_4_button_text'),
            ppm_export_get_field_value($post->ID, 'section_4_button_url'),
            ppm_export_pick_field($post->ID, 'section_4_image', 'image_4', $profile, true),

            ppm_export_image_url(ppm_export_get_field_value($post->ID, 'city_featured_image')),

            get_post_meta($post->ID, '_yoast_wpseo_title', true),
            get_post_meta($post->ID, '_yoast_wpseo_metadesc', true),
            get_post_meta($post->ID, '_yoast_wpseo_focuskw', true),
        ];

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

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
            'post_name'   => $post_slug,
            'post_status' => $post_status,
        ];

        // Never erase an existing title because of a malformed or missing CSV
        // value. New posts still require a title.
        if ($post_title !== '') {
            $post_args['post_title'] = $post_title;
        } elseif (!$existing) {
            $skipped++;
            continue;
        }

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
