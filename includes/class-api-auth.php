<?php
/**
 * Phoenix Agentic CRM - API Authentication Handler
 *
 * Handles API key-based authentication using X-Phoenix-Key header.
 * Stores the API key in wp_options under the 'phoenix_api_key' option.
 * Provides an admin settings page for generating/regenerating the key.
 *
 * @package    Phoenix_Agentic_CRM
 * @subpackage API
 * @version    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_API_Auth
 *
 * Manages API key generation, validation, and the admin settings UI.
 */
class Phoenix_CRM_API_Auth {

    /**
     * Option name for the API key in wp_options.
     */
    const OPTION_KEY = 'phoenix_api_key';

    /**
     * The header name used to pass the API key on requests.
     */
    const AUTH_HEADER = 'X-Phoenix-Key';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — hooks into WordPress admin and REST API.
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_phoenix_regenerate_key', array( $this, 'handle_regenerate_key' ) );
    }

    /**
     * Add the settings page under Settings menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Phoenix CRM API', 'phoenix-agentic-crm' ),
            __( 'Phoenix API', 'phoenix-agentic-crm' ),
            'manage_options',
            'phoenix-api-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register the option in the whitelist.
     */
    public function register_settings() {
        register_setting( 'phoenix_api_settings', self::OPTION_KEY, array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
            'show_in_rest'      => false,
        ) );
    }

    /**
     * Handle key regeneration via admin_post action.
     */
    public function handle_regenerate_key() {
        // Verify nonce and capability.
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'phoenix_regenerate_key' ) ) {
            wp_die( __( 'Security check failed.', 'phoenix-agentic-crm' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions.', 'phoenix-agentic-crm' ) );
        }

        // Generate a new key and save it.
        $new_key = self::generate_key();
        update_option( self::OPTION_KEY, $new_key, false );

        // Redirect back with a success flag.
        wp_safe_redirect( add_query_arg( 'phoenix_key_regenerated', '1', admin_url( 'options-general.php?page=phoenix-api-settings' ) ) );
        exit;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $current_key = get_option( self::OPTION_KEY, '' );
        $regenerated = isset( $_GET['phoenix_key_regenerated'] ) && '1' === $_GET['phoenix_key_regenerated'];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Phoenix CRM API Settings', 'phoenix-agentic-crm' ); ?></h1>

            <?php if ( $regenerated ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'API key regenerated successfully. The old key is no longer valid.', 'phoenix-agentic-crm' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'phoenix_api_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="phoenix_api_key"><?php esc_html_e( 'Current API Key', 'phoenix-agentic-crm' ); ?></label>
                        </th>
                        <td>
                            <?php if ( ! empty( $current_key ) ) : ?>
                                <code id="phoenix_api_key" style="font-size:1.1em;padding:4px 8px;"><?php echo esc_html( $current_key ); ?></code>
                                <p class="description">
                                    <?php esc_html_e( 'Use this key in the X-Phoenix-Key header for all API requests.', 'phoenix-agentic-crm' ); ?>
                                </p>
                            <?php else : ?>
                                <p><em><?php esc_html_e( 'No API key set. Generate one below.', 'phoenix-agentic-crm' ); ?></em></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Key', 'phoenix-agentic-crm' ) ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Regenerate API Key', 'phoenix-agentic-crm' ); ?></h2>
            <p><?php esc_html_e( 'Generating a new key will invalidate the current one immediately. All integrations using the old key will stop working until updated.', 'phoenix-agentic-crm' ); ?></p>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phoenix_regenerate_key' ), 'phoenix_regenerate_key' ) ); ?>" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to regenerate the API key? This will break any active integrations.', 'phoenix-agentic-crm' ); ?>');">
                <?php esc_html_e( 'Regenerate Key', 'phoenix-agentic-crm' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Authenticate the current REST request.
     *
     * Reads the X-Phoenix-Key header and compares it against the stored key.
     * Called as a callback in register_rest_route() via the 'permission_callback' argument.
     *
     * @param WP_REST_Request $request The incoming request object.
     * @return bool True if the key matches, false otherwise.
     */
    public static function authenticate_request( $request ) {
        $header_key = $request->get_header( self::AUTH_HEADER );

        if ( empty( $header_key ) ) {
            return false;
        }

        $stored_key = get_option( self::OPTION_KEY, '' );

        if ( empty( $stored_key ) ) {
            return false;
        }

        return hash_equals( $stored_key, $header_key );
    }

    /**
     * Generate a cryptographically secure API key.
     *
     * Uses random_bytes() for modern PHP or openssl_random_pseudo_bytes() as fallback.
     * Returns a 64-character hex string (256-bit entropy).
     *
     * @return string The generated API key.
     */
    public static function generate_key() {
        try {
            $bytes = random_bytes( 32 );
        } catch ( Exception $e ) {
            $bytes = openssl_random_pseudo_bytes( 32 );
        }
        return bin2hex( $bytes );
    }

    /**
     * Get the stored API key (masked for display).
     *
     * @return string Masked key, e.g. "abcd••••••••"
     */
    public static function get_masked_key() {
        $key = get_option( self::OPTION_KEY, '' );
        if ( empty( $key ) ) {
            return '';
        }
        return substr( $key, 0, 4 ) . str_repeat( '•', max( 0, strlen( $key ) - 4 ) );
    }
}