<?php
/**
 * Phoenix CRM Dashboard Widget — Strategy & Goals
 *
 * Displays active strategic goals with horizon badges, period ranges,
 * and a time-elapsed progress indicator. Provides a link to the full
 * goals management page.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Widget_Strategy_Goals
 *
 * Static widget rendering the most recent active strategic goals
 * on the CRM dashboard.
 *
 * @since 1.1.0
 */
class Phoenix_CRM_Widget_Strategy_Goals {

    /**
     * Return the widget ID.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_id() {
        return 'strategy-goals';
    }

    /**
     * Return the human-readable widget title.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_title() {
        return __( 'Strategy &amp; Goals', 'phoenix-crm' );
    }

    /**
     * Render the strategy goals widget.
     *
     * Shows up to 5 active goals with horizon badges, period info,
     * and a time-progress bar.
     *
     * @since 1.1.0
     * @return void
     */
    public static function render() {
        $goals = self::get_active_goals( 5 );
        ?>
        <div class="phoenix-widget-strategy-goals" style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin:0 0 12px 0;font-size:14px;"><?php esc_html_e( 'Strategy &amp; Goals', 'phoenix-crm' ); ?></h3>

            <?php if ( empty( $goals ) ) : ?>
                <p style="font-size:13px;color:#666;"><em><?php esc_html_e( 'No active goals found.', 'phoenix-crm' ); ?></em></p>
            <?php else : ?>
                <ul style="list-style:none;margin:0;padding:0;">
                    <?php foreach ( $goals as $goal ) : ?>
                        <?php
                        $title_link = admin_url( 'admin.php?page=phoenix-crm-goals&edit_goal=' . (int) $goal->id );
                        $progress   = self::time_progress( $goal );
                        $bar_color  = self::progress_color( $progress );
                        $horizon    = ! empty( $goal->horizon ) ? $goal->horizon : __( 'general', 'phoenix-crm' );
                        ?>
                        <li style="padding:8px 0;border-bottom:1px solid #f0f0f1;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                <a href="<?php echo esc_url( $title_link ); ?>" style="font-size:13px;font-weight:600;color:#2271b1;text-decoration:none;">
                                    <?php echo esc_html( $goal->title ); ?>
                                </a>
                                <span class="phoenix-horizon-badge" style="display:inline-block;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:600;text-transform:uppercase;background:#e8f4fd;color:#0a4b78;">
                                    <?php echo esc_html( $horizon ); ?>
                                </span>
                            </div>
                            <div style="font-size:11px;color:#666;margin-bottom:6px;">
                                <?php
                                $period_start = ! empty( $goal->period_start ) && '0000-00-00' !== $goal->period_start
                                    ? date_i18n( get_option( 'date_format' ), strtotime( $goal->period_start ) )
                                    : '—';
                                $period_end   = ! empty( $goal->period_end ) && '0000-00-00' !== $goal->period_end
                                    ? date_i18n( get_option( 'date_format' ), strtotime( $goal->period_end ) )
                                    : '—';
                                printf(
                                    /* translators: 1: start date, 2: end date */
                                    esc_html__( '%1$s — %2$s', 'phoenix-crm' ),
                                    esc_html( $period_start ),
                                    esc_html( $period_end )
                                );
                                ?>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:8px;background:#f0f0f1;border-radius:4px;overflow:hidden;">
                                    <div style="height:100%;width:<?php echo esc_attr( $progress ); ?>%;background:<?php echo esc_attr( $bar_color ); ?>;border-radius:4px;transition:width .3s;"></div>
                                </div>
                                <span style="font-size:11px;color:#666;min-width:32px;text-align:right;"><?php echo (int) $progress; ?>%</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p style="margin:10px 0 0 0;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-goals' ) ); ?>" class="button">
                        <?php esc_html_e( 'View All Goals', 'phoenix-crm' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Query active goals from the database.
     *
     * @since 1.1.0
     *
     * @param int $limit Maximum number of goals to return.
     * @return object[] Array of goal row objects.
     */
    public static function get_active_goals( $limit = 5 ) {
        global $wpdb;

        $tables = Phoenix_CRM_Database::get_table_names();
        $table  = isset( $tables[3] ) ? $tables[3] : $wpdb->prefix . 'phoenix_goals';

        if ( ! self::table_exists( $table ) ) {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, horizon, period_start, period_end, status
                 FROM {$table}
                 WHERE status = %s
                 ORDER BY period_end ASC
                 LIMIT %d",
                'active',
                $limit
            )
        );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Calculate time-elapsed progress for a goal based on its period.
     *
     * Returns the percentage of days elapsed between period_start
     * and period_end. Capped at 100 %.
     *
     * @since 1.1.0
     *
     * @param object $goal Goal row object with period_start and period_end.
     * @return int Progress percentage (0-100).
     */
    private static function time_progress( $goal ) {
        $start_ts = ! empty( $goal->period_start ) && '0000-00-00' !== $goal->period_start
            ? strtotime( $goal->period_start )
            : 0;
        $end_ts   = ! empty( $goal->period_end ) && '0000-00-00' !== $goal->period_end
            ? strtotime( $goal->period_end )
            : 0;

        if ( $start_ts <= 0 || $end_ts <= 0 || $end_ts <= $start_ts ) {
            return 0;
        }

        $now          = current_time( 'timestamp' );
        $total_days   = ( $end_ts - $start_ts ) / DAY_IN_SECONDS;
        $elapsed_days = ( $now - $start_ts ) / DAY_IN_SECONDS;

        if ( $elapsed_days <= 0 ) {
            return 0;
        }

        $pct = round( ( $elapsed_days / $total_days ) * 100 );

        return min( 100, max( 0, (int) $pct ) );
    }

    /**
     * Return a hex colour for the time-progress bar.
     *
     * @since 1.1.0
     *
     * @param int $progress Percentage 0-100.
     * @return string Hex colour.
     */
    private static function progress_color( $progress ) {
        if ( $progress > 75 ) {
            return '#2c7a3e'; // green — on track / near completion.
        }
        if ( $progress > 50 ) {
            return '#d98c1c'; // orange — mid-way.
        }
        return '#2271b1'; // blue — early stage.
    }

    /**
     * Check whether a database table exists.
     *
     * @since 1.1.0
     *
     * @param string $table_name Fully qualified table name.
     * @return bool
     */
    private static function table_exists( $table_name ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        return strtolower( $result ) === strtolower( $table_name );
    }
}