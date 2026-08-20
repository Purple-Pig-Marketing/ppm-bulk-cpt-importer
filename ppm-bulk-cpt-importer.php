<?php
/**
 * Plugin Name: PPM Bulk CPT Importer
 * Plugin URI: https://bringinghomebacon.com
 * Description: Internal PPM tool for bulk creating and updating CPT pages via CSV (URL-based images only).
 * Version: 1.6.0
 * Author: Purple Pig Marketing
 * Author URI: https://bringinghomebacon.com
 * License: Proprietary
 * Update URI: https://github.com/Purple-Pig-Marketing/ppm-bulk-cpt-importer
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------- */
/* VERSION                                                                    */
/* -------------------------------------------------------------------------- */

// Read the version back out of the plugin header above so the admin badge and
// the stylesheet cache-buster can never drift from the real version. Bumping
// the header is the only edit a release needs.
if (!defined('PPM_BULK_IMPORTER_VERSION')) {
    $ppm_header_data = get_file_data(__FILE__, ['Version' => 'Version'], 'plugin');

    define(
        'PPM_BULK_IMPORTER_VERSION',
        $ppm_header_data['Version'] !== '' ? $ppm_header_data['Version'] : '0.0.0'
    );
}

/* -------------------------------------------------------------------------- */
/* WP-CLI                                                                     */
/* -------------------------------------------------------------------------- */

// The export engine is shared by the admin download button and the WP-CLI
// command, so the two cannot drift into producing different files.
require_once __DIR__ . '/includes/export.php';

// The commands themselves load only under WP-CLI, so a normal page load never
// parses them. They exist for fleet work: this plugin is already on every site,
// so one loop over SSH can run the same command everywhere instead of fifty
// admin logins.
if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/includes/cli.php';
}

/* -------------------------------------------------------------------------- */
/* CONTENT PROFILES                                                           */
/* -------------------------------------------------------------------------- */

/**
 * A content profile describes one kind of page this plugin builds: the CSV
 * column set, how each column is read back out on export, and the post-level
 * settings every imported page of that kind should carry.
 *
 * Everything downstream reads from this registry rather than keeping its own
 * copy of the column list, so adding a page type is an array entry instead of a
 * new branch in the template, the exporter, the preview, and the importer.
 *
 * Column definition keys:
 *   name            CSV column header, and the ACF field name for ACF columns.
 *   source          'post' | 'acf' | 'meta'. Defaults to 'acf'.
 *   post_field      For source 'post': the WP_Post property to read.
 *   meta_key        For source 'meta': the post meta key to read and write.
 *   legacy          For source 'acf': an older field name to fall back to.
 *   image           Value is an image; export normalizes it to a plain URL.
 *   featured_image  Also drive the real WP featured image from this column.
 */
function ppm_get_content_profiles() {
    $profiles = [
        'city' => [
            'label'             => 'City / Service Area Pages',
            'acf_group'         => [
                'key'   => 'group_6a73ce80a0100',
                'title' => 'PPM Import City Pages ACF',
            ],
            'elementor_templates' => [
                [
                    'file'        => 'city-service-locations.json',
                    'label'       => 'Service Locations layout',
                    'description' => 'Theme Builder single template. Needs Elementor Pro for the single-page document type, and its display condition still has to be pointed at the post type after installing. Its three placeholder images import from the site it was exported from.',
                ],
            ],
            'description'       => 'Organic city and service-area pages built on the standard PPM four-section structure.',
            'default_cpt'       => 'service-areas',
            'template_file'     => 'ppm-cpt-import-template.csv',
            'export_prefix'     => 'ppm-cpt-export',
            // The legacy heading_N / section_N / image_N fields only ever
            // existed on city pages, so only this profile offers the export
            // selector that chooses between them and the standard fields.
            'legacy_fields'     => true,
            'supports_variants' => false,
            // Left unset on purpose: city pages get their layout from the
            // Elementor theme-builder condition on the post type, and they are
            // meant to be indexed.
            'post_template'     => '',
            'force_noindex'     => false,
            'columns'           => ppm_assign_city_field_keys(array_merge(
                [
                    ['name' => 'post_title',  'source' => 'post', 'post_field' => 'post_title'],
                    ['name' => 'post_slug',   'source' => 'post', 'post_field' => 'post_name'],
                    ['name' => 'post_status', 'source' => 'post', 'post_field' => 'post_status'],
                    ['name' => 'city_name', 'label' => 'city name'],
                ],
                ppm_city_section_columns(),
                [
                    ['name' => 'city_featured_image', 'image' => true, 'featured_image' => true, 'label' => 'City - Featured Image'],
                    ['name' => 'yoast_title',            'source' => 'meta', 'meta_key' => '_yoast_wpseo_title'],
                    ['name' => 'yoast_meta_description', 'source' => 'meta', 'meta_key' => '_yoast_wpseo_metadesc'],
                    ['name' => 'yoast_focus_keyphrase',  'source' => 'meta', 'meta_key' => '_yoast_wpseo_focuskw'],
                ]
            )),
        ],

        'ppc' => [
            'label'             => 'PPC Landing Pages',
            'acf_group'         => [
                'key'   => 'group_7b84df91b0200',
                'title' => 'PPM Import PPC Landing Pages ACF',
            ],
            'elementor_templates' => [
                [
                    'file'        => 'ppc-landing-page.json',
                    'label'       => 'PPC landing page layout',
                    'description' => 'Not bundled yet. Build the layout once in Elementor against the PPC fields, export it, and commit the JSON as templates/elementor/ppc-landing-page.json — every client site then gets it from a plugin update.',
                ],
            ],
            'description'       => 'Standalone paid-traffic landing pages. Imported noindexed and on the Elementor canvas template so they never compete with the organic city pages for the same queries.',
            'default_cpt'       => 'ppc-landing',
            'template_file'     => 'ppm-ppc-import-template.csv',
            'export_prefix'     => 'ppm-ppc-export',
            'legacy_fields'     => false,
            'supports_variants' => true,
            // Canvas strips the theme header and footer, which is the layout a
            // paid landing page wants. Noindex is forced rather than offered as
            // a column because a landing page that mirrors a city page and gets
            // indexed cannibalizes the page it was meant to support.
            'post_template'     => 'elementor_canvas',
            'force_noindex'     => true,
            'register_cpt'      => [
                'post_type' => 'ppc-landing',
                'args'      => [
                    'labels' => [
                        'name'               => 'PPC Landing Pages',
                        'singular_name'      => 'PPC Landing Page',
                        'menu_name'          => 'PPC Landing Pages',
                        'add_new_item'       => 'Add New PPC Landing Page',
                        'edit_item'          => 'Edit PPC Landing Page',
                        'view_item'          => 'View PPC Landing Page',
                        'search_items'       => 'Search PPC Landing Pages',
                        'not_found'          => 'No PPC landing pages found.',
                        'not_found_in_trash' => 'No PPC landing pages found in Trash.',
                    ],
                    'public'              => true,
                    'publicly_queryable'  => true,
                    'show_ui'             => true,
                    'show_in_menu'        => true,
                    'show_in_rest'        => true,
                    'has_archive'         => false,
                    // Paid landing pages have no business in on-site search
                    // results alongside the organic pages.
                    'exclude_from_search' => true,
                    'menu_icon'           => 'dashicons-megaphone',
                    'menu_position'       => 21,
                    // 'elementor' is the feature Elementor's editor actually
                    // gates on (Utils::is_post_type_support checks
                    // post_type_supports, and the settings checkbox only calls
                    // add_post_type_support behind the scenes). Declaring it
                    // here means the editor works on every site without anyone
                    // ticking a box or this plugin rewriting Elementor's
                    // options.
                    'supports'            => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'elementor'],
                    // A short parent slug rather than root-level pages: a
                    // root-level CPT rewrite competes with WordPress page
                    // resolution, and /lp/ makes the whole set trivial to
                    // exclude from sitemaps and filter in analytics.
                    'rewrite'             => ['slug' => 'lp', 'with_front' => false],
                ],
            ],
            'columns'           => ppm_ppc_columns(),
        ],
    ];

    $profiles = apply_filters('ppm_content_profiles', $profiles);

    foreach ($profiles as $key => $profile) {
        $profiles[$key] = ppm_normalize_profile($profile);
    }

    return $profiles;
}

