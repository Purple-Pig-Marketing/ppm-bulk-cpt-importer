<?php
/**
 * WP-CLI commands.
 *
 * A thin wrapper over the shared export engine in export.php. The admin screen
 * calls the same functions, so the button and the command cannot drift into
 * producing different files.
 *
 * Loaded only under WP-CLI. The commands exist for fleet work: this plugin is
 * already installed on every site, so one loop over SSH can run the same
 * command everywhere instead of fifty admin logins.
 *
 * One WP Engine detail, since nearly every site is hosted there: its SSH
 * gateway swallows echo, print_r and WP_CLI::line(). All reporting here goes
 * through WP_CLI::log(), which uses WP-CLI's logger and survives.
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
 * Built for auditing content across a fleet of sites: run it everywhere, pull
 * the files back, and search them in one place to find which pages still carry
 * outdated copy.
 *
 * Elementor content is read from the live element tree rather than
 * post_content, which holds only a plain text copy written at save time.
 *
 * ## OPTIONS
 *
 * [--post-type=<slug>]
 * : Post type to export. Defaults to page.
 *
 * [--profile=<key>]
 * : Export using a declared content profile's exact columns, matching what the
 * admin screen produces for that profile. Without this, a generic inventory
 * column set is used that works for any post type.
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
 * : Omit the body text column. Much faster, and enough when you only need an
 * inventory of what pages exist.
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
    $status_list  = (string) $flag($assoc_args, 'post-status', implode(',', ppm_export_statuses()));
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
    }

    $output = ppm_cli_resolve_output_path($output, $post_type);
    $handle = fopen($output, 'w');

    if ($handle === false) {
        WP_CLI::error(sprintf('Could not open %s for writing.', $output));
    }

    $reported = 0;

    $total = ppm_stream_export($handle, [
        'post_type'       => $post_type,
        'post_status'     => $statuses,
        'batch'           => $batch,
        'profile'         => $profile,
        'include_content' => !$skip_content,
        'progress'        => function ($count) use (&$reported) {
            if ($count - $reported >= 500) {
                WP_CLI::log(sprintf('  %d exported...', $count));
                $reported = $count;
            }
        },
    ]);

    fclose($handle);

    WP_CLI::log(sprintf('Wrote %d %s rows to %s', $total, $post_type, $output));
    WP_CLI::success(sprintf('%s export complete.', $post_type));
}

WP_CLI::add_command('ppm export', 'ppm_cli_export');

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
