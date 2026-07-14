<?php
/**
 * Plugin Name: PPM Bulk CPT Importer
 * Plugin URI: https://bringinghomebacon.com
 * Description: Internal PPM tool for bulk creating and updating CPT pages via CSV (URL-based images only).
 * Version: 1.0.16
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
/* BRANDED ADMIN ASSETS                                                       */
/* -------------------------------------------------------------------------- */

add_action('admin_enqueue_scripts', function ($hook_suffix) {
    if ($hook_suffix !== 'toplevel_page_ppm-bulk-import') {
        return;
    }

    $asset_base = plugin_dir_url(__FILE__) . 'assets/';

    $css = <<<CSS
@font-face {
    font-family: 'PPM Neue Haas Text';
    src: url('{$asset_base}fonts/Neue-Haas-Grotesk-Text-Pro.woff2') format('woff2');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}
@font-face {
    font-family: 'PPM Neue Haas Text';
    src: url('{$asset_base}fonts/Neue-Haas-Grotesk-Text-Pro-Medium.woff2') format('woff2');
    font-weight: 600;
    font-style: normal;
    font-display: swap;
}
@font-face {
    font-family: 'PPM Neue Haas Display';
    src: url('{$asset_base}fonts/NeueHaasDisplay-Bold.woff2') format('woff2');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}
@font-face {
    font-family: 'PPM Neue Haas Display';
    src: url('{$asset_base}fonts/NeueHaasDisplay-Black.woff2') format('woff2');
    font-weight: 900;
    font-style: normal;
    font-display: swap;
}

body.toplevel_page_ppm-bulk-import {
    background: #ebeef9;
}
body.toplevel_page_ppm-bulk-import #wpcontent {
    padding-left: 0;
}
.ppm-admin-page,
.ppm-admin-page * {
    box-sizing: border-box;
}
.ppm-admin-page {
    --ppm-cta: #5339dd;
    --ppm-cta-dark: #432cc0;
    --ppm-purple: #522b82;
    --ppm-navy: #2c3d88;
    --ppm-deep: #1b214f;
    --ppm-text: #303030;
    --ppm-border: #cfcfe4;
    --ppm-bg: #ebeef9;
    --ppm-card: #ffffff;
    margin: 0;
    min-height: calc(100vh - 32px);
    color: var(--ppm-text);
    font-family: 'PPM Neue Haas Text', Arial, sans-serif;
}
.ppm-admin-hero {
    position: relative;
    overflow: hidden;
    padding: 34px 40px 38px;
    color: #fff;
    background: linear-gradient(120deg, #522b82 0%, #3c328e 48%, #2c3d88 100%);
}
.ppm-admin-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    opacity: .13;
    background-image: radial-gradient(circle at 20% 30%, #fff 0 1px, transparent 1.5px);
    background-size: 20px 20px;
    pointer-events: none;
}
.ppm-admin-hero-inner {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    max-width: 1240px;
}
.ppm-admin-brand {
    display: flex;
    align-items: center;
    gap: 24px;
}
.ppm-admin-logo {
    width: 230px;
    max-width: 34vw;
    height: auto;
    display: block;
}
.ppm-admin-heading h1 {
    margin: 0 0 7px;
    padding: 0;
    color: #fff;
    font: 900 38px/1.05 'PPM Neue Haas Display', Arial, sans-serif;
    letter-spacing: -.02em;
}
.ppm-admin-heading p {
    margin: 0;
    max-width: 620px;
    color: rgba(255,255,255,.86);
    font-size: 16px;
    line-height: 1.45;
}
.ppm-admin-version {
    flex: 0 0 auto;
    padding: 8px 12px;
    border: 1px solid rgba(255,255,255,.28);
    border-radius: 999px;
    background: rgba(255,255,255,.1);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
}
.ppm-admin-content {
    max-width: 1240px;
    padding: 34px 40px 48px;
}
.ppm-admin-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    align-items: stretch;
}
.ppm-admin-card {
    display: flex;
    flex-direction: column;
    min-height: 430px;
    padding: 30px;
    border: 1px solid var(--ppm-border);
    border-radius: 14px;
    background: var(--ppm-card);
    box-shadow: 0 10px 28px rgba(27,33,79,.08);
}
.ppm-admin-card-icon {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    margin-bottom: 18px;
    border-radius: 12px;
    background: #e4e1ff;
    color: var(--ppm-cta);
    font-size: 23px;
}
.ppm-admin-card h2 {
    margin: 0 0 9px;
    color: #2f052d;
    font: 700 27px/1.1 'PPM Neue Haas Display', Arial, sans-serif;
}
.ppm-admin-card > p {
    margin: 0 0 24px;
    color: #606074;
    font-size: 15px;
    line-height: 1.5;
}
.ppm-admin-form {
    display: flex;
    flex: 1;
    flex-direction: column;
}
.ppm-field {
    margin: 0 0 20px;
}
.ppm-field label {
    display: block;
    margin: 0 0 8px;
    color: #2f052d;
    font-size: 14px;
    font-weight: 600;
}
.ppm-admin-page input[type='text'],
.ppm-admin-page input[type='file'],
.ppm-admin-page select {
    width: 100%;
    min-height: 46px;
    margin: 0;
    padding: 9px 12px;
    border: 1px solid var(--ppm-border);
    border-radius: 8px;
    background: #fff;
    color: var(--ppm-text);
    font-family: inherit;
    font-size: 15px;
    box-shadow: none;
}
.ppm-admin-page input[type='text']:focus,
.ppm-admin-page input[type='file']:focus,
.ppm-admin-page select:focus {
    border-color: var(--ppm-cta);
    box-shadow: 0 0 0 2px rgba(83,57,221,.14);
    outline: none;
}
.ppm-admin-page input[type='file'] {
    padding: 8px;
}
.ppm-admin-page input[type='file']::file-selector-button {
    margin-right: 12px;
    padding: 8px 13px;
    border: 0;
    border-radius: 6px;
    background: #ebeef9;
    color: #2f052d;
    font-family: inherit;
    font-weight: 600;
    cursor: pointer;
}
.ppm-description {
    margin: -7px 0 20px;
    color: #747488;
    font-size: 13px;
    line-height: 1.45;
}
.ppm-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: auto;
    padding-top: 10px;
}
.ppm-admin-page .button.ppm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 9px 18px;
    border: 1px solid var(--ppm-cta);
    border-radius: 8px;
    background: #fff;
    color: #2f052d;
    font-family: inherit;
    font-size: 15px;
    font-weight: 600;
    line-height: 1;
    text-decoration: none;
    box-shadow: none;
    transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
}
.ppm-admin-page .button.ppm-btn:hover,
.ppm-admin-page .button.ppm-btn:focus {
    border-color: var(--ppm-cta);
    background: var(--ppm-cta);
    color: #fff;
    transform: translateY(-1px);
}
.ppm-admin-page .button.ppm-btn-primary {
    border-color: var(--ppm-cta);
    background: var(--ppm-cta);
    color: #fff;
}
.ppm-admin-page .button.ppm-btn-primary:hover,
.ppm-admin-page .button.ppm-btn-primary:focus {
    border-color: var(--ppm-cta-dark);
    background: var(--ppm-cta-dark);
}
.ppm-admin-page .notice,
.ppm-admin-page .updated,
.ppm-admin-page .error {
    margin: 24px 0 0;
}
.ppm-import-preview {
    margin-top: 28px;
    padding: 28px;
    border: 1px solid var(--ppm-border);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(27,33,79,.06);
}
.ppm-import-preview h2 {
    margin: 0 0 18px;
    color: #2f052d;
    font: 700 25px/1.15 'PPM Neue Haas Display', Arial, sans-serif;
}
.ppm-import-preview .widefat {
    border-color: var(--ppm-border);
}
.ppm-import-preview .widefat thead th {
    background: #ebeef9;
    color: #2f052d;
    font-weight: 600;
}
@media (max-width: 900px) {
    .ppm-admin-actions { grid-template-columns: 1fr; }
    .ppm-admin-hero-inner { align-items: flex-start; flex-direction: column; }
    .ppm-admin-card { min-height: auto; }
}
@media (max-width: 600px) {
    .ppm-admin-hero, .ppm-admin-content { padding-left: 20px; padding-right: 20px; }
    .ppm-admin-brand { align-items: flex-start; flex-direction: column; gap: 18px; }
    .ppm-admin-logo { max-width: 240px; }
    .ppm-admin-heading h1 { font-size: 31px; }
}
CSS;

    wp_register_style('ppm-bulk-import-admin', false, [], '1.0.16');
    wp_enqueue_style('ppm-bulk-import-admin');
    wp_add_inline_style('ppm-bulk-import-admin', $css);
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
    $logo_url = plugins_url('assets/ppm-wordmark.svg', __FILE__);
    ?>
    <div class="wrap ppm-admin-page">
        <header class="ppm-admin-hero">
            <div class="ppm-admin-hero-inner">
                <div class="ppm-admin-brand">
                    <img class="ppm-admin-logo" src="<?php echo esc_url($logo_url); ?>" alt="Purple Pig Marketing">
                    <div class="ppm-admin-heading">
                        <h1>Bulk CPT Importer</h1>
                        <p>Create, update, export, and standardize custom post type pages from one reliable CSV workflow.</p>
                    </div>
                </div>
                <span class="ppm-admin-version">v1.0.16</span>
            </div>
        </header>

        <main class="ppm-admin-content">
            <div class="ppm-admin-actions">
                <section class="ppm-admin-card">
                    <div class="ppm-admin-card-icon"><span class="dashicons dashicons-database-import"></span></div>
                    <h2>Import CPT Pages</h2>
                    <p>Preview your CSV mapping, then create new pages or update existing pages by post slug.</p>

                    <form class="ppm-admin-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('ppm_bulk_import'); ?>

                        <div class="ppm-field">
                            <label for="ppm-import-cpt-slug">CPT Slug</label>
                            <input id="ppm-import-cpt-slug" type="text" name="cpt_slug" required value="<?php echo esc_attr($_POST['cpt_slug'] ?? ''); ?>" placeholder="Example: service-areas">
                        </div>

                        <div class="ppm-field">
                            <label for="ppm-import-csv">CSV File</label>
                            <input id="ppm-import-csv" type="file" name="csv_file" accept=".csv" required>
                        </div>

                        <div class="ppm-form-actions">
                            <input type="submit" name="preview_csv" class="button ppm-btn ppm-btn-primary" value="Preview Import">
                            <a class="button ppm-btn" href="?page=ppm-bulk-import&amp;download_template=1">Download CSV Template</a>
                        </div>
                    </form>
                </section>

                <section class="ppm-admin-card">
                    <div class="ppm-admin-card-icon"><span class="dashicons dashicons-database-export"></span></div>
                    <h2>Export Existing Pages</h2>
                    <p>Export every page in a CPT using the exact CSV structure expected by the importer.</p>

                    <form class="ppm-admin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ppm_export_cpt_csv'); ?>
                        <input type="hidden" name="action" value="ppm_export_cpt_csv">

                        <div class="ppm-field">
                            <label for="ppm-export-cpt-slug">CPT Slug</label>
                            <input id="ppm-export-cpt-slug" type="text" name="cpt_slug" required placeholder="Example: service-areas">
                        </div>

                        <div class="ppm-field">
                            <label for="ppm-field-profile">Source Field Structure</label>
                            <select id="ppm-field-profile" name="field_profile">
                                <option value="auto" selected>Auto-detect per page (recommended)</option>
                                <option value="standard">Standard PPM importer fields only</option>
                                <option value="legacy">Legacy heading/section fields only</option>
                            </select>
                        </div>

                        <p class="ppm-description">Auto-detect uses the standardized importer field when populated, then falls back to the legacy field used by older city pages.</p>

                        <div class="ppm-form-actions">
                            <input type="submit" class="button ppm-btn" value="Export Existing Pages">
                        </div>
                    </form>
                </section>
            </div>
    <?php

    if (isset($_POST['preview_csv']) && check_admin_referer('ppm_bulk_import')) {
        echo '<div class="ppm-import-preview">';
        ppm_preview_import($_FILES['csv_file'], sanitize_text_field($_POST['cpt_slug']));
        echo '</div>';
    }

    if (isset($_POST['run_import']) && check_admin_referer('ppm_bulk_import')) {
        echo '<div class="ppm-import-preview">';
        ppm_run_import(
            sanitize_text_field($_POST['import_token']),
            sanitize_text_field($_POST['cpt_slug'])
        );
        echo '</div>';
    }
    ?>
        </main>
    </div>
    <?php
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