/**
 * The repeating four-section body shared by every city page.
 */
function ppm_city_section_columns() {
    $columns = [];

    for ($i = 1; $i <= 4; $i++) {
        $columns[] = ['name' => "section_{$i}_title",       'legacy' => "heading_{$i}", 'label' => "Section {$i} - Title"];
        $columns[] = ['name' => "section_{$i}_sub_title",   'label' => "Section {$i} - Sub Title"];
        $columns[] = ['name' => "section_{$i}_paragraph",   'legacy' => "section_{$i}", 'label' => "Section {$i} - Paragraph", 'field_type' => 'wysiwyg'];
        $columns[] = ['name' => "section_{$i}_button_text", 'label' => "Section {$i} - Button Text"];
        $columns[] = ['name' => "section_{$i}_button_url",  'label' => "Section {$i} - Button URL", 'field_type' => 'url'];
        $columns[] = ['name' => "section_{$i}_image",       'legacy' => "image_{$i}", 'image' => true, 'label' => "Section {$i} - Image"];
    }

    return $columns;
}

/**
 * Stamp the city columns with the field keys from acf-field-groups.json.
 *
 * These keys are load-bearing, not cosmetic. Every city page already imported
 * carries them in the hidden `_fieldname` post meta that ACF and Elementor
 * resolve values through, so a regenerated key would orphan live content. They
 * run in a +7 hex stride from the group key, in field order, which is how the
 * original export was written.
 */
function ppm_assign_city_field_keys($columns) {
    $n = 0;

    foreach ($columns as $i => $column) {
        $source = isset($column['source']) ? $column['source'] : 'acf';

        if ($source !== 'acf') {
            continue;
        }

        $n++;
        $columns[$i]['field_key'] = sprintf('field_6a73ce80a%04x', 0x100 + 7 * $n);
    }

    return $columns;
}

/**
 * The PPC landing page column set.
 *
 * Flat and numbered for the same reason the city sections are: the importer
 * maps one CSV column to one field name and has no repeater support, so a
 * repeater here would mean a second import path to maintain.
 *
 * Wide by design. Blank cells are skipped on import, so an unused slot costs
 * nothing, while a missing slot means editing the field group mid-campaign.
 */
function ppm_ppc_columns() {
    $columns = [
        ['name' => 'post_title',  'source' => 'post', 'post_field' => 'post_title'],
        ['name' => 'post_slug',   'source' => 'post', 'post_field' => 'post_name'],
        ['name' => 'post_status', 'source' => 'post', 'post_field' => 'post_status'],

        ['name' => 'hero_eyebrow'],
        ['name' => 'hero_headline'],
        ['name' => 'hero_subheadline'],
        ['name' => 'hero_image', 'image' => true],
        ['name' => 'hero_cta_text'],
        ['name' => 'hero_cta_url', 'field_type' => 'url'],
        // Held separately from the CTA so a call-tracking number can be swapped
        // per campaign without touching the form CTA.
        ['name' => 'hero_phone'],

        ['name' => 'offer_headline'],
        ['name' => 'offer_details',    'field_type' => 'wysiwyg'],
        ['name' => 'offer_disclaimer', 'field_type' => 'wysiwyg'],
        ['name' => 'offer_expires'],
        ['name' => 'offer_promo_code'],
    ];

    for ($i = 1; $i <= 4; $i++) {
        $columns[] = ['name' => "benefit_{$i}_title"];
        $columns[] = ['name' => "benefit_{$i}_text", 'field_type' => 'wysiwyg'];
    }

    for ($i = 1; $i <= 3; $i++) {
        $columns[] = ['name' => "process_{$i}_title"];
        $columns[] = ['name' => "process_{$i}_text", 'field_type' => 'wysiwyg'];
    }

    $columns[] = ['name' => 'proof_rating'];
    $columns[] = ['name' => 'proof_review_count'];

    for ($i = 1; $i <= 3; $i++) {
        $columns[] = ['name' => "testimonial_{$i}_quote", 'field_type' => 'wysiwyg'];
        $columns[] = ['name' => "testimonial_{$i}_name"];
        $columns[] = ['name' => "testimonial_{$i}_location"];
    }

    for ($i = 1; $i <= 3; $i++) {
        $columns[] = ['name' => "badge_{$i}_image", 'image' => true];
    }

    $columns[] = ['name' => 'form_heading'];
    $columns[] = ['name' => 'form_subheading'];
    // Google Ads wants a reachable privacy statement on the landing page; the
    // canvas template has no theme footer to inherit one from.
    $columns[] = ['name' => 'form_privacy_text', 'field_type' => 'wysiwyg'];
    $columns[] = ['name' => 'form_shortcode'];

    for ($i = 1; $i <= 4; $i++) {
        $columns[] = ['name' => "faq_{$i}_question"];
        $columns[] = ['name' => "faq_{$i}_answer", 'field_type' => 'wysiwyg'];
    }

    $columns[] = ['name' => 'final_cta_headline'];
    $columns[] = ['name' => 'final_cta_text', 'field_type' => 'wysiwyg'];
    $columns[] = ['name' => 'final_cta_button_text'];
    $columns[] = ['name' => 'final_cta_button_url', 'field_type' => 'url'];

    $columns[] = ['name' => 'tracking_conversion_label'];
    $columns[] = ['name' => 'tracking_event_name'];
    $columns[] = ['name' => 'tracking_call_number'];

    $columns[] = ['name' => 'ppc_featured_image', 'image' => true, 'featured_image' => true];

    // No focus keyphrase: the page is noindexed, so there is nothing to rank.
    // The title still shows in the browser tab and on any shared link.
    $columns[] = ['name' => 'yoast_title',            'source' => 'meta', 'meta_key' => '_yoast_wpseo_title'];
    $columns[] = ['name' => 'yoast_meta_description', 'source' => 'meta', 'meta_key' => '_yoast_wpseo_metadesc'];

    return $columns;
}

/**
 * A/B variant columns, added to any profile that sets 'supports_variants'.
 *
 * Variants are flat fields rather than a relationship or a taxonomy so one CSV
 * row still equals one page, and so an entire test can be created, paused, or
 * rewritten from the same sheet as the pages themselves. 'variant_group' is the
 * join key: every page testing the same offer carries the same value, and the
 * label is what distinguishes them inside that group.
 */
function ppm_variant_columns() {
    return [
        ['name' => 'variant_group'],
        ['name' => 'variant_label'],
        ['name' => 'variant_is_control'],
        ['name' => 'variant_campaign'],
    ];
}

