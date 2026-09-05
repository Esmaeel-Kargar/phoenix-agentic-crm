<?php
/**
 * Phoenix Agentic CRM - Event Logger
 *
 * Static helper class for recording and querying system events
 * in the wp_phoenix_events table.
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
 * Class Phoenix_CRM_Event_Logger
 *
 * Provides a static interface to log and retrieve events.
 * All queries use $wpdb->prepare() for SQL injection protection.
 */
class Phoenix_CRM_Event_Logger {

    /**
     * Get the full table name with WordPress prefix.
     *
     * @return string
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'phoenix_events';
    }

    /**
     * Log an event to the database.
     *
     * @param string $actor       Username or system identifier who performed the action.
     * @param string $action      Action type (e.g., 'created', 'updated', 'deleted', 'login').
     * @param string $target_type Object type the action was performed on (e.g., 'contact', 'deal', 'note').
     * @param int    $target_id   ID of the target object.
     * @param string $note        Optional human-readable note or context.
     *
     * @return int|false The inserted event ID on success, false on failure.
     */
    public static function log_event( $actor, $action, $target_type, $target_id, $note = '' ) {
        global $wpdb;

        $data = array(
            'actor'       => $actor,
            'action'      => $action,
            'target_type' => $target_type,
            'target_id'   => intval( $target_id ),
            'note'        => $note,
            'created_at'  => current_time( 'mysql' ),
        );

        $format = array(
            '%s', // actor
            '%s', // action
            '%s', // target_type
            '%d', // target_id
            '%s', // note
            '%s', // created_at
        );

        $inserted = $wpdb->insert(
            self::get_table_name(),
            $data,
            $format
        );

        if ( false === $inserted ) {
            /**
             * Fires when an event log insertion fails.
             *
             * @since 1.0.0
             *
             * @param array  $data The data array that was attempted to be inserted.
             * @param string $error The database error message.
             */
            do_action( 'phoenix_event_log_failed', $data, $wpdb->last_error );
            return false;
        }

        $event_id = (int) $wpdb->insert_id;

        /**
         * Fires immediately after an event is successfully logged.
         *
         * @since 1.0.0
         *
         * @param int    $event_id   The ID of the newly inserted event.
         * @param string $actor      The actor who performed the action.
         * @param string $action     The action type.
         * @param string $target_type The type of the target object.
         * @param int    $target_id  The ID of the target object.
         * @param string $note       Optional note associated with the event.
         */
        do_action( 'phoenix_event_logged', $event_id, $actor, $action, $target_type, $target_id, $note );

        return $event_id;
    }

    /**
     * Retrieve events with optional filtering.
     *
     * @param array $filters {
     *     Optional. Associative array of filter criteria.
     *
     *     @type string $actor       Filter by actor name.
     *     @type string $action      Filter by action type.
     *     @type string $target_type Filter by target object type.
     *     @type int    $target_id   Filter by target object ID.
     *     @type string $from        Start date/time (MySQL datetime string).
     *     @type string $to          End date/time (MySQL datetime string).
     * }
     * @param int   $limit   Maximum number of events to return (default 50).
     *
     * @return array Array of event objects, or empty array on failure.
     */
    public static function get_events( $filters = array(), $limit = 50 ) {
        global $wpdb;

        $table  = self::get_table_name();
        $where  = array();
        $values = array();

        if ( ! empty( $filters['actor'] ) ) {
            $where[]  = 'actor = %s';
            $values[] = $filters['actor'];
        }

        if ( ! empty( $filters['action'] ) ) {
            $where[]  = '`action` = %s';
            $values[] = $filters['action'];
        }

        if ( ! empty( $filters['target_type'] ) ) {
            $where[]  = 'target_type = %s';
            $values[] = $filters['target_type'];
        }

        if ( isset( $filters['target_id'] ) && is_numeric( $filters['target_id'] ) ) {
            $where[]  = 'target_id = %d';
            $values[] = intval( $filters['target_id'] );
        }

        if ( ! empty( $filters['from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = $filters['from'];
        }

        if ( ! empty( $filters['to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = $filters['to'];
        }

        $where_clause = '';
        if ( ! empty( $where ) ) {
            $where_clause = 'WHERE ' . implode( ' AND ', $where );
        }

        $limit = max( 1, intval( $limit ) );

        $sql = "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC LIMIT %d";
        $values[] = $limit;

        $prepared = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $results = $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return is_array( $results ) ? $results : array();
    }

    /**
     * Retrieve events filtered by actor.
     *
     * @param string $actor Username or system identifier.
     * @param int    $limit Maximum number of events to return (default 50).
     *
     * @return array Array of event objects.
     */
    public static function get_events_by_actor( $actor, $limit = 50 ) {
        return self::get_events(
            array( 'actor' => $actor ),
            $limit
        );
    }

    /**
     * Retrieve events filtered by action type.
     *
     * @param string $action Action type to filter by.
     * @param int    $limit  Maximum number of events to return (default 50).
     *
     * @return array Array of event objects.
     */
    public static function get_events_by_action( $action, $limit = 50 ) {
        return self::get_events(
            array( 'action' => $action ),
            $limit
        );
    }

    /**
     * Retrieve events within a date range.
     *
     * @param string $from  Start date/time (MySQL datetime string, e.g. '2025-01-01 00:00:00').
     * @param string $to    End date/time (MySQL datetime string, e.g. '2025-12-31 23:59:59').
     * @param int    $limit Maximum number of events to return (default 50).
     *
     * @return array Array of event objects.
     */
    public static function get_events_by_date_range( $from, $to, $limit = 50 ) {
        return self::get_events(
            array(
                'from' => $from,
                'to'   => $to,
            ),
            $limit
        );
    }
}