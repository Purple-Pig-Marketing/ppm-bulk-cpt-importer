<?php
/**
 * WP-CLI commands.
 *
 * Loaded only under WP-CLI. The point of these is fleet work: the plugin is
 * already installed on every site, so a loop over SSH can run the same command
 * everywhere instead of fifty admin logins.
 *
 * Two WP Engine constraints shaped this file, since that is where nearly every
 * site is hosted:
 *
 *   - Its SSH gateway swallows echo, print_r and WP_CLI::line(). Everything
 *     here reports through WP_CLI::log(), which goes through WP-CLI's logger
 *     and survives.
 *   - Gateway sessions are cut off after roughly ten minutes, so the export
 *     reads in batches and streams each row straight to disk rather than
 *     building the whole file in memory.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Export posts to a CSV file.
 *
 * Built for auditing content across a fleet of sites: run it on every site,
 * pull the files back, and search them in one place to find which pages still
 * carry outdated copy.
 *
 * Reads Elementor page content from where it actually lives. Elementor keeps
 * the real content in the _elementor_data post meta and writes only a plain
 * text copy into post_content when a page is saved, so post_content alone is
 * stale on any page not saved recently, and empty on some older ones.
 *
 * ## OPTIONS
 *
 * [--post-type=<slug>]
 * : Post type to export. Defaults to page.
 *
 * [--profile=<key>]
 * : Export using a declared content profile's exact columns, matching what the
 * admin screen produces. Without this, a generic inventory column set is used
 * that works for any post type.
 *
 * [--post-status=<list>]
 * : Comma separated statuses. Defaults to publish,draft,pending,private,future.
 *
 * [--output=<path>]
 * : Where to write the CSV. Defaults to a timestamped file in the uploads
 * directory. The full path is printed on completion.
 *
 * [--batch=<number>]
 * : Posts to read per query. Defaults to 200. Lower it on sites with very
 * large pages.
 *
 * [--skip-content]
 * : Omit the content_plain column. Much faster, and enough when you only need
 * an inventory of what pages exist.
 *
 * ## EXAMPLES
 *
 *     # Every page on this site, including body text
 *     wp ppm export --post-type=page
 *
 *     # Match the admin screen's city page export exactly
 *     wp ppm export --profile=city --post-type=service-areas
 *
 *     # Inventory only, no body text
 *     wp ppm export --post-type=page --skip-content --output=/tmp/pages.csv
 *
 * @when after_wp_load
 */
function ppm_cli_export($args, $assoc_args) {
    $flag = 'WP_CLI\\Utils\\get_flag_value';

    $post_type    = (string) $flag($assoc_args, 'post-type', 'page');
    $profile_key  = (string) $flag($assoc_args, 'profile', '');
    $status_list  = (string) $flag($assoc_args, 'post-status', 'publish,draft,pending,private,future');
    $output       = (string) $flag($assoc_args, 'output', '');
    $batch        = max(1, (int) $flag($assoc_args, 'batch', 200));
    $skip_content = (bool) $flag($assoc_args, 'skip-content', false);

    if (!post_type_exists($post_type)) {
        WP_CLI::error(sprintf('Post type "%s" is not registered on this site.', $post_type));
    }

    $statuses = array_filter(array_map('trim', explode(',', $status_list)));

    if (!$statuses) {
        WP_CLI::error('No post statuses to export.');
    }

    // A profile reuses the declared column set and the same row builder the
    // admin export uses, so both produce byte-identical files.
    $profile = null;

    if ($profile_key !== '') {
        $profiles = ppm_get_content_profiles();

        if (!isset($profiles[$profile_key])) {
            WP_CLI::error(sprintf(
                'Unknown profile "%s". Available: %s',
                $profile_key,
                implode(', ', array_keys($profiles))
            ));
        }

        $profile = $profiles[$profile_key];
        $headers = ppm_profile_column_names($profile);
    } else {
        $headers = ppm_cli_inventory_columns($skip_content);
    }

    $output = ppm_cli_resolve_output_path($output, $post_type);
    $handle = fopen($output, 'w');

    if ($handle === false) {
        WP_CLI::error(sprintf('Could not open %s for writing.', $output));
    }

    // Excel reads a UTF-8 CSV as the system codepage without this.
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $headers);

    $total    = 0;
    $paged    = 1;
    $reported = 0;

    while (true) {
        $posts = get_posts([
            'post_type'        => $post_type,
            'post_status'      => $statuses,
            'posts_per_page'   => $batch,
            'paged'            => $paged,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ]);

        if (empty($posts)) {
            break;
        }

        foreach ($posts as $post) {
            $row = $profile
                ? ppm_export_build_row($post, $profile['columns'], 'auto')
                : ppm_cli_inventory_row($post, $skip_content);

            fputcsv($handle, $row);
            $total++;
        }

        $paged++;

        // Without this the object cache grows for the whole run and a large
        // site exhausts memory before the gateway even times out.
        if (function_exists('WP_CLI\\Utils\\wp_clear_object_cache')) {
            WP_CLI\Utils\wp_clear_object_cache();
        }

        if ($total - $reported >= 500) {
            WP_CLI::log(sprintf('  %d exported...', $total));
            $reported = $total;
        }
    }

    fclose($handle);

    WP_CLI::log(sprintf('Wrote %d %s rows to %s', $total, $post_type, $output));
    WP_CLI::success(sprintf('%s export complete.', $post_type));
}

