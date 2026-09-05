<?php
/**
 * Phoenix Agentic CRM - Admin Events List Table
 *
 * Renders the Event Log viewer under the CRM admin menu.
 * Extends WP_List_Table with filtering, pagination, search,
 * and inline note expansion.
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
 * Class Phoenix_CRM_Admin_Events
 *
 * Displays wp_phoenix_events rows in an interactive WP_List_Table.
 */
class Phoenix_CRM_Admin_Events extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'event',
                'plural'   => 'events',
                'ajax'     => false,
            )
        );
    }

    /**
     * Define table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox" />',
            'id'         => __( 'ID', 'phoenix-crm' ),
            'actor'      => __( 'Actor', 'phoenix-crm' ),
            'action'     => __( 'Action', 'phoenix-crm' ),
            'target'     => __( 'Target', 'phoenix-crm' ),
            'note'       => __( 'Note', 'phoenix-crm' ),
            'created_at' => __( 'Created', 'phoenix-crm' ),
        );
    }

    /**
     * Define sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'         => array( 'id', true ),
            'actor'      => array( 'actor', false ),
            'action'     => array( 'action', false ),
            'created_at' => array( 'created_at', false ),
        );
    }

    /**
     * Default column renderer.
     *
     * @param object $item        Row data.
     * @param string $column_name Column name.
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
    }

    /**
     * Render the checkbox column.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="event_id[]" value="%d" />',
            intval( $item->id )
        );
    }

    /**
     * Render the ID column.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_id( $item ) {
        return intval( $item->id );
    }

    /**
     * Render the Actor column with a color-coded badge.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_actor( $item ) {
        $actor = esc_html( $item->actor );

        // Colour based on actor prefix / pattern.
        $color = self::get_actor_color( $item->actor );

        return sprintf(
            '<span class="phoenix-actor-badge" style="display:inline-block;background:%s;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">%s</span>',
            esc_attr( $color ),
            $actor
        );
    }

    /**
     * Render the Action column with semantic colouring.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_action( $item ) {
        $action = esc_html( $item->action );
        $color  = self::get_action_color( $item->action );

        return sprintf(
            '<span style="color:%s;font-weight:600;">%s</span>',
            esc_attr( $color ),
            $action
        );
    }

    /**
     * Render the Target column (type + id).
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_target( $item ) {
        $type = esc_html( $item->target_type );
        $id   = intval( $item->target_id );

        if ( $id > 0 ) {
            return sprintf(
                '<code>%s</code> <span style="color:#72777c;">#%d</span>',
                $type,
                $id
            );
        }

        return sprintf( '<code>%s</code>', $type );
    }

    /**
     * Render the Note column — preview with expand toggle.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_note( $item ) {
        $note       = $item->note;
        $note_esc   = esc_html( $note );
        $note_br    = nl2br( $note_esc );
        $truncated  = mb_strlen( $note ) > 80;
        $preview    = $truncated ? esc_html( mb_substr( $note, 0, 80 ) ) . '&hellip;' : $note_br;
        $expanded   = $note_br;

        if ( empty( $note ) ) {
            return '<span style="color:#999;">&mdash;</span>';
        }

        if ( ! $truncated ) {
            return $note_br;
        }

        $row_id = 'event-note-' . intval( $item->id );

        return sprintf(
            '<div class="phoenix-note-preview" id="%s-preview">%s <a href="#" class="phoenix-note-toggle" data-event-id="%d" style="font-size:12px;">%s</a></div>
            <div class="phoenix-note-full" id="%s-full" style="display:none;">%s <a href="#" class="phoenix-note-toggle" data-event-id="%d" style="font-size:12px;">%s</a></div>',
            $row_id,
            $preview,
            intval( $item->id ),
            esc_html__( 'Show more', 'phoenix-crm' ),
            $row_id,
            $expanded,
            intval( $item->id ),
            esc_html__( 'Show less', 'phoenix-crm' )
        );
    }

    /**
     * Render the Created column.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_created_at( $item ) {
        if ( empty( $item->created_at ) || '0000-00-00 00:00:00' === $item->created_at ) {
            return '<span style="color:#999;">&mdash;</span>';
        }

        $time  = strtotime( $item->created_at );
        $label = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );

        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr( $label ),
            $label
        );
    }

    /**
     * Define extra table navigation (top only) with filter dropdowns.
     *
     * @param string $which 'top' or 'bottom'.
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_events';

        // Unique actors.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $actors = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT actor FROM {$table} ORDER BY actor ASC"
            )
        );

        // Unique actions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $actions = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT `action` FROM {$table} ORDER BY `action` ASC"
            )
        );

        $current_actor  = isset( $_REQUEST['filter_actor'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_actor'] ) ) : '';
        $current_action = isset( $_REQUEST['filter_action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_action'] ) ) : '';
        $current_from   = isset( $_REQUEST['filter_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_from'] ) ) : '';
        $current_to     = isset( $_REQUEST['filter_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_to'] ) ) : '';
        ?>
        <div class="alignleft actions phoenix-events-filters" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <select name="filter_actor" id="filter-actor">
                <option value=""><?php esc_html_e( 'All Actors', 'phoenix-crm' ); ?></option>
                <?php foreach ( $actors as $actor ) : ?>
                    <option value="<?php echo esc_attr( $actor ); ?>" <?php selected( $current_actor, $actor ); ?>><?php echo esc_html( $actor ); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="filter_action" id="filter-action">
                <option value=""><?php esc_html_e( 'All Actions', 'phoenix-crm' ); ?></option>
                <?php foreach ( $actions as $action ) : ?>
                    <option value="<?php echo esc_attr( $action ); ?>" <?php selected( $current_action, $action ); ?>><?php echo esc_html( $action ); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="filter_from" id="filter-from" value="<?php echo esc_attr( $current_from ); ?>" placeholder="<?php esc_attr_e( 'From date', 'phoenix-crm' ); ?>" style="max-width:140px;" />

            <input type="date" name="filter_to" id="filter-to" value="<?php echo esc_attr( $current_to ); ?>" placeholder="<?php esc_attr_e( 'To date', 'phoenix-crm' ); ?>" style="max-width:140px;" />

            <?php
            submit_button(
                __( 'Filter', 'phoenix-crm' ),
                'secondary',
                'filter_action_btn',
                false
            );
            ?>
        </div>
        <?php
    }

    /**
     * Prepare the list table items.
     */
    public function prepare_items() {
        global $wpdb;

        $table      = $wpdb->prefix . 'phoenix_events';
        $per_page   = 50;
        $current_page = $this->get_pagenum();
        $offset     = ( $current_page - 1 ) * $per_page;

        // Build WHERE clauses from filters and search.
        $where   = array( '1=1' );
        $values  = array();

        // Actor filter.
        if ( ! empty( $_REQUEST['filter_actor'] ) ) {
            $actor   = sanitize_text_field( wp_unslash( $_REQUEST['filter_actor'] ) );
            $where[] = 'actor = %s';
            $values[] = $actor;
        }

        // Action filter.
        if ( ! empty( $_REQUEST['filter_action'] ) ) {
            $action  = sanitize_text_field( wp_unslash( $_REQUEST['filter_action'] ) );
            $where[] = '`action` = %s';
            $values[] = $action;
        }

        // Date range filter.
        if ( ! empty( $_REQUEST['filter_from'] ) ) {
            $from    = sanitize_text_field( wp_unslash( $_REQUEST['filter_from'] ) ) . ' 00:00:00';
            $where[] = 'created_at >= %s';
            $values[] = $from;
        }

        if ( ! empty( $_REQUEST['filter_to'] ) ) {
            $to      = sanitize_text_field( wp_unslash( $_REQUEST['filter_to'] ) ) . ' 23:59:59';
            $where[] = 'created_at <= %s';
            $values[] = $to;
        }

        // Search by note.
        $search = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : '';
        if ( ! empty( $search ) ) {
            $where[]  = 'note LIKE %s';
            $values[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Sort order.
        $orderby = 'created_at';
        $order   = 'DESC';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $allowed_orderby = array( 'id', 'actor', 'action', 'created_at' );
            $input_orderby   = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
            if ( in_array( $input_orderby, $allowed_orderby, true ) ) {
                $orderby = $input_orderby;
            }
        }

        if ( ! empty( $_REQUEST['order'] ) ) {
            $input_order = strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) );
            if ( in_array( $input_order, array( 'ASC', 'DESC' ), true ) ) {
                $order = $input_order;
            }
        }

        $where_clause = implode( ' AND ', $where );

        // Count total items.
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
            $values
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_items = (int) $wpdb->get_var( $count_sql );

        // Fetch page of results.
        $data_sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            array_merge( $values, array( $per_page, $offset ) )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->items = $wpdb->get_results( $data_sql );

        // Pagination args.
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page ),
            )
        );

        // Columns.
        $columns               = $this->get_columns();
        $hidden                = array();
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
    }

    /**
     * No items fallback message.
     */
    public function no_items() {
        esc_html_e( 'No events found.', 'phoenix-crm' );
    }

    /**
     * Render the admin page for the Event Log list table.
     *
     * Called by Phoenix_CRM_Admin_Menu via route_to().
     * Outputs the WP_List_Table with search, filters, and inline JS.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'phoenix-crm' ) );
        }

        $table = new self();
        $table->prepare_items();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Event Log', 'phoenix-crm' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="phoenix-crm-event-log" />
                <?php
                $table->search_box( __( 'Search notes', 'phoenix-crm' ), 'phoenix-events-search' );
                $table->display();
                ?>
            </form>
        </div>

        <style>
        .wp-list-table .column-id { width: 60px; }
        .wp-list-table .column-actor { width: 120px; }
        .wp-list-table .column-action { width: 100px; }
        .wp-list-table .column-target { width: 140px; }
        .wp-list-table .column-note { width: auto; }
        .wp-list-table .column-created_at { width: 160px; }
        .phoenix-note-preview,
        .phoenix-note-full { word-break: break-word; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.phoenix-note-toggle', function(e) {
                e.preventDefault();
                var id  = $(this).data('event-id');
                $('#event-note-' + id + '-preview').toggle();
                $('#event-note-' + id + '-full').toggle();
            });
        });
        </script>
        <?php
    }

    // -----------------------------------------------------------------------
    //  Helpers – colour mapping
    // -----------------------------------------------------------------------

    /**
     * Map an actor name to a background colour.
     *
     * @param string $actor Actor name.
     * @return string Hex colour.
     */
    private static function get_actor_color( $actor ) {
        $actor = strtolower( $actor );

        $map = array(
            'system'       => '#607d8b',
            'admin'        => '#9c27b0',
            'agent'        => '#2196f3',
            'ai'           => '#2196f3',
            'phoenix'      => '#4caf50',
            'cron'         => '#ff9800',
            'webhook'      => '#e91e63',
            'user'         => '#3f51b5',
        );

        foreach ( $map as $key => $color ) {
            if ( false !== strpos( $actor, $key ) ) {
                return $color;
            }
        }

        // Deterministic colour by hash for unknown actors.
        $hash = crc32( $actor );
        $hue  = $hash % 360;
        return sprintf( 'hsl(%d, 50%%, 45%%)', absint( $hue ) );
    }

    /**
     * Map an action verb to a colour.
     *
     * @param string $action Action verb.
     * @return string Hex colour.
     */
    private static function get_action_color( $action ) {
        $action = strtolower( $action );

        $map = array(
            'created'  => '#4caf50',
            'updated'  => '#2196f3',
            'deleted'  => '#f44336',
            'archived' => '#ff9800',
            'restored' => '#4caf50',
            'synced'   => '#9c27b0',
            'failed'   => '#f44336',
            'error'    => '#f44336',
            'login'    => '#3f51b5',
            'logout'   => '#607d8b',
            'sent'     => '#00bcd4',
            'received' => '#4caf50',
            'approved' => '#4caf50',
            'rejected' => '#f44336',
            'pending'  => '#ff9800',
        );

        if ( isset( $map[ $action ] ) ) {
            return $map[ $action ];
        }

        return '#333333';
    }
}