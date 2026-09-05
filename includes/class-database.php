<?php
/**
 * Phoenix CRM Database Schema Installer
 *
 * Handles creation and updating of all plugin database tables using
 * WordPress dbDelta() for safe additive schema migrations.
 *
 * @package Phoenix_CRM
 * @since   1.0.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Phoenix_CRM_Database
 *
 * Responsible for installing, upgrading, and dropping the nine custom tables
 * that underpin the Phoenix Agentic CRM.
 */
class Phoenix_CRM_Database {

    /**
     * Option key that stores the current schema version.
     */
    const DB_VERSION_KEY = 'phoenix_crm_db_version';

    /**
     * Install or upgrade all plugin tables.
     *
     * Uses dbDelta() so existing tables are safely altered (new columns,
     * indexes, etc.) without data loss.
     *
     * @return void
     */
    public static function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = self::build_schema_sql($charset_collate);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_KEY, PHOENIX_CRM_VERSION, false);
    }

    /**
     * Drop all plugin tables (used on deactivation when the user opts to
     * remove data, or during unit-test teardown).
     *
     * @return void
     */
    public static function uninstall() {
        global $wpdb;

        $tables = self::get_table_names();

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::DB_VERSION_KEY);
    }

    /**
     * Return the fully-qualified table names (with wpdb prefix).
     *
     * @return string[]
     */
    public static function get_table_names() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        return [
            "{$prefix}phoenix_raw_data",
            "{$prefix}phoenix_events",
            "{$prefix}phoenix_proposals",
            "{$prefix}phoenix_goals",
            "{$prefix}phoenix_projects",
            "{$prefix}phoenix_saved_items",
            "{$prefix}phoenix_notes",
            "{$prefix}phoenix_report_templates",
            "{$prefix}phoenix_user_tasks",
        ];
    }

    // -----------------------------------------------------------------------
    //  Schema builder
    // -----------------------------------------------------------------------

    /**
     * Build and return the multi-table CREATE SQL expected by dbDelta().
     *
     * dbDelta() is sensitive to formatting: each column and key definition
     * must be on its own line, and the whole statement must end with a
     * semicolon on its own line.
     *
     * @param string $charset_collate The charset / collation string from $wpdb.
     *
     * @return string Concatenated SQL (multiple CREATE TABLE statements).
     */
    private static function build_schema_sql($charset_collate) {
        global $wpdb;

        $prefix = $wpdb->prefix;

        $tables = [];

        // -------------------------------------------------------------------
        // 1. phoenix_raw_data
        //    Raw ingested data from any source (webhooks, scraping, APIs).
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_raw_data (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(50) NOT NULL DEFAULT '',
            category VARCHAR(50) NOT NULL DEFAULT '',
            data LONGTEXT NOT NULL,
            collected_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            synced_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY idx_raw_source (source),
            KEY idx_raw_category (category),
            KEY idx_raw_collected_at (collected_at)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 2. phoenix_events
        //    Audit trail / activity log for all agent-driven actions.
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_events (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            actor VARCHAR(50) NOT NULL DEFAULT '',
            action VARCHAR(100) NOT NULL DEFAULT '',
            target_type VARCHAR(50) NOT NULL DEFAULT '',
            target_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            note TEXT,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY idx_events_actor (actor),
            KEY idx_events_action (action),
            KEY idx_events_target (target_type, target_id),
            KEY idx_events_created_at (created_at)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 3. phoenix_proposals
        //    Proposals or decisions made by agents (or human-in-the-loop).
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_proposals (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT,
            type VARCHAR(50) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reason TEXT,
            archived TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            decided_at DATETIME NULL DEFAULT NULL,
            executed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_proposals_type (type),
            KEY idx_proposals_status (status),
            KEY idx_proposals_archived (archived),
            KEY idx_proposals_created_at (created_at)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 4. phoenix_goals
        //    Strategic goals with time horizons (quarterly, yearly, etc.).
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_goals (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT,
            horizon VARCHAR(20) NOT NULL DEFAULT '',
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            KEY idx_goals_horizon (horizon),
            KEY idx_goals_status (status),
            KEY idx_goals_period (period_start, period_end)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 5. phoenix_projects
        //    Concrete projects spun up from proposals or created directly.
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_projects (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            type VARCHAR(20) NOT NULL DEFAULT '',
            description TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'planning',
            proposal_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            completed_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_projects_type (type),
            KEY idx_projects_status (status),
            KEY idx_projects_proposal_id (proposal_id),
            KEY idx_projects_created_at (created_at)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 6. phoenix_saved_items
        //    Bookmarks / saved links with review state.
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_saved_items (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(512) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL DEFAULT '',
            source VARCHAR(50) NOT NULL DEFAULT '',
            note TEXT,
            checked TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            saved_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY idx_items_source (source),
            KEY idx_items_checked (checked),
            KEY idx_items_saved_at (saved_at)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 7. phoenix_notes
        //    Free-form notes attached to any agent workflow.
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_notes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            content TEXT,
            source VARCHAR(50) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY idx_notes_source (source),
            KEY idx_notes_created_at (created_at)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 8. phoenix_report_templates
        //    Saved report/render format definitions.
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_report_templates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL DEFAULT '',
            format TEXT,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY idx_templates_name (name)
        ) {$charset_collate};";

        // -------------------------------------------------------------------
        // 9. phoenix_user_tasks
        //    Personal task list for dashboard users, with priority and due date.
        // -------------------------------------------------------------------
        $tables[] = "CREATE TABLE {$prefix}phoenix_user_tasks (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT,
            priority VARCHAR(10) NOT NULL DEFAULT 'medium',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            due_date DATE NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            completed_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_tasks_user (user_id),
            KEY idx_user_tasks_status (status),
            KEY idx_user_tasks_due (due_date),
            KEY idx_user_tasks_priority (priority)
        ) {$charset_collate};";

        return implode("\n", $tables) . "\n";
    }
}