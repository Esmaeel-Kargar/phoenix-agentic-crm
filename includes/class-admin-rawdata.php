<?php
/**
 * Phoenix Agentic CRM - Admin Raw Data List Table
 *
 * Renders the Raw Data viewer under the CRM admin menu.
 * Extends WP_List_Table with source filtering, pagination,
 * data preview expansion, and bulk Sync/Delete actions.
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
 * Class Phoenix_CRM_Admin_RawData
 *
 * Displays wp_phoenix_raw_data rows in a read-only WP_List_Table
 * with AJAX-powered Sync All and Delete All controls.
 */
class Phoenix_CRM_Admin_RawData extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'raw-data',
                'plural'   => 'raw-data',
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
            'cb'           => '<input type="checkbox" />',
            'id'           => __( 'ID', 'phoenix-crm' ),
            'source'       => __( 'Source', 'phoenix-crm' ),
            'category'     => __( 'Category', 'phoenix-crm' ),
            'data_preview' => __( 'Data', 'phoenix-crm' ),
            'collected_at' => __( 'Collected', 'phoenix-crm' ),
            'synced'       => __( 'Synced', 'phoenix-crm' ),
        );
    }

    /**
     * Define sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'           => array( 'id', true ),
            'source'       => array( 'source', false ),
            'category'     => array( 'category', false ),
            'collected_at' => array( 'collected_at', false ),
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
            '<input type="checkbox" name="raw_id[]" value="%d" />',
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
     * Render the Source column with a coloured badge.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_source( $item ) {
        $source = esc_html( $item->source );
        $color  = self::get_source_color( $source );

        return sprintf(
            '<span class="phoenix-source-badge" style="display:inline-block;background:%s;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">%s</span>',
            esc_attr( $color ),
            $source
        );
    }

    /**
     * Render the Category column.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_category( $item ) {
        $category = esc_html( $item->category );

        if ( empty( $category ) ) {
            return '<span style="color:#999;">&mdash;</span>';
        }

        return sprintf(
            '<code>%s</code>',
            $category
        );
    }

    /**
     * Render the Data preview column — truncate + expand.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_data_preview( $item ) {
        $data       = $item->data;
        $data_esc   = esc_html( $data );
        $data_br    = nl2br( $data_esc );
        $truncated  = mb_strlen( $data ) > 100;
        $preview    = $truncated ? esc_html( mb_substr( $data, 0, 100 ) ) . '&hellip;' : $data_br;
        $expanded   = $data_br;

        if ( empty( $data ) ) {
            return '<span style="color:#999;">&mdash;</span>';
        }

        $row_id = 'raw-data-' . intval( $item->id );

        if ( ! $truncated ) {
            return sprintf(
                '<div class="phoenix-data-preview" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;background:#f0f0f1;padding:4px 6px;border-radius:3px;">%s</div>',
                $data_br
            );
        }

        return sprintf(
            '<div class="phoenix-data-preview" id="%s-preview" style="font-family:monospace;font-size:11px;background:#f0f0f1;padding:4px 6px;border-radius:3px;">%s <a href="#" class="phoenix-raw-toggle" data-raw-id="%d" style="font-size:11px;">%s</a></div>
            <div class="phoenix-data-full" id="%s-full" style="display:none;font-family:monospace;font-size:11px;background:#f0f0f1;padding:4px 6px;border-radius:3px;max-height:200px;overflow-y:auto;">%s <a href="#" class="phoenix-raw-toggle" data-raw-id="%d" style="font-size:11px;">%s</a></div>',
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
     * Render the Collected column.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_collected_at( $item ) {
        if ( empty( $item->collected_at ) || '0000-00-00 00:00:00' === $item->collected_at ) {
            return '<span style="color:#999;">&mdash;</span>';
        }

        $time  = strtotime( $item->collected_at );
        $label = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );

        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr( $label ),
            $label
        );
    }

    /**
     * Render the Synced column — checkmark or X icon.
     *
     * @param object $item Row data.
     * @return string
     */
    protected function column_synced( $item ) {
        if ( ! empty( $item->synced_at ) && '0000-00-00 00:00:00' !== $item->synced_at ) {
            $time  = strtotime( $item->synced_at );
            $label = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );

            return sprintf(
                '<span title="%s" style="color:#4caf50;font-size:18px;">&#10003;</span>',
                esc_attr( $label )
            );
        }

        return '<span style="color:#ccc;font-size:18px;">&#10007;</span>';
    }

    /**
     * Define extra table navigation (top) with filter dropdowns and action buttons.
     *
     * @param string $which 'top' or 'bottom'.
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_raw_data';

        // Unique sources.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $sources = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT source FROM {$table} ORDER BY source ASC"
            )
        );

        $current_source = isset( $_REQUEST['filter_source'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_source'] ) ) : '';
        ?>
        <div class="alignleft actions phoenix-raw-filters" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <select name="filter_source" id="filter-source">
                <option value=""><?php esc_html_e( 'All Sources', 'phoenix-crm' ); ?></option>
                <?php foreach ( $sources as $source ) : ?>
                    <option value="<?php echo esc_attr( $source ); ?>" <?php selected( $current_source, $source ); ?>><?php echo esc_html( $source ); ?></option>
                <?php endforeach; ?>
            </select>

            <?php
            submit_button(
                __( 'Filter', 'phoenix-crm' ),
                'secondary',
                'filter_action_btn',
                false
            );
            ?>

            <button type="button" id="phoenix-sync-all" class="button button-primary" style="margin-left:8px;">
                <?php esc_html_e( 'Sync All', 'phoenix-crm' ); ?>
            </button>

            <button type="button" id="phoenix-delete-all" class="button button-secondary" style="color:#b32d2e;border-color:#b32d2e;">
                <?php esc_html_e( 'Delete All', 'phoenix-crm' ); ?>
            </button>

            <span id="phoenix-raw-action-msg" style="margin-left:8px;font-style:italic;color:#666;"></span>
        </div>
        <?php
    }

    /**
     * Prepare the list table items.
     */
    public function prepare_items() {
        global $wpdb;

        $table        = $wpdb->prefix . 'phoenix_raw_data';
        $per_page     = 25;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        // Build WHERE clauses.
        $where  = array( '1=1' );
        $values = array();

        // Source filter.
        if ( ! empty( $_REQUEST['filter_source'] ) ) {
            $source   = sanitize_text_field( wp_unslash( $_REQUEST['filter_source'] ) );
            $where[]  = 'source = %s';
            $values[] = $source;
        }

        $where_clause = implode( ' AND ', $where );

        // Sort order.
        $orderby = 'collected_at';
        $order   = 'DESC';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $allowed_orderby = array( 'id', 'source', 'category', 'collected_at' );
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
        esc_html_e( 'No raw data records found.', 'phoenix-crm' );
    }

    /**
     * Render the admin page for the Raw Data list table.
     *
     * Called by Phoenix_CRM_Admin_Menu via route_to().
     * Outputs the WP_List_Table with filters, Sync All / Delete All buttons,
     * and inline JS for AJAX actions and data expansion.
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
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Raw Data', 'phoenix-crm' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="phoenix-crm-raw-data" />
                <?php
                $table->display();
                ?>
            </form>
        </div>

        <style>
        .wp-list-table .column-id { width: 60px; }
        .wp-list-table .column-source { width: 110px; }
        .wp-list-table .column-category { width: 110px; }
        .wp-list-table .column-data_preview { width: auto; }
        .wp-list-table .column-collected_at { width: 160px; }
        .wp-list-table .column-synced { width: 60px; text-align: center; }
        #phoenix-raw-action-msg { font-size: 13px; }
        #phoenix-delete-all[disabled],
        #phoenix-sync-all[disabled] { opacity: 0.6; cursor: not-allowed; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Toggle full data preview.
            $(document).on('click', '.phoenix-raw-toggle', function(e) {
                e.preventDefault();
                var id = $(this).data('raw-id');
                $('#raw-data-' + id + '-preview').toggle();
                $('#raw-data-' + id + '-full').toggle();
            });

            // Sync All — AJAX.
            $('#phoenix-sync-all').on('click', function() {
                if ( ! confirm( '<?php echo esc_js( __( 'Sync all raw data records? This may take a moment.', 'phoenix-crm' ) ); ?>' ) ) {
                    return;
                }
                var $btn   = $(this);
                var $msg   = $('#phoenix-raw-action-msg');
                $btn.prop('disabled', true);
                $msg.text( '<?php echo esc_js( __( 'Syncing...', 'phoenix-crm' ) ); ?>' );
                $.post(ajaxurl, {
                    action: 'phoenix_raw_sync_all',
                    _ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'phoenix_raw_sync_all' ) ); ?>'
                }, function(resp) {
                    if ( resp.success ) {
                        $msg.text( resp.data.message );
                        location.reload();
                    } else {
                        $msg.text( resp.data ? resp.data.message : '<?php echo esc_js( __( 'Sync failed.', 'phoenix-crm' ) ); ?>' );
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    $msg.text( '<?php echo esc_js( __( 'Request failed.', 'phoenix-crm' ) ); ?>' );
                    $btn.prop('disabled', false);
                });
            });

            // Delete All — confirmation dialog.
            $('#phoenix-delete-all').on('click', function() {
                if ( ! confirm( '<?php echo esc_js( __( 'Are you sure you want to DELETE all raw data records? This cannot be undone.', 'phoenix-crm' ) ); ?>' ) ) {
                    return;
                }
                var $btn   = $(this);
                var $msg   = $('#phoenix-raw-action-msg');
                $btn.prop('disabled', true);
                $msg.text( '<?php echo esc_js( __( 'Deleting...', 'phoenix-crm' ) ); ?>' );
                $.post(ajaxurl, {
                    action: 'phoenix_raw_delete_all',
                    _ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'phoenix_raw_delete_all' ) ); ?>'
                }, function(resp) {
                    if ( resp.success ) {
                        $msg.text( resp.data.message );
                        location.reload();
                    } else {
                        $msg.text( resp.data ? resp.data.message : '<?php echo esc_js( __( 'Delete failed.', 'phoenix-crm' ) ); ?>' );
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    $msg.text( '<?php echo esc_js( __( 'Request failed.', 'phoenix-crm' ) ); ?>' );
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    // -----------------------------------------------------------------------
    //  Bulk actions
    // -----------------------------------------------------------------------

    /**
     * Define bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'sync'   => __( 'Sync Selected', 'phoenix-crm' ),
            'delete' => __( 'Delete Selected', 'phoenix-crm' ),
        );
    }

    /**
     * Process bulk actions (called from admin page handler).
     * This is a stub — actual processing is handled by the page controller
     * via wp_ajax_ actions. The UI also provides Sync All / Delete All buttons.
     */
    public static function process_bulk_action() {
        // Bulk action processing is handled via admin-post hooks.
    }

    // -----------------------------------------------------------------------
    //  Helpers – source colour mapping
    // -----------------------------------------------------------------------

    /**
     * Map a source name to a background colour.
     *
     * @param string $source Source identifier.
     * @return string Hex colour.
     */
    private static function get_source_color( $source ) {
        $source = strtolower( $source );

        $map = array(
            'webhook'  => '#e91e63',
            'api'      => '#2196f3',
            'scrape'   => '#ff9800',
            'scraping' => '#ff9800',
            'cron'     => '#607d8b',
            'manual'   => '#4caf50',
            'import'   => '#9c27b0',
            'agent'    => '#00bcd4',
            'ai'       => '#00bcd4',
        );

        foreach ( $map as $key => $color ) {
            if ( false !== strpos( $source, $key ) ) {
                return $color;
            }
        }

        // Deterministic colour by hash.
        $hash = crc32( $source );
        $hue  = $hash % 360;
        return sprintf( 'hsl(%d, 50%%, 45%%)', absint( $hue ) );
    }

    // -----------------------------------------------------------------------
    //  AJAX Handlers
    // -----------------------------------------------------------------------

    /**
     * Register AJAX actions for Sync All and Delete All.
     *
     * @return void
     */
    public static function init_ajax() {
        add_action( 'wp_ajax_phoenix_raw_sync_all', array( __CLASS__, 'ajax_sync_all' ) );
        add_action( 'wp_ajax_phoenix_raw_delete_all', array( __CLASS__, 'ajax_delete_all' ) );
    }

    /**
     * AJAX handler: Mark all raw data records as synced.
     *
     * @return void
     */
    public static function ajax_sync_all() {
        check_ajax_referer( 'phoenix_raw_sync_all' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'phoenix-crm' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_raw_data';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET synced_at = %s WHERE synced_at = '0000-00-00 00:00:00' OR synced_at IS NULL",
                current_time( 'mysql' )
            )
        );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => __( 'Database error while syncing.', 'phoenix-crm' ) ) );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of records synced */
                __( 'Synced %d records.', 'phoenix-crm' ),
                $updated
            ),
        ) );
    }

    /**
     * AJAX handler: Delete all raw data records.
     *
     * @return void
     */
    public static function ajax_delete_all() {
        check_ajax_referer( 'phoenix_raw_delete_all' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'phoenix-crm' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_raw_data';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $deleted = $wpdb->query( "TRUNCATE TABLE {$table}" );

        if ( false === $deleted ) {
            wp_send_json_error( array( 'message' => __( 'Database error while deleting.', 'phoenix-crm' ) ) );
        }

        wp_send_json_success( array(
            'message' => __( 'All raw data records deleted.', 'phoenix-crm' ),
        ) );
    }
}