<?php
/**
 * Phoenix CRM Dashboard Widget — Active Projects
 *
 * Lists active (non-completed, non-cancelled) projects with type badges,
 * status badges, and progress bars. Provides a link to the full projects
 * management page.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Widget_Projects
 *
 * Static widget for the CRM dashboard showing the most recent
 * active projects.
 *
 * @since 1.1.0
 */
class Phoenix_CRM_Widget_Projects {

    /**
     * Return the widget ID.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_id() {
        return 'projects';
    }

    /**
     * Return the human-readable widget title.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_title() {
        return __( 'Active Projects', 'phoenix-crm' );
    }

    /**
     * Render the active projects widget.
     *
     * Displays up to 5 active projects, each with a progress bar,
     * type badge, and status badge.
     *
     * @since 1.1.0
     * @return void
     */
    public static function render() {
        $projects = self::get_active_projects( 5 );
        ?>
        <div class="phoenix-widget-projects" style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin:0 0 12px 0;font-size:14px;"><?php esc_html_e( 'Active Projects', 'phoenix-crm' ); ?></h3>

            <?php if ( empty( $projects ) ) : ?>
                <p style="font-size:13px;color:#666;"><em><?php esc_html_e( 'No active projects found.', 'phoenix-crm' ); ?></em></p>
            <?php else : ?>
                <ul style="list-style:none;margin:0;padding:0;">
                    <?php foreach ( $projects as $project ) : ?>
                        <?php
                        $title_link = admin_url( 'admin.php?page=phoenix-crm-projects&edit_project=' . (int) $project->id );
                        $progress   = self::calculate_progress( $project );
                        $bar_color  = self::progress_color( $progress );
                        ?>
                        <li style="padding:8px 0;border-bottom:1px solid #f0f0f1;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                <a href="<?php echo esc_url( $title_link ); ?>" style="font-size:13px;font-weight:600;color:#2271b1;text-decoration:none;">
                                    <?php echo esc_html( $project->title ); ?>
                                </a>
                                <div style="display:flex;gap:4px;">
                                    <span class="phoenix-status-badge" style="display:inline-block;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:600;text-transform:uppercase;background:#e2e3e5;color:#383d41;">
                                        <?php echo esc_html( $project->type ?: 'normal' ); ?>
                                    </span>
                                    <span class="phoenix-status-badge <?php echo esc_attr( $project->status ); ?>" style="display:inline-block;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:600;text-transform:uppercase;
                                        <?php
                                        $status_colors = array(
                                            'planning' => 'background:#e2e3e5;color:#383d41;',
                                            'active'   => 'background:#d4edda;color:#155724;',
                                            'on_hold'  => 'background:#fff3cd;color:#856404;',
                                        );
                                        echo isset( $status_colors[ $project->status ] ) ? esc_attr( $status_colors[ $project->status ] ) : 'background:#e2e3e5;color:#383d41;';
                                        ?>
                                    ">
                                        <?php echo esc_html( $project->status ); ?>
                                    </span>
                                </div>
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
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-projects' ) ); ?>" class="button">
                        <?php esc_html_e( 'View All Projects', 'phoenix-crm' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Query active projects from the database.
     *
     * @since 1.1.0
     *
     * @param int $limit Maximum number of projects to return.
     * @return object[] Array of project row objects.
     */
    public static function get_active_projects( $limit = 5 ) {
        global $wpdb;

        $tables = Phoenix_CRM_Database::get_table_names();
        $table  = isset( $tables[4] ) ? $tables[4] : $wpdb->prefix . 'phoenix_projects';

        if ( ! self::table_exists( $table ) ) {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, type, status, created_at, completed_at
                 FROM {$table}
                 WHERE status NOT IN ('completed', 'cancelled')
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            )
        );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Calculate a project's completion progress as a percentage.
     *
     * Returns 100 if the project is completed (completed_at is set),
     * otherwise estimates based on time elapsed since creation with
     * a cap at 95 %.
     *
     * @since 1.1.0
     *
     * @param object $project Project row object.
     * @return int Progress percentage (0-100).
     */
    private static function calculate_progress( $project ) {
        if ( ! empty( $project->completed_at ) && '0000-00-00 00:00:00' !== $project->completed_at ) {
            return 100;
        }

        if ( 'completed' === $project->status ) {
            return 100;
        }

        // Estimate based on time elapsed since creation (max 95 %).
        $created = ! empty( $project->created_at ) && '0000-00-00 00:00:00' !== $project->created_at
            ? strtotime( $project->created_at )
            : 0;

        if ( $created <= 0 ) {
            return 10;
        }

        $elapsed_days  = max( 0, ( current_time( 'timestamp' ) - $created ) / DAY_IN_SECONDS );
        $estimated_days = 90; // assume ~3 months default.

        $progress = (int) round( min( 95, ( $elapsed_days / $estimated_days ) * 100 ) );

        return max( 5, $progress );
    }

    /**
     * Return a hex colour for the progress bar.
     *
     * @since 1.1.0
     *
     * @param int $progress Percentage 0-100.
     * @return string Hex colour.
     */
    private static function progress_color( $progress ) {
        if ( $progress > 75 ) {
            return '#2c7a3e'; // green.
        }
        if ( $progress > 50 ) {
            return '#d98c1c'; // orange.
        }
        if ( $progress > 25 ) {
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