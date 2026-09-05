<?php
/**
 * Phoenix Agentic CRM - Admin Projects List Table & Forms
 *
 * Displays projects in a WP_List_Table with inline add/edit form.
 * Fields: title, type (normal/special), description, status, optional proposal_id.
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
 * Class Phoenix_CRM_Admin_Projects
 *
 * Renders the Projects admin page with list table and add/edit capabilities.
 *
 * @since 1.0.0
 */
class Phoenix_CRM_Admin_Projects {

    /**
     * The screen hook suffix for the projects page.
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
     * Register the Projects submenu page under the Phoenix CRM menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        $this->screen_hook = add_submenu_page(
            'phoenix-crm',
            __( 'Projects', 'phoenix-crm' ),
            __( 'Projects', 'phoenix-crm' ),
            'manage_options',
            'phoenix-crm-projects',
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
        $this->handle_save_project();
        $this->handle_delete_project();
        $this->handle_bulk_actions();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue CSS for the projects page.
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
            '.phoenix-project-form-wrap {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .phoenix-project-form-wrap h2 {
                margin-top: 0;
            }
            .phoenix-project-form-wrap table.form-table th {
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
            .phoenix-status-badge.planning {
                background: #e2e3e5;
                color: #383d41;
            }
            .phoenix-status-badge.active {
                background: #d4edda;
                color: #155724;
            }
            .phoenix-status-badge.completed {
                background: #cce5ff;
                color: #004085;
            }
            .phoenix-status-badge.on_hold {
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
     * Handle saving (add/edit) a project.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_save_project() {
        if ( empty( $_POST['phoenix_project_save'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'phoenix_project_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'phoenix-crm' ) );
        }

        global $wpdb;

        $title       = isset( $_POST['project_title'] ) ? sanitize_text_field( wp_unslash( $_POST['project_title'] ) ) : '';
        $type        = isset( $_POST['project_type'] ) ? sanitize_text_field( wp_unslash( $_POST['project_type'] ) ) : '';
        $description = isset( $_POST['project_description'] ) ? wp_kses_post( wp_unslash( $_POST['project_description'] ) ) : '';
        $status      = isset( $_POST['project_status'] ) ? sanitize_text_field( wp_unslash( $_POST['project_status'] ) ) : 'planning';
        $proposal_id = isset( $_POST['project_proposal_id'] ) ? absint( $_POST['project_proposal_id'] ) : null;
        $project_id  = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;

        if ( empty( $title ) ) {
            $this->set_notice( 'error', __( 'Project title is required.', 'phoenix-crm' ) );
            return;
        }

        if ( ! in_array( $type, array( 'normal', 'special' ), true ) ) {
            $type = 'normal';
        }

        $allowed_statuses = array( 'planning', 'active', 'completed', 'on_hold', 'cancelled' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'planning';
        }

        if ( $proposal_id && $proposal_id <= 0 ) {
            $proposal_id = null;
        }

        $table = $wpdb->prefix . 'phoenix_projects';
        $now   = current_time( 'mysql' );

        if ( $project_id > 0 ) {
            // Update existing project.
            $data = array(
                'title'       => $title,
                'type'        => $type,
                'description' => $description,
                'status'      => $status,
                'proposal_id' => $proposal_id,
            );

            // If status changed to completed, set completed_at.
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $project_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( 'completed' === $status && $existing !== 'completed' ) {
                $data['completed_at'] = $now;
            }

            $updated = $wpdb->update( $table, $data, array( 'id' => $project_id ), array( '%s', '%s', '%s', '%s', '%d' ), array( '%d' ) );

            if ( false !== $updated ) {
                $this->set_notice( 'success', __( 'Project updated.', 'phoenix-crm' ) );
                do_action( 'phoenix_event_logged', 0, 'user', 'project_updated', 'project', $project_id, 'Project updated via admin.' );
            } else {
                $this->set_notice( 'error', __( 'Failed to update project.', 'phoenix-crm' ) );
            }
        } else {
            // Insert new project.
            $inserted = $wpdb->insert(
                $table,
                array(
                    'title'       => $title,
                    'type'        => $type,
                    'description' => $description,
                    'status'      => $status,
                    'proposal_id' => $proposal_id,
                    'created_at'  => $now,
                ),
                array( '%s', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( false !== $inserted ) {
                $new_id = $wpdb->insert_id;
                $this->set_notice( 'success', __( 'Project created.', 'phoenix-crm' ) );
                do_action( 'phoenix_event_logged', 0, 'user', 'project_created', 'project', $new_id, 'Project created via admin.' );
            } else {
                $this->set_notice( 'error', __( 'Failed to create project.', 'phoenix-crm' ) );
            }
        }
    }

    /**
     * Handle single project deletion.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_delete_project() {
        if ( empty( $_GET['delete_project'] ) ) {
            return;
        }

        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'phoenix_delete_project' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'phoenix-crm' ) );
        }

        global $wpdb;
        $project_id = absint( $_GET['delete_project'] );

        if ( $project_id <= 0 ) {
            return;
        }

        $table   = $wpdb->prefix . 'phoenix_projects';
        $deleted = $wpdb->delete( $table, array( 'id' => $project_id ), array( '%d' ) );

        if ( false !== $deleted && $deleted > 0 ) {
            $this->set_notice( 'success', __( 'Project deleted.', 'phoenix-crm' ) );
            do_action( 'phoenix_event_logged', 0, 'user', 'project_deleted', 'project', $project_id, 'Project deleted via admin.' );
        } else {
            $this->set_notice( 'error', __( 'Project not found or could not be deleted.', 'phoenix-crm' ) );
        }

        wp_safe_redirect( remove_query_arg( array( 'delete_project', '_wpnonce' ) ) );
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

        if ( ! in_array( $action, array( 'activate', 'complete', 'hold', 'cancel', 'plan', 'delete' ), true ) ) {
            return;
        }

        if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-projects' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'phoenix-crm' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'phoenix-crm' ) );
        }

        if ( empty( $_REQUEST['project_ids'] ) || ! is_array( $_REQUEST['project_ids'] ) ) {
            return;
        }

        $ids      = array_map( 'absint', wp_unslash( $_REQUEST['project_ids'] ) );
        $table    = $wpdb->prefix . 'phoenix_projects';
        $count    = 0;

        foreach ( $ids as $pid ) {
            if ( $pid <= 0 ) {
                continue;
            }

            if ( 'delete' === $action ) {
                $result = $wpdb->delete( $table, array( 'id' => $pid ), array( '%d' ) );
                if ( false !== $result && $result > 0 ) {
                    $count++;
                    do_action( 'phoenix_event_logged', 0, 'user', 'project_deleted', 'project', $pid, 'Project deleted via bulk action.' );
                }
            } else {
                $status_map = array(
                    'plan'     => 'planning',
                    'activate' => 'active',
                    'complete' => 'completed',
                    'hold'     => 'on_hold',
                    'cancel'   => 'cancelled',
                );
                $new_status = isset( $status_map[ $action ] ) ? $status_map[ $action ] : '';

                if ( $new_status ) {
                    $data       = array( 'status' => $new_status );
                    $data_types = array( '%s' );

                    if ( 'completed' === $new_status ) {
                        $current_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $pid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        if ( $current_status !== 'completed' ) {
                            $data['completed_at'] = current_time( 'mysql' );
                            $data_types[]         = '%s';
                        }
                    }

                    $result = $wpdb->update( $table, $data, array( 'id' => $pid ), $data_types, array( '%d' ) );
                    if ( false !== $result && $result > 0 ) {
                        $count++;
                        do_action( 'phoenix_event_logged', 0, 'user', 'project_status_changed', 'project', $pid, "Project status changed to {$new_status} via bulk action." );
                    }
                }
            }
        }

        $notice_key = 'phoenix_projects_bulk_notice';
        if ( $count > 0 ) {
            set_transient(
                $notice_key,
                array(
                    'type'    => 'success',
                    'message' => sprintf(
                        /* translators: %d: number of projects affected */
                        __( '%d project(s) updated.', 'phoenix-crm' ),
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
                    'message' => __( 'No matching projects were updated.', 'phoenix-crm' ),
                ),
                30
            );
        }

        wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'project_ids', '_wpnonce', '_wp_http_referer' ) ) );
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
            'phoenix_project_notice',
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
        $notice = get_transient( 'phoenix_project_notice' );
        if ( $notice && is_array( $notice ) ) {
            $class = 'notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible';
            printf(
                '<div class="%s"><p>%s</p></div>',
                esc_attr( $class ),
                wp_kses_post( $notice['message'] )
            );
            delete_transient( 'phoenix_project_notice' );
        }

        // If editing, fetch the project record.
        $edit_project = null;
        if ( ! empty( $_GET['edit_project'] ) ) {
            $edit_id = absint( $_GET['edit_project'] );
            if ( $edit_id > 0 ) {
                global $wpdb;
                $table        = $wpdb->prefix . 'phoenix_projects';
                $edit_project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Projects', 'phoenix-crm' ); ?></h1>
            <hr class="wp-header-end">

            <?php $this->render_project_form( $edit_project ); ?>

            <form id="projects-filter" method="get">
                <?php
                printf( '<input type="hidden" name="page" value="%s" />', esc_attr( 'phoenix-crm-projects' ) );

                $list_table = new Phoenix_CRM_Admin_Projects_List();
                $list_table->prepare_items();
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the add/edit project form.
     *
     * @since 1.0.0
     * @param object|null $project Optional project object for editing.
     * @return void
     */
    private function render_project_form( $project = null ) {
        $is_editing = null !== $project;
        ?>
        <div class="phoenix-project-form-wrap">
            <h2><?php echo $is_editing ? esc_html__( 'Edit Project', 'phoenix-crm' ) : esc_html__( 'Add New Project', 'phoenix-crm' ); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field( 'phoenix_project_action' ); ?>

                <?php if ( $is_editing ) : ?>
                    <input type="hidden" name="project_id" value="<?php echo intval( $project->id ); ?>" />
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="project_title"><?php esc_html_e( 'Title', 'phoenix-crm' ); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="project_title" name="project_title" value="<?php echo $is_editing ? esc_attr( $project->title ) : ''; ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="project_type"><?php esc_html_e( 'Type', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <select id="project_type" name="project_type">
                                <option value="normal" <?php selected( $is_editing, true ) ? selected( $project->type, 'normal' ) : selected( true, true, true ); ?>><?php esc_html_e( 'Normal', 'phoenix-crm' ); ?></option>
                                <option value="special" <?php selected( $is_editing, true ) ? selected( $project->type, 'special' ) : ''; ?>><?php esc_html_e( 'Special', 'phoenix-crm' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="project_description"><?php esc_html_e( 'Description', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <textarea id="project_description" name="project_description" rows="4" class="large-text"><?php echo $is_editing ? esc_textarea( $project->description ) : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="project_status"><?php esc_html_e( 'Status', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <select id="project_status" name="project_status">
                                <option value="planning" <?php selected( $is_editing, true ) ? selected( $project->status, 'planning' ) : selected( true, true, true ); ?>><?php esc_html_e( 'Planning', 'phoenix-crm' ); ?></option>
                                <option value="active" <?php selected( $is_editing, true ) ? selected( $project->status, 'active' ) : ''; ?>><?php esc_html_e( 'Active', 'phoenix-crm' ); ?></option>
                                <option value="completed" <?php selected( $is_editing, true ) ? selected( $project->status, 'completed' ) : ''; ?>><?php esc_html_e( 'Completed', 'phoenix-crm' ); ?></option>
                                <option value="on_hold" <?php selected( $is_editing, true ) ? selected( $project->status, 'on_hold' ) : ''; ?>><?php esc_html_e( 'On Hold', 'phoenix-crm' ); ?></option>
                                <option value="cancelled" <?php selected( $is_editing, true ) ? selected( $project->status, 'cancelled' ) : ''; ?>><?php esc_html_e( 'Cancelled', 'phoenix-crm' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="project_proposal_id"><?php esc_html_e( 'Proposal ID (optional)', 'phoenix-crm' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="project_proposal_id" name="project_proposal_id" value="<?php echo $is_editing && $project->proposal_id ? intval( $project->proposal_id ) : ''; ?>" class="small-text" min="1" placeholder="<?php esc_attr_e( 'e.g. 42', 'phoenix-crm' ); ?>" />
                            <p class="description"><?php esc_html_e( 'Link this project to an existing proposal ID.', 'phoenix-crm' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="phoenix_project_save" class="button button-primary">
                        <?php echo $is_editing ? esc_html__( 'Update Project', 'phoenix-crm' ) : esc_html__( 'Add Project', 'phoenix-crm' ); ?>
                    </button>
                    <?php if ( $is_editing ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-projects' ) ); ?>" class="button">
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
//  WP_List_Table for Projects
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Phoenix_CRM_Admin_Projects_List
 *
 * Concrete WP_List_Table that queries the phoenix_projects table.
 *
 * @since 1.0.0
 */
class Phoenix_CRM_Admin_Projects_List extends WP_List_Table {

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'project',
                'plural'   => 'projects',
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
            'cb'          => '<input type="checkbox" />',
            'id'          => __( 'ID', 'phoenix-crm' ),
            'title'       => __( 'Title', 'phoenix-crm' ),
            'type'        => __( 'Type', 'phoenix-crm' ),
            'status'      => __( 'Status', 'phoenix-crm' ),
            'proposal_id' => __( 'Proposal', 'phoenix-crm' ),
            'created'     => __( 'Created', 'phoenix-crm' ),
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
            'activate' => __( 'Activate', 'phoenix-crm' ),
            'complete' => __( 'Complete', 'phoenix-crm' ),
            'hold'     => __( 'On Hold', 'phoenix-crm' ),
            'cancel'   => __( 'Cancel', 'phoenix-crm' ),
            'plan'     => __( 'Revert to Planning', 'phoenix-crm' ),
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
            '<input type="checkbox" name="project_ids[]" value="%d" />',
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
                'page'          => 'phoenix-crm-projects',
                'edit_project'  => $item->id,
            ),
            admin_url( 'admin.php' )
        );

        $delete_link = wp_nonce_url(
            add_query_arg(
                array(
                    'page'           => 'phoenix-crm-projects',
                    'delete_project' => $item->id,
                ),
                admin_url( 'admin.php' )
            ),
            'phoenix_delete_project'
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
        $badge_class = sanitize_html_class( str_replace( '_', '_', $item->status ) );
        $label       = ucwords( str_replace( '_', ' ', $item->status ) );

        return sprintf(
            '<span class="phoenix-status-badge %s">%s</span>',
            esc_attr( $badge_class ),
            esc_html( $label )
        );
    }

    /**
     * Render the proposal ID column.
     *
     * @since 1.0.0
     * @param object $item The current row item.
     * @return string
     */
    public function column_proposal_id( $item ) {
        if ( empty( $item->proposal_id ) ) {
            return '&mdash;';
        }
        return intval( $item->proposal_id );
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

        $table    = $wpdb->prefix . 'phoenix_projects';
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