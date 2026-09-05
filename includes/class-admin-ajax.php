<?php
/**
 * Phoenix Agentic CRM - Admin AJAX Handlers
 *
 * Registers wp_ajax_* handlers for proposal actions (approve, reject,
 * archive) and API key regeneration. Each handler verifies nonce +
 * capability and returns JSON.
 *
 * @package    Phoenix_Agentic_CRM
 * @subpackage Admin
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Class Phoenix_CRM_Admin_AJAX
 *
 * Static class that registers and handles all admin AJAX actions.
 *
 * @since 1.0.0
 */
class Phoenix_CRM_Admin_AJAX {

    /**
     * Register all AJAX handlers.
     *
     * Call once, typically on admin_init or plugins_loaded.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_hooks() {
        // Proposal single-row actions.
        add_action( 'wp_ajax_phoenix_approve_proposal', array( __CLASS__, 'handle_approve_proposal' ) );
        add_action( 'wp_ajax_phoenix_reject_proposal',  array( __CLASS__, 'handle_reject_proposal' ) );
        add_action( 'wp_ajax_phoenix_archive_proposal', array( __CLASS__, 'handle_archive_proposal' ) );

        // API key regeneration.
        add_action( 'wp_ajax_phoenix_regenerate_key',   array( __CLASS__, 'handle_regenerate_key' ) );
    }

    /**
     * Verify the AJAX request nonce and capability.
     *
     * Sends a JSON error and exits on failure.
     *
     * @since 1.0.0
     * @return void
     */
    private static function verify_request() {
        // Verify nonce.
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'phoenix_ajax_nonce' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'phoenix-crm' ),
            ) );
        }

        // Verify capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'phoenix-crm' ),
            ) );
        }
    }

    /**
     * AJAX handler — approve a single proposal.
     *
     * Expects: proposal_id (int), _wpnonce.
     *
     * @since 1.0.0
     * @return void
     */
    public static function handle_approve_proposal() {
        self::verify_request();

        $proposal_id = isset( $_REQUEST['proposal_id'] ) ? absint( $_REQUEST['proposal_id'] ) : 0;

        if ( $proposal_id <= 0 ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid proposal ID.', 'phoenix-crm' ),
            ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_proposals';

        // Fetch the proposal to verify it exists and is pending.
        $proposal = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, status FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $proposal_id
        ) );

        if ( ! $proposal ) {
            wp_send_json_error( array(
                'message' => __( 'Proposal not found.', 'phoenix-crm' ),
            ) );
        }

        if ( 'pending' !== $proposal->status ) {
            wp_send_json_error( array(
                'message' => __( 'Only pending proposals can be approved.', 'phoenix-crm' ),
            ) );
        }

        $updated = $wpdb->update(
            $table,
            array(
                'status'     => 'approved',
                'decided_at' => current_time( 'mysql' ),
                'executed'   => 1,
            ),
            array( 'id' => $proposal_id ),
            array( '%s', '%s', '%d' ),
            array( '%d' )
        );

        if ( false === $updated || 0 === $updated ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to approve proposal.', 'phoenix-crm' ),
            ) );
        }

        // Log the event.
        do_action(
            'phoenix_event_logged',
            0,
            'user',
            'proposal_approved',
            'proposal',
            $proposal_id,
            sprintf(
                /* translators: %s: proposal title */
                __( 'Proposal approved via AJAX — %s', 'phoenix-crm' ),
                $proposal->title
            )
        );

        /**
         * Fires when a proposal is approved via AJAX.
         *
         * @since 1.0.0
         * @param int    $proposal_id The approved proposal ID.
         * @param string $title       The proposal title.
         */
        do_action( 'phoenix_proposal_decided', $proposal_id, 'approved', '' );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: proposal title */
                __( 'Proposal "%s" approved.', 'phoenix-crm' ),
                $proposal->title
            ),
            'proposal_id' => $proposal_id,
            'new_status'  => 'approved',
        ) );
    }

    /**
     * AJAX handler — reject a single proposal.
     *
     * Expects: proposal_id (int), _wpnonce.
     *
     * @since 1.0.0
     * @return void
     */
    public static function handle_reject_proposal() {
        self::verify_request();

        $proposal_id = isset( $_REQUEST['proposal_id'] ) ? absint( $_REQUEST['proposal_id'] ) : 0;

        if ( $proposal_id <= 0 ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid proposal ID.', 'phoenix-crm' ),
            ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_proposals';

        $proposal = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, status FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $proposal_id
        ) );

        if ( ! $proposal ) {
            wp_send_json_error( array(
                'message' => __( 'Proposal not found.', 'phoenix-crm' ),
            ) );
        }

        if ( 'pending' !== $proposal->status ) {
            wp_send_json_error( array(
                'message' => __( 'Only pending proposals can be rejected.', 'phoenix-crm' ),
            ) );
        }

        $updated = $wpdb->update(
            $table,
            array(
                'status'     => 'rejected',
                'decided_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $proposal_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated || 0 === $updated ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to reject proposal.', 'phoenix-crm' ),
            ) );
        }

        // Log the event.
        do_action(
            'phoenix_event_logged',
            0,
            'user',
            'proposal_rejected',
            'proposal',
            $proposal_id,
            sprintf(
                /* translators: %s: proposal title */
                __( 'Proposal rejected via AJAX — %s', 'phoenix-crm' ),
                $proposal->title
            )
        );

        do_action( 'phoenix_proposal_decided', $proposal_id, 'rejected', '' );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: proposal title */
                __( 'Proposal "%s" rejected.', 'phoenix-crm' ),
                $proposal->title
            ),
            'proposal_id' => $proposal_id,
            'new_status'  => 'rejected',
        ) );
    }

    /**
     * AJAX handler — archive a single proposal.
     *
     * Expects: proposal_id (int), _wpnonce.
     *
     * @since 1.0.0
     * @return void
     */
    public static function handle_archive_proposal() {
        self::verify_request();

        $proposal_id = isset( $_REQUEST['proposal_id'] ) ? absint( $_REQUEST['proposal_id'] ) : 0;

        if ( $proposal_id <= 0 ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid proposal ID.', 'phoenix-crm' ),
            ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_proposals';

        $proposal = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, status, archived FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $proposal_id
        ) );

        if ( ! $proposal ) {
            wp_send_json_error( array(
                'message' => __( 'Proposal not found.', 'phoenix-crm' ),
            ) );
        }

        if ( (int) $proposal->archived === 1 ) {
            wp_send_json_error( array(
                'message' => __( 'Proposal is already archived.', 'phoenix-crm' ),
            ) );
        }

        $updated = $wpdb->update(
            $table,
            array( 'archived' => 1 ),
            array( 'id' => $proposal_id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( false === $updated || 0 === $updated ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to archive proposal.', 'phoenix-crm' ),
            ) );
        }

        // Log the event.
        do_action(
            'phoenix_event_logged',
            0,
            'user',
            'proposal_archived',
            'proposal',
            $proposal_id,
            sprintf(
                /* translators: %s: proposal title */
                __( 'Proposal archived via AJAX — %s', 'phoenix-crm' ),
                $proposal->title
            )
        );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: proposal title */
                __( 'Proposal "%s" archived.', 'phoenix-crm' ),
                $proposal->title
            ),
            'proposal_id' => $proposal_id,
            'new_status'  => 'archived',
        ) );
    }

    /**
     * AJAX handler — regenerate the API key.
     *
     * Expects: _wpnonce.
     *
     * @since 1.0.0
     * @return void
     */
    public static function handle_regenerate_key() {
        self::verify_request();

        /**
         * Generate a new random API key.
         * Uses wp_generate_password() with 40 characters and special chars
         * for maximum entropy, then applies a unique prefix.
         */
        $raw_key = 'phx_' . wp_generate_password( 40, true, false );

        // Hash the key for storage (like how WordPress salts work).
        $hashed_key = wp_hash( $raw_key );

        // Store the hashed key.
        $updated = update_option( 'phoenix_crm_api_key', $hashed_key, false );

        if ( ! $updated ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to regenerate API key.', 'phoenix-crm' ),
            ) );
        }

        // Log the event.
        do_action(
            'phoenix_event_logged',
            0,
            'user',
            'api_key_regenerated',
            'system',
            0,
            __( 'API key regenerated via admin AJAX.', 'phoenix-crm' )
        );

        wp_send_json_success( array(
            'message' => __( 'API key regenerated successfully.', 'phoenix-crm' ),
            'api_key' => $raw_key,
        ) );
    }
}