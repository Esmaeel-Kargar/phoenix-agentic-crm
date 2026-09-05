<?php
/**
 * Phoenix CRM Widgets Manager
 *
 * Manages dashboard widgets: registration, ordering, and rendering.
 * Provides a 2-column grid dashboard with draggable widgets and
 * a recent activity section.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Widgets_Manager
 *
 * Static registry and renderer for CRM dashboard widgets.
 * All rendering methods are public static for call_user_func compatibility.
 */
class Phoenix_CRM_Widgets_Manager {

    /**
     * Registered widgets keyed by ID.
     *
     * Each entry: array( 'id' => string, 'class_name' => string, 'title' => string )
     *
     * @var array
     */
    private static $widgets = array();

    /**
     * Default widget order (list of widget IDs).
     *
     * @var string[]
     */
    private static $default_order = array(
        'user-tasks',
        'notes',
        'agent-tasks',
        'calendar',
        'projects',
        'progress',
        'strategy-goals',
    );

    /**
     * Register a widget for the dashboard.
     *
     * @param string $id        Unique widget identifier (slug).
     * @param string $class_name Fully-qualified class name that implements the widget.
     * @param string $title     Display title for the widget header.
     * @return void
     */
    public static function register_widget( $id, $class_name, $title ) {
        if ( ! class_exists( $class_name ) ) {
            return;
        }
        self::$widgets[ $id ] = array(
            'id'         => $id,
            'class_name' => $class_name,
            'title'      => $title,
        );
    }

