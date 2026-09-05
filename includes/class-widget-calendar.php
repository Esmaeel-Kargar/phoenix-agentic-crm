<?php
/**
 * Phoenix CRM Dashboard Widget — Calendar
 *
 * Displays a mini month calendar highlighting dates with deadlines
 * from goals, projects, and user tasks. Includes week-deadline count
 * and simple month navigation.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Widget_Calendar
 *
 * Static widget for the CRM dashboard showing a mini calendar
 * with deadline overlays.
 *
 * @since 1.1.0
 */
class Phoenix_CRM_Widget_Calendar {

    /**
     * Return the widget ID.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_id() {
        return 'calendar';
    }

    /**
     * Return the human-readable widget title.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_title() {
        return __( 'Calendar', 'phoenix-crm' );
    }

    /**
     * Render the full calendar widget.
     *
     * Shows: upcoming-deadline count, mini month table, prev/next links.
     *
     * @since 1.1.0
     * @return void
     */
    public static function render() {
        $year  = isset( $_GET['cal_year'] ) ? absint( $_GET['cal_year'] ) : (int) current_time( 'Y' );
        $month = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'm' );

        // Clamp valid range.
        if ( $month < 1 ) {
            $month = 1;
        }
        if ( $month > 12 ) {
            $month = 12;
        }
        if ( $year < 2020 ) {
            $year = 2020;
        }
        if ( $year > 2100 ) {
            $year = 2100;
        }

        $deadline_dates  = self::get_month_data( $year, $month );
        $week_deadlines  = self::get_week_deadlines();

        // Navigation.
        $prev_month = $month - 1;
        $prev_year  = $year;
        if ( $prev_month < 1 ) {
            $prev_month = 12;
            $prev_year  = $year - 1;
        }
        $next_month = $month + 1;
        $next_year  = $year;
        if ( $next_month > 12 ) {
            $next_month = 1;
            $next_year  = $year + 1;
        }

        $base_url = remove_query_arg( array( 'cal_year', 'cal_month' ) );

        $month_name = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
        ?>
        <div class="phoenix-widget-calendar" style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <h3 style="margin:0;font-size:14px;"><?php echo esc_html( $month_name ); ?></h3>
                <span style="font-size:12px;color:#666;">
                    <?php
                    printf(
                        /* translators: %d: number of deadlines in the coming week */
                        esc_html__( '%d deadline(s) this week', 'phoenix-crm' ),
                        (int) $week_deadlines
                    );
                    ?>
                </span>
            </div>

