<?php
/**
 * Plugin Name:     Phoenix Agentic CRM
 * Plugin URI:      https://github.com/dynamixsystems/phoenix-agentic-crm
 * Description:     Autonomous agentic CRM for WordPress — raw data ingestion, event tracking, proposals, goals, projects, saved items, notes, and report templates.
 * Version:         1.0.0
 * Author:          Dynamix Systems
 * Author URI:      https://dynamixsystems.com
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     phoenix-crm
 * Domain Path:     /languages
 *
 * Phoenix Agentic CRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define('PHOENIX_CRM_VERSION', '1.0.0');
define('PHOENIX_CRM_PLUGIN_FILE', __FILE__);
define('PHOENIX_CRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PHOENIX_CRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PHOENIX_CRM_MIN_PHP_VERSION', '7.4');
define('PHOENIX_CRM_MIN_WP_VERSION', '5.8');

// ---------------------------------------------------------------------------
// Load Admin Menu class (used in is_admin() bootstrap below)
// ---------------------------------------------------------------------------
require_once PHOENIX_CRM_PLUGIN_DIR . 'includes/class-admin-menu.php';

// ---------------------------------------------------------------------------
// Autoload internal classes
// ---------------------------------------------------------------------------

/**
 * Simple autoloader for plugin classes.
 *
 * Maps Phoenix_CRM_{Class} to includes/class-{class}.php
 * and Phoenix_CRM_Database_{Class} to includes/database/class-{class}.php
 */
spl_autoload_register(function ($class) {
    // Only handle our namespace.
    if (strpos($class, 'Phoenix_CRM_') !== 0) {
        return;
    }

    // Strip prefix.
    $relative = substr($class, strlen('Phoenix_CRM_'));

    // Determine subdirectory.
    $subdir = '';
    if (strpos($relative, 'Database_') === 0) {
        $subdir   = 'database/';
        $relative = substr($relative, strlen('Database_'));
    }

    // Convert class name to file name: Class_Name => class-class-name.php
    $file = 'class-' . str_replace('_', '-', strtolower($relative)) . '.php';
    $path = PHOENIX_CRM_PLUGIN_DIR . 'includes/' . $subdir . $file;

    if (file_exists($path)) {
        require_once $path;
    }
});

// ---------------------------------------------------------------------------
// Hooks – Activation / Deactivation
// ---------------------------------------------------------------------------

register_activation_hook(__FILE__, ['Phoenix_CRM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Phoenix_CRM_Activator', 'deactivate']);

// ---------------------------------------------------------------------------
// Bootstrap – check requirements on every admin page load
// ---------------------------------------------------------------------------

if (is_admin()) {
    add_action('admin_init', 'phoenix_crm_check_requirements');
}

/**
 * Verify minimum PHP and WordPress versions.
 *
 * Deactivates the plugin with a user-facing notice if requirements are not met.
 */
function phoenix_crm_check_requirements() {
    if (version_compare(PHP_VERSION, PHOENIX_CRM_MIN_PHP_VERSION, '<')) {
        phoenix_crm_deactivate_and_notify(
            sprintf(
                /* translators: %1$s: required PHP version, %2$s: current PHP version */
                __('Phoenix Agentic CRM requires PHP %1$s or later (you are running %2$s).', 'phoenix-crm'),
                PHOENIX_CRM_MIN_PHP_VERSION,
                PHP_VERSION
            )
        );
        return;
    }

    global $wp_version;
    if (version_compare($wp_version, PHOENIX_CRM_MIN_WP_VERSION, '<')) {
        phoenix_crm_deactivate_and_notify(
            sprintf(
                /* translators: %1$s: required WP version, %2$s: current WP version */
                __('Phoenix Agentic CRM requires WordPress %1$s or later (you are running %2$s).', 'phoenix-crm'),
                PHOENIX_CRM_MIN_WP_VERSION,
                $wp_version
            )
        );
    }
}

/**
 * Deactivate the plugin and show an admin notice.
 *
 * @param string $message The notice text to display.
 */
function phoenix_crm_deactivate_and_notify($message) {
    deactivate_plugins(plugin_basename(PHOENIX_CRM_PLUGIN_FILE));
    wp_die(
        esc_html($message),
        __('Plugin Requirement Error', 'phoenix-crm'),
        ['back_link' => true]
    );
}

// ---------------------------------------------------------------------------
// Plugin loaded action
// ---------------------------------------------------------------------------

/**
 * Fired once all plugin files are loaded.
 */
add_action('plugins_loaded', 'phoenix_crm_loaded');
function phoenix_crm_loaded() {
    // Initialize Cron Manager (registers cron schedules and hooks).
    Phoenix_CRM_Cron_Manager::init();

    // Initialize Admin Menu (top-level menu and submenu pages).
    Phoenix_CRM_Admin_Menu::init();

    // Initialize Raw Data AJAX handlers (Sync All, Delete All).
    Phoenix_CRM_Admin_RawData::init_ajax();

    // Initialize API Authentication (admin menu, settings, AJAX handler).
    Phoenix_CRM_API_Auth::get_instance();

    // Initialize API Endpoints (registers REST routes).
    Phoenix_CRM_API_Endpoints::get_instance();

    // Hook the admin settings key regeneration.
    add_action( 'admin_post_phoenix_crm_admin_regenerate_key', array( 'Phoenix_CRM_Admin_Settings', 'handle_regenerate_key' ) );

    // -----------------------------------------------------------------------
    // Admin-only bootstrap
    // -----------------------------------------------------------------------
    if (is_admin()) {
        add_action('admin_enqueue_scripts', 'phoenix_admin_enqueue_assets');
    }
}

/**
 * Enqueue admin CSS and JS assets with localized AJAX data.
 */
function phoenix_admin_enqueue_assets() {
    $screen = get_current_screen();
    // Only enqueue on our plugin admin pages.
    if (!$screen || strpos($screen->id, 'phoenix-crm') === false) {
        return;
    }

    wp_enqueue_style(
        'phoenix-crm-admin',
        PHOENIX_CRM_PLUGIN_URL . 'admin-assets/style.css',
        [],
        PHOENIX_CRM_VERSION
    );

    wp_enqueue_script(
        'phoenix-crm-admin',
        PHOENIX_CRM_PLUGIN_URL . 'admin-assets/script.js',
        ['jquery'],
        PHOENIX_CRM_VERSION,
        true
    );

    wp_localize_script('phoenix-crm-admin', 'phoenix_crm_admin', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('phoenix_crm_admin_nonce'),
    ]);
}