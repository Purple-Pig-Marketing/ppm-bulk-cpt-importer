<?php
/**
 * Before/after image comparison.
 *
 * Deliberately not a third-party widget. The one available here belongs to a
 * Crocoblock addon, which is not installed on every client site — a template
 * that depends on it renders an empty section wherever it is missing — and its
 * styling is only adjustable as far as its own controls allow, which was not far
 * enough. Two stacked images and a draggable divider is a small enough thing to
 * own outright, and owning it means the markup and the CSS are ours.
 *
 * Rendered through a shortcode rather than a dynamic tag because the comparison
 * needs four values at once (two images, two labels) and a dynamic tag supplies
 * one value to one control.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * [ppm_before_after before="field_name" after="field_name"]
 *
 * Reads ACF image fields on the current post. Renders nothing at all when
 * either image is missing, so an unfilled comparison leaves no broken frame
 * behind on a live page.
 */
function ppm_before_after_shortcode($atts) {
    $atts = shortcode_atts([
        'before'       => '',
        'after'        => '',
        'before_label' => 'Before',
        'after_label'  => 'After',
        'class'        => '',
    ], $atts, 'ppm_before_after');

    $before = ppm_before_after_image_url($atts['before']);
    $after  = ppm_before_after_image_url($atts['after']);

    if ($before === '' || $after === '') {
        return '';
    }

    // The label fields are optional; falling back to the defaults keeps a
    // half-filled row usable rather than unlabelled.
    $before_label = $atts['before_label'] !== '' ? $atts['before_label'] : 'Before';
    $after_label  = $atts['after_label'] !== '' ? $atts['after_label'] : 'After';

    ob_start();
    ?>
    <div class="ppm-ba <?php echo esc_attr($atts['class']); ?>" data-ppm-ba>
        <div class="ppm-ba__frame">
            <img class="ppm-ba__img ppm-ba__img--after" src="<?php echo esc_url($after); ?>" alt="<?php echo esc_attr($after_label); ?>" loading="lazy">
            <div class="ppm-ba__clip">
                <img class="ppm-ba__img ppm-ba__img--before" src="<?php echo esc_url($before); ?>" alt="<?php echo esc_attr($before_label); ?>" loading="lazy">
            </div>

            <span class="ppm-ba__label ppm-ba__label--before"><?php echo esc_html($before_label); ?></span>
            <span class="ppm-ba__label ppm-ba__label--after"><?php echo esc_html($after_label); ?></span>

            <div class="ppm-ba__handle" aria-hidden="true">
                <span class="ppm-ba__grip"></span>
            </div>

            <?php
            // A range input is the control, positioned over the frame and made
            // invisible. It brings keyboard support, touch and accessibility for
            // free, where a bare mousedown handler would have none of them.
            ?>
            <input
                class="ppm-ba__range"
                type="range"
                min="0"
                max="100"
                value="50"
                step="0.1"
                aria-label="<?php echo esc_attr(sprintf('Reveal %s or %s', $before_label, $after_label)); ?>"
            >
        </div>
    </div>
    <?php

    return trim(ob_get_clean());
}
add_shortcode('ppm_before_after', 'ppm_before_after_shortcode');

/**
 * The URL behind an ACF image field, whichever return format it uses.
 */
function ppm_before_after_image_url($field) {
    if ($field === '') {
        return '';
    }

    $value = function_exists('get_field')
        ? get_field($field)
        : get_post_meta(get_the_ID(), $field, true);

    if (is_array($value)) {
        return !empty($value['url']) ? $value['url'] : '';
    }

    if (is_numeric($value)) {
        $url = wp_get_attachment_image_url((int) $value, 'large');

        return $url ? $url : '';
    }

    return is_string($value) ? $value : '';
}

/**
 * Styles and behavior, loaded only where a comparison can appear.
 *
 * Kept in the plugin rather than in the template's own stylesheet so it can be
 * corrected in a release without regenerating and reinstalling the template.
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_singular(array_values(ppm_noindexed_post_types()) ?: ['ppc-landing'])) {
        return;
    }

    wp_register_style('ppm-before-after', false, [], PPM_BULK_IMPORTER_VERSION);
    wp_enqueue_style('ppm-before-after');
    wp_add_inline_style('ppm-before-after', ppm_before_after_css());

    wp_register_script('ppm-before-after', '', [], PPM_BULK_IMPORTER_VERSION, true);
    wp_enqueue_script('ppm-before-after');
    wp_add_inline_script('ppm-before-after', ppm_before_after_js());
});

function ppm_before_after_css() {
    return <<<CSS
.ppm-ba{width:100%}
.ppm-ba__frame{position:relative;overflow:hidden;border-radius:10px;
  box-shadow:0 4px 16px rgba(0,0,0,.13);line-height:0;isolation:isolate}
.ppm-ba__img{display:block;width:100%;height:auto;aspect-ratio:4/3;object-fit:cover}
/* The before image is clipped from the right, so the divider position and the
   visible width are the same number and cannot drift apart. */
