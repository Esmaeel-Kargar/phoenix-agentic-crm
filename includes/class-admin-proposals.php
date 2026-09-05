<?php
/**
 * Phoenix Agentic CRM - Admin Proposals List Table
 *
 * Displays proposals in a WP_List_Table with search, pagination,
 * bulk actions (approve/reject/archive), and inline AJAX row actions.
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
 * Class Phoenix_CRM_Admin_Proposals
 *
 * Renders the Proposals admin page using WordPress's WP_List_Table.
 */
class Phoenix_CRM_Admin_Proposals {

    /**
     * The screen hook suffix for the proposals page.
     *
     * @var string
     */
    private $screen_hook;

    /**
     * Constructor — hook into admin menu and screen load.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    /**
     * Register the Proposals submenu page under the Phoenix CRM menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        $this->screen_hook = add_submenu_page(
            'phoenix-crm',                              // Parent slug (placeholder — adjust as needed)
            __( 'Proposals', 'phoenix-crm' ),
            __( 'Proposals', 'phoenix-crm' ),
            'manage_options',
            'phoenix-crm-proposals',
            array( $this, 'render_page' )
        );

        // Load the list table only on this screen.
        add_action( "load-{$this->screen_hook}", array( $this, 'load_screen' ) );
    }

    /**
     * Screen load — handle bulk actions and enqueue assets.
     *
     * @since 1.0.0
     * @return void
     */
    public function load_screen() {
        // Process bulk actions before the screen is rendered.
        $this->handle_bulk_actions();

        // Enqueue admin styles and inline AJAX script.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue CSS and JS for the proposals page.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== $this->screen_hook ) {
            return;
        }

