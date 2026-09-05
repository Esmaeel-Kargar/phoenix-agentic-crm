<?php
/**
 * Phoenix CRM Dashboard Widget — Progress
 *
 * Shows four summary progress bars: Goals completion, Proposals approved,
 * Projects completed, and Tasks completion for the current user.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Widget_Progress
 *
 * Static widget rendering four KPI progress bars on the CRM dashboard.
 *
 * @since 1.1.0
 */
class Phoenix_CRM_Widget_Progress {

    /**
     * Return the widget ID.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_id() {
        return 'progress';
    }

    /**
     * Return the human-readable widget title.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_title() {
        return __( 'Progress', 'phoenix-crm' );
    }

    /**
     * Render the four progress bars.
     *
     * Each bar shows the label, a percentage figure, and a coloured bar.
     *
     * @since 1.1.0
     * @return void
     */
    public static function render() {
        $stats = self::get_progress_stats();
        ?>
        <div class="phoenix-widget-progress" style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin:0 0 12px 0;font-size:14px;"><?php esc_html_e( 'Progress', 'phoenix-crm' ); ?></h3>

            <div style="display:flex;flex-direction:column;gap:14px;">
                <?php
                $items = array(
                    array(
                        'label' => __( 'Goals Completion', 'phoenix-crm' ),
                        'pct'   => $stats['goals_pct'],
                        'n'     => $stats['goals_completed'],
                        'd'     => $stats['goals_total'],
                    ),
                    array(
                        'label' => __( 'Proposals Approved', 'phoenix-crm' ),
                        'pct'   => $stats['proposals_pct'],
                        'n'     => $stats['proposals_approved'],
                        'd'     => $stats['proposals_total'],
                    ),
                    array(
                        'label' => __( 'Projects Completed', 'phoenix-crm' ),
                        'pct'   => $stats['projects_pct'],
                        'n'     => $stats['projects_completed'],
                        'd'     => $stats['projects_total'],
                    ),
                    array(
                        'label' => __( 'Tasks Completion', 'phoenix-crm' ),
                        'pct'   => $stats['tasks_pct'],
                        'n'     => $stats['tasks_done'],
                        'd'     => $stats['tasks_total'],
                    ),
                );

                foreach ( $items as $item ) :
                    $bar_color = self::bar_color( $item['pct'] );
                    ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                            <span style="font-weight:600;color:#333;"><?php echo esc_html( $item['label'] ); ?></span>
                            <span style="color:#666;">
                                <?php
                                printf(
                                    /* translators: 1: numerator count, 2: denominator count */
                                    esc_html__( '%1$d / %2$d', 'phoenix-crm' ),
                                    (int) $item['n'],
                                    (int) $item['d']
                                );
                                ?>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;height:12px;background:#f0f0f1;border-radius:6px;overflow:hidden;">
                                <div style="height:100%;width:<?php echo esc_attr( $item['pct'] ); ?>%;background:<?php echo esc_attr( $bar_color ); ?>;border-radius:6px;transition:width .3s;"></div>
                            </div>
                            <span style="font-size:12px;font-weight:700;color:<?php echo esc_attr( $bar_color ); ?>;min-width:38px;text-align:right;">
                                <?php echo (int) $item['pct']; ?>%
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Gather progress statistics across all CRM entities.
     *
     * @since 1.1.0
     *
     * @return array{
     *     goals_completed: int,
     *     goals_total: int,
     *     goals_pct: int,
     *     proposals_approved: int,
     *     proposals_total: int,
     *     proposals_pct: int,
     *     projects_completed: int,
     *     projects_total: int,
     *     projects_pct: int,
     *     tasks_done: int,
     *     tasks_total: int,
     *     tasks_pct: int,
     * }
     */
    public static function get_progress_stats() {
        global $wpdb;

        $defaults = array(
            'goals_completed'     => 0,
            'goals_total'         => 0,
            'goals_pct'           => 0,
            'proposals_approved'  => 0,
            'proposals_total'     => 0,
            'proposals_pct'       => 0,
            'projects_completed'  => 0,
            'projects_total'      => 0,
            'projects_pct'        => 0,
            'tasks_done'          => 0,
            'tasks_total'         => 0,
            'tasks_pct'           => 0,
        );

        $tables = Phoenix_CRM_Database::get_table_names();

        // --- Goals ---
        if ( ! empty( $tables[3] ) && self::table_exists( $tables[3] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables[3]}" );
            if ( ! is_null( $total ) ) {
                $defaults['goals_total'] = (int) $total;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $completed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[3]} WHERE status = %s",
                    'completed'
                )
            );
            if ( ! is_null( $completed ) ) {
                $defaults['goals_completed'] = (int) $completed;
            }
        }

        // --- Proposals (exclude archived) ---
        if ( ! empty( $tables[2] ) && self::table_exists( $tables[2] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$tables[2]} WHERE archived = 0"
            );
            if ( ! is_null( $total ) ) {
                $defaults['proposals_total'] = (int) $total;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $approved = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[2]} WHERE status = %s AND archived = 0",
                    'approved'
                )
            );
            if ( ! is_null( $approved ) ) {
                $defaults['proposals_approved'] = (int) $approved;
            }
        }

        // --- Projects ---
        if ( ! empty( $tables[4] ) && self::table_exists( $tables[4] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables[4]}" );
            if ( ! is_null( $total ) ) {
                $defaults['projects_total'] = (int) $total;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $completed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[4]} WHERE status = %s",
                    'completed'
                )
            );
            if ( ! is_null( $completed ) ) {
                $defaults['projects_completed'] = (int) $completed;
            }
        }

        // --- User tasks (current user) ---
        $user_tasks_table = $wpdb->prefix . 'phoenix_user_tasks';
        if ( self::table_exists( $user_tasks_table ) ) {
            $user_id = get_current_user_id();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$user_tasks_table} WHERE user_id = %d",
                    $user_id
                )
            );
            if ( ! is_null( $total ) ) {
                $defaults['tasks_total'] = (int) $total;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $done = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$user_tasks_table} WHERE user_id = %d AND status = %s",
                    $user_id,
                    'done'
                )
            );
            if ( ! is_null( $done ) ) {
                $defaults['tasks_done'] = (int) $done;
            }
        }

        // Calculate percentages.
        $defaults['goals_pct']    = self::safe_pct( $defaults['goals_completed'], $defaults['goals_total'] );
        $defaults['proposals_pct'] = self::safe_pct( $defaults['proposals_approved'], $defaults['proposals_total'] );
        $defaults['projects_pct']  = self::safe_pct( $defaults['projects_completed'], $defaults['projects_total'] );
        $defaults['tasks_pct']     = self::safe_pct( $defaults['tasks_done'], $defaults['tasks_total'] );

        return $defaults;
    }

    /**
     * Safely compute a percentage (0-100), avoiding division by zero.
     *
     * @since 1.1.0
     *
     * @param int $part   Numerator.
     * @param int $total  Denominator.
     * @return int Percentage rounded to nearest integer.
     */
    private static function safe_pct( $part, $total ) {
        if ( $total <= 0 ) {
            return 0;
        }
        return (int) round( ( $part / $total ) * 100 );
    }

    /**
     * Return a bar colour based on the percentage.
     *
     * @since 1.1.0
     *
     * @param int $pct Percentage (0-100).
     * @return string Hex colour.
     */
    private static function bar_color( $pct ) {
        if ( $pct > 75 ) {
            return '#2c7a3e'; // green.
        }
        if ( $pct > 50 ) {
            return '#d98c1c'; // orange.
        }
        return '#dc3232'; // red.
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