    /**
     * Render the full dashboard page with widget grid and recent activity.
     *
     * Called via call_user_func from Phoenix_CRM_Admin_Menu.
     *
     * @return void
     */
    public static function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'phoenix-crm' ) );
        }

        $user_id = get_current_user_id();
        $order   = self::get_widget_order( $user_id );
        $version = defined( 'PHOENIX_CRM_VERSION' ) ? PHOENIX_CRM_VERSION : '—';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Phoenix CRM Dashboard', 'phoenix-crm' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Drag widgets to rearrange your dashboard view.', 'phoenix-crm' ); ?></p>

            <hr />

            <!-- Widget Grid -->
            <div id="phoenix-crm-widget-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:20px 0;">
                <?php foreach ( $order as $widget_id ) : ?>
                    <?php if ( isset( self::$widgets[ $widget_id ] ) ) : ?>
                        <?php self::render_widget( $widget_id ); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Recent Activity -->
            <div id="phoenix-crm-recent-activity" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Recent Activity', 'phoenix-crm' ); ?></h2>
                <?php
                $events = Phoenix_CRM_Event_Logger::get_events( array(), 10 );
                if ( empty( $events ) ) :
                    ?>
                    <p><em><?php esc_html_e( 'No activity recorded yet.', 'phoenix-crm' ); ?></em></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Actor', 'phoenix-crm' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Action', 'phoenix-crm' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Target', 'phoenix-crm' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Note', 'phoenix-crm' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Date', 'phoenix-crm' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $events as $event ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $event->actor ); ?></td>
                                    <td><?php echo esc_html( $event->action ); ?></td>
                                    <td>
                                        <?php
                                        echo esc_html(
                                            ! empty( $event->target_type )
                                                ? $event->target_type . ' #' . $event->target_id
                                                : '—'
                                        );
                                        ?>
                                    </td>
                                    <td><?php echo esc_html( ! empty( $event->note ) ? substr( $event->note, 0, 80 ) : '—' ); ?></td>
                                    <td><?php echo esc_html( $event->created_at ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:10px;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-event-log' ) ); ?>" class="button">
                            <?php esc_html_e( 'View Full Event Log', 'phoenix-crm' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

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
     * Render a single widget by its ID.
     *
     * Outputs the widget wrapper container and delegates body content
     * to the registered widget class's static render() method.
     *
     * @param string $id Registered widget ID.
     * @return void
     */
    public static function render_widget( $id ) {
        if ( ! isset( self::$widgets[ $id ] ) ) {
            echo '<p>' . esc_html__( 'Widget not found.', 'phoenix-crm' ) . '</p>';
            return;
        }

        $widget    = self::$widgets[ $id ];
        $class     = $widget['class_name'];
        $title     = $widget['title'];
        $widget_id = 'phoenix-widget--' . sanitize_html_class( $id );

        ?>
        <div id="<?php echo esc_attr( $widget_id ); ?>" class="phoenix-widget" data-widget-id="<?php echo esc_attr( $id ); ?>" style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div class="phoenix-widget-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f0f0f1;cursor:move;background:#f8f9fa;border-radius:4px 4px 0 0;">
                <h3 style="margin:0;font-size:14px;font-weight:600;"><?php echo esc_html( $title ); ?></h3>
                <span class="phoenix-widget-toggle dashicons dashicons-minus" style="cursor:pointer;color:#787c82;" title="<?php esc_attr_e( 'Toggle widget', 'phoenix-crm' ); ?>"></span>
            </div>
            <div class="phoenix-widget-body" style="padding:16px;">
                <?php
                if ( method_exists( $class, 'render' ) ) {
                    call_user_func( array( $class, 'render' ) );
                } else {
                    echo '<p><em>' . esc_html__( 'Widget render method not found.', 'phoenix-crm' ) . '</em></p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get the widget order for a given user.
     *
     * Reads from user meta 'phoenix_dashboard_widget_order'. Falls back
     * to the default order if no saved order exists.
     *
     * @param int $user_id WordPress user ID.
     * @return string[] Ordered list of widget IDs.
     */
    public static function get_widget_order( $user_id ) {
        $saved = get_user_meta( $user_id, 'phoenix_dashboard_widget_order', true );

        if ( is_array( $saved ) && ! empty( $saved ) ) {
            // Remove any IDs that are no longer registered.
            $valid = array();
            foreach ( $saved as $id ) {
                if ( isset( self::$widgets[ $id ] ) ) {
                    $valid[] = $id;
                }
            }
            if ( ! empty( $valid ) ) {
                // Append any newly registered widgets not in saved order.
                foreach ( self::$default_order as $id ) {
                    if ( ! in_array( $id, $valid, true ) && isset( self::$widgets[ $id ] ) ) {
                        $valid[] = $id;
                    }
                }
                return $valid;
            }
        }

        return self::$default_order;
    }

    /**
     * Save the widget order for a given user.
     *
     * @param int      $user_id WordPress user ID.
     * @param string[] $order   Ordered list of widget IDs.
     * @return void
     */
    public static function save_widget_order( $user_id, $order ) {
        $sanitized = array();
        foreach ( $order as $id ) {
            $sanitized[] = sanitize_text_field( $id );
        }
        update_user_meta( $user_id, 'phoenix_dashboard_widget_order', $sanitized );
    }

    /**
     * AJAX handler for saving widget order.
     *
     * Hooked to wp_ajax_phoenix_widget_save_order.
     * Verifies nonce 'phoenix_crm_admin_nonce' and capability 'manage_options'.
     *
     * @return void
     */
    public static function handle_ajax_save_order() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'phoenix_crm_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'phoenix-crm' ) ) );
        }

        // Check capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'phoenix-crm' ) ) );
        }

        // Validate order data.
        $order = isset( $_POST['order'] ) ? $_POST['order'] : array();
        if ( ! is_array( $order ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'phoenix-crm' ) ) );
        }

        $user_id = get_current_user_id();
        self::save_widget_order( $user_id, $order );

        wp_send_json_success( array( 'message' => __( 'Widget order saved.', 'phoenix-crm' ) ) );
    }

    /**
     * Initialize the widgets manager.
     *
     * Registers all default widgets and hooks AJAX actions.
     * Call from plugins_loaded or admin_init.
     *
     * @return void
     */
    public static function init() {
        // Register default widgets in order.
        self::register_widget( 'user-tasks', 'Phoenix_CRM_Widget_User_Tasks', __( 'User Tasks', 'phoenix-crm' ) );
        self::register_widget( 'agent-tasks', 'Phoenix_CRM_Widget_Agent_Tasks', __( 'Agent Tasks', 'phoenix-crm' ) );
        self::register_widget( 'notes', 'Phoenix_CRM_Widget_Notes', __( 'Notes', 'phoenix-crm' ) );
        self::register_widget( 'calendar', 'Phoenix_CRM_Widget_Calendar', __( 'Calendar', 'phoenix-crm' ) );
        self::register_widget( 'projects', 'Phoenix_CRM_Widget_Projects', __( 'Projects', 'phoenix-crm' ) );
        self::register_widget( 'progress', 'Phoenix_CRM_Widget_Progress', __( 'Progress', 'phoenix-crm' ) );
        self::register_widget( 'strategy-goals', 'Phoenix_CRM_Widget_Strategy_Goals', __( 'Strategy & Goals', 'phoenix-crm' ) );

        // v1.1.0 — new widgets
        self::register_widget( 'main-inputs', 'Phoenix_CRM_Widget_Main_Inputs', __( 'Main Inputs', 'phoenix-crm' ) );
        self::register_widget( 'monitoring', 'Phoenix_CRM_Widget_Monitoring', __( 'System Monitoring', 'phoenix-crm' ) );
        self::register_widget( 'control-panel', 'Phoenix_CRM_Widget_Control_Panel', __( 'Control Panel', 'phoenix-crm' ) );
        self::register_widget( 'response-feed', 'Phoenix_CRM_Widget_Response_Feed', __( 'Notifications', 'phoenix-crm' ) );

        // Hook AJAX handler for saving widget order.
        add_action( 'wp_ajax_phoenix_widget_save_order', array( __CLASS__, 'handle_ajax_save_order' ) );

        // Hook AJAX handlers from the User Tasks widget.
        if ( class_exists( 'Phoenix_CRM_Widget_User_Tasks' ) ) {
            add_action( 'wp_ajax_phoenix_task_toggle', array( 'Phoenix_CRM_Widget_User_Tasks', 'handle_ajax_toggle' ) );
            add_action( 'wp_ajax_phoenix_add_task', array( 'Phoenix_CRM_Widget_User_Tasks', 'handle_ajax_add' ) );
        }

        // Hook AJAX handlers from Notes widget.
        if ( class_exists( 'Phoenix_CRM_Widget_Notes' ) ) {
            add_action( 'wp_ajax_phoenix_add_note', array( 'Phoenix_CRM_Widget_Notes', 'handle_ajax_add_note' ) );
        }

        // Hook AJAX handlers from Main Inputs widget.
        if ( class_exists( 'Phoenix_CRM_Widget_Main_Inputs' ) ) {
            add_action( 'wp_ajax_phoenix_run_collector', array( 'Phoenix_CRM_Widget_Main_Inputs', 'handle_ajax_run_collector' ) );
        }

        // Enqueue widget assets.
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Enqueue widget-specific CSS and JS assets.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public static function enqueue_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'phoenix-crm' ) === false ) {
            return;
        }

        // Widget drag-and-drop sorting (jQuery UI Sortable).
        wp_enqueue_script( 'jquery-ui-sortable' );

        // Inline widget CSS.
        $css = '
            .phoenix-widget { transition: box-shadow 0.2s ease; }
            .phoenix-widget:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
            .phoenix-widget.ui-sortable-helper { box-shadow: 0 4px 16px rgba(0,0,0,0.18); }
            .phoenix-widget-header { user-select: none; }
            .phoenix-widget-placeholder { background: #f0f6fc; border: 2px dashed #2271b1; border-radius: 4px; min-height: 100px; }
            .phoenix-widget.collapsed .phoenix-widget-body { display: none; }
        ';
        wp_add_inline_style( 'wp-admin', $css );

        // Inline widget JS for drag-and-drop and toggle.
        $js = '
            (function($) {
                $(function() {
                    // Drag-and-drop sorting.
                    $("#phoenix-crm-widget-grid").sortable({
                        items: "> .phoenix-widget",
                        placeholder: "phoenix-widget-placeholder",
                        handle: ".phoenix-widget-header",
                        tolerance: "pointer",
                        distance: 5,
                        update: function() {
                            var order = [];
                            $("#phoenix-crm-widget-grid > .phoenix-widget").each(function() {
                                order.push($(this).data("widget-id"));
                            });
                            $.post(ajaxurl, {
                                action: "phoenix_widget_save_order",
                                order: order,
                                nonce: phoenix_crm_admin.nonce
                            });
                        }
                    });

                    // Widget toggle (collapse/expand).
                    $("#phoenix-crm-widget-grid").on("click", ".phoenix-widget-toggle", function() {
                        var widget = $(this).closest(".phoenix-widget");
                        widget.toggleClass("collapsed");
                        $(this).toggleClass("dashicons-minus dashicons-plus");
                    });
                });
            })(jQuery);
        ';
        wp_add_inline_script( 'jquery-ui-sortable', $js );
    }
}