<?php
/**
 * Phoenix CRM Dashboard Widget — Response Feed
 *
 * Displays system responses, notifications, and alerts.
 * Shows pending actions, errors, and system messages.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Phoenix_CRM_Widget_Response_Feed
 */
class Phoenix_CRM_Widget_Response_Feed {

    /**
     * Get widget ID.
     */
    public static function get_id() {
        return 'response-feed';
    }

    /**
     * Get widget title.
     */
    public static function get_title() {
        return __( 'Notifications', 'phoenix-crm' );
    }

    /**
     * Render the response feed widget.
     */
    public static function render() {
        $pending    = self::get_pending_count();
        $errors     = self::get_recent_errors();
        $notices    = self::get_system_notices();
        ?>
        <div class="phoenix-widget-response">
            <!-- Pending Actions -->
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-weight:600;font-size:13px;">
                        <?php esc_html_e( 'Pending Actions', 'phoenix-crm' ); ?>
                    </span>
                    <span class="phoenix-status-badge pending" style="font-size:11px;">
                        <?php echo esc_html( sprintf( _n( '%d item', '%d items', $pending, 'phoenix-crm' ), $pending ) ); ?>
                    </span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ( $pending > 0 ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-proposals' ) ); ?>" class="button button-small">
                            <?php esc_html_e( 'Review Proposals', 'phoenix-crm' ); ?> (<?php echo esc_html( $pending ); ?>)
                        </a>
                    <?php else : ?>
                        <em style="font-size:12px;color:#72777c;">
                            <?php esc_html_e( 'No pending items.', 'phoenix-crm' ); ?>
                        </em>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Errors -->
            <div style="margin-bottom:14px;border-top:1px solid #f0f0f1;padding-top:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-weight:600;font-size:13px;color:#d63638;">
                        <?php esc_html_e( 'Recent Errors', 'phoenix-crm' ); ?>
                    </span>
                    <span style="font-size:12px;color:#72777c;">
                        <?php echo esc_html( sprintf( _n( '%d error', '%d errors', count( $errors ), 'phoenix-crm' ), count( $errors ) ) ); ?>
                    </span>
                </div>
                <?php if ( empty( $errors ) ) : ?>
                    <em style="font-size:12px;color:#72777c;">
                        <?php esc_html_e( 'No errors in the last 24 hours.', 'phoenix-crm' ); ?>
                    </em>
                <?php else : ?>
                    <ul style="margin:0;padding:0;list-style:none;">
                        <?php foreach ( $errors as $error ) : ?>
                            <li style="padding:6px 0;border-bottom:1px solid #f8f9fa;font-size:12px;display:flex;gap:6px;">
                                <span style="color:#d63638;">⚠</span>
                                <span style="flex:1;"><?php echo esc_html( mb_substr( $error->note ?: $error->action, 0, 80 ) ); ?></span>
                                <span style="color:#72777c;white-space:nowrap;"><?php echo esc_html( human_time_diff( strtotime( $error->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- System Notices -->
            <div style="border-top:1px solid #f0f0f1;padding-top:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-weight:600;font-size:13px;">
                        <?php esc_html_e( 'System', 'phoenix-crm' ); ?>
                    </span>
                </div>
                <?php if ( empty( $notices ) ) : ?>
                    <em style="font-size:12px;color:#72777c;">
                        <?php esc_html_e( 'All systems operational.', 'phoenix-crm' ); ?>
                    </em>
                <?php else : ?>
                    <ul style="margin:0;padding:0;list-style:none;">
                        <?php foreach ( $notices as $notice ) : ?>
                            <li style="padding:4px 0;font-size:12px;display:flex;gap:6px;">
                                <span style="color:#ff9800;">ℹ</span>
                                <span><?php echo esc_html( $notice ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get count of pending proposals.
     */
    private static function get_pending_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_proposals';
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s AND archived = %d", 'pending', 0 ) );
        return (int) $count;
    }

    /**
     * Get recent error events (last 24h).
     */
    private static function get_recent_errors() {
        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_events';
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, action, note, created_at FROM {$table} 
             WHERE (action LIKE %s OR note LIKE %s) 
             AND created_at >= %s 
             ORDER BY created_at DESC LIMIT 5",
            '%error%', '%fail%', gmdate( 'Y-m-d H:i:s', time() - 86400 )
        ) );
        return is_array( $results ) ? $results : array();
    }

    /**
     * Get system notices.
     */
    private static function get_system_notices() {
        global $wpdb;
        $notices = array();

        // Check cron health.
        $crons = _get_cron_array();
        $has_internal = false;
        $has_external = false;
        foreach ( (array) $crons as $timestamp => $hooks ) {
            foreach ( $hooks as $hook => $data ) {
                if ( 'phoenix_collect_internal' === $hook ) $has_internal = true;
                if ( 'phoenix_collect_external' === $hook ) $has_external = true;
            }
        }
        if ( ! $has_internal ) {
            $notices[] = __( 'Internal collection cron not scheduled.', 'phoenix-crm' );
        }
        if ( ! $has_external ) {
            $notices[] = __( 'External collection cron not scheduled.', 'phoenix-crm' );
        }

        return $notices;
    }
}