.ppm-ba__clip{position:absolute;inset:0;width:50%;overflow:hidden}
.ppm-ba__clip .ppm-ba__img{width:100vw;max-width:none}
.ppm-ba__label{position:absolute;bottom:12px;z-index:2;
  padding:5px 11px;border-radius:100px;
  font:600 12px/1.2 inherit;letter-spacing:.4px;text-transform:uppercase;
  color:#fff;background:rgba(0,0,0,.62);backdrop-filter:blur(2px);pointer-events:none}
.ppm-ba__label--before{left:12px}
.ppm-ba__label--after{right:12px}
.ppm-ba__handle{position:absolute;top:0;bottom:0;left:50%;z-index:3;
  width:2px;margin-left:-1px;background:#fff;
  box-shadow:0 0 6px rgba(0,0,0,.4);pointer-events:none}
.ppm-ba__grip{position:absolute;top:50%;left:50%;
  width:44px;height:44px;margin:-22px 0 0 -22px;border-radius:100px;
  background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.34);
  display:flex;align-items:center;justify-content:center}
/* Two arrows drawn with borders, so the grip needs no icon font or SVG. */
.ppm-ba__grip::before,.ppm-ba__grip::after{content:"";position:absolute;
  width:0;height:0;border-top:5px solid transparent;border-bottom:5px solid transparent}
.ppm-ba__grip::before{left:13px;border-right:7px solid #1c1c1c}
.ppm-ba__grip::after{right:13px;border-left:7px solid #1c1c1c}
/* The control is a real range input laid over the frame and made invisible:
   keyboard, touch and screen readers all work without writing any of it. */
.ppm-ba__range{position:absolute;inset:0;z-index:4;width:100%;height:100%;
  margin:0;padding:0;opacity:0;cursor:ew-resize;appearance:none;-webkit-appearance:none;
  background:transparent}
.ppm-ba__range::-webkit-slider-thumb{appearance:none;-webkit-appearance:none;
  width:44px;height:100%;cursor:ew-resize}
.ppm-ba__range::-moz-range-thumb{width:44px;height:100%;border:0;
  background:transparent;cursor:ew-resize}
.ppm-ba__range:focus-visible{outline:3px solid #2D7B9C;outline-offset:3px}
@media(max-width:767px){
  .ppm-ba__label{font-size:11px;padding:4px 9px;bottom:9px}
  .ppm-ba__label--before{left:9px}.ppm-ba__label--after{right:9px}
  .ppm-ba__grip{width:38px;height:38px;margin:-19px 0 0 -19px}
}
CSS;
}

function ppm_before_after_js() {
    return <<<JS
(function () {
    'use strict';

    function paint(root, value) {
        var clip = root.querySelector('.ppm-ba__clip');
        var handle = root.querySelector('.ppm-ba__handle');

        if (clip) { clip.style.width = value + '%'; }
        if (handle) { handle.style.left = value + '%'; }
    }

    function bind(root) {
        // Elementor re-runs its frontend init and can call this more than once.
        // Without the flag every pass would add another listener, and the
        // handler would run as many times as the page had booted.
        if (root.__ppmBa) { return; }
        root.__ppmBa = true;

        var range = root.querySelector('.ppm-ba__range');
        if (!range) { return; }

        paint(root, range.value);
        range.addEventListener('input', function () { paint(root, range.value); });
    }

    function boot() {
        document.querySelectorAll('[data-ppm-ba]').forEach(bind);
    }

    // Inline scripts in the body usually run after DOMContentLoaded has already
    // fired, so binding only to that event would leave every comparison stuck at
    // its starting position.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // Elementor rebuilds widgets after its own init; boot is idempotent.
    if (window.jQuery) {
        jQuery(window).on('elementor/frontend/init', function () {
            setTimeout(boot, 300);
        });
    }
})();
JS;
}
