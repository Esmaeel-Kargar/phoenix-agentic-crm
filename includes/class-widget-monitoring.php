<?php
/**
 * Phoenix CRM Dashboard Widget — Monitoring
 *
 * Real-time system monitoring: collector health, cron status,
 * data volume, API status.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Phoenix_CRM_Widget_Monitoring
 */
class Phoenix_CRM_Widget_Monitoring {

    /**
     * Get widget ID.
     */
    public static function get_id() {
        return 'monitoring';
    }

    /**
     * Get widget title.
     */
    public static function get_title() {
        return __( 'System Monitoring', 'phoenix-crm' );
    }

    /**
     * Render the monitoring widget.
     */
    public static function render() {
        $stats = self::get_monitor_stats();
        ?>
        <div class="phoenix-widget-monitoring">
            <!-- Status Cards Row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <?php self::render_monitor_card( __( 'Raw Records', 'phoenix-crm' ), $stats['raw_count'], 'dashicons-database', '#2271b1' ); ?>
                <?php self::render_monitor_card( __( 'Events', 'phoenix-crm' ), $stats['event_count'], 'dashicons-clock', '#2c7a3e' ); ?>
                <?php self::render_monitor_card( __( 'Proposals', 'phoenix-crm' ), $stats['proposal_count'], 'dashicons-edit-page', '#7b4ca0' ); ?>
                <?php self::render_monitor_card( __( 'Tasks', 'phoenix-crm' ), $stats['task_count'], 'dashicons-yes-alt', '#b2621a' ); ?>
            </div>

            <!-- Cron Status -->
            <div style="font-size:12px;border-top:1px solid #f0f0f1;padding-top:10px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span><?php esc_html_e( 'Internal Collector', 'phoenix-crm' ); ?></span>
                    <span id="phoenix-cron-internal-status" style="color:<?php echo $stats['cron_internal'] ? '#4caf50' : '#f44336'; ?>;">
                        <?php echo $stats['cron_internal'] ? esc_html__( 'Scheduled', 'phoenix-crm' ) : esc_html__( 'Not found', 'phoenix-crm' ); ?>
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span><?php esc_html_e( 'External Collector', 'phoenix-crm' ); ?></span>
                    <span style="color:<?php echo $stats['cron_external'] ? '#4caf50' : '#f44336'; ?>;">
                        <?php echo $stats['cron_external'] ? esc_html__( 'Scheduled', 'phoenix-crm' ) : esc_html__( 'Not found', 'phoenix-crm' ); ?>
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span><?php esc_html_e( 'DB Schema', 'phoenix-crm' ); ?></span>
                    <span style="color:<?php echo $stats['db_ok'] ? '#4caf50' : '#f44336'; ?>;">
                        <?php echo $stats['db_ok'] ? esc_html__( 'OK v' . PHOENIX_CRM_VERSION, 'phoenix-crm' ) : esc_html__( 'Tables missing', 'phoenix-crm' ); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a small monitor stat card.
     */
    private static function render_monitor_card( $label, $count, $icon, $color ) {
        ?>
        <div style="background:#f8f9fa;border-radius:4px;padding:10px;display:flex;align-items:center;gap:10px;">
            <span class="dashicons <?php echo esc_attr( $icon ); ?>" style="font-size:24px;width:24px;height:24px;color:<?php echo esc_attr( $color ); ?>;"></span>
            <div>
                <div style="font-size:18px;font-weight:700;line-height:1.2;"><?php echo esc_html( number_format_i18n( $count ) ); ?></div>
                <div style="font-size:11px;color:#72777c;"><?php echo esc_html( $label ); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Get monitoring statistics.
     */
    private static function get_monitor_stats() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $stats = array(
            'raw_count'     => 0,
            'event_count'   => 0,
            'proposal_count' => 0,
            'task_count'    => 0,
            'cron_internal' => false,
            'cron_external' => false,
            'db_ok'         => false,
        );

        // Check cron jobs.
        $crons = _get_cron_array();
        foreach ( (array) $crons as $timestamp => $hooks ) {
            foreach ( $hooks as $hook => $data ) {
                if ( 'phoenix_collect_internal' === $hook ) {
                    $stats['cron_internal'] = true;
                }
                if ( 'phoenix_collect_external' === $hook ) {
                    $stats['cron_external'] = true;
                }
            }
        }

        // Table counts.
        $tables = Phoenix_CRM_Database::get_table_names();

        if ( self::table_exists( $tables[0] ) ) { // phoenix_raw_data
            $stats['raw_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables[0]}" );
        }
        if ( self::table_exists( $tables[1] ) ) { // phoenix_events
            $stats['event_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables[1]}" );
        }
        if ( self::table_exists( $tables[2] ) ) { // phoenix_proposals
            $stats['proposal_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables[2]}" );
        }
        if ( self::table_exists( $tables[8] ) ) { // phoenix_user_tasks
            $stats['task_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables[8]}" );
        }

        // Check all tables exist.
        $all_ok = true;
        foreach ( $tables as $t ) {
            if ( ! self::table_exists( $t ) ) {
                $all_ok = false;
                break;
            }
        }
        $stats['db_ok'] = $all_ok;

        return $stats;
    }

    /**
     * Check if a table exists.
     */
    private static function table_exists( $table_name ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        return strtolower( $result ) === strtolower( $table_name );
    }
}