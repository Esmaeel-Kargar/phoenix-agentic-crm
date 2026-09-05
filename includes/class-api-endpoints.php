<?php
/**
 * Phoenix Agentic CRM - REST API Endpoints
 *
 * Registers all REST API routes under the 'phoenix/v1' namespace.
 * Each endpoint is protected by X-Phoenix-Key authentication via
 * the Phoenix_CRM_API_Auth::authenticate_request() callback.
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
 * Class Phoenix_CRM_API_Endpoints
 *
 * Registers and handles all REST API endpoints for the CRM.
 */
class Phoenix_CRM_API_Endpoints {

    /**
     * The REST API namespace.
     */
    const API_NAMESPACE = 'phoenix/v1';

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
     * Constructor — hooks into REST API init.
     */
    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register all REST API routes.
     */
    public function register_routes() {
        // GET /raw-data — Retrieve raw (unsynced) data records.
        register_rest_route( self::API_NAMESPACE, '/raw-data', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_raw_data' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->get_raw_data_args(),
        ) );

        // POST /raw-data/sync — Mark raw data records as synced.
        register_rest_route( self::API_NAMESPACE, '/raw-data/sync', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'sync_raw_data' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->get_sync_raw_data_args(),
        ) );

        // GET /proposals — Retrieve proposals with optional filters.
        register_rest_route( self::API_NAMESPACE, '/proposals', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_proposals' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->get_proposals_args(),
        ) );

        // POST /proposals — Create a new proposal.
        register_rest_route( self::API_NAMESPACE, '/proposals', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_proposal' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->create_proposal_args(),
        ) );

        // POST /proposals/{id}/decide — Approve or reject a proposal.
        register_rest_route( self::API_NAMESPACE, '/proposals/(?P<id>\d+)/decide', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'decide_proposal' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->decide_proposal_args(),
        ) );

        // GET /goals — Retrieve goals.
        register_rest_route( self::API_NAMESPACE, '/goals', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_goals' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->get_goals_args(),
        ) );

        // POST /goals — Create a new goal.
        register_rest_route( self::API_NAMESPACE, '/goals', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_goal' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->create_goal_args(),
        ) );

        // GET /projects — Retrieve projects.
        register_rest_route( self::API_NAMESPACE, '/projects', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_projects' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->get_projects_args(),
        ) );

        // POST /projects — Create a new project.
        register_rest_route( self::API_NAMESPACE, '/projects', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_project' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->create_project_args(),
        ) );

        // POST /events — Log an event.
        register_rest_route( self::API_NAMESPACE, '/events', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_event' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->create_event_args(),
        ) );

        // GET /events — Retrieve events with optional filters.
        register_rest_route( self::API_NAMESPACE, '/events', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_events' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => $this->get_events_args(),
        ) );

        // GET /summary — Dashboard summary (auth required).
        register_rest_route( self::API_NAMESPACE, '/summary', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_summary' ),
            'permission_callback' => array( 'Phoenix_CRM_API_Auth', 'authenticate_request' ),
            'args'                => array(),
        ) );
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /raw-data — Return unsynced raw data records.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_raw_data( $request ) {
        global $wpdb;

        $table   = $wpdb->prefix . 'phoenix_raw_data';
        $source  = sanitize_text_field( $request->get_param( 'source' ) );
        $cat     = sanitize_text_field( $request->get_param( 'category' ) );
        $limit   = absint( $request->get_param( 'limit' ) ) ?: 50;
        $synced  = rest_sanitize_boolean( $request->get_param( 'synced' ) );

        $where = array( '1=1' );
        $params = array();

        if ( $source ) {
            $where[]  = 'source = %s';
            $params[] = $source;
        }
        if ( $cat ) {
            $where[]  = 'category = %s';
            $params[] = $cat;
        }
        if ( ! $synced ) {
            $where[] = '(synced_at IS NULL OR synced_at = %s)';
            $params[] = '0000-00-00 00:00:00';
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY collected_at DESC LIMIT %d";
        $params[]  = $limit;

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( null === $results ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $results,
            'total'   => count( $results ),
        ), 200 );
    }

    /**
     * POST /raw-data/sync — Mark raw data records as synced.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function sync_raw_data( $request ) {
        global $wpdb;

        $ids = $request->get_json_params();
        if ( ! is_array( $ids ) || empty( $ids ) ) {
            return new WP_Error( 'invalid_ids', __( 'A non-empty array of IDs is required.', 'phoenix-agentic-crm' ), array( 'status' => 400 ) );
        }

        // Sanitize and validate IDs.
        $ids   = array_map( 'absint', $ids );
        $ids   = array_filter( $ids );
        $count = count( $ids );

        if ( 0 === $count ) {
            return new WP_Error( 'invalid_ids', __( 'No valid IDs provided.', 'phoenix-agentic-crm' ), array( 'status' => 400 ) );
        }

        $table     = $wpdb->prefix . 'phoenix_raw_data';
        $id_placeholders = implode( ',', array_fill( 0, $count, '%d' ) );

        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET synced_at = NOW() WHERE id IN ({$id_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ids
        ) );

        if ( false === $updated ) {
            return new WP_Error( 'db_error', __( 'Failed to update sync status.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'updated' => $updated,
        ), 200 );
    }

    /**
     * GET /proposals — Retrieve proposals with optional filters.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_proposals( $request ) {
        global $wpdb;

        $table    = $wpdb->prefix . 'phoenix_proposals';
        $status   = sanitize_text_field( $request->get_param( 'status' ) );
        $type     = sanitize_text_field( $request->get_param( 'type' ) );
        $archived = $request->has_param( 'archived' ) ? rest_sanitize_boolean( $request->get_param( 'archived' ) ) : null;

        $where  = array( '1=1' );
        $params = array();

        if ( $status ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }
        if ( $type ) {
            $where[]  = 'type = %s';
            $params[] = $type;
        }
        if ( null !== $archived ) {
            $where[]  = 'archived = %d';
            $params[] = $archived ? 1 : 0;
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";
        $results   = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( null === $results ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $results,
            'total'   => count( $results ),
        ), 200 );
    }

    /**
     * POST /proposals — Create a new proposal.
     *
     * This endpoint is intended to be used by external agents (e.g. Dixi)
     * to submit proposals for user review.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_proposal( $request ) {
        global $wpdb;

        $title       = sanitize_text_field( $request->get_param( 'title' ) );
        $description = wp_kses_post( $request->get_param( 'description' ) );
        $type        = sanitize_text_field( $request->get_param( 'type' ) );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Proposal title is required.', 'phoenix-agentic-crm' ), array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'phoenix_proposals';

        $inserted = $wpdb->insert(
            $table,
            array(
                'title'       => $title,
                'description' => $description,
                'type'        => $type,
                'status'      => 'pending',
                'archived'    => 0,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_error', __( 'Failed to create proposal.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        $proposal_id = $wpdb->insert_id;

        // Log the event via the Event Logger's hook.
        do_action(
            'phoenix_event_logged',
            0,
            'api:user',
            'proposal_created',
            'proposal',
            $proposal_id,
            sprintf(
                /* translators: 1: proposal type, 2: proposal title */
                __( 'Proposal created via API — Type: %1$s, Title: %2$s', 'phoenix-agentic-crm' ),
                $type,
                $title
            )
        );

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'id' => $proposal_id,
            ),
        ), 201 );
    }

    /**
     * POST /proposals/{id}/decide — Approve or reject a proposal.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function decide_proposal( $request ) {
        global $wpdb;

        $proposal_id = (int) $request->get_param( 'id' );
        $status      = sanitize_text_field( $request->get_param( 'status' ) );
        $reason      = sanitize_textarea_field( $request->get_param( 'reason' ) );

        if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
            return new WP_Error( 'invalid_status', __( 'Status must be "approved" or "rejected".', 'phoenix-agentic-crm' ), array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'phoenix_proposals';

        // Check the proposal exists.
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $proposal_id ) );
        if ( ! $existing ) {
            return new WP_Error( 'not_found', __( 'Proposal not found.', 'phoenix-agentic-crm' ), array( 'status' => 404 ) );
        }

        $decision_time = current_time( 'mysql' );
        $updated = $wpdb->update(
            $table,
            array(
                'status'        => $status,
                'reason'        => $reason,
                'decided_at'    => $decision_time,
            ),
            array( 'id' => $proposal_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            return new WP_Error( 'db_error', __( 'Failed to update proposal.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'id'     => $proposal_id,
                'status' => $status,
            ),
        ), 200 );
    }

    /**
     * GET /goals — Retrieve goals.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_goals( $request ) {
        global $wpdb;

        $table = $wpdb->prefix . 'phoenix_goals';
        $results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

        if ( null === $results ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $results,
            'total'   => count( $results ),
        ), 200 );
    }

    /**
     * POST /goals — Create a new goal.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_goal( $request ) {
        global $wpdb;

        $title       = sanitize_text_field( $request->get_param( 'title' ) );
        $description = wp_kses_post( $request->get_param( 'description' ) );
        $horizon     = sanitize_text_field( $request->get_param( 'horizon' ) );
        $period_start = $request->get_param( 'period_start' );
        $period_end   = $request->get_param( 'period_end' );
        $status      = sanitize_text_field( $request->get_param( 'status' ) );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Goal title is required.', 'phoenix-agentic-crm' ), array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'phoenix_goals';

        $inserted = $wpdb->insert(
            $table,
            array(
                'title'        => $title,
                'description'  => $description,
                'horizon'      => $horizon,
                'period_start' => $period_start ? $period_start : null,
                'period_end'   => $period_end ? $period_end : null,
                'status'       => $status ?: 'active',
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_error', __( 'Failed to create goal.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array( 'id' => $wpdb->insert_id ),
        ), 201 );
    }

    /**
     * GET /projects — Retrieve projects.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_projects( $request ) {
        global $wpdb;

        $table = $wpdb->prefix . 'phoenix_projects';
        $results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

        if ( null === $results ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $results,
            'total'   => count( $results ),
        ), 200 );
    }

    /**
     * POST /projects — Create a new project.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_project( $request ) {
        global $wpdb;

        $title       = sanitize_text_field( $request->get_param( 'title' ) );
        $type        = sanitize_text_field( $request->get_param( 'type' ) );
        $description = wp_kses_post( $request->get_param( 'description' ) );
        $status      = sanitize_text_field( $request->get_param( 'status' ) );
        $proposal_id = $request->has_param( 'proposal_id' ) ? absint( $request->get_param( 'proposal_id' ) ) : null;

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Project title is required.', 'phoenix-agentic-crm' ), array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'phoenix_projects';

        $inserted = $wpdb->insert(
            $table,
            array(
                'title'       => $title,
                'type'        => $type,
                'description' => $description,
                'status'      => $status ?: 'active',
                'proposal_id' => $proposal_id,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_error', __( 'Failed to create project.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array( 'id' => $wpdb->insert_id ),
        ), 201 );
    }

    /**
     * POST /events — Log an event (used by Dixi to record activities).
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_event( $request ) {
        global $wpdb;

        $actor       = sanitize_text_field( $request->get_param( 'actor' ) );
        $action      = sanitize_text_field( $request->get_param( 'action' ) );
        $target_type = sanitize_text_field( $request->get_param( 'target_type' ) );
        $target_id   = $request->has_param( 'target_id' ) ? sanitize_text_field( $request->get_param( 'target_id' ) ) : null;
        $note        = sanitize_textarea_field( $request->get_param( 'note' ) );

        if ( empty( $actor ) || empty( $action ) ) {
            return new WP_Error( 'missing_fields', __( 'Actor and action are required.', 'phoenix-agentic-crm' ), array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'phoenix_events';

        $inserted = $wpdb->insert(
            $table,
            array(
                'actor'       => $actor,
                'action'      => $action,
                'target_type' => $target_type,
                'target_id'   => $target_id,
                'note'        => $note,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_error', __( 'Failed to log event.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array( 'id' => $wpdb->insert_id ),
        ), 201 );
    }

    /**
     * GET /events — Retrieve events with optional filters.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_events( $request ) {
        global $wpdb;

        $table    = $wpdb->prefix . 'phoenix_events';
        $actor    = sanitize_text_field( $request->get_param( 'actor' ) );
        $action   = sanitize_text_field( $request->get_param( 'action' ) );
        $date_from = $request->get_param( 'date_from' );
        $date_to   = $request->get_param( 'date_to' );

        $where  = array( '1=1' );
        $params = array();

        if ( $actor ) {
            $where[]  = 'actor = %s';
            $params[] = $actor;
        }
        if ( $action ) {
            $where[]  = 'action = %s';
            $params[] = $action;
        }
        if ( $date_from ) {
            $where[]  = 'created_at >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[]  = 'created_at <= %s';
            $params[] = $date_to;
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 100";
        $results   = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( null === $results ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'phoenix-agentic-crm' ), array( 'status' => 500 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $results,
            'total'   => count( $results ),
        ), 200 );
    }

    /**
     * GET /summary — Dashboard summary (public, no auth required).
     *
     * Returns pending proposals count, latest events, and active goals.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_summary( $request ) {
        global $wpdb;

        $prefix = $wpdb->prefix;

        // Pending proposals count.
        $pending_proposals = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}phoenix_proposals WHERE status = %s",
                'pending'
            )
        );

        // Active goals count.
        $active_goals = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}phoenix_goals WHERE status = %s",
                'active'
            )
        );

        // Latest 5 events.
        $latest_events = $wpdb->get_results(
            "SELECT * FROM {$prefix}phoenix_events ORDER BY created_at DESC LIMIT 5",
            ARRAY_A
        );

        // Latest 5 proposals.
        $latest_proposals = $wpdb->get_results(
            "SELECT id, title, type, status, created_at FROM {$prefix}phoenix_proposals ORDER BY created_at DESC LIMIT 5",
            ARRAY_A
        );

        // Active projects count.
        $active_projects = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}phoenix_projects WHERE status = %s",
                'active'
            )
        );

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'pending_proposals' => $pending_proposals,
                'active_goals'      => $active_goals,
                'active_projects'   => $active_projects,
                'latest_events'     => $latest_events ?: array(),
                'latest_proposals'  => $latest_proposals ?: array(),
            ),
        ), 200 );
    }

    // -------------------------------------------------------------------------
    // Argument Schemas
    // -------------------------------------------------------------------------

    /**
     * Argument schema for GET /raw-data.
     *
     * @return array
     */
    private function get_raw_data_args() {
        return array(
            'source'   => array(
                'description' => __( 'Filter by data source.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'category' => array(
                'description' => __( 'Filter by category.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'limit'    => array(
                'description' => __( 'Maximum number of records to return.', 'phoenix-agentic-crm' ),
                'type'        => 'integer',
                'default'     => 50,
                'required'    => false,
            ),
            'synced'   => array(
                'description' => __( 'Filter by sync status. Defaults to unsynced only.', 'phoenix-agentic-crm' ),
                'type'        => 'boolean',
                'default'     => false,
                'required'    => false,
            ),
        );
    }

    /**
     * Argument schema for POST /raw-data/sync.
     *
     * @return array
     */
    private function get_sync_raw_data_args() {
        return array(
            'ids' => array(
                'description' => __( 'Array of raw data record IDs to mark as synced.', 'phoenix-agentic-crm' ),
                'type'        => 'array',
                'items'       => array( 'type' => 'integer' ),
                'required'    => true,
            ),
        );
    }

    /**
     * Argument schema for GET /proposals.
     *
     * @return array
     */
    private function get_proposals_args() {
        return array(
            'status'   => array(
                'description' => __( 'Filter by proposal status.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'enum'        => array( 'pending', 'approved', 'rejected' ),
                'required'    => false,
            ),
            'type'     => array(
                'description' => __( 'Filter by proposal type.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'archived' => array(
                'description' => __( 'Filter by archived status.', 'phoenix-agentic-crm' ),
                'type'        => 'boolean',
                'required'    => false,
            ),
        );
    }

    /**
     * Argument schema for POST /proposals.
     *
     * @return array
     */
    private function create_proposal_args() {
        return array(
            'title'       => array(
                'description' => __( 'Proposal title.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => true,
            ),
            'description' => array(
                'description' => __( 'Proposal description (HTML allowed).', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'type'        => array(
                'description' => __( 'Proposal type.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'enum'        => array( 'task', 'project', 'goal', 'strategy' ),
                'required'    => false,
            ),
        );
    }

    /**
     * Argument schema for POST /proposals/{id}/decide.
     *
     * @return array
     */
    private function decide_proposal_args() {
        return array(
            'id'     => array(
                'description' => __( 'Proposal ID.', 'phoenix-agentic-crm' ),
                'type'        => 'integer',
                'required'    => true,
            ),
            'status' => array(
                'description' => __( 'Decision: approved or rejected.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'enum'        => array( 'approved', 'rejected' ),
                'required'    => true,
            ),
            'reason' => array(
                'description' => __( 'Optional reason for the decision.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
        );
    }

    /**
     * Argument schema for GET /goals.
     *
     * @return array
     */
    private function get_goals_args() {
        return array();
    }

    /**
     * Argument schema for POST /goals.
     *
     * @return array
     */
    private function create_goal_args() {
        return array(
            'title'        => array(
                'description' => __( 'Goal title.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => true,
            ),
            'description'  => array(
                'description' => __( 'Goal description.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'horizon'      => array(
                'description' => __( 'Time horizon (e.g. short-term, medium-term, long-term).', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'period_start' => array(
                'description' => __( 'Goal period start date (Y-m-d).', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'format'      => 'date',
                'required'    => false,
            ),
            'period_end'   => array(
                'description' => __( 'Goal period end date (Y-m-d).', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'format'      => 'date',
                'required'    => false,
            ),
            'status'       => array(
                'description' => __( 'Goal status.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'enum'        => array( 'active', 'completed', 'paused', 'cancelled' ),
                'required'    => false,
            ),
        );
    }

    /**
     * Argument schema for GET /projects.
     *
     * @return array
     */
    private function get_projects_args() {
        return array();
    }

    /**
     * Argument schema for POST /projects.
     *
     * @return array
     */
    private function create_project_args() {
        return array(
            'title'       => array(
                'description' => __( 'Project title.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => true,
            ),
            'type'        => array(
                'description' => __( 'Project type.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'description' => array(
                'description' => __( 'Project description.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'status'      => array(
                'description' => __( 'Project status.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'enum'        => array( 'active', 'completed', 'on_hold', 'cancelled' ),
                'required'    => false,
            ),
            'proposal_id' => array(
                'description' => __( 'Optional proposal ID this project originates from.', 'phoenix-agentic-crm' ),
                'type'        => 'integer',
                'required'    => false,
            ),
        );
    }

    /**
     * Argument schema for POST /events.
     *
     * @return array
     */
    private function create_event_args() {
        return array(
            'actor'       => array(
                'description' => __( 'Event actor (e.g. "dixi", "system", "user").', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => true,
            ),
            'action'      => array(
                'description' => __( 'Action performed.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => true,
            ),
            'target_type' => array(
                'description' => __( 'Type of the target entity (e.g. proposal, project, goal).', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'target_id'   => array(
                'description' => __( 'ID of the target entity.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'note'        => array(
                'description' => __( 'Optional note or description of the event.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
        );
    }

    /**
     * Argument schema for GET /events.
     *
     * @return array
     */
    private function get_events_args() {
        return array(
            'actor'     => array(
                'description' => __( 'Filter by actor.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'action'    => array(
                'description' => __( 'Filter by action.', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'date_from' => array(
                'description' => __( 'Start date (Y-m-d or Y-m-d H:i:s).', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'date_to'   => array(
                'description' => __( 'End date (Y-m-d or Y-m-d H:i:s).', 'phoenix-agentic-crm' ),
                'type'        => 'string',
                'required'    => false,
            ),
        );
    }
}