function ppm_normalize_columns($columns) {
    $normalized = [];

    foreach ((array) $columns as $column) {
        if (empty($column['name'])) {
            continue;
        }

        $normalized[] = wp_parse_args($column, [
            'source'         => 'acf',
            'post_field'     => '',
            'meta_key'       => '',
            'legacy'         => '',
            'image'          => false,
            'featured_image' => false,
            // ACF presentation. Blank means derive it: 'url' for images and
            // 'text' otherwise, with the label built from the column name.
            'field_key'      => '',
            'field_type'     => '',
            'label'          => '',
        ]);
    }

    return $normalized;
}

function ppm_normalize_profile($profile) {
    $profile = wp_parse_args($profile, [
        'label'             => '',
        'description'       => '',
        'default_cpt'       => '',
        'template_file'     => 'ppm-cpt-import-template.csv',
        'export_prefix'     => 'ppm-cpt-export',
        'legacy_fields'     => false,
        'supports_variants' => false,
        'post_template'     => '',
        'force_noindex'     => false,
        'register_cpt'      => [],
        'acf_group'         => [],
        'elementor_templates' => [],
        'columns'           => [],
    ]);

    $columns = ppm_normalize_columns($profile['columns']);

    // Variant columns sit after the page content but ahead of the SEO columns,
    // which keeps the SEO block at the end of the sheet where operators expect
    // it. Profiles without variants keep their declared order untouched.
    if ($profile['supports_variants']) {
        $content = [];
        $meta    = [];

        foreach ($columns as $column) {
            if ($column['source'] === 'meta') {
                $meta[] = $column;
            } else {
                $content[] = $column;
            }
        }

        $columns = array_merge($content, ppm_normalize_columns(ppm_variant_columns()), $meta);
    }

    $profile['columns'] = $columns;

    return $profile;
}

function ppm_get_profile($profile_key = '') {
    $profiles = ppm_get_content_profiles();

    if ($profile_key !== '' && isset($profiles[$profile_key])) {
        return $profiles[$profile_key];
    }

    return reset($profiles);
}

/**
 * Resolve the profile for this request. POST wins so a submitted form keeps its
 * own profile even if the address bar still carries another one.
 */
function ppm_get_current_profile_key() {
    $profiles = ppm_get_content_profiles();

    foreach ([$_POST, $_GET] as $source) {
        if (!isset($source['ppm_profile'])) {
            continue;
        }

        $key = sanitize_key(wp_unslash($source['ppm_profile']));

        if (isset($profiles[$key])) {
            return $key;
        }
    }

    reset($profiles);

    return (string) key($profiles);
}

function ppm_profile_column_names($profile) {
    return wp_list_pluck($profile['columns'], 'name');
}

/**
 * Every column across every profile that writes to a plain post meta key.
 *
 * Unioned rather than read from the selected profile alone so a sheet exported
 * under one profile and imported under another still lands each column where
 * that column belongs.
 */
function ppm_get_all_meta_columns() {
    $map = [];

    foreach (ppm_get_content_profiles() as $profile) {
        foreach ($profile['columns'] as $column) {
            if ($column['source'] === 'meta' && $column['meta_key'] !== '') {
                $map[$column['name']] = $column['meta_key'];
            }
        }
    }

    return $map;
}

/**
 * Columns that drive the WP featured image, mapped to the source they also
 * write to ('acf' writes the field as well, 'none' sets the thumbnail only).
 */
function ppm_get_all_featured_image_columns() {
    $columns = [];

    foreach (ppm_get_content_profiles() as $profile) {
        foreach ($profile['columns'] as $column) {
            if ($column['featured_image']) {
                $columns[$column['name']] = $column['source'];
            }
        }
    }

    return $columns;
}

/**
 * 'featured_image' is an older column name still present in CSVs already in
 * circulation. It never had an ACF field behind it, only the thumbnail.
 */
function ppm_get_featured_image_aliases() {
    return ['featured_image' => 'none'];
}

/* -------------------------------------------------------------------------- */
/* PROFILE POST TYPES                                                         */
/* -------------------------------------------------------------------------- */

/**
 * Register the post type for any profile that declares one.
 *
 * City pages are deliberately absent: their post type is registered per site
 * (the slug differs between clients), and this plugin only ever imports into
 * it. A profile that ships its own post type gets an identical slug on every
 * site, which is what lets one ACF field group and one Elementor template
 * serve the whole client base.
 */
function ppm_register_profile_post_types() {
    foreach (ppm_get_content_profiles() as $profile) {
        if (empty($profile['register_cpt']['post_type'])) {
            continue;
        }

        $post_type = $profile['register_cpt']['post_type'];

        // A site that already registers this post type its own way keeps it.
        if (post_type_exists($post_type)) {
            continue;
        }

        register_post_type($post_type, $profile['register_cpt']['args']);
    }
}
// Priority 0, not the usual 5: ACF fires acf/init from its own init:5
// callback, and ppm_register_local_acf_groups() runs there and checks
// post_type_exists(). ACF loads before this plugin alphabetically, so at equal
// priority its callback would win the tie and the field group would never
// register. Priority 0 settles the order deterministically.
add_action('init', 'ppm_register_profile_post_types', 0);

/**
 * Rewrite rules for a newly registered post type do not exist until they are
 * flushed, so /lp/... would 404 until someone opened Permalinks. Stamping the
 * plugin version means this costs one flush per release instead of one per
 * request.
 */
add_action('init', function () {
    if (get_option('ppm_rewrite_version') === PPM_BULK_IMPORTER_VERSION) {
        return;
    }

    flush_rewrite_rules();
    update_option('ppm_rewrite_version', PPM_BULK_IMPORTER_VERSION);
}, 20);

/* -------------------------------------------------------------------------- */
/* ACF FIELD GROUPS                                                           */
/* -------------------------------------------------------------------------- */

/**
 * Register each profile's ACF field group from PHP, so a new client site needs
 * a plugin install rather than a plugin install plus a JSON import.
 *
 * Skipped whenever the site already has a group with the same key in the
 * database. ACF merges local over database for matching keys
 * (_acf_apply_get_local_internal_posts does array_merge with the local copy
 * second), so registering unconditionally would silently discard any per-site
 * edit anyone had made to that group. Filling the gap is useful; overwriting
 * someone's work is not.
 */
function ppm_register_local_acf_groups() {
    if (!function_exists('acf_add_local_field_group') || !post_type_exists('acf-field-group')) {
        return;
    }

    foreach (ppm_get_content_profiles() as $profile_key => $profile) {
        if (empty($profile['acf_group']['key'])) {
            continue;
        }

        $post_type = ppm_profile_post_type($profile);

        // Binding a group to a post type that does not exist on this site would
        // do nothing useful and would show as orphaned in the ACF admin.
        if ($post_type === '' || !post_type_exists($post_type)) {
            continue;
        }

        if (!ppm_should_register_acf_group($profile_key, $profile, $post_type)) {
            continue;
        }

        acf_add_local_field_group(ppm_build_acf_group($profile_key, $profile, $post_type));
    }
}