        // Inline CSS for status badges.
        wp_add_inline_style(
            'list-tables',
            '.phoenix-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.4;
                text-transform: uppercase;
            }
            .phoenix-status-badge.pending {
                background: #fff3cd;
                color: #856404;
            }
            .phoenix-status-badge.approved {
                background: #d4edda;
                color: #155724;
            }
            .phoenix-status-badge.rejected {
                background: #f8d7da;
                color: #721c24;
            }
            .phoenix-status-badge.archived {
                background: #e2e3e5;
                color: #383d41;
            }
            .phoenix-row-actions a {
                text-decoration: none;
            }
            .phoenix-row-actions .approve {
                color: #28a745;
            }
            .phoenix-row-actions .reject {
                color: #dc3545;
            }
            .phoenix-row-actions .archive {
                color: #6c757d;
            }'
        );

        // Inline JS for AJAX row actions.
        $ajax_script = '
        (function($) {
            $(function() {
                $(".phoenix-row-action").on("click", function(e) {
                    e.preventDefault();

                    var $link   = $(this);
                    var $row    = $link.closest("tr");
                    var data    = {
                        action:     $link.data("action"),
                        proposal_id: $link.data("id"),
                        _wpnonce:   "' . wp_create_nonce( 'phoenix_ajax_nonce' ) . '"
                    };

                    $link.text("...").css("pointer-events", "none");

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            // Reload the page to reflect changes.
                            location.reload();
                        } else {
                            alert(response.data.message || "Request failed.");
                            $link.text($link.data("label")).css("pointer-events", "");
                        }
                    }).fail(function() {
                        alert("AJAX error. Please try again.");
                        $link.text($link.data("label")).css("pointer-events", "");
                    });
                });
            });
        })(jQuery);';

        wp_add_inline_script( 'jquery', $ajax_script );
    }

    /**
     * Handle bulk actions: approve, reject, archive.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_bulk_actions() {
        global $wpdb;

        // Bail if no valid action or nonce.
        if ( empty( $_REQUEST['action'] ) && empty( $_REQUEST['action2'] ) ) {
            return;
        }

        $action = '';
        if ( ! empty( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
            $action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
        } elseif ( ! empty( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
            $action = sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) );
        }

        if ( ! in_array( $action, array( 'approve', 'reject', 'archive' ), true ) ) {
            return;
        }

        // Verify nonce.
        if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-proposals' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        // Ensure the user has proper capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'phoenix-crm' ) );
        }

        if ( empty( $_REQUEST['proposal_ids'] ) || ! is_array( $_REQUEST['proposal_ids'] ) ) {
            return;
        }

        $ids       = array_map( 'absint', wp_unslash( $_REQUEST['proposal_ids'] ) );
        $table     = $wpdb->prefix . 'phoenix_proposals';
        $count     = 0;

        foreach ( $ids as $pid ) {
            if ( $pid <= 0 ) {
                continue;
            }

            if ( 'approve' === $action ) {
                $updated = $wpdb->update(
                    $table,
                    array(
                        'status'     => 'approved',
                        'decided_at' => current_time( 'mysql' ),
                        'executed'   => 1,
                    ),
                    array( 'id' => $pid, 'status' => 'pending' ),
                    array( '%s', '%s', '%d' ),
                    array( '%d', '%s' )
                );

                if ( false !== $updated && $updated > 0 ) {
                    $count++;
                    do_action( 'phoenix_event_logged', 0, 'user', 'proposal_approved', 'proposal', $pid, 'Proposal approved via bulk action.' );
                }
            } elseif ( 'reject' === $action ) {
                $updated = $wpdb->update(
                    $table,
                    array(
                        'status'     => 'rejected',
                        'decided_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $pid, 'status' => 'pending' ),
                    array( '%s', '%s' ),
                    array( '%d', '%s' )
                );

                if ( false !== $updated && $updated > 0 ) {
                    $count++;
                    do_action( 'phoenix_event_logged', 0, 'user', 'proposal_rejected', 'proposal', $pid, 'Proposal rejected via bulk action.' );
                }
            } elseif ( 'archive' === $action ) {
                $updated = $wpdb->update(
                    $table,
                    array( 'archived' => 1 ),
                    array( 'id' => $pid ),
                    array( '%d' ),
                    array( '%d' )
                );

                if ( false !== $updated && $updated > 0 ) {
                    $count++;
                    do_action( 'phoenix_event_logged', 0, 'user', 'proposal_archived', 'proposal', $pid, 'Proposal archived via bulk action.' );
                }
            }
        }

        // Set a transient notice.
        $notice_key = 'phoenix_proposals_bulk_notice';
        if ( $count > 0 ) {
            set_transient(
                $notice_key,
                array(
                    'type'    => 'success',
                    'message' => sprintf(
                        /* translators: %d: number of proposals affected */
                        __( '%d proposal(s) updated.', 'phoenix-crm' ),
                        $count
                    ),
                ),
                30
            );
        } else {
            set_transient(
                $notice_key,
                array(
                    'type'    => 'info',
                    'message' => __( 'No matching proposals were updated.', 'phoenix-crm' ),
                ),
                30
            );
        }

        // Redirect to avoid re-submission.
        wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'proposal_ids', '_wpnonce', '_wp_http_referer' ) ) );
        exit;
    }

    /**
     * Render the admin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_page() {
        // Show any admin notices from transients.
        $notice = get_transient( 'phoenix_proposals_bulk_notice' );
        if ( $notice && is_array( $notice ) ) {
            $class = 'notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible';
            printf(
                '<div class="%s"><p>%s</p></div>',
                esc_attr( $class ),
                esc_html( $notice['message'] )
            );
            delete_transient( 'phoenix_proposals_bulk_notice' );
        }

        // Instantiate the list table and prepare items.
        $list_table = new Phoenix_CRM_Admin_Proposals_List();
        $list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Proposals', 'phoenix-crm' ); ?></h1>
            <hr class="wp-header-end">

            <form id="proposals-filter" method="get">
                <?php
                // Pass the page slug so WP admin knows where we are.
                printf( '<input type="hidden" name="page" value="%s" />', esc_attr( 'phoenix-crm-proposals' ) );

                $list_table->search_box( __( 'Search Proposals', 'phoenix-crm' ), 'proposal-search' );
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }
}

// ---------------------------------------------------------------------------
//  WP_List_Table for Proposals
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Phoenix_CRM_Admin_Proposals_List
 *
 * Concrete WP_List_Table that queries the phoenix_proposals table.
 *
 * @since 1.0.0
 */
