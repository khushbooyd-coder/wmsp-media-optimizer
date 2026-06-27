<?php
/**
 * Plugin Name:       Media Optimizer — Image & Video Speed
 * Plugin URI:        https://github.com/khushbooyd-coder/wmsp-media-optimizer
 * Description:       Automatically compress images, generate WebP, lazy load images & videos, and replace YouTube/Vimeo embeds with lightweight thumbnails. No external service or API required.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            The Herb Company
 * Author URI:        https://theherbcompany.co.in
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wmsp-media-optimizer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── PHP version check — show admin notice instead of crashing ─────────────
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>Media Optimizer:</strong> Requires PHP 7.4 or higher. '
            . 'Your server is running PHP ' . PHP_VERSION . '. Please contact your host to upgrade.'
            . '</p></div>';
    });
    return;
}

// ── WordPress version check ───────────────────────────────────────────────
if ( version_compare( get_bloginfo('version'), '5.8', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>Media Optimizer:</strong> Requires WordPress 5.8 or higher. '
            . 'Please update WordPress first.'
            . '</p></div>';
    });
    return;
}

// ── Emergency bypass — visit /?mo_bypass=1 to disable plugin instantly ────
if ( isset( $_GET['mo_bypass'] ) ) {
    if ( ! headers_sent() ) setcookie( 'mo_bypass', '1', 0, COOKIEPATH, COOKIE_DOMAIN );
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-warning"><p>'
            . '🆘 <strong>Media Optimizer bypass active.</strong> The plugin is disabled for your browser session. '
            . '<a href="' . esc_url( remove_query_arg('mo_bypass') ) . '">Re-enable</a>'
            . '</p></div>';
    });
    return;
}
if ( isset( $_COOKIE['mo_bypass'] ) ) return;

define( 'WMSP_MO_VERSION', '1.4.0' );
define( 'WMSP_MO_FILE',    __FILE__ );
define( 'WMSP_MO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WMSP_MO_URL',     plugin_dir_url( __FILE__ ) );

// ── Load modules ──────────────────────────────────────────────────────────
require_once WMSP_MO_DIR . 'includes/class-image-compressor.php';
require_once WMSP_MO_DIR . 'includes/class-video-optimizer.php';
require_once WMSP_MO_DIR . 'admin/class-admin.php';

/**
 * Main plugin singleton.
 */
final class WMSP_Media_Optimizer {

    private static ?self $instance = null;

    public WMSP_Image_Compressor $images;
    public WMSP_Video_Optimizer  $videos;

    public static function get(): self {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->images = new WMSP_Image_Compressor();
        $this->videos = new WMSP_Video_Optimizer();

        if ( is_admin() ) new WMSP_MO_Admin( $this );

        add_action( 'init', [ $this, 'boot' ] );

        register_activation_hook(   WMSP_MO_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( WMSP_MO_FILE, [ $this, 'deactivate' ] );
    }

    public function boot(): void {
        $o = $this->get_options();
        if ( $o['compress_on_upload'] ) $this->images->hook_upload();
        if ( $o['image_lazy'] )         $this->images->hook_lazy();
        if ( $o['video_facade'] )       $this->videos->hook_facade();
        if ( $o['video_lazy'] )         $this->videos->hook_native_lazy();
    }

    public function get_options(): array {
        return wp_parse_args( get_option( 'wmsp_mo_options', [] ), [
            'compress_on_upload' => 1,
            'image_quality'      => 80,
            'image_lazy'         => 1,
            'generate_webp'      => 1,
            'video_facade'       => 1,
            'video_lazy'         => 1,
        ]);
    }

    public function save_options( array $new ): void {
        update_option( 'wmsp_mo_options', [
            'compress_on_upload' => ! empty( $new['compress_on_upload'] ) ? 1 : 0,
            'image_quality'      => max( 40, min( 95, (int)( $new['image_quality'] ?? 80 ) ) ),
            'image_lazy'         => ! empty( $new['image_lazy'] ) ? 1 : 0,
            'generate_webp'      => ! empty( $new['generate_webp'] ) ? 1 : 0,
            'video_facade'       => ! empty( $new['video_facade'] ) ? 1 : 0,
            'video_lazy'         => ! empty( $new['video_lazy'] ) ? 1 : 0,
        ]);
    }

    public function activate(): void {
        // Set defaults only on first install
        if ( ! get_option('wmsp_mo_options') ) {
            add_option( 'wmsp_mo_options', [
                'compress_on_upload' => 1,
                'image_quality'      => 80,
                'image_lazy'         => 1,
                'generate_webp'      => 1,
                'video_facade'       => 1,
                'video_lazy'         => 1,
            ]);
        }
        // Show welcome notice
        set_transient( 'wmsp_mo_activated', 1, 30 );
    }

    public function deactivate(): void {
        // Clean up bulk progress if interrupted
        delete_option( 'wmsp_mo_bulk_run' );
    }
}

function wmsp_mo(): WMSP_Media_Optimizer {
    return WMSP_Media_Optimizer::get();
}
wmsp_mo();