/**
 * Whether this plugin may install its own field group onto a post type.
 *
 * Only onto a post type the plugin itself registered.
 *
 * A site that had city pages before it ever had this plugin already has a field
 * group on that post type: its own key, but the same field names. Adding ours
 * alongside it puts two groups with duplicate field names on one post type —
 * every box twice in the editor, and an import that can rewrite the hidden
 * `_fieldname` key references the site's existing Elementor bindings resolve
 * through. That is the collision the importer's field keys were regenerated to
 * escape, and re-creating it automatically on every update would be worse than
 * the manual JSON import it replaced.
 *
 * A post type this plugin registers is new to the site, so nothing can already
 * be bound to it. That is the only case where installing a group is safe
 * without inspecting what the site already has.
 *
 * Sites that do want the group can opt in through the filter.
 */
function ppm_should_register_acf_group($profile_key, $profile, $post_type) {
    $owns_post_type = !empty($profile['register_cpt']['post_type'])
        && $profile['register_cpt']['post_type'] === $post_type;

    // Never replace a group the site already stores under this key. ACF merges
    // local over database, so registering would discard their edits.
    if ($owns_post_type && ppm_acf_group_in_database($profile['acf_group']['key'])) {
        $owns_post_type = false;
    }

    return (bool) apply_filters(
        'ppm_register_acf_group',
        $owns_post_type,
        $profile_key,
        $post_type
    );
}
// init:6 rather than acf/init. ACF fires acf/init from its init:5 callback and
// only registers the acf-field-group post type afterwards, so the database
// check below would run against an unregistered post type. By init:6 ACF's post
// types and ours (init:0) both exist, and nothing has asked ACF for field
// groups yet — that happens lazily on admin screens and template reads.
add_action('init', 'ppm_register_local_acf_groups', 6);

/**
 * Whether a field group with this key is already stored in the database.
 */
function ppm_acf_group_in_database($group_key) {
    static $cache = [];

    if (isset($cache[$group_key])) {
        return $cache[$group_key];
    }

    // ACF stores each field group as an acf-field-group post whose post_name is
    // the group key. Disabled groups count: the site still owns that key.
    $found = get_posts([
        'post_type'        => 'acf-field-group',
        'post_status'      => ['publish', 'acf-disabled'],
        'name'             => $group_key,
        'posts_per_page'   => 1,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => false,
    ]);

    $cache[$group_key] = !empty($found);

    return $cache[$group_key];
}

/**
 * Field keys for profiles that do not pin them explicitly.
 *
 * Derived from the profile and column name so the same field gets the same key
 * on every site and across every release, without a hand-maintained list.
 */
function ppm_acf_field_key($profile_key, $name) {
    return 'field_' . substr(md5('ppm:' . $profile_key . ':' . $name), 0, 13);
}

/**
 * Turn a column name into a field label: benefit_1_title -> Benefit 1 Title.
 */
function ppm_humanize_column($name) {
    $abbreviations = ['cta' => 'CTA', 'url' => 'URL', 'faq' => 'FAQ', 'ppc' => 'PPC'];
    $words = [];

    foreach (explode('_', $name) as $word) {
        if (ctype_digit($word)) {
            $words[] = $word;
        } elseif (isset($abbreviations[$word])) {
            $words[] = $abbreviations[$word];
        } else {
            $words[] = ucfirst($word);
        }
    }

    return implode(' ', $words);
}

function ppm_build_acf_field($profile_key, $column) {
    $type = $column['field_type'];

    if ($type === '') {
        // Images are stored as plain URL strings, not attachment objects, because
        // the CSV carries URLs and attachment IDs differ from site to site.
        $type = $column['image'] ? 'url' : 'text';
    }

    $field = [
        'key'               => $column['field_key'] !== ''
            ? $column['field_key']
            : ppm_acf_field_key($profile_key, $column['name']),
        'label'             => $column['label'] !== ''
            ? $column['label']
            : ppm_humanize_column($column['name']),
        'name'              => $column['name'],
        'aria-label'        => '',
        'type'              => $type,
        'instructions'      => '',
        'required'          => 0,
        'conditional_logic' => 0,
        'wrapper'           => ['width' => '', 'class' => '', 'id' => ''],
        'default_value'     => '',
    ];

    if ($type === 'wysiwyg') {
        return $field + [
            'allow_in_bindings' => 1,
            'tabs'              => 'all',
            'toolbar'           => 'full',
            'media_upload'      => 1,
            'delay'             => 0,
        ];
    }

    if ($type === 'url') {
        return $field + [
            'allow_in_bindings' => 0,
            'placeholder'       => '',
        ];
    }

    return $field + [
        'maxlength'         => '',
        'allow_in_bindings' => 1,
        'placeholder'       => '',
        'prepend'           => '',
        'append'            => '',
    ];
}

/**
 * Build the ACF group for a profile from the same column list the importer and
 * exporter read, so a field can never exist in one and be missing from the other.
 */
function ppm_build_acf_group($profile_key, $profile, $post_type) {
    $fields = [];

    foreach ($profile['columns'] as $column) {
        // Post fields and Yoast meta are written directly, not through ACF.
        if ($column['source'] !== 'acf') {
            continue;
        }

        $fields[] = ppm_build_acf_field($profile_key, $column);
    }

    return [
        'key'                   => $profile['acf_group']['key'],
        'title'                 => $profile['acf_group']['title'],
        'fields'                => $fields,
        'location'              => [[['param' => 'post_type', 'operator' => '==', 'value' => $post_type]]],
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen'        => '',
        'active'                => true,
        'description'           => '',
        'show_in_rest'          => 0,
    ];
}

/* -------------------------------------------------------------------------- */
/* NOINDEX ENFORCEMENT                                                        */
/* -------------------------------------------------------------------------- */

/**
 * Post types belonging to a profile that forces noindex.
 */
function ppm_noindexed_post_types() {
    static $post_types = null;

    if ($post_types !== null) {
        return $post_types;
    }

    $post_types = [];

    foreach (ppm_get_content_profiles() as $profile) {
        if (!$profile['force_noindex']) {
            continue;
        }

        $post_type = ppm_profile_post_type($profile);

        if ($post_type !== '') {
            $post_types[] = $post_type;
        }
    }

    $post_types = array_values(array_unique($post_types));

    return $post_types;
}

/**
 * The post type a profile operates on: the one it registers if it ships its
 * own, otherwise the site-specific slug it defaults to.
 */
function ppm_profile_post_type($profile) {
    if (!empty($profile['register_cpt']['post_type'])) {
        return $profile['register_cpt']['post_type'];
    }

    return $profile['default_cpt'];
}

function ppm_post_type_is_noindexed($post_type) {
    return in_array($post_type, ppm_noindexed_post_types(), true);
}

// The importer writes the per-post noindex meta, but a page built by hand in
// the admin would miss it. Enforcing at the post type level covers both, and
// covers a page whose meta someone later clears.
add_filter('wpseo_robots_array', function ($robots) {
    if (is_singular() && ppm_post_type_is_noindexed(get_post_type())) {
        $robots['index']  = 'noindex';
        $robots['follow'] = 'nofollow';
    }

    return $robots;
});

add_filter('wp_robots', function ($robots) {
    // Yoast renders its own robots tag and is handled above; adding core's on
    // top would emit the directive twice.
    if (defined('WPSEO_VERSION')) {
        return $robots;
    }

    if (is_singular() && ppm_post_type_is_noindexed(get_post_type())) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
    }

    return $robots;
});

// Keep the whole post type out of both sitemap generators, rather than relying
// on each page carrying the per-post noindex meta.
add_filter('wpseo_sitemap_exclude_post_type', function ($excluded, $post_type) {
    return ppm_post_type_is_noindexed($post_type) ? true : $excluded;
}, 10, 2);

