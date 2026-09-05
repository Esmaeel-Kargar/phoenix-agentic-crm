<?php
/**
 * Phoenix Agentic CRM - Proposal Manager
 *
 * Static helper class for managing proposals (create, decide, archive, stats)
 * in the wp_phoenix_proposals table.
 *
 * @package    Phoenix_Agentic_CRM
 * @subpackage includes
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Class Phoenix_CRM_Proposal_Manager
 *
 * Provides a static interface for the full lifecycle of proposals,
 * from creation through decision, archiving, and learning-data export.
 * All queries use $wpdb->prepare() for SQL injection protection.
 */
class Phoenix_CRM_Proposal_Manager {

    /**
     * Get the full proposals table name with WordPress prefix.
     *
     * @since 1.0.0
     * @return string
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'phoenix_proposals';
    }

    /**
     * Valid status values for a proposal.
     *
     * @since 1.0.0
     * @return string[]
     */
    private static function valid_statuses() {
        return array( 'pending', 'approved', 'rejected' );
    }

    /**
     * Valid type values for a proposal.
     *
     * @since 1.0.0
     * @return string[]
     */
    private static function valid_types() {
        return array( 'task', 'project', 'goal', 'strategy' );
    }

    /**
     * Create a new proposal.
     *
     * @param  string   $title       Short title for the proposal.
     * @param  string   $description Full proposal text and reasoning.
     * @param  string   $type        Proposal type: task, project, goal, or strategy.
     * @param  string   $actor       Actor identifier for event logging.
     *
     * @return int|false The inserted proposal ID on success, false on failure.
     */
    public static function create_proposal( $title, $description, $type, $actor = 'dixi' ) {
        global $wpdb;

        $type = sanitize_text_field( strtolower( $type ) );

        if ( ! in_array( $type, self::valid_types(), true ) ) {
            /**
             * Fires when a proposal creation fails due to an invalid type.
             *
             * @since 1.0.0
             *
             * @param string $title The attempted proposal title.
             * @param string $type  The invalid type value provided.
             */
            do_action( 'phoenix_proposal_create_failed', $title, $type );
            return false;
        }

        $data = array(
            'title'       => sanitize_text_field( $title ),
            'description' => wp_kses_post( $description ),
            'type'        => $type,
            'status'      => 'pending',
            'reason'      => null,
            'archived'    => 0,
            'created_at'  => current_time( 'mysql' ),
            'decided_at'  => null,
            'executed'    => 0,
        );

        $format = array(
            '%s', // title
            '%s', // description
            '%s', // type
            '%s', // status
            null, // reason (NULL allowed)
            '%d', // archived
            '%s', // created_at
            null, // decided_at (NULL allowed)
            '%d', // executed
        );

        $inserted = $wpdb->insert(
            self::get_table_name(),
            $data,
            $format
        );

        if ( false === $inserted ) {
            /**
             * Fires when a proposal insertion fails.
             *
             * @since 1.0.0
             *
             * @param array  $data  The data array that was attempted to be inserted.
             * @param string $error The database error message.
             */
            do_action( 'phoenix_proposal_insert_failed', $data, $wpdb->last_error );
            return false;
        }

        $proposal_id = (int) $wpdb->insert_id;

        /**
         * Fires immediately after a proposal is successfully created.
         *
         * @since 1.0.0
         *
         * @param int    $proposal_id The ID of the newly created proposal.
         * @param string $title       The proposal title.
         * @param string $type        The proposal type.
         */
        do_action( 'phoenix_proposal_created', $proposal_id, $title, $type );

        // Log the event via the Event Logger's hook.
        do_action(
            'phoenix_event_logged',
            0,
            $actor,
            'proposal_created',
            'proposal',
            $proposal_id,
            sprintf(
                /* translators: 1: proposal type, 2: proposal title */
                __( 'Proposal created — Type: %1$s, Title: %2$s', 'phoenix-agentic-crm' ),
                $type,
                $title
            )
        );

        return $proposal_id;
    }

