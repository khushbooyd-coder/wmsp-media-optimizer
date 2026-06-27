<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMSP_MO_Admin {

    private WMSP_Media_Optimizer $plugin;

    public function __construct( WMSP_Media_Optimizer $plugin ) {
        $this->plugin = $plugin;
        add_action( 'admin_menu',            [ $this, 'menu' ] );
        add_action( 'admin_notices',         [ $this, 'welcome_notice' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );

        // Keep AJAX for JS-based interactions (save, stats)
        add_action( 'wp_ajax_mo_save',      [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_mo_clear_log', [ $this, 'ajax_clear_log' ] );

        // Handle clear log from server-side form
        add_action( 'admin_init', [ $this, 'handle_clear_log' ] );
    }

    public function menu(): void {
        add_menu_page(
            'Media Optimizer', 'Media Optimizer', 'manage_options',
            'mo-optimizer', [ $this, 'page' ],
            'dashicons-images-alt2', 85
        );
    }

    public function assets( string $hook ): void {
        if ( strpos( $hook, 'mo-optimizer' ) === false ) return;
        wp_enqueue_style(  'mo-admin', WMSP_MO_URL . 'assets/css/admin.css', [], WMSP_MO_VERSION );
        // JS now only used for quality slider display — no AJAX bulk
        wp_enqueue_script( 'mo-admin', WMSP_MO_URL . 'assets/js/admin.js', [], WMSP_MO_VERSION, true );
    }

    public function page(): void {
        include WMSP_MO_DIR . 'admin/views/main.php';
    }

    public function welcome_notice(): void {
        if ( ! get_transient('wmsp_mo_activated') ) return;
        delete_transient('wmsp_mo_activated');
        echo '<div class="notice notice-success is-dismissible"><p>'
            . '🌿 <strong>Media Optimizer activated!</strong> '
            . '<a href="' . esc_url( admin_url('admin.php?page=mo-optimizer') ) . '">Open Media Optimizer →</a> to start compressing your images.'
            . '</p></div>';
    }

    public function handle_clear_log(): void {
        if ( ! isset( $_POST['mo_action'] ) || $_POST['mo_action'] !== 'clear_log' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'mo_clear_log_nonce' );
        delete_option( 'wmsp_mo_image_log' );
        wp_redirect( admin_url( 'admin.php?page=mo-optimizer&log_cleared=1' ) );
        exit;
    }

    private function verify(): void {
        check_ajax_referer( 'mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );
    }

    public function ajax_save(): void {
        check_ajax_referer( 'mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );
        $this->plugin->save_options( $_POST['options'] ?? [] );
        wp_send_json_success( [ 'msg' => 'Saved.' ] );
    }

    public function ajax_clear_log(): void {
        check_ajax_referer( 'mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );
        delete_option( 'wmsp_mo_image_log' );
        wp_send_json_success();
    }
}