add_filter('wp_sitemaps_post_types', function ($post_types) {
    foreach (array_keys($post_types) as $post_type) {
        if (ppm_post_type_is_noindexed($post_type)) {
            unset($post_types[$post_type]);
        }
    }

    return $post_types;
});

/* -------------------------------------------------------------------------- */
/* ADMIN NOTICE SUPPRESSION                                                   */
/* -------------------------------------------------------------------------- */

// Unrelated plugins inject promotional notices into every admin screen, which
// lands them in the middle of this plugin's header layout. Strip them on this
// one screen. The importer reports its own results inline from the page body
// rather than through these hooks, so nothing of ours is lost.
add_action('in_admin_header', function () {
    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();

    if (!$screen || $screen->id !== 'toplevel_page_ppm-bulk-import') {
        return;
    }

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    remove_all_actions('user_admin_notices');
    remove_all_actions('network_admin_notices');
}, PHP_INT_MAX);

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
.ppm-profile-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 14px;
}
.ppm-admin-page .ppm-profile-tab {
    padding: 11px 21px;
    border: 1px solid var(--ppm-border);
    border-radius: 999px;
    background: #fff;
    color: #2f052d;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.2;
    text-decoration: none;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.ppm-admin-page .ppm-profile-tab:hover,
.ppm-admin-page .ppm-profile-tab:focus {
    border-color: var(--ppm-cta);
    color: var(--ppm-cta);
    box-shadow: none;
    outline: none;
}
.ppm-admin-page .ppm-profile-tab.is-active,
.ppm-admin-page .ppm-profile-tab.is-active:hover {
    border-color: var(--ppm-cta);
    background: var(--ppm-cta);
    color: #fff;
}
.ppm-profile-description {
    margin: 0 0 26px;
    color: #606074;
    font-size: 14px;
    line-height: 1.5;
}
.ppm-inline-notice {
    margin: 0 0 24px;
    padding: 15px 20px;
    border: 1px solid #e8cd93;
    border-left: 4px solid #d9a441;
    border-radius: 10px;
    background: #fdf8ec;
}
.ppm-inline-notice p {
    margin: 0;
    color: #5c4718;
    font-size: 14px;
    line-height: 1.55;
}
.ppm-admin-page .ppm-inline-notice a {
    color: var(--ppm-cta);
    font-weight: 600;
}
.ppm-inline-notice-ok {
    border-color: #b7dcc3;
    border-left-color: #3f9d5f;
    background: #f1faf3;
}
.ppm-inline-notice-ok p {
    color: #1f5533;
}
.ppm-template-card,
.ppm-audit-card {
    margin-top: 24px;
    min-height: 0;
}
.ppm-audit-form {
    max-width: 460px;
}
.ppm-audit-form .ppm-field {
    margin-bottom: 14px;
}
.ppm-checkbox {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 12px;
    color: #2f052d;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}
.ppm-admin-page .ppm-checkbox input[type='checkbox'] {
    width: 18px;
    height: 18px;
    margin: 0;
}
.ppm-audit-note {
    margin: 18px 0 0;
    padding-top: 16px;
    border-top: 1px solid var(--ppm-border);
}
.ppm-audit-note code {
    padding: 2px 6px;
    border-radius: 4px;
    background: #ebeef9;
    font-size: 13px;
}
.ppm-template-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.ppm-template-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin: 0;
    padding: 16px 18px;
    border: 1px solid var(--ppm-border);
    border-radius: 10px;
    background: #fbfbfe;
}
.ppm-template-meta strong {
    display: block;
    margin-bottom: 4px;
    color: #2f052d;
    font-size: 15px;
}
.ppm-template-note {
    display: block;
    max-width: 70ch;
    color: #747488;
    font-size: 13px;
    line-height: 1.5;
}
.ppm-template-action form {
    margin: 0;
}
.ppm-template-state {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 999px;
    background: #ebeef9;
    color: #747488;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
@media (max-width: 782px) {
    .ppm-template-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }
}
.ppm-inline-notice code {
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(92,71,24,.09);
    font-size: 13px;
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

    wp_register_style('ppm-bulk-import-admin', false, [], PPM_BULK_IMPORTER_VERSION);
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
/* CONTENT AUDIT EXPORT                                                       */
/* -------------------------------------------------------------------------- */

add_action('admin_post_ppm_export_inventory', 'ppm_export_inventory_csv');

/**
 * Stream a content audit of any post type to the browser.
 *
 * The same export the WP-CLI command writes to a file, sent as a download
 * instead, so a site can be audited without a terminal.
 */
function ppm_export_inventory_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to export this data.');
    }

    check_admin_referer('ppm_export_inventory');

    $post_type = isset($_POST['post_type'])
        ? sanitize_key(wp_unslash($_POST['post_type']))
        : '';

    if (!$post_type || !post_type_exists($post_type)) {
        wp_die('Choose a post type that exists on this site.');
    }

    $include_content = !empty($_POST['include_content']);

    // Reading the body text of every page is the slow part, so give the request
    // room. Managed hosts often disallow this; there is nothing to do if so,
    // which is why the form warns that large sites may need the CLI command.
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = sprintf(
        'ppm-content-audit-%s-%s.csv',
        sanitize_file_name($post_type),
        gmdate('Y-m-d')
    );

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if ($output === false) {
        wp_die('Could not open the CSV output stream.');
    }

    ppm_stream_export($output, [
        'post_type'       => $post_type,
        'include_content' => $include_content,
        // Smaller batches than the CLI default: each one is flushed to the
        // browser, which keeps the download visibly progressing rather than
        // looking stalled on a site with thousands of pages.
        'batch'           => 100,
        'progress'        => function () {
            flush();
        },
    ]);

    fclose($output);
    exit;
}

function ppm_render_content_audit_card() {
    $post_types = ppm_exportable_post_types();

    if (empty($post_types)) {
        return;
    }

    $selected = isset($post_types['page']) ? 'page' : key($post_types);
    ?>
    <section class="ppm-admin-card ppm-audit-card">
        <div class="ppm-admin-card-icon"><span class="dashicons dashicons-search"></span></div>
        <h2>Content Audit</h2>
        <p>Download a spreadsheet of every page on this site &mdash; address, status, which editor built it, SEO fields, and the words on the page. Search it to find outdated copy without opening pages one by one.</p>

        <form class="ppm-audit-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ppm_export_inventory'); ?>
            <input type="hidden" name="action" value="ppm_export_inventory">

            <div class="ppm-field">
                <label for="ppm-audit-post-type">What to export</label>
                <select id="ppm-audit-post-type" name="post_type">
                    <?php foreach ($post_types as $name => $label) : ?>
                        <option value="<?php echo esc_attr($name); ?>" <?php selected($name, $selected); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="ppm-checkbox">
                <input type="checkbox" name="include_content" value="1" checked>
                <span>Include the text of each page</span>
            </label>

            <p class="ppm-description">Elementor pages are read from the live layout, not the stored excerpt, so the text is what visitors actually see. Leaving this unticked is much faster and still lists every page.</p>

            <div class="ppm-form-actions">
                <input type="submit" class="button ppm-btn ppm-btn-primary" value="Download Content Audit">
            </div>
        </form>

        <p class="ppm-description ppm-audit-note">On a very large site the download may time out. If that happens, the same export runs without a time limit from the command line: <code>wp ppm export --post-type=page</code></p>
    </section>
    <?php
}