    /**
     * Retrieve proposals with optional filtering.
     *
     * @param string|null $status   Optional. Filter by status (pending, approved, rejected).
     * @param string|null $type     Optional. Filter by type (task, project, goal, strategy).
     * @param bool        $archived Optional. Whether to include archived proposals (default false).
     *
     * @return array Array of proposal objects, or empty array on failure.
     */
    public static function get_proposals( $status = null, $type = null, $archived = false ) {
        global $wpdb;

        $table  = self::get_table_name();
        $where  = array();
        $values = array();

        if ( null !== $status && '' !== $status ) {
            $status = sanitize_text_field( strtolower( $status ) );
            if ( in_array( $status, self::valid_statuses(), true ) ) {
                $where[]  = 'status = %s';
                $values[] = $status;
            }
        }

        if ( null !== $type && '' !== $type ) {
            $type = sanitize_text_field( strtolower( $type ) );
            if ( in_array( $type, self::valid_types(), true ) ) {
                $where[]  = 'type = %s';
                $values[] = $type;
            }
        }

        if ( $archived ) {
            $where[] = 'archived = 1';
        } else {
            $where[] = 'archived = 0';
        }

        $where_clause = '';
        if ( ! empty( $where ) ) {
            $where_clause = 'WHERE ' . implode( ' AND ', $where );
        }

        $sql = "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC";

        $prepared = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $results = $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return is_array( $results ) ? $results : array();
    }

    /**
     * Decide (approve or reject) a proposal.
     *
     * Approving creates an 'event' for execution tracking.
     * Rejecting stores the reason on the proposal record.
     *
     * @param int    $id     The proposal ID.
     * @param string $status New status: 'approved' or 'rejected'.
     * @param string $reason Optional. Human-readable reason for the decision.
     *
     * @return bool True on success, false on failure.
     */
    public static function decide_proposal( $id, $status, $reason = '' ) {
        global $wpdb;

        $id = intval( $id );
        if ( $id <= 0 ) {
            return false;
        }

        $status = sanitize_text_field( strtolower( $status ) );
        if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
            return false;
        }