WP_CLI::add_command('ppm export', 'ppm_cli_export');

/**
 * Columns for the generic export, used when no profile is given.
 *
 * post_path rather than post_slug is the identifier that matters: pages nest,
 * and two pages under different parents can share a slug. The path is what
 * uniquely identifies a page, and what an import would have to match on.
 */
function ppm_cli_inventory_columns($skip_content) {
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

    if (!$skip_content) {
        $columns[] = 'word_count';
        $columns[] = 'content_plain';
    }

    return $columns;
}

function ppm_cli_inventory_row($post, $skip_content) {
    $parent_path = $post->post_parent ? ppm_cli_post_path(get_post($post->post_parent)) : '';

    $row = [
        $post->ID,
        $post->post_type,
        ppm_cli_post_path($post),
        $post->post_name,
        $parent_path,
        $post->post_title,
        $post->post_status,
        $post->menu_order,
        get_page_template_slug($post->ID),
        ppm_cli_builder($post),
        get_permalink($post),
        $post->post_modified_gmt,
        get_post_meta($post->ID, '_yoast_wpseo_title', true),
        get_post_meta($post->ID, '_yoast_wpseo_metadesc', true),
        get_post_meta($post->ID, '_yoast_wpseo_focuskw', true),
        get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true) === '1' ? '1' : '',
    ];

    if (!$skip_content) {
        $content = ppm_cli_plain_content($post);

        $row[] = ppm_cli_word_count($content);
        $row[] = $content;
    }

    return $row;
}

/**
 * Full hierarchical path, e.g. services/turf-installation.
 */
function ppm_cli_post_path($post) {
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
 * can be edited as text and which are builder trees.
 */
function ppm_cli_builder($post) {
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
 * plain text. post_content is only a copy written at save time, so it is stale
 * on anything edited before that behaviour existed and misleading everywhere
 * else. Falls back to post_content when Elementor is absent or returns nothing.
 */
function ppm_cli_plain_content($post) {
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
            return ppm_cli_normalize_text($text);
        }
    }

    return ppm_cli_normalize_text($post->post_content);
}

function ppm_cli_normalize_text($text) {
    $text = (string) $text;

    if ($text === '') {
        return '';
    }

    $text = strip_shortcodes($text);
    $text = wp_strip_all_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // Collapse the runs of whitespace a builder tree leaves behind, while
    // keeping line breaks so the text stays readable in a spreadsheet cell.
    $text = str_replace("\r\n", "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = preg_replace('/ *\n */', "\n", $text);

    return trim($text);
}

function ppm_cli_word_count($text) {
    if ($text === '') {
        return 0;
    }

    return count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
}

/**
 * Where to write the file.
 *
 * Defaults into uploads because that is reliably writable on managed hosting,
 * where the site root often is not.
 */
function ppm_cli_resolve_output_path($output, $post_type) {
    if ($output !== '') {
        $directory = dirname($output);

        if (!is_dir($directory) || !is_writable($directory)) {
            WP_CLI::error(sprintf('%s is not a writable directory.', $directory));
        }

        return $output;
    }

    $uploads = wp_upload_dir();

    if (!empty($uploads['error'])) {
        WP_CLI::error('Could not resolve the uploads directory: ' . $uploads['error']);
    }

    $directory = trailingslashit($uploads['basedir']) . 'ppm-exports';

    if (!wp_mkdir_p($directory)) {
        WP_CLI::error(sprintf('Could not create %s.', $directory));
    }

    return sprintf(
        '%s/ppm-export-%s-%s.csv',
        untrailingslashit($directory),
        sanitize_file_name($post_type),
        gmdate('Y-m-d-His')
    );
}
