<?php
/**
 * Phoenix Agentic CRM - Admin Goals List Table & Forms
 *
 * Displays goals in a WP_List_Table with inline add/edit form.
 * Supports nonce protection and admin notices.
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
 * Class Phoenix_CRM_Admin_Goals
 *
 * Renders the Goals admin page with list table and add/edit capabilities.
 *
 * @since 1.0.0
 */
class Phoenix_CRM_Admin_Goals {

    /**
     * The screen hook suffix for the goals page.
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
     * Register the Goals submenu page under the Phoenix CRM menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        $this->screen_hook = add_submenu_page(
            'phoenix-crm',
            __( 'Goals', 'phoenix-crm' ),
            __( 'Goals', 'phoenix-crm' ),
            'manage_options',
            'phoenix-crm-goals',
            array( $this, 'render_page' )
        );

        add_action( "load-{$this->screen_hook}", array( $this, 'load_screen' ) );
    }

    /**
     * Screen load — handle form submissions and bulk actions.
     *
     * @since 1.0.0
     * @return void
     */
    public function load_screen() {
        $this->handle_save_goal();
        $this->handle_delete_goal();
        $this->handle_bulk_actions();

        add_action( 'admin_enqueue_styles', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue CSS for the goals page.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== $this->screen_hook ) {
            return;
        }

        wp_add_inline_style(
            'list-tables',
            '.phoenix-goal-form-wrap {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .phoenix-goal-form-wrap h2 {
                margin-top: 0;
            }
            .phoenix-goal-form-wrap table.form-table th {
                width: 160px;
            }
            .phoenix-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.4;
                text-transform: uppercase;
            }
            .phoenix-status-badge.active {
                background: #d4edda;
                color: #155724;
            }
            .phoenix-status-badge.completed {
                background: #cce5ff;
                color: #004085;
            }
            .phoenix-status-badge.paused {
                background: #fff3cd;
                color: #856404;
            }
            .phoenix-status-badge.cancelled {
                background: #f8d7da;
                color: #721c24;
            }'
        );
    }

    /**
     * Handle saving (add/edit) a goal.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_save_goal() {
        if ( empty( $_POST['phoenix_goal_save'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'phoenix_goal_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'phoenix-crm' ) );
        }

        global $wpdb;

        $title       = isset( $_POST['goal_title'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_title'] ) ) : '';
        $description = isset( $_POST['goal_description'] ) ? wp_kses_post( wp_unslash( $_POST['goal_description'] ) ) : '';
        $horizon     = isset( $_POST['goal_horizon'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_horizon'] ) ) : '';
        $period_start = isset( $_POST['goal_period_start'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_period_start'] ) ) : '';
        $period_end   = isset( $_POST['goal_period_end'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_period_end'] ) ) : '';
        $status      = isset( $_POST['goal_status'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_status'] ) ) : 'active';
        $goal_id     = isset( $_POST['goal_id'] ) ? absint( $_POST['goal_id'] ) : 0;

        if ( empty( $title ) ) {
            $this->set_notice( 'error', __( 'Goal title is required.', 'phoenix-crm' ) );
            return;
        }

        if ( ! in_array( $status, array( 'active', 'completed', 'paused', 'cancelled' ), true ) ) {
            $status = 'active';
        }

        $allowed_horizons = array( 'short-term', 'medium-term', 'long-term', 'quarterly', 'yearly' );
        if ( ! empty( $horizon ) && ! in_array( $horizon, $allowed_horizons, true ) ) {
            $horizon = '';
        }

        $table = $wpdb->prefix . 'phoenix_goals';
        $now   = current_time( 'mysql' );

        if ( $goal_id > 0 ) {
            // Update existing goal.
            $updated = $wpdb->update(
                $table,
                array(
                    'title'        => $title,
                    'description'  => $description,
                    'horizon'      => $horizon,
                    'period_start' => $period_start ?: null,
                    'period_end'   => $period_end ?: null,
                    'status'       => $status,
                ),
                array( 'id' => $goal_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );

            if ( false !== $updated ) {
                $this->set_notice( 'success', __( 'Goal updated.', 'phoenix-crm' ) );
                do_action( 'phoenix_event_logged', 0, 'user', 'goal_updated', 'goal', $goal_id, 'Goal updated via admin.' );
            } else {
                $this->set_notice( 'error', __( 'Failed to update goal.', 'phoenix-crm' ) );
            }
        } else {
            // Insert new goal.
            $inserted = $wpdb->insert(
                $table,
                array(
                    'title'        => $title,
                    'description'  => $description,
                    'horizon'      => $horizon,
                    'period_start' => $period_start ?: null,
                    'period_end'   => $period_end ?: null,
                    'status'       => $status,
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s' )
            );

            if ( false !== $inserted ) {
                $new_id = $wpdb->insert_id;
                $this->set_notice( 'success', __( 'Goal created.', 'phoenix-crm' ) );
                do_action( 'phoenix_event_logged', 0, 'user', 'goal_created', 'goal', $new_id, 'Goal created via admin.' );
            } else {
                $this->set_notice( 'error', __( 'Failed to create goal.', 'phoenix-crm' ) );
            }
        }
    }

    /**
     * Handle single goal deletion.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_delete_goal() {
        if ( empty( $_GET['delete_goal'] ) ) {
            return;
        }

        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'phoenix_delete_goal' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'phoenix-crm' ) );
        }

        global $wpdb;
        $goal_id = absint( $_GET['delete_goal'] );

        if ( $goal_id <= 0 ) {
            return;
        }

        $table  = $wpdb->prefix . 'phoenix_goals';
        $deleted = $wpdb->delete( $table, array( 'id' => $goal_id ), array( '%d' ) );

        if ( false !== $deleted && $deleted > 0 ) {
            $this->set_notice( 'success', __( 'Goal deleted.', 'phoenix-crm' ) );
            do_action( 'phoenix_event_logged', 0, 'user', 'goal_deleted', 'goal', $goal_id, 'Goal deleted via admin.' );
        } else {
            $this->set_notice( 'error', __( 'Goal not found or could not be deleted.', 'phoenix-crm' ) );
        }

        // Redirect to remove the delete_goal parameter.
        wp_safe_redirect( remove_query_arg( array( 'delete_goal', '_wpnonce' ) ) );
        exit;
    }

    /**
     * Handle bulk actions.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_bulk_actions() {
        global $wpdb;

        if ( empty( $_REQUEST['action'] ) && empty( $_REQUEST['action2'] ) ) {
            return;
        }

        $action = '';
        if ( ! empty( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
            $action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
        } elseif ( ! empty( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
            $action = sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) );
        }

        if ( ! in_array( $action, array( 'activate', 'complete', 'pause', 'cancel', 'delete' ), true ) ) {
            return;
        }

        if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-goals' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'phoenix-crm' ) );
        }

        if ( empty( $_REQUEST['goal_ids'] ) || ! is_array( $_REQUEST['goal_ids'] ) ) {
            return;
        }

        $ids      = array_map( 'absint', wp_unslash( $_REQUEST['goal_ids'] ) );
        $table    = $wpdb->prefix . 'phoenix_goals';
        $count    = 0;

        foreach ( $ids as $gid ) {
            if ( $gid <= 0 ) {
                continue;
            }

            if ( 'delete' === $action ) {
                $result = $wpdb->delete( $table, array( 'id' => $gid ), array( '%d' ) );
                if ( false !== $result && $result > 0 ) {
                    $count++;
                    do_action( 'phoenix_event_logged', 0, 'user', 'goal_deleted', 'goal', $gid, 'Goal deleted via bulk action.' );
                }
            } else {
                $status_map = array(
                    'activate'  => 'active',
                    'complete'  => 'completed',
                    'pause'     => 'paused',
                    'cancel'    => 'cancelled',
                );
                $new_status = isset( $status_map[ $action ] ) ? $status_map[ $action ] : '';

                if ( $new_status ) {
                    $result = $wpdb->update(
                        $table,
                        array( 'status' => $new_status ),
                        array( 'id' => $gid ),
                        array( '%s' ),
                        array( '%d' )
                    );
                    if ( false !== $result && $result > 0 ) {
                        $count++;
                        do_action( 'phoenix_event_logged', 0, 'user', 'goal_status_changed', 'goal', $gid, "Goal status changed to {$new_status} via bulk action." );
                    }
                }
            }
        }

        $notice_key = 'phoenix_goals_bulk_notice';
        if ( $count > 0 ) {
            set_transient(
                $notice_key,
                array(
                    'type'    => 'success',
                    'message' => sprintf(
                        /* translators: %d: number of goals affected */
                        __( '%d goal(s) updated.', 'phoenix-crm' ),
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
                    'message' => __( 'No matching goals were updated.', 'phoenix-crm' ),
                ),
                30
            );
        }

        wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'goal_ids', '_wpnonce', '_wp_http_referer' ) ) );
        exit;
    }

    /**
     * Store an admin notice transient.
     *
     * @since 1.0.0
     * @param string $type    Notice type: success, error, info, warning.
     * @param string $message Notice message.
     * @return void
     */
    private function set_notice( $type, $message ) {
        set_transient(
            'phoenix_goal_notice',
            array(
                'type'    => sanitize_key( $type ),
                'message' => wp_kses_post( $message ),
            ),
            30
        );
    }

    /**
     * Render the admin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_page() {
        // Show any admin notices.
        $notice = get_transient( 'phoenix_goal_notice' );
        if ( $notice && is_array( $notice ) ) {
            $class = 'notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible';
            printf(
                '<div class="%s"><p>%s</p></div>',
                esc_attr( $class ),
                wp_kses_post( $notice['message'] )
            );
            delete_transient( 'phoenix_goal_notice' );
        }

        // If editing, fetch the goal record.
        $edit_goal = null;
        if ( ! empty( $_GET['edit_goal'] ) ) {
            $edit_id = absint( $_GET['edit_goal'] );
            if ( $edit_id > 0 ) {
                global $wpdb;
                $table     = $wpdb->prefix . 'phoenix_goals';
                $edit_goal = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Goals', 'phoenix-crm' ); ?></h1>
            <hr class="wp-header-end">

            <?php $this->render_goal_form( $edit_goal ); ?>

            <form id="goals-filter" method="get">
                <?php
                printf( '<input type="hidden" name="page" value="%s" />', esc_attr( 'phoenix-crm-goals' ) );

                $list_table = new Phoenix_CRM_Admin_Goals_List();
                $list_table->prepare_items();
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the add/edit goal form.
     *
     * @since 1.0.0
     * @param object|null $goal Optional goal object for editing.
     * @return void
     */
    private function render_goal_form( $goal = null ) {
        $is_editing = null !== $goal;
        $form_action = $is_editing
            ? __( 'Update Goal', 'phoenix-crm' )
            : __( 'Add New Goal', 'phoenix-crm' );
        ?>
        <div class="phoenix-goal-form-wrap">
            <h2><?php echo esc_html( $form_action ); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field( 'phoenix_goal_action' ); ?>

                <?php if ( $is_editing ) : ?>
                    <input type="hidden" name="goal_id" value="<?php echo intval( $goal->id ); ?>" />
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="goal_title"><?php esc_html_e( 'Title', 'phoenix-crm' ); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="goal_title" name="goal_title" value="<?php echo $is_editing ? esc_attr( $goal->title ) : ''; ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="goal_description"><?php esc_html_e( 'Description', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <textarea id="goal_description" name="goal_description" rows="4" class="large-text"><?php echo $is_editing ? esc_textarea( $goal->description ) : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="goal_horizon"><?php esc_html_e( 'Horizon', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <select id="goal_horizon" name="goal_horizon">
                                <option value=""><?php esc_html_e( '— Select —', 'phoenix-crm' ); ?></option>
                                <option value="short-term" <?php selected( $is_editing, true ) ? selected( $goal->horizon, 'short-term' ) : ''; ?>><?php esc_html_e( 'Short-term', 'phoenix-crm' ); ?></option>
                                <option value="medium-term" <?php selected( $is_editing, true ) ? selected( $goal->horizon, 'medium-term' ) : ''; ?>><?php esc_html_e( 'Medium-term', 'phoenix-crm' ); ?></option>
                                <option value="long-term" <?php selected( $is_editing, true ) ? selected( $goal->horizon, 'long-term' ) : ''; ?>><?php esc_html_e( 'Long-term', 'phoenix-crm' ); ?></option>
                                <option value="quarterly" <?php selected( $is_editing, true ) ? selected( $goal->horizon, 'quarterly' ) : ''; ?>><?php esc_html_e( 'Quarterly', 'phoenix-crm' ); ?></option>
                                <option value="yearly" <?php selected( $is_editing, true ) ? selected( $goal->horizon, 'yearly' ) : ''; ?>><?php esc_html_e( 'Yearly', 'phoenix-crm' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="goal_period_start"><?php esc_html_e( 'Period Start', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <input type="date" id="goal_period_start" name="goal_period_start" value="<?php echo $is_editing ? esc_attr( $goal->period_start ) : ''; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="goal_period_end"><?php esc_html_e( 'Period End', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <input type="date" id="goal_period_end" name="goal_period_end" value="<?php echo $is_editing ? esc_attr( $goal->period_end ) : ''; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="goal_status"><?php esc_html_e( 'Status', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <select id="goal_status" name="goal_status">
                                <option value="active" <?php selected( $is_editing, true ) ? selected( $goal->status, 'active' ) : ''; ?>><?php esc_html_e( 'Active', 'phoenix-crm' ); ?></option>
                                <option value="completed" <?php selected( $is_editing, true ) ? selected( $goal->status, 'completed' ) : ''; ?>><?php esc_html_e( 'Completed', 'phoenix-crm' ); ?></option>
                                <option value="paused" <?php selected( $is_editing, true ) ? selected( $goal->status, 'paused' ) : ''; ?>><?php esc_html_e( 'Paused', 'phoenix-crm' ); ?></option>
                                <option value="cancelled" <?php selected( $is_editing, true ) ? selected( $goal->status, 'cancelled' ) : ''; ?>><?php esc_html_e( 'Cancelled', 'phoenix-crm' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="phoenix_goal_save" class="button button-primary">
                        <?php echo esc_html( $is_editing ? __( 'Update Goal', 'phoenix-crm' ) : __( 'Add Goal', 'phoenix-crm' ) ); ?>
                    </button>
                    <?php if ( $is_editing ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-goals' ) ); ?>" class="button">
                            <?php esc_html_e( 'Cancel', 'phoenix-crm' ); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }
}

// ---------------------------------------------------------------------------
//  WP_List_Table for Goals
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Phoenix_CRM_Admin_Goals_List
 *
 * Concrete WP_List_Table that queries the phoenix_goals table.
 *
 * @since 1.0.0
 */
class Phoenix_CRM_Admin_Goals_List extends WP_List_Table {

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'goal',
                'plural'   => 'goals',
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
            'horizon'  => __( 'Horizon', 'phoenix-crm' ),
            'period'   => __( 'Period', 'phoenix-crm' ),
            'status'   => __( 'Status', 'phoenix-crm' ),
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
            'horizon' => array( 'horizon', false ),
            'status'  => array( 'status', false ),
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
            'activate' => __( 'Activate', 'phoenix-crm' ),
            'complete' => __( 'Complete', 'phoenix-crm' ),
            'pause'    => __( 'Pause', 'phoenix-crm' ),
            'cancel'   => __( 'Cancel', 'phoenix-crm' ),
            'delete'   => __( 'Delete', 'phoenix-crm' ),
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
            '<input type="checkbox" name="goal_ids[]" value="%d" />',
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
     * Render the title column with row actions.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_title( $item ) {
        $edit_link = add_query_arg(
            array(
                'page'      => 'phoenix-crm-goals',
                'edit_goal' => $item->id,
            ),
            admin_url( 'admin.php' )
        );

        $delete_link = wp_nonce_url(
            add_query_arg(
                array(
                    'page'        => 'phoenix-crm-goals',
                    'delete_goal' => $item->id,
                ),
                admin_url( 'admin.php' )
            ),
            'phoenix_delete_goal'
        );

        $actions = array(
            'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html__( 'Edit', 'phoenix-crm' ) ),
            'delete' => sprintf( '<a href="%s" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_link ), esc_js( __( 'Are you sure?', 'phoenix-crm' ) ), esc_html__( 'Delete', 'phoenix-crm' ) ),
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            esc_url( $edit_link ),
            esc_html( $item->title ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Render the horizon column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_horizon( $item ) {
        if ( empty( $item->horizon ) ) {
            return '&mdash;';
        }
        return esc_html( ucwords( str_replace( '-', ' ', $item->horizon ) ) );
    }

    /**
     * Render the period column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_period( $item ) {
        $start = ( $item->period_start && '0000-00-00' !== $item->period_start )
            ? date_i18n( get_option( 'date_format' ), strtotime( $item->period_start ) )
            : '';

        $end = ( $item->period_end && '0000-00-00' !== $item->period_end )
            ? date_i18n( get_option( 'date_format' ), strtotime( $item->period_end ) )
            : '';

        if ( empty( $start ) && empty( $end ) ) {
            return '&mdash;';
        }

        return sprintf( '%s &ndash; %s', esc_html( $start ), esc_html( $end ) );
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
        $label       = ucfirst( $item->status );

        return sprintf(
            '<span class="phoenix-status-badge %s">%s</span>',
            esc_attr( $badge_class ),
            esc_html( $label )
        );
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
     * Prepare items for display — query, paginate, sort.
     *
     * @since 1.0.0
     * @return void
     */
    public function prepare_items() {
        global $wpdb;

        $table    = $wpdb->prefix . 'phoenix_goals';
        $per_page = 20;

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );

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
        $orderby = 'id';
        $order   = 'DESC';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $allowed = array( 'id', 'title', 'horizon', 'status' );
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

        // Count total.
        $count_sql   = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
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

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page ),
            )
        );
    }
}