<?php
/**
 * Phoenix CRM Dashboard Widget — Agent Tasks
 *
 * Displays pending proposals as actionable task cards with approve/reject
 * links and a link to the full Proposals management page.
 *
 * @package    Phoenix_CRM
 * @subpackage Widgets
 * @since      1.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Widget_Agent_Tasks
 *
 * Renders an Agent Tasks widget for the CRM dashboard v2.
 *
 * @since 1.1.0
 */
class Phoenix_CRM_Widget_Agent_Tasks {

    /**
     * Return the unique widget ID.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_id() {
        return 'agent-tasks';
    }

    /**
     * Return the display title for the widget.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_title() {
        return __( 'Agent Tasks', 'phoenix-crm' );
    }

    /**
     * Render the widget HTML.
     *
     * Outputs proposals as task cards ordered by urgency — pending first,
     * then approved, then rejected. Each card includes a title, type badge,
     * status badge, creation date, and decision action links that point to
     * the full Proposals admin page.
     *
     * @since 1.1.0
     * @return void
     */
    public static function render() {
        $tasks = self::get_agent_tasks();
        ?>
        <div class="phoenix-card phoenix-widget-agent-tasks">
            <h3><?php echo esc_html( self::get_title() ); ?></h3>

            <?php if ( empty( $tasks ) ) : ?>
                <p><em><?php esc_html_e( 'No agent tasks pending.', 'phoenix-crm' ); ?></em></p>
            <?php else : ?>
                <?php foreach ( $tasks as $task ) : ?>
                    <div class="phoenix-task-card" style="padding:12px 0;border-bottom:1px solid #f0f0f1;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div style="flex:1;">
                                <strong><?php echo esc_html( $task->title ); ?></strong>
                                <div style="margin-top:4px;">
                                    <span class="phoenix-badge"><?php echo esc_html( ucfirst( $task->type ) ); ?></span>
                                    <span class="phoenix-status-badge <?php echo esc_attr( $task->status ); ?>" style="
                                        display:inline-block;padding:2px 8px;border-radius:3px;
                                        font-size:11px;font-weight:600;text-transform:uppercase;
                                        <?php
                                        if ( 'pending' === $task->status ) {
                                            echo 'background:#fff3cd;color:#856404;';
                                        } elseif ( 'approved' === $task->status ) {
                                            echo 'background:#d4edda;color:#155724;';
                                        } elseif ( 'rejected' === $task->status ) {
                                            echo 'background:#f8d7da;color:#721c24;';
                                        }
                                        ?>
                                    ">
                                        <?php echo esc_html( $task->status ); ?>
                                    </span>
                                </div>
                                <div class="phoenix-card-meta" style="font-size:11px;color:#787c82;margin-top:4px;">
                                    <?php
                                    $time = strtotime( $task->created_at );
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: human-readable time difference */
                                            __( '%s ago', 'phoenix-crm' ),
                                            human_time_diff( $time, current_time( 'timestamp' ) )
                                        )
                                    );
                                    ?>
                                </div>
                            </div>
                            <?php if ( 'pending' === $task->status ) : ?>
                                <div class="phoenix-task-actions" style="white-space:nowrap;margin-left:12px;">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-proposals' ) ); ?>" class="button button-small" style="text-decoration:none;color:#28a745;">
                                        <?php esc_html_e( 'Approve', 'phoenix-crm' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-proposals' ) ); ?>" class="button button-small" style="text-decoration:none;color:#dc3545;">
                                        <?php esc_html_e( 'Reject', 'phoenix-crm' ); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p style="margin-top:12px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-proposals' ) ); ?>" class="button">
                        <?php esc_html_e( 'View All Proposals', 'phoenix-crm' ); ?> &rarr;
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Retrieve agent task proposals from the database.
     *
     * Returns non-archived proposals ordered by status priority (pending
     * first, then approved, then rejected) and then by creation date
     * (newest first).
     *
     * @since 1.1.0
     *
     * @param int $limit Maximum number of tasks to return (default 10).
     * @return array Array of proposal objects with id, title, type, status, created_at.
     */
    public static function get_agent_tasks( $limit = 10 ) {
        global $wpdb;

        $table = $wpdb->prefix . 'phoenix_proposals';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, type, status, created_at
                 FROM {$table}
                 WHERE archived = 0
                 ORDER BY FIELD(status, 'pending', 'approved', 'rejected'), created_at DESC
                 LIMIT %d",
                (int) $limit
            )
        );

        return is_array( $results ) ? $results : array();
    }
}