<?php
/**
 * Phoenix CRM Admin Settings
 *
 * Renders the consolidated Settings page showing API key status,
 * plugin version, and database table health.
 *
 * @package Phoenix_CRM
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Admin_Settings
 *
 * Displays CRM configuration: API key with regenerate, plugin metadata,
 * and database table status.
 */
class Phoenix_CRM_Admin_Settings {

    /**
     * Nonce action for key regeneration.
     *
     * @var string
     */
    const REGENERATE_NONCE_ACTION = 'phoenix_crm_admin_regenerate_key';

    /**
     * Nonce name for key regeneration.
     *
     * @var string
     */
    const REGENERATE_NONCE_NAME = 'phoenix_crm_admin_regenerate_key_nonce';

    /**
     * Option name for the API key (mirrors Phoenix_CRM_API_Auth).
     *
     * @var string
     */
    const API_KEY_OPTION = 'phoenix_api_key';

    /**
     * Render the full Settings page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'phoenix-crm' ) );
        }

        $version       = defined( 'PHOENIX_CRM_VERSION' ) ? PHOENIX_CRM_VERSION : '—';
        $api_key       = get_option( self::API_KEY_OPTION, '' );
        $db_version    = get_option( Phoenix_CRM_Database::DB_VERSION_KEY, '' );
        $tables        = Phoenix_CRM_Database::get_table_names();
        $table_status  = self::check_tables( $tables );
        $all_tables_ok = ! in_array( false, $table_status, true );

        // Handle regeneration success message.
        $regenerated = isset( $_GET['phoenix_crm_key_regenerated'] ) && '1' === $_GET['phoenix_crm_key_regenerated'];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Phoenix CRM Settings', 'phoenix-crm' ); ?></h1>

            <?php if ( $regenerated ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'API key regenerated successfully. The previous key is no longer valid.', 'phoenix-crm' ); ?></p>
                </div>
            <?php endif; ?>

            <hr />

            <!-- Plugin Information -->
            <h2><?php esc_html_e( 'Plugin Information', 'phoenix-crm' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Plugin Version', 'phoenix-crm' ); ?></th>
                    <td><code><?php echo esc_html( $version ); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Database Schema Version', 'phoenix-crm' ); ?></th>
                    <td>
                        <?php if ( ! empty( $db_version ) ) : ?>
                            <code><?php echo esc_html( $db_version ); ?></code>
                        <?php else : ?>
                            <em><?php esc_html_e( 'Not installed', 'phoenix-crm' ); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <!-- API Key -->
            <h2><?php esc_html_e( 'API Key', 'phoenix-crm' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current API Key', 'phoenix-crm' ); ?></th>
                    <td>
                        <?php if ( ! empty( $api_key ) ) : ?>
                            <code id="phoenix-crm-api-key-display" style="font-size:1.1em;padding:4px 8px;word-break:break-all;"><?php echo esc_html( $api_key ); ?></code>
                            <p class="description">
                                <?php esc_html_e( 'Pass this key in the X-Phoenix-Key header for API requests.', 'phoenix-crm' ); ?>
                            </p>
                        <?php else : ?>
                            <p><em><?php esc_html_e( 'No API key set. Generate one below.', 'phoenix-crm' ); ?></em></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Regenerate Key', 'phoenix-crm' ); ?></th>
                    <td>
                        <p><?php esc_html_e( 'Generating a new key immediately invalidates the current one. All integrations using the old key will stop working.', 'phoenix-crm' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                            <?php wp_nonce_field( self::REGENERATE_NONCE_ACTION, self::REGENERATE_NONCE_NAME ); ?>
                            <input type="hidden" name="action" value="phoenix_crm_admin_regenerate_key" />
                            <?php submit_button(
                                __( 'Regenerate API Key', 'phoenix-crm' ),
                                'secondary',
                                'phoenix_crm_regenerate_key_submit',
                                false,
                                array(
                                    'onclick' => 'return confirm(\'' .
                                        esc_js( __( 'Are you sure? This will break any active integrations using the current key.', 'phoenix-crm' ) ) .
                                        '\');',
                                )
                            ); ?>
                        </form>
                    </td>
                </tr>
            </table>

            <!-- Database Tables Status -->
            <h2><?php esc_html_e( 'Database Status', 'phoenix-crm' ); ?></h2>
            <?php if ( $all_tables_ok ) : ?>
                <div class="notice notice-success inline" style="margin:10px 0;">
                    <p><?php esc_html_e( 'All custom database tables are present.', 'phoenix-crm' ); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning inline" style="margin:10px 0;">
                    <p><?php esc_html_e( 'Some custom database tables are missing. Re-activate the plugin to install the schema.', 'phoenix-crm' ); ?></p>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Table Name', 'phoenix-crm' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Status', 'phoenix-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tables as $i => $table_name ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $table_name ); ?></code></td>
                            <td>
                                <?php if ( $table_status[ $i ] ) : ?>
                                    <span style="color:#2c7a3e;">&#10003; <?php esc_html_e( 'Exists', 'phoenix-crm' ); ?></span>
                                <?php else : ?>
                                    <span style="color:#b2621a;">&#10007; <?php esc_html_e( 'Missing', 'phoenix-crm' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr style="margin-top:40px;" />
            <p class="description" style="text-align:right;">
                <?php
                printf(
                    /* translators: %s: plugin version number */
                    esc_html__( 'Phoenix Agentic CRM v%s', 'phoenix-crm' ),
                    esc_html( $version )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Handle the API key regeneration POST request.
     *
     * Verifies nonce and capability, generates a new key, saves it,
     * then redirects back to the settings page with a success flag.
     *
     * @return void
     */
    public static function handle_regenerate_key() {
        // Verify nonce.
        if ( ! isset( $_POST[ self::REGENERATE_NONCE_NAME ] )
            || ! wp_verify_nonce( $_POST[ self::REGENERATE_NONCE_NAME ], self::REGENERATE_NONCE_ACTION )
        ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        // Verify capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'phoenix-crm' ) );
        }

        // Verify the action.
        if ( ! isset( $_POST['action'] ) || 'phoenix_crm_admin_regenerate_key' !== $_POST['action'] ) {
            wp_die( esc_html__( 'Invalid request.', 'phoenix-crm' ) );
        }

        // Generate and save.
        if ( class_exists( 'Phoenix_CRM_API_Auth' ) && method_exists( 'Phoenix_CRM_API_Auth', 'generate_key' ) ) {
            $new_key = Phoenix_CRM_API_Auth::generate_key();
        } else {
            try {
                $bytes = random_bytes( 32 );
            } catch ( Exception $e ) {
                $bytes = openssl_random_pseudo_bytes( 32 );
            }
            $new_key = bin2hex( $bytes );
        }

        update_option( self::API_KEY_OPTION, $new_key, false );

        // Redirect back.
        wp_safe_redirect(
            add_query_arg(
                'phoenix_crm_key_regenerated',
                '1',
                admin_url( 'admin.php?page=phoenix-crm-settings' )
            )
        );
        exit;
    }

    /**
     * Check whether each table exists in the database.
     *
     * @param string[] $tables Fully-qualified table names.
     * @return bool[] Indexed array matching $tables order.
     */
    private static function check_tables( $tables ) {
        global $wpdb;

        $status = array();
        foreach ( $tables as $table_name ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    'SHOW TABLES LIKE %s',
                    $table_name
                )
            );
            $status[] = strtolower( $result ) === strtolower( $table_name );
        }

        return $status;
    }
}