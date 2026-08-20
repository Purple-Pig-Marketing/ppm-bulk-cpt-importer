<?php
/**
 * Shared export engine.
 *
 * Used by both the admin screen's download button and the WP-CLI command, so
 * the two can never drift into producing different files. The only difference
 * between them is where the CSV is written: a browser download in one case, a
 * file on the server in the other.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Columns for the generic export, used when no content profile is given.
 *
 * post_path rather than post_slug is the identifier that matters here: pages
 * nest, and two pages under different parents can share a slug. The path is
 * what uniquely identifies a page, and what any future import would match on.
 */
function ppm_inventory_columns($include_content = true) {
    $columns = [
        'post_id',
        'post_type',
        'post_path',
        'post_slug',
        'post_parent_path',
        'post_title',
        'post_status',
        'menu_order',
        'page_template',
        'builder',
        'permalink',
        'modified',
        'yoast_title',
        'yoast_meta_description',
        'yoast_focus_keyphrase',
        'yoast_noindex',
    ];

    if ($include_content) {
        $columns[] = 'word_count';
        $columns[] = 'content_plain';
    }

    return $columns;
}

function ppm_inventory_row($post, $include_content = true) {
    $parent_path = $post->post_parent ? ppm_post_path(get_post($post->post_parent)) : '';

    $row = [
        $post->ID,
        $post->post_type,
        ppm_post_path($post),
        $post->post_name,
        $parent_path,
        $post->post_title,
        $post->post_status,
        $post->menu_order,
        get_page_template_slug($post->ID),
        ppm_detect_builder($post),
        get_permalink($post),
        $post->post_modified_gmt,
        get_post_meta($post->ID, '_yoast_wpseo_title', true),
        get_post_meta($post->ID, '_yoast_wpseo_metadesc', true),
        get_post_meta($post->ID, '_yoast_wpseo_focuskw', true),
        get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true) === '1' ? '1' : '',
    ];

    if ($include_content) {
        $content = ppm_plain_content($post);

        $row[] = ppm_text_word_count($content);
        $row[] = $content;
    }

    return $row;
}

/**
 * Full hierarchical path, e.g. services/turf-installation.
 */
function ppm_post_path($post) {
    if (!$post instanceof WP_Post) {
        return '';
    }

    if (is_post_type_hierarchical($post->post_type)) {
        return (string) get_page_uri($post);
    }

    return (string) $post->post_name;
}

/**
 * Which editor built this post, so an audit can tell at a glance which pages
 * are editable as text and which are builder trees.
 */
function ppm_detect_builder($post) {
    if (get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
        return 'elementor';
    }

    if (function_exists('has_blocks') && has_blocks($post->post_content)) {
        return 'block';
    }

    if (trim((string) $post->post_content) === '') {
        return 'empty';
    }

    return 'classic';
}

/**
 * The readable text of a post, whichever editor built it.
 *
 * For an Elementor page this asks Elementor to render its live element tree to
 * plain text. post_content holds only a copy written at save time, so it is
 * stale on anything edited before that behaviour existed and misleading
 * everywhere else — which would quietly under-report the very outdated content
 * an audit exists to find. Falls back to post_content when Elementor is absent
 * or returns nothing.
 */
function ppm_plain_content($post) {
    $is_elementor = get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder';

    if (
        $is_elementor
        && did_action('elementor/loaded')
        && class_exists('\\Elementor\\Plugin')
        && isset(\Elementor\Plugin::$instance->db)
        && method_exists(\Elementor\Plugin::$instance->db, 'get_plain_text')
    ) {
        $text = \Elementor\Plugin::$instance->db->get_plain_text($post->ID);

        if (is_string($text) && trim($text) !== '') {
            return ppm_normalize_text($text);
        }
    }

    return ppm_normalize_text($post->post_content);
}

function ppm_normalize_text($text) {
    $text = (string) $text;

    if ($text === '') {
        return '';
    }

    $text = strip_shortcodes($text);
    $text = wp_strip_all_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // Collapse the whitespace a builder tree leaves behind, while keeping line
    // breaks so the text stays readable inside a spreadsheet cell.
    $text = str_replace("\r\n", "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/ *\n */', "\n", $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
}

function ppm_text_word_count($text) {
    if ($text === '') {
        return 0;
    }

    return count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
}

function ppm_export_statuses() {
    return ['publish', 'draft', 'pending', 'private', 'future'];
}

/**
 * Write a complete CSV export to an open stream.
 *
 * Reads in batches and writes each row straight out rather than building the
 * whole file in memory, so a site with thousands of pages does not exhaust
 * memory or run past a request timeout.
 *
 * Pass a 'profile' to use that profile's declared columns and the same row
 * builder the profile export uses; omit it for the generic inventory columns
 * that suit any post type.
 *
 * @return int Number of rows written.
 */
function ppm_stream_export($handle, $args = []) {
    $args = wp_parse_args($args, [
        'post_type'       => 'page',
        'post_status'     => ppm_export_statuses(),
        'batch'           => 200,
        'profile'         => null,
        'field_profile'   => 'auto',
        'include_content' => true,
        'progress'        => null,
    ]);

    $headers = $args['profile']
        ? ppm_profile_column_names($args['profile'])
        : ppm_inventory_columns($args['include_content']);

    // Excel reads a UTF-8 CSV as the system codepage without this.
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $headers);

    // Reading thousands of posts fills the object cache with entries nothing
    // will read again. Suspending additions keeps memory flat. Note this is
    // deliberately not wp_cache_flush(): on hosting with a shared persistent
    // object cache that would evict the whole site's cache, not just ours.
    $suspend = function_exists('wp_suspend_cache_addition');

    if ($suspend) {
        wp_suspend_cache_addition(true);
    }

    $total = 0;
    $paged = 1;

    while (true) {
        $posts = get_posts([
            'post_type'        => $args['post_type'],
            'post_status'      => $args['post_status'],
            'posts_per_page'   => $args['batch'],
            'paged'            => $paged,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ]);

        if (empty($posts)) {
            break;
        }

        foreach ($posts as $post) {
            $row = $args['profile']
                ? ppm_export_build_row($post, $args['profile']['columns'], $args['field_profile'])
                : ppm_inventory_row($post, $args['include_content']);

            fputcsv($handle, $row);
            $total++;
        }

        $paged++;

        if (is_callable($args['progress'])) {
            call_user_func($args['progress'], $total);
        }
    }

    if ($suspend) {
        wp_suspend_cache_addition(false);
    }

    return $total;
}

/**
 * Post types worth offering in the admin export dropdown.
 *
 * Attachments are excluded: exporting the media library as page content is
 * never what anyone means by an audit.
 */
function ppm_exportable_post_types() {
    $post_types = get_post_types(['public' => true], 'objects');
    $options = [];

    foreach ($post_types as $post_type) {
        if ($post_type->name === 'attachment') {
            continue;
        }

        $options[$post_type->name] = sprintf(
            '%s (%s)',
            $post_type->labels->name ?: $post_type->name,
            $post_type->name
        );
    }

    return $options;
}
