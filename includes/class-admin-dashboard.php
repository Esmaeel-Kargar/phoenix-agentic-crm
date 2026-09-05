<?php
/**
 * Phoenix CRM Admin Dashboard
 *
 * Renders the main dashboard page with a widget grid (delegated to
 * Widgets_Manager), an inline Recent Activity table, and quick-action
 * buttons.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Admin_Dashboard
 *
 * Displays the CRM dashboard overview with widget-driven summary cards,
 * latest events table, and one-click action links.
 */
class Phoenix_CRM_Admin_Dashboard {

    /**
     * Number of rows in the recent events table.
     *
     * @var int
     */
    const RECENT_EVENTS_LIMIT = 10;

    /**
     * Render the full dashboard page.
     *
     * Starts output buffering, delegates widget rendering to
     * Widgets_Manager, then renders the inline Recent Activity
     * table and quick-action links.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'phoenix-crm' ) );
        }

        // Start output buffering so Widgets_Manager output is captured.
        ob_start();

        // Ensure widgets are registered.
        Phoenix_CRM_Widgets_Manager::init();

        $version = defined( 'PHOENIX_CRM_VERSION' ) ? PHOENIX_CRM_VERSION : '—';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Phoenix CRM Dashboard', 'phoenix-crm' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Overview of your agentic CRM activity and latest events.', 'phoenix-crm' ); ?></p>

            <hr />

            <!-- Dashboard Widget Grid (delegated to Widgets_Manager) -->
            <?php Phoenix_CRM_Widgets_Manager::render_dashboard(); ?>

            <!-- Recent Activity Table -->
            <div id="phoenix-crm-recent-activity" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Recent Activity', 'phoenix-crm' ); ?></h2>
                <?php self::render_recent_activity(); ?>
            </div>

            <!-- Quick Actions -->
            <div id="phoenix-crm-quick-actions" style="margin-top:30px;">
                <h2><?php esc_html_e( 'Quick Actions', 'phoenix-crm' ); ?></h2>
                <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:10px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-proposals' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'View Proposals', 'phoenix-crm' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-goals' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Manage Goals', 'phoenix-crm' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-projects' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Browse Projects', 'phoenix-crm' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-settings' ) ); ?>" class="button">
                        <?php esc_html_e( 'Settings', 'phoenix-crm' ); ?>
                    </a>
                </div>
            </div>

            <!-- Plugin Version Footer -->
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

        // Flush the output buffer.
        ob_end_flush();
    }

    /**
     * Render the inline Recent Activity table.
     *
     * Displays the latest 10 events from phoenix_events with ID, Actor,
     * Action, Target, truncated Note (60 chars), and Date columns.
     *
     * @return void
     */
    private static function render_recent_activity() {
        $events = self::get_recent_events();

        if ( empty( $events ) ) {
            echo '<p><em>' . esc_html__( 'No events recorded yet. Events will appear here as the CRM processes data.', 'phoenix-crm' ) . '</em></p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
            <thead>
                <tr>
                    <th scope="col" style="width:60px;"><?php esc_html_e( 'ID', 'phoenix-crm' ); ?></th>
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
                        <td><?php echo esc_html( $event->id ); ?></td>
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
                        <td><?php echo esc_html( ! empty( $event->note ) ? mb_substr( $event->note, 0, 60 ) : '—' ); ?></td>
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
        <?php
    }

    /**
     * Render a single summary statistics card.
     *
     * @param string $label      Card heading.
     * @param int    $count      The statistic value.
     * @param string $dashicon   Dashicon CSS class (e.g. 'dashicons-edit-page').
     * @param string $accent_col Accent colour for the left border.
     * @param string $link_url   Optional URL to link the card.
     * @return void
     */
    private static function render_card( $label, $count, $dashicon, $accent_col, $link_url = '' ) {
        $tag   = ! empty( $link_url ) ? 'a' : 'div';
        $attrs = ! empty( $link_url )
            ? 'href="' . esc_url( $link_url ) . '" style="text-decoration:none;display:block;flex:1;min-width:200px;"'
            : 'style="flex:1;min-width:200px;"';
        ?>
        <<?php echo esc_attr( $tag ); ?> <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe attrs built above ?>>
            <div style="background:#fff;border-left:5px solid <?php echo esc_attr( $accent_col ); ?>;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.1);padding:20px;display:flex;align-items:center;gap:15px;">
                <span class="dashicons <?php echo esc_attr( $dashicon ); ?>" style="font-size:36px;width:36px;height:36px;color:<?php echo esc_attr( $accent_col ); ?>;"></span>
                <div>
                    <div style="font-size:28px;font-weight:700;line-height:1.2;"><?php echo esc_html( number_format_i18n( $count ) ); ?></div>
                    <div style="font-size:13px;color:#666;"><?php echo esc_html( $label ); ?></div>
                </div>
            </div>
        </<?php echo esc_attr( $tag ); ?>>
        <?php
    }

    /**
     * Query summary statistics from custom CRM tables.
     *
     * @return array Associative array with keys: proposals, active_goals, projects, events.
     */
    private static function get_summary_stats() {
        global $wpdb;

        $defaults = array(
            'proposals'    => 0,
            'active_goals' => 0,
            'projects'     => 0,
            'events'       => 0,
        );

        $tables = Phoenix_CRM_Database::get_table_names();

        // Proposals total (index 2).
        if ( isset( $tables[2] ) && self::table_exists( $tables[2] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[2]}"
                )
            );
            if ( is_numeric( $count ) ) {
                $defaults['proposals'] = (int) $count;
            }
        }

        // Active goals (index 3, status = 'active').
        if ( isset( $tables[3] ) && self::table_exists( $tables[3] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[3]} WHERE status = 'active'"
                )
            );
            if ( is_numeric( $count ) ) {
                $defaults['active_goals'] = (int) $count;
            }
        }

        // Projects total (index 4).
        if ( isset( $tables[4] ) && self::table_exists( $tables[4] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[4]}"
                )
            );
            if ( is_numeric( $count ) ) {
                $defaults['projects'] = (int) $count;
            }
        }

        // Events total (index 1).
        if ( isset( $tables[1] ) && self::table_exists( $tables[1] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables[1]}"
                )
            );
            if ( is_numeric( $count ) ) {
                $defaults['events'] = (int) $count;
            }
        }

        return $defaults;
    }

    /**
     * Query the most recent events.
     *
     * @return array List of event objects with id, actor, action, target_type, target_id, note, created_at.
     */
    private static function get_recent_events() {
        global $wpdb;

        $tables = Phoenix_CRM_Database::get_table_names();

        if ( ! isset( $tables[1] ) || ! self::table_exists( $tables[1] ) ) {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, actor, action, target_type, target_id, note, created_at
                 FROM {$tables[1]}
                 ORDER BY created_at DESC
                 LIMIT %d",
                self::RECENT_EVENTS_LIMIT
            )
        );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Check whether a database table exists.
     *
     * @param string $table_name Fully qualified table name.
     * @return bool
     */
    private static function table_exists( $table_name ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );

        return strtolower( $result ) === strtolower( $table_name );
    }
}