/* -------------------------------------------------------------------------- */
/* ELEMENTOR TEMPLATE LIBRARY                                                 */
/* -------------------------------------------------------------------------- */

function ppm_bundled_template_path($file) {
    return plugin_dir_path(__FILE__) . 'templates/elementor/' . $file;
}

/**
 * Look a template up by filename among the ones this profile declares.
 *
 * Returning only declared templates is what keeps a submitted filename from
 * reaching the filesystem: an arbitrary path simply finds no match.
 */
function ppm_find_profile_template($profile, $file) {
    foreach ($profile['elementor_templates'] as $template) {
        if ($template['file'] === $file) {
            return $template;
        }
    }

    return null;
}

/**
 * The template post this plugin created for a bundled file, or 0.
 *
 * Verifies the post still exists so a template someone deleted becomes
 * installable again rather than showing as installed forever.
 */
function ppm_installed_template_id($profile_key, $file) {
    $installed = get_option('ppm_installed_templates', []);
    $key = $profile_key . ':' . $file;

    if (!is_array($installed) || empty($installed[$key])) {
        return 0;
    }

    $template_id = (int) $installed[$key];

    if (!get_post($template_id)) {
        unset($installed[$key]);
        update_option('ppm_installed_templates', $installed);

        return 0;
    }

    return $template_id;
}

function ppm_remember_installed_template($profile_key, $file, $template_id) {
    $installed = get_option('ppm_installed_templates', []);

    if (!is_array($installed)) {
        $installed = [];
    }

    $installed[$profile_key . ':' . $file] = (int) $template_id;

    update_option('ppm_installed_templates', $installed);
}

/**
 * Import one bundled template into Elementor's library.
 *
 * Goes through the local source rather than Elementor's own
 * templates_manager->import_template(): that one expects an uploaded file
 * payload and additionally gates on the site's JSON-upload permission, neither
 * of which applies to a file shipped inside this plugin. The local source takes
 * a path directly, which is exactly the case here.
 *
 * Returns a status slug for the redirect.
 */
function ppm_install_bundled_template($profile_key, $profile, $file) {
    if (!ppm_find_profile_template($profile, $file)) {
        return 'missing';
    }

    if (!did_action('elementor/loaded') || !class_exists('\\Elementor\\Plugin')) {
        return 'no-elementor';
    }

    if (ppm_installed_template_id($profile_key, $file)) {
        return 'exists';
    }

    $path = ppm_bundled_template_path($file);

    if (!is_readable($path)) {
        return 'missing';
    }

    $source = \Elementor\Plugin::$instance->templates_manager->get_source('local');

    if (!$source) {
        return 'failed';
    }

    $result = $source->import_template(basename($path), $path);

    if (is_wp_error($result) || empty($result)) {
        return 'failed';
    }

    // import_template() returns a list of imported items, one per JSON file.
    $item = is_array($result) ? reset($result) : $result;

    if (empty($item['template_id'])) {
        return 'failed';
    }

    ppm_remember_installed_template($profile_key, $file, (int) $item['template_id']);

    return 'installed';
}

add_action('admin_post_ppm_install_elementor_template', 'ppm_install_elementor_template');

function ppm_install_elementor_template() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to install templates.');
    }

    check_admin_referer('ppm_install_elementor_template');

    $profile_key = ppm_get_current_profile_key();
    $profile     = ppm_get_profile($profile_key);

    $file = isset($_POST['template_file'])
        ? sanitize_file_name(wp_unslash($_POST['template_file']))
        : '';

    $status = ppm_install_bundled_template($profile_key, $profile, $file);

    wp_safe_redirect(add_query_arg(
        [
            'page'         => 'ppm-bulk-import',
            'ppm_profile'  => $profile_key,
            'ppm_template' => $status,
        ],
        admin_url('admin.php')
    ));

    exit;
}

/**
 * Report the outcome of an install after the redirect.
 *
 * Only a status slug travels in the URL, never a message, so nothing arbitrary
 * can be echoed back onto the screen.
 */
function ppm_render_template_status_notice() {
    if (empty($_GET['ppm_template'])) {
        return;
    }

    $messages = [
        'installed'    => ['ok', 'Template installed. It is now under Templates &rsaquo; Saved Templates, where its display conditions can be set.'],
        'exists'       => ['ok', 'That template is already installed on this site.'],
        'missing'      => ['warn', 'That template is not bundled in this release of the plugin.'],
        'no-elementor' => ['warn', 'Elementor is not active, so there is nothing to install the template into.'],
        'failed'       => ['warn', 'Elementor rejected the template file. A Theme Builder template needs Elementor Pro for its document type to exist.'],
    ];

    $status = sanitize_key(wp_unslash($_GET['ppm_template']));

    if (!isset($messages[$status])) {
        return;
    }

    printf(
        '<div class="ppm-inline-notice ppm-inline-notice-%s"><p>%s</p></div>',
        esc_attr($messages[$status][0]),
        $messages[$status][1]
    );
}