        // Fetch the current proposal to verify it exists and is pending.
        $table  = self::get_table_name();
        $sql    = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $id
        );
        $proposal = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( null === $proposal ) {
            return false;
        }

        // Only allow deciding a pending proposal.
        if ( 'pending' !== $proposal->status ) {
            return false;
        }

        $update_data = array(
            'status'     => $status,
            'decided_at' => current_time( 'mysql' ),
        );
        $update_format = array( '%s', '%s' );

        if ( 'rejected' === $status && ! empty( $reason ) ) {
            $update_data['reason'] = sanitize_textarea_field( $reason );
            $update_format[]       = '%s';
        }

        if ( 'approved' === $status ) {
            $update_data['executed'] = 1;
            $update_format[]         = '%d';
        }

        $updated = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $id ),
            $update_format,
            array( '%d' )
        );

        if ( false === $updated ) {
            return false;
        }

        // Log the decision event.
        $action_label = ( 'approved' === $status ) ? 'proposal_approved' : 'proposal_rejected';

        do_action(
            'phoenix_event_logged',
            0,
            'user',
            $action_label,
            'proposal',
            $id,
            ( 'rejected' === $status && ! empty( $reason ) )
                ? sprintf(
                    /* translators: 1: proposal title, 2: rejection reason */
                    __( 'Proposal rejected — %1$s. Reason: %2$s', 'phoenix-agentic-crm' ),
                    $proposal->title,
                    $reason
                )
                : sprintf(
                    /* translators: %s: proposal title */
                    __( 'Proposal approved — %s', 'phoenix-agentic-crm' ),
                    $proposal->title
                )
        );

        /**
         * Fires after a proposal has been decided (approved or rejected).
         * The Event Logger catches this hook to record the decision.
         *
         * @since 1.0.0
         *
         * @param int    $id     The proposal ID.
         * @param string $status The decision status ('approved' or 'rejected').
         * @param string $reason Optional reason for the decision.
         */
        do_action( 'phoenix_proposal_decided', $id, $status, $reason );

        return true;
    }

    /**
     * Archive proposals older than a given number of days.
     *
     * Marks non-archived proposals whose created_at date is older than
     * the specified threshold as archived, making them available for
     * agent learning without cluttering active lists.
     *
     * @param int $days Age threshold in days (default 90).
     *
     * @return int|false Number of rows updated on success, false on failure.
     */
    public static function archive_old_proposals( $days = 90 ) {
        global $wpdb;

        $days = max( 1, intval( $days ) );

        $table  = self::get_table_name();
        $sql    = $wpdb->prepare(
            "UPDATE {$table} SET archived = 1 WHERE archived = 0 AND created_at < DATE_SUB( %s, INTERVAL %d DAY )", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            current_time( 'mysql' ),
            $days
        );

        $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( false === $result ) {
            return false;
        }

        /**
         * Fires after old proposals have been archived.
         *
         * @since 1.0.0
         *
         * @param int $days  The age threshold in days that was used.
         * @param int $count The number of proposals archived.
         */
        do_action( 'phoenix_proposals_archived', $days, $result );

        return $result;
    }

    /**
     * Get learning data for Dixi training.
     *
     * Returns all non-archived proposals with a final status
     * (approved or rejected), including title, description, type,
     * decision outcome, and reason — suitable for fine-tuning and
     * behavioral learning.
     *
     * @return array Array of proposal objects suitable for training.
     */
    public static function get_learning_data() {
        global $wpdb;

        $table = self::get_table_name();

        $sql = $wpdb->prepare(
            "SELECT id, title, description, type, status, reason, created_at, decided_at
             FROM {$table}
             WHERE status IN ( %s, %s )
               AND archived = 0
             ORDER BY decided_at DESC",
            'approved',
            'rejected'
        );

        $results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return is_array( $results ) ? $results : array();
    }

    /**
     * Get proposal statistics.
     *
     * Returns counts for each status and the overall approval rate.
     *
     * @return array Associative array with keys:
     *               total, pending, approved, rejected, approval_rate, archived.
     */
    public static function get_stats() {
        global $wpdb;

        $table = self::get_table_name();

        // Aggregate counts in a single query.
        $sql = "SELECT
                    COUNT(*)                                                          AS total,
                    SUM( CASE WHEN status = 'pending'   THEN 1 ELSE 0 END )            AS pending,
                    SUM( CASE WHEN status = 'approved'  THEN 1 ELSE 0 END )            AS approved,
                    SUM( CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END )            AS rejected,
                    SUM( CASE WHEN archived = 1         THEN 1 ELSE 0 END )            AS archived
                FROM {$table}";

        $row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( null === $row ) {
            return array(
                'total'         => 0,
                'pending'       => 0,
                'approved'      => 0,
                'rejected'      => 0,
                'archived'      => 0,
                'approval_rate' => 0.0,
            );
        }

        $total_pending   = (int) $row->pending;
        $total_approved  = (int) $row->approved;
        $total_rejected  = (int) $row->rejected;
        $total_archived  = (int) $row->archived;
        $total           = (int) $row->total;

        // Approval rate = approved / (approved + rejected), avoid division by zero.
        $decided = $total_approved + $total_rejected;
        $rate    = ( $decided > 0 ) ? round( ( $total_approved / $decided ) * 100, 2 ) : 0.0;

        return array(
            'total'         => $total,
            'pending'       => $total_pending,
            'approved'      => $total_approved,
            'rejected'      => $total_rejected,
            'archived'      => $total_archived,
            'approval_rate' => $rate,
        );
    }
}