            <table style="width:100%;border-collapse:collapse;text-align:center;font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:4px 0;color:#666;"><?php esc_html_e( 'Su', 'phoenix-crm' ); ?></th>
                        <th style="padding:4px 0;color:#666;"><?php esc_html_e( 'Mo', 'phoenix-crm' ); ?></th>
                        <th style="padding:4px 0;color:#666;"><?php esc_html_e( 'Tu', 'phoenix-crm' ); ?></th>
                        <th style="padding:4px 0;color:#666;"><?php esc_html_e( 'We', 'phoenix-crm' ); ?></th>
                        <th style="padding:4px 0;color:#666;"><?php esc_html_e( 'Th', 'phoenix-crm' ); ?></th>
                        <th style="padding:4px 0;color:#666;"><?php esc_html_e( 'Fr', 'phoenix-crm' ); ?></th>
                        <th style="padding:4px 0;color:#666;"><?php esc_html_e( 'Sa', 'phoenix-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $first_day_ts = mktime( 0, 0, 0, $month, 1, $year );
                    $days_in_month = (int) date_i18n( 't', $first_day_ts );
                    $start_dow    = (int) date_i18n( 'w', $first_day_ts );
                    $today_ts     = current_time( 'timestamp' );
                    $today_str    = date_i18n( 'Y-m-d', $today_ts );

                    $day = 1;
                    $cell_count = $start_dow; // leading empty cells.

                    echo '<tr>';
                    for ( $i = 0; $i < $start_dow; $i++ ) {
                        echo '<td style="padding:3px 0;color:#ccc;">&nbsp;</td>';
                    }

                    while ( $day <= $days_in_month ) {
                        if ( $cell_count > 0 && $cell_count % 7 === 0 ) {
                            echo '</tr><tr>';
                        }

                        $date_str  = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                        $has_deadline = in_array( $date_str, $deadline_dates, true );
                        $is_today     = $date_str === $today_str;

                        $cell_style  = 'padding:3px 0;border-radius:50%;';
                        $cell_style .= $is_today ? 'background:#2271b1;color:#fff;font-weight:700;' : '';
                        $cell_style .= ( $has_deadline && ! $is_today ) ? 'background:#d4edda;color:#155724;font-weight:600;' : '';
                        $cell_style .= ( ! $is_today && ! $has_deadline ) ? 'color:#333;' : '';

                        echo '<td style="' . esc_attr( $cell_style ) . '">' . esc_html( $day ) . '</td>';

                        $day++;
                        $cell_count++;
                    }

                    // Trailing empty cells.
                    while ( $cell_count % 7 !== 0 ) {
                        echo '<td style="padding:3px 0;color:#ccc;">&nbsp;</td>';
                        $cell_count++;
                    }

                    echo '</tr>';
                    ?>
                </tbody>
            </table>

            <div style="display:flex;justify-content:space-between;margin-top:10px;">
                <a href="<?php echo esc_url( add_query_arg( array( 'cal_year' => $prev_year, 'cal_month' => $prev_month ), $base_url ) ); ?>" class="button button-small">&larr; <?php esc_html_e( 'Prev', 'phoenix-crm' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( array( 'cal_year' => $next_year, 'cal_month' => $next_month ), $base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Next', 'phoenix-crm' ); ?> &rarr;</a>
            </div>
        </div>
        <?php
    }

    /**
     * Get an array of date strings that have deadlines in the given month.
     *
     * Checks phoenix_goals (period_start / period_end), phoenix_projects
     * (created_at / completed_at), and phoenix_user_tasks (due_date) if
     * the table exists.
     *
     * @since 1.1.0
     *
     * @param int $year  Four-digit year.
     * @param int $month Numeric month (1-12).
     * @return string[] Array of 'Y-m-d' date strings with deadlines.
     */
    public static function get_month_data( $year, $month ) {
        global $wpdb;

        $dates = array();
        $month_start = sprintf( '%04d-%02d-01', $year, $month );
        $month_end   = date_i18n( 'Y-m-t', strtotime( $month_start ) );

        $tables = Phoenix_CRM_Database::get_table_names();

        // --- Goals that overlap this month (period_start <= month_end AND period_end >= month_start) ---
        if ( ! empty( $tables[3] ) && self::table_exists( $tables[3] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $goal_hits = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT period_start FROM {$tables[3]}
                     WHERE status = 'active'
                       AND period_start <= %s
                       AND period_end >= %s",
                    $month_end,
                    $month_start
                )
            );
            if ( is_array( $goal_hits ) ) {
                foreach ( $goal_hits as $ds ) {
                    $dates[] = $ds;
                }
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $goal_end_hits = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT period_end FROM {$tables[3]}
                     WHERE status = 'active'
                       AND period_start <= %s
                       AND period_end >= %s",
                    $month_end,
                    $month_start
                )
            );
            if ( is_array( $goal_end_hits ) ) {
                foreach ( $goal_end_hits as $ds ) {
                    $dates[] = $ds;
                }
            }
        }

        // --- Projects whose created_at or completed_at falls in this month ---
        if ( ! empty( $tables[4] ) && self::table_exists( $tables[4] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $proj_dates = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT DATE(created_at) FROM {$tables[4]}
                     WHERE created_at >= %s
                       AND created_at < %s",
                    $month_start,
                    date_i18n( 'Y-m-d', strtotime( '+1 month', strtotime( $month_start ) ) )
                )
            );
            if ( is_array( $proj_dates ) ) {
                foreach ( $proj_dates as $ds ) {
                    $dates[] = $ds;
                }
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $proj_comp = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT DATE(completed_at) FROM {$tables[4]}
                     WHERE completed_at IS NOT NULL
                       AND completed_at >= %s
                       AND completed_at < %s",
                    $month_start,
                    date_i18n( 'Y-m-d', strtotime( '+1 month', strtotime( $month_start ) ) )
                )
            );
            if ( is_array( $proj_comp ) ) {
                foreach ( $proj_comp as $ds ) {
                    $dates[] = $ds;
                }
            }
        }

        // --- User tasks (if table exists ---
        $user_tasks_table = $wpdb->prefix . 'phoenix_user_tasks';
        if ( self::table_exists( $user_tasks_table ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $task_dates = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT due_date FROM {$user_tasks_table}
                     WHERE due_date >= %s
                       AND due_date <= %s",
                    $month_start,
                    $month_end
                )
            );
            if ( is_array( $task_dates ) ) {
                foreach ( $task_dates as $ds ) {
                    $dates[] = $ds;
                }
            }
        }

        $dates = array_unique( $dates );
        sort( $dates );

        return $dates;
    }

    /**
     * Count deadlines in the next 7 days.
     *
     * @since 1.1.0
     * @return int
     */
    public static function get_week_deadlines() {
        global $wpdb;

        $count   = 0;
        $today   = current_time( 'Y-m-d' );
        $week_end = date_i18n( 'Y-m-d', strtotime( '+7 days', strtotime( $today ) ) );

        $tables = Phoenix_CRM_Database::get_table_names();

        // Goals ending within the week.
        if ( ! empty( $tables[3] ) && self::table_exists( $tables[3] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $goal_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[3]}
                     WHERE status = 'active'
                       AND period_end >= %s
                       AND period_end <= %s",
                    $today,
                    $week_end
                )
            );
            if ( ! is_null( $goal_count ) ) {
                $count += (int) $goal_count;
            }
        }

        // Projects completed this week.
        if ( ! empty( $tables[4] ) && self::table_exists( $tables[4] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $proj_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[4]}
                     WHERE completed_at IS NOT NULL
                       AND DATE(completed_at) >= %s
                       AND DATE(completed_at) <= %s",
                    $today,
                    $week_end
                )
            );
            if ( ! is_null( $proj_count ) ) {
                $count += (int) $proj_count;
            }
        }

        // User tasks due this week.
        $user_tasks_table = $wpdb->prefix . 'phoenix_user_tasks';
        if ( self::table_exists( $user_tasks_table ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $task_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$user_tasks_table}
                     WHERE due_date >= %s
                       AND due_date <= %s",
                    $today,
                    $week_end
                )
            );
            if ( ! is_null( $task_count ) ) {
                $count += (int) $task_count;
            }
        }

        return $count;
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