class Phoenix_CRM_Admin_Proposals_List extends WP_List_Table {

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'proposal',
                'plural'   => 'proposals',
                'ajax'     => false,
            )
        );
    }

    /**
     * Define table columns.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'       => '<input type="checkbox" />',
            'id'       => __( 'ID', 'phoenix-crm' ),
            'title'    => __( 'Title', 'phoenix-crm' ),
            'type'     => __( 'Type', 'phoenix-crm' ),
            'status'   => __( 'Status', 'phoenix-crm' ),
            'created'  => __( 'Created', 'phoenix-crm' ),
            'actions'  => __( 'Actions', 'phoenix-crm' ),
        );
    }

    /**
     * Define sortable columns.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'      => array( 'id', false ),
            'title'   => array( 'title', false ),
            'type'    => array( 'type', false ),
            'status'  => array( 'status', false ),
            'created' => array( 'created_at', true ),
        );
    }

    /**
     * Define bulk actions.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'approve' => __( 'Approve', 'phoenix-crm' ),
            'reject'  => __( 'Reject', 'phoenix-crm' ),
            'archive' => __( 'Archive', 'phoenix-crm' ),
        );
    }

    /**
     * Render the checkbox column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="proposal_ids[]" value="%d" />',
            intval( $item->id )
        );
    }

    /**
     * Render the ID column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_id( $item ) {
        return intval( $item->id );
    }

    /**
     * Render the title column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_title( $item ) {
        return esc_html( $item->title );
    }

    /**
     * Render the type column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_type( $item ) {
        return esc_html( ucfirst( $item->type ) );
    }

    /**
     * Render the status column with a colored badge.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_status( $item ) {
        $badge_class = sanitize_html_class( $item->status );

        if ( (int) $item->archived === 1 ) {
            $badge_class = 'archived';
        }

        $label = (int) $item->archived === 1
            ? __( 'Archived', 'phoenix-crm' )
            : ucfirst( $item->status );

        return sprintf(
            '<span class="phoenix-status-badge %s">%s</span>',
            esc_attr( $badge_class ),
            esc_html( $label )
        );
    }

    /**
     * Render the created-at column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_created( $item ) {
        if ( empty( $item->created_at ) || '0000-00-00 00:00:00' === $item->created_at ) {
            return '&mdash;';
        }

        $time = strtotime( $item->created_at );
        return sprintf(
            '<abbr title="%s">%s</abbr>',
            esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) ),
            esc_html( human_time_diff( $time, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'phoenix-crm' ) )
        );
    }

    /**
     * Render the actions column with inline approve/reject/archive links.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_actions( $item ) {
        $id = intval( $item->id );

        // Only pending proposals can be acted upon; archived items are read-only.
        if ( 'pending' !== $item->status || (int) $item->archived === 1 ) {
            return '&mdash;';
        }

        $actions = array();

        $actions[] = sprintf(
            '<a href="#" class="phoenix-row-action approve" data-action="phoenix_approve_proposal" data-id="%d" data-label="%s">%s</a>',
            $id,
            esc_attr__( 'Approve', 'phoenix-crm' ),
            esc_html__( 'Approve', 'phoenix-crm' )
        );

        $actions[] = sprintf(
            '<a href="#" class="phoenix-row-action reject" data-action="phoenix_reject_proposal" data-id="%d" data-label="%s">%s</a>',
            $id,
            esc_attr__( 'Reject', 'phoenix-crm' ),
            esc_html__( 'Reject', 'phoenix-crm' )
        );

        $actions[] = sprintf(
            '<a href="#" class="phoenix-row-action archive" data-action="phoenix_archive_proposal" data-id="%d" data-label="%s">%s</a>',
            $id,
            esc_attr__( 'Archive', 'phoenix-crm' ),
            esc_html__( 'Archive', 'phoenix-crm' )
        );

        return '<span class="phoenix-row-actions">' . implode( ' | ', $actions ) . '</span>';
    }

    /**
     * Define the default column handler.
     *
     * @since 1.0.0
     * @param object $item        The current row item.
     * @param string $column_name The column name.
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
    }

    /**
     * Prepare items for display — query, paginate, sort, search.
     *
     * @since 1.0.0
     * @return void
     */
    public function prepare_items() {
        global $wpdb;

        $table  = $wpdb->prefix . 'phoenix_proposals';
        $per_page = 20;

        // Column headers.
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );

        // Build the query.
        $where  = array( '1=1' );
        $params = array();

        // Search.
        $search = isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : '';
        if ( '' !== $search ) {
            $where[]  = '( title LIKE %s OR description LIKE %s )';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Sorting.
        $orderby = 'created_at';
        $order   = 'DESC';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $allowed = array( 'id', 'title', 'type', 'status', 'created_at' );
            $maybe   = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
            if ( in_array( $maybe, $allowed, true ) ) {
                $orderby = $maybe;
            }
        }

        if ( ! empty( $_REQUEST['order'] ) ) {
            $maybe = strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) );
            if ( in_array( $maybe, array( 'ASC', 'DESC' ), true ) ) {
                $order = $maybe;
            }
        }

        // Count total items.
        $count_sql  = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
        $total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Pagination.
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        // Fetch items.
        $data_sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            array_merge( $params, array( $per_page, $offset ) )
        );

        $this->items = $wpdb->get_results( $data_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Set pagination args.
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page ),
            )
        );
    }
}