function ppm_render_template_library($profile_key, $profile) {
    if (empty($profile['elementor_templates'])) {
        return;
    }
    ?>
    <section class="ppm-admin-card ppm-template-card">
        <div class="ppm-admin-card-icon"><span class="dashicons dashicons-layout"></span></div>
        <h2>Page Templates</h2>
        <p>Install the bundled Elementor layout for this page type instead of importing the JSON by hand on every site.</p>

        <ul class="ppm-template-list">
            <?php foreach ($profile['elementor_templates'] as $template) : ?>
                <?php
                $path         = ppm_bundled_template_path($template['file']);
                $is_bundled   = is_readable($path);
                $installed_id = ppm_installed_template_id($profile_key, $template['file']);
                ?>
                <li class="ppm-template-row">
                    <div class="ppm-template-meta">
                        <strong><?php echo esc_html($template['label']); ?></strong>
                        <?php if (!empty($template['description'])) : ?>
                            <span class="ppm-template-note"><?php echo esc_html($template['description']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="ppm-template-action">
                        <?php if (!$is_bundled) : ?>
                            <span class="ppm-template-state">Not bundled</span>
                        <?php elseif ($installed_id) : ?>
                            <a class="button ppm-btn" href="<?php echo esc_url((string) get_edit_post_link($installed_id)); ?>">Installed &mdash; Edit</a>
                        <?php else : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('ppm_install_elementor_template'); ?>
                                <input type="hidden" name="action" value="ppm_install_elementor_template">
                                <input type="hidden" name="ppm_profile" value="<?php echo esc_attr($profile_key); ?>">
                                <input type="hidden" name="template_file" value="<?php echo esc_attr($template['file']); ?>">
                                <input type="submit" class="button ppm-btn ppm-btn-primary" value="Install Template">
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
}

/* -------------------------------------------------------------------------- */
/* SETUP NOTICES                                                              */
/* -------------------------------------------------------------------------- */

/**
 * Warn when a profile's pages are imported onto an Elementor template but
 * Elementor cannot edit that post type.
 *
 * The post type declares the 'elementor' feature at registration, so this
 * should stay silent. It is here as a safety net for the cases that registration
 * cannot cover: Elementor not installed at all, or another plugin stripping the
 * feature back off. It tests post_type_supports() because that is the same
 * condition Elementor's own Utils::is_post_type_support() checks, rather than
 * the settings option, which is only one of the ways that support gets added.
 *
 * Printed inline because this screen strips the admin_notices hooks.
 */
function ppm_render_elementor_support_notice($profile) {
    // Derived from the template rather than a separate flag so the two can
    // never disagree: only an Elementor template needs Elementor.
    if (strpos($profile['post_template'], 'elementor_') !== 0) {
        return;
    }

    $post_type = ppm_profile_post_type($profile);

    if ($post_type === '') {
        return;
    }

    if (!defined('ELEMENTOR_VERSION')) {
        printf(
            '<div class="ppm-inline-notice"><p><strong>Elementor is not active.</strong> %s are imported onto the <code>%s</code> template, which needs Elementor installed and active before those pages will render.</p></div>',
            esc_html($profile['label']),
            esc_html($profile['post_template'])
        );

        return;
    }

    if (post_type_supports($post_type, 'elementor')) {
        return;
    }

    printf(
        '<div class="ppm-inline-notice"><p><strong>Elementor cannot edit these pages.</strong> The <code>%s</code> post type has lost Elementor support, so imported pages will have no <em>Edit with Elementor</em> button. Enabling it under Post Types in <a href="%s">Elementor &rsaquo; Settings</a> restores it. Importing works either way &mdash; only editing the layout is blocked.</p></div>',
        esc_html($post_type),
        esc_url(admin_url('admin.php?page=elementor'))
    );
}

/* -------------------------------------------------------------------------- */
/* MAIN PAGE                                                                  */
/* -------------------------------------------------------------------------- */

function ppm_bulk_import_page() {
    $logo_url = plugins_url('assets/ppm-wordmark.svg', __FILE__);

    $profiles    = ppm_get_content_profiles();
    $profile_key = ppm_get_current_profile_key();
    $profile     = ppm_get_profile($profile_key);

    $cpt_placeholder = $profile['default_cpt'] !== ''
        ? 'Example: ' . $profile['default_cpt']
        : 'Example: service-areas';

    $template_url = add_query_arg(
        [
            'page'              => 'ppm-bulk-import',
            'ppm_profile'       => $profile_key,
            'download_template' => 1,
        ],
        admin_url('admin.php')
    );
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
                <span class="ppm-admin-version">v<?php echo esc_html(PPM_BULK_IMPORTER_VERSION); ?></span>
            </div>
        </header>

        <main class="ppm-admin-content">
            <?php
            // A single registered profile needs no chooser, so the screen stays
            // exactly as it was until a second page type is registered.
            if (count($profiles) > 1) :
            ?>
                <nav class="ppm-profile-tabs">
                    <?php foreach ($profiles as $key => $entry) : ?>
                        <a
                            class="ppm-profile-tab<?php echo $key === $profile_key ? ' is-active' : ''; ?>"
                            href="<?php echo esc_url(add_query_arg(['page' => 'ppm-bulk-import', 'ppm_profile' => $key], admin_url('admin.php'))); ?>"
                        ><?php echo esc_html($entry['label']); ?></a>
                    <?php endforeach; ?>
                </nav>

                <?php if ($profile['description'] !== '') : ?>
                    <p class="ppm-profile-description"><?php echo esc_html($profile['description']); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php ppm_render_template_status_notice(); ?>
            <?php ppm_render_elementor_support_notice($profile); ?>

            <div class="ppm-admin-actions">
                <section class="ppm-admin-card">
                    <div class="ppm-admin-card-icon"><span class="dashicons dashicons-database-import"></span></div>
                    <h2>Import CPT Pages</h2>
                    <p>Preview your CSV mapping, then create new pages or update existing pages by post slug.</p>

                    <form class="ppm-admin-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('ppm_bulk_import'); ?>
                        <input type="hidden" name="ppm_profile" value="<?php echo esc_attr($profile_key); ?>">

                        <div class="ppm-field">
                            <label for="ppm-import-cpt-slug">CPT Slug</label>
                            <input id="ppm-import-cpt-slug" type="text" name="cpt_slug" required value="<?php echo esc_attr($_POST['cpt_slug'] ?? ''); ?>" placeholder="<?php echo esc_attr($cpt_placeholder); ?>">
                        </div>

                        <div class="ppm-field">
                            <label for="ppm-import-csv">CSV File</label>
                            <input id="ppm-import-csv" type="file" name="csv_file" accept=".csv" required>
                        </div>

                        <div class="ppm-form-actions">
                            <input type="submit" name="preview_csv" class="button ppm-btn ppm-btn-primary" value="Preview Import">
                            <a class="button ppm-btn" href="<?php echo esc_url($template_url); ?>">Download CSV Template</a>
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
                        <input type="hidden" name="ppm_profile" value="<?php echo esc_attr($profile_key); ?>">

                        <div class="ppm-field">
                            <label for="ppm-export-cpt-slug">CPT Slug</label>
                            <input id="ppm-export-cpt-slug" type="text" name="cpt_slug" required placeholder="<?php echo esc_attr($cpt_placeholder); ?>">
                        </div>

                        <?php if ($profile['legacy_fields']) : ?>
                            <div class="ppm-field">
                                <label for="ppm-field-profile">Source Field Structure</label>
                                <select id="ppm-field-profile" name="field_profile">
                                    <option value="auto" selected>Auto-detect per page (recommended)</option>
                                    <option value="standard">Standard PPM importer fields only</option>
                                    <option value="legacy">Legacy heading/section fields only</option>
                                </select>
                            </div>

                            <p class="ppm-description">Auto-detect uses the standardized importer field when populated, then falls back to the legacy field used by older city pages.</p>
                        <?php endif; ?>

                        <div class="ppm-form-actions">
                            <input type="submit" class="button ppm-btn" value="Export Existing Pages">
                        </div>
                    </form>
                </section>
            </div>

            <?php ppm_render_template_library($profile_key, $profile); ?>
            <?php ppm_render_content_audit_card(); ?>
    <?php

    if (isset($_POST['preview_csv']) && check_admin_referer('ppm_bulk_import')) {
        echo '<div class="ppm-import-preview">';
        ppm_preview_import($_FILES['csv_file'], sanitize_text_field($_POST['cpt_slug']), $profile_key);
        echo '</div>';
    }

    if (isset($_POST['run_import']) && check_admin_referer('ppm_bulk_import')) {
        echo '<div class="ppm-import-preview">';
        ppm_run_import(
            sanitize_text_field($_POST['import_token']),
            sanitize_text_field($_POST['cpt_slug']),
            $profile_key
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
    $profile = ppm_get_profile(ppm_get_current_profile_key());

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($profile['template_file']) . '"');

    echo implode(',', ppm_profile_column_names($profile));
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

/**
 * A slug that is not a registered post type would still import: wp_insert_post()
 * accepts any string, so the rows land as posts nothing on the site can query or
 * display. Reject it on both the preview and the run instead.
 */
function ppm_validate_cpt_slug($cpt_slug) {
    if ($cpt_slug === '' || !post_type_exists($cpt_slug)) {
        printf(
            "<div class='error'><p><strong>Unknown post type.</strong> <code>%s</code> is not registered on this site, so nothing was imported. Check the CPT slug against Post Types in the admin menu.</p></div>",
            esc_html($cpt_slug !== '' ? $cpt_slug : '(empty)')
        );

        return false;
    }

    return true;
}

function ppm_preview_import($file, $cpt_slug, $profile_key = '') {
    $profile = ppm_get_profile($profile_key);

    if (!ppm_validate_cpt_slug($cpt_slug)) {
        return;
    }

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
        <input type="hidden" name="ppm_profile" value="<?php echo esc_attr($profile_key); ?>">
        <input type="hidden" name="cpt_slug" value="<?php echo esc_attr($cpt_slug); ?>">
        <input type="hidden" name="import_token" value="<?php echo esc_attr($token); ?>">

        <table class="widefat striped">
            <thead><tr><th>CSV Column</th><th>Destination</th></tr></thead>
            <tbody>
                <?php foreach ($header as $col): ?>
                    <tr>
                        <td><?php echo esc_html($col); ?></td>
                        <td><?php echo esc_html(ppm_detect_destination($col, $profile)); ?></td>
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

function ppm_detect_destination($column, $profile = null) {
    if (!is_array($profile)) {
        $profile = ppm_get_profile(ppm_get_current_profile_key());
    }

    foreach ($profile['columns'] as $definition) {
        if ($definition['name'] !== $column) {
            continue;
        }

        if ($definition['source'] === 'post') {
            return 'WordPress Post Field';
        }

        if ($definition['source'] === 'meta') {
            return strpos($definition['meta_key'], '_yoast_wpseo') === 0
                ? 'Yoast SEO Field'
                : 'Post Meta Field';
        }

        if ($definition['featured_image']) {
            return 'Image URL (ACF + Featured Image)';
        }

        if ($definition['image']) {
            return 'Image URL (string)';
        }

        return 'ACF / Post Meta';
    }

    // Columns the operator added by hand are still imported, so they still need
    // a label. Fall back to the original name-pattern guesses.
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

/**
 * Resolve an image URL to a media library attachment ID.
 *
 * The CSV carries plain URLs because attachment IDs differ from site to site.
 * Elementor's background controls only accept dynamic tags that return a media
 * object, so a URL string cannot drive a background on its own. Converting the
 * URL to a real attachment is what lets the featured-image tag do that job.
 *
 * Returns 0 when the URL is unusable or the sideload fails.
 */
function ppm_resolve_attachment_id($url, $post_id = 0) {
    static $cache = [];

    $url = trim((string) $url);

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return 0;
    }

    // Many rows in one import commonly share a hero image. Without this, each
    // row would sideload its own duplicate copy of the same file.
    if (isset($cache[$url])) {
        return $cache[$url];
    }

    $attachment_id = (int) attachment_url_to_postid($url);

    // A resized URL (…-1024x768.jpg) never matches; retry against the original.
    if (!$attachment_id) {
        $original = preg_replace('/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', $url);

        if ($original !== $url) {
            $attachment_id = (int) attachment_url_to_postid($original);
        }
    }

    if (!$attachment_id) {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $sideloaded = media_sideload_image($url, $post_id, null, 'id');
        $attachment_id = is_wp_error($sideloaded) ? 0 : (int) $sideloaded;
    }

    $cache[$url] = $attachment_id;

    return $attachment_id;
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

function ppm_export_pick_field($post_id, $standard_field, $legacy_field, $field_profile = 'auto', $is_image = false) {
    $field_profile = in_array($field_profile, ['auto', 'standard', 'legacy'], true)
        ? $field_profile
        : 'auto';

    if ($field_profile === 'standard') {
        $value = ppm_export_get_field_value($post_id, $standard_field);
    } elseif ($field_profile === 'legacy') {
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

/**
 * Read one column definition back off a post for the export sheet.
 *
 * $field_profile is the standard-vs-legacy selector from the export form, and
 * only applies to columns that declare a legacy fallback.
 */
function ppm_export_column_value($post, $column, $field_profile) {
    if ($column['source'] === 'post') {
        $post_field = $column['post_field'];

        return isset($post->$post_field) ? $post->$post_field : '';
    }

    if ($column['source'] === 'meta') {
        return get_post_meta($post->ID, $column['meta_key'], true);
    }

    if ($column['legacy'] !== '') {
        return ppm_export_pick_field(
            $post->ID,
            $column['name'],
            $column['legacy'],
            $field_profile,
            $column['image']
        );
    }

    $value = ppm_export_get_field_value($post->ID, $column['name']);

    return $column['image'] ? ppm_export_image_url($value) : $value;
}

function ppm_export_build_row($post, $columns, $field_profile) {
    $row = [];

    foreach ($columns as $column) {
        $row[] = ppm_export_column_value($post, $column, $field_profile);
    }

    return $row;
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

    $field_profile = isset($_POST['field_profile'])
        ? sanitize_key(wp_unslash($_POST['field_profile']))
        : 'auto';

    if (!in_array($field_profile, ['auto', 'standard', 'legacy'], true)) {
        $field_profile = 'auto';
    }

    $profile = ppm_get_profile(ppm_get_current_profile_key());
    $columns = $profile['columns'];

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

    $headers = ppm_profile_column_names($profile);

    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = sprintf(
        '%s-%s-%s.csv',
        sanitize_file_name($profile['export_prefix']),
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
        fputcsv($output, ppm_export_build_row($post, $columns, $field_profile));
    }

    fclose($output);
    exit;
}

/* -------------------------------------------------------------------------- */
/* IMPORT                                                                     */
/* -------------------------------------------------------------------------- */

function ppm_run_import($token, $cpt_slug, $profile_key = '') {

    if (!ppm_validate_cpt_slug($cpt_slug)) {
        return;
    }

    // Force ACF field groups to initialize in this admin context
    if (function_exists('acf_get_field_groups')) {
        acf_get_field_groups();
    }

    $profile = ppm_get_profile($profile_key);

    // Unioned across every registered profile rather than taken from the
    // selected one alone, so a sheet exported under one profile and imported
    // under another still routes each column to the right destination.
    $meta_columns     = ppm_get_all_meta_columns();
    $featured_columns = array_merge(
        ppm_get_featured_image_aliases(),
        ppm_get_all_featured_image_columns()
    );

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

        // Profile-level page settings. Both are no-ops for a profile that
        // leaves them unset, which is why city pages are untouched here.
        if ($profile['post_template'] !== '') {
            update_post_meta($post_id, '_wp_page_template', $profile['post_template']);
        }

        if ($profile['force_noindex']) {
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '1');
        }

        foreach ($row as $key => $value) {
            if (in_array($key, ['post_title', 'post_slug', 'post_status'], true)) {
                continue;
            }

            $value = ppm_prepare_csv_value($value);

            if ($value === '') {
                continue;
            }

            if (isset($meta_columns[$key])) {
                update_post_meta($post_id, $meta_columns[$key], $value);
                continue;
            }

            if (isset($featured_columns[$key])) {
                // Keep the raw URL on the ACF field so the image shortcode and
                // any URL-based markup keep working exactly as before. The
                // legacy 'featured_image' column is registered as an alias with
                // no field behind it, so it skips this write.
                if ($featured_columns[$key] === 'acf') {
                    ppm_update_acf_or_meta($post_id, $cpt_slug, $key, $value);
                }

                // Also set the real WP featured image, which is what Elementor's
                // post-featured-image dynamic tag reads to drive the hero
                // background.
                $attachment_id = ppm_resolve_attachment_id($value, $post_id);

                if ($attachment_id) {
                    set_post_thumbnail($post_id, $attachment_id);
                }

                continue;
            }

            ppm_update_acf_or_meta($post_id, $cpt_slug, $key, $value);
        }
    }

    echo "<div class='updated'><p><strong>Import complete.</strong> Created: {$created} | Updated: {$updated} | Skipped: {$skipped}</p></div>";
}
