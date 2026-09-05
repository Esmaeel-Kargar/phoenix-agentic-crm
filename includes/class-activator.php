<?php
/**
 * Phoenix CRM Activation / Deactivation Hooks
 *
 * Runs when the plugin is activated or deactivated. Activation installs the
 * database schema and schedules maintenance tasks. Deactivation cleans up
 * scheduled events and optional data.
 *
 * @package Phoenix_CRM
 * @since   1.0.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Phoenix_CRM_Activator
 *
 * Static-only container for activation / deactivation routines.
 */
class Phoenix_CRM_Activator {

    // -----------------------------------------------------------------------
    //  Activation
    // -----------------------------------------------------------------------

    /**
     * Handle plugin activation.
     *
     * 1. Check minimum requirements.
     * 2. Install / upgrade database tables.
     * 3. Save plugin version option.
     * 4. Schedule recurring maintenance cron.
     *
     * @return void
     */
    public static function activate() {
        self::check_requirements();

        // Install database tables.
        Phoenix_CRM_Database::install();

        // Store the plugin version.
        update_option('phoenix_crm_version', PHOENIX_CRM_VERSION, false);

        // Schedule maintenance cron (daily).
        if (!wp_next_scheduled('phoenix_crm_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'phoenix_crm_daily_maintenance');
        }

        // Flush rewrite rules so any custom post types or endpoints registered
        // in the future will take effect immediately.
        flush_rewrite_rules();
    }

    // -----------------------------------------------------------------------
    //  Deactivation
    // -----------------------------------------------------------------------

    /**
     * Handle plugin deactivation.
     *
     * 1. Clear scheduled cron hooks owned by the plugin.
     * 2. Optionally remove database tables (controlled by a filter so site
     *    owners can preserve data if they wish).
     * 3. Flush rewrite rules.
     *
     * @return void
     */
    public static function deactivate() {
        // Clear scheduled crons.
        $cron_hooks = [
            'phoenix_crm_daily_maintenance',
        ];
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        // By default, deactivation does NOT drop tables.
        // Sites that want to remove all data on deactivation can use:
        //   add_filter('phoenix_crm_drop_tables_on_deactivate', '__return_true');
        $drop_tables = apply_filters('phoenix_crm_drop_tables_on_deactivate', false);
        if ($drop_tables) {
            Phoenix_CRM_Database::uninstall();
        }

        flush_rewrite_rules();
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    /**
     * Verify PHP and WordPress version requirements.
     *
     * Fails hard (wp_die) if unmet so the plugin never runs on an
     * incompatible platform.
     *
     * @return void
     */
    private static function check_requirements() {
        if (version_compare(PHP_VERSION, PHOENIX_CRM_MIN_PHP_VERSION, '<')) {
            wp_die(
                esc_html(
                    sprintf(
                        'Phoenix Agentic CRM requires PHP %s or later. You are running %s.',
                        PHOENIX_CRM_MIN_PHP_VERSION,
                        PHP_VERSION
                    )
                ),
                esc_html__('Phoenix CRM – Requirements Error', 'phoenix-crm'),
                ['back_link' => true]
            );
        }

        global $wp_version;
        if (version_compare($wp_version, PHOENIX_CRM_MIN_WP_VERSION, '<')) {
            wp_die(
                esc_html(
                    sprintf(
                        'Phoenix Agentic CRM requires WordPress %s or later. You are running %s.',
                        PHOENIX_CRM_MIN_WP_VERSION,
                        $wp_version
                    )
                ),
                esc_html__('Phoenix CRM – Requirements Error', 'phoenix-crm'),
                ['back_link' => true]
            );
        }
    }
}