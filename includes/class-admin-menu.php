<?php
/**
 * Phoenix CRM Admin Menu
 *
 * Registers the top-level "Phoenix CRM" admin menu and all submenu pages.
 * Each submenu callback delegates rendering to the appropriate admin class.
 *
 * @package Phoenix_CRM
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Admin_Menu
 *
 * Manages the WordPress admin menu structure for the Phoenix Agentic CRM.
 */
class Phoenix_CRM_Admin_Menu {

    /**
     * The top-level menu slug.
     *
     * @var string
     */
    const MENU_SLUG = 'phoenix-crm';

    /**
     * Capability required to access any CRM admin page.
     *
     * @var string
     */
    const REQUIRED_CAP = 'manage_options';

    /**
     * Registered submenu page definitions.
     *
     * @var array
     */
    private static $submenus = array();

    /**
     * Full list of submenu page definitions.
     *
     * @return array
     */
    private static function get_submenu_definitions() {
        return array(
            array(
                'title'    => __( 'Dashboard', 'phoenix-crm' ),
                'menu'     => __( 'Dashboard', 'phoenix-crm' ),
                'slug'     => self::MENU_SLUG, // Same as parent = first/active submenu.
                'class'    => 'Phoenix_CRM_Admin_Dashboard',
                'callback' => 'render_page',
            ),
            array(
                'title'    => __( 'Proposals', 'phoenix-crm' ),
                'menu'     => __( 'Proposals', 'phoenix-crm' ),
                'slug'     => 'phoenix-crm-proposals',
                'class'    => 'Phoenix_CRM_Admin_Proposals',
                'callback' => 'render_page',
            ),
            array(
                'title'    => __( 'Goals', 'phoenix-crm' ),
                'menu'     => __( 'Goals', 'phoenix-crm' ),
                'slug'     => 'phoenix-crm-goals',
                'class'    => 'Phoenix_CRM_Admin_Goals',
                'callback' => 'render_page',
            ),
            array(
                'title'    => __( 'Projects', 'phoenix-crm' ),
                'menu'     => __( 'Projects', 'phoenix-crm' ),
                'slug'     => 'phoenix-crm-projects',
                'class'    => 'Phoenix_CRM_Admin_Projects',
                'callback' => 'render_page',
            ),
            array(
                'title'    => __( 'Event Log', 'phoenix-crm' ),
                'menu'     => __( 'Event Log', 'phoenix-crm' ),
                'slug'     => 'phoenix-crm-event-log',
                'class'    => 'Phoenix_CRM_Admin_Events',
                'callback' => 'render_page',
            ),
            array(
                'title'    => __( 'Raw Data', 'phoenix-crm' ),
                'menu'     => __( 'Raw Data', 'phoenix-crm' ),
                'slug'     => 'phoenix-crm-raw-data',
                'class'    => 'Phoenix_CRM_Admin_RawData',
                'callback' => 'render_page',
            ),
            array(
                'title'    => __( 'Settings', 'phoenix-crm' ),
                'menu'     => __( 'Settings', 'phoenix-crm' ),
                'slug'     => 'phoenix-crm-settings',
                'class'    => 'Phoenix_CRM_Admin_Settings',
                'callback' => 'render_page',
            ),
        );
    }

    /**
     * Hook into WordPress admin_menu to register all pages.
     *
     * @return void
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    /**
     * Register the top-level menu page and all submenu pages.
     *
     * @return void
     */
    public static function register_menu() {
        if ( ! current_user_can( self::REQUIRED_CAP ) ) {
            return;
        }

        // Top-level menu page.
        add_menu_page(
            __( 'Phoenix Agentic CRM', 'phoenix-crm' ),
            __( 'Phoenix CRM', 'phoenix-crm' ),
            self::REQUIRED_CAP,
            self::MENU_SLUG,
            array( __CLASS__, 'render_dashboard_proxy' ),
            'dashicons-analytics', // Icon.
            30                     // Position (above Tools, below Comments).
        );

        // Register each submenu page.
        foreach ( self::get_submenu_definitions() as $submenu ) {
            add_submenu_page(
                self::MENU_SLUG,
                $submenu['title'],
                $submenu['menu'],
                self::REQUIRED_CAP,
                $submenu['slug'],
                array( __CLASS__, 'route_submenu_callback_' . sanitize_title( $submenu['slug'] ) )
            );
        }
    }

    /**
     * Proxy render for the default (Dashboard) submenu.
     *
     * The first submenu with slug === parent slug is the default view.
     */
    public static function render_dashboard_proxy() {
        self::route_to( 'Phoenix_CRM_Admin_Dashboard', 'render_page' );
    }

    /**
     * Magic-style route methods for each submenu slug.
     *
     * WordPress calls these via the callback registered above.
     *
     * @param string $name      The method name.
     * @param array  $arguments The arguments (unused).
     * @return void
     */
    public static function __callStatic( $name, $arguments ) {
        // Match route_submenu_callback_{sanitized_slug}.
        if ( strpos( $name, 'route_submenu_callback_' ) !== 0 ) {
            return;
        }

        $sanitized_slug = substr( $name, strlen( 'route_submenu_callback_' ) );

        // Find the matching definition.
        foreach ( self::get_submenu_definitions() as $submenu ) {
            $slug_to_match = sanitize_title( $submenu['slug'] );
            if ( $slug_to_match === $sanitized_slug ) {
                self::route_to( $submenu['class'], $submenu['callback'] );
                return;
            }
        }
    }

    /**
     * Safely invoke a render method on an admin class.
     *
     * Checks that the class and method exist before calling.
     *
     * @param string $class_name The fully-qualified class name.
     * @param string $method     The method name to call (typically 'render_page').
     * @return void
     */
    private static function route_to( $class_name, $method ) {
        if ( ! current_user_can( self::REQUIRED_CAP ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'phoenix-crm' ) );
        }

        if ( ! class_exists( $class_name ) ) {
            wp_die(
                esc_html( sprintf(
                    /* translators: %s: class name */
                    __( 'Admin class %s not found. Please ensure the plugin is fully installed.', 'phoenix-crm' ),
                    $class_name
                ) )
            );
        }

        if ( ! method_exists( $class_name, $method ) ) {
            wp_die(
                esc_html( sprintf(
                    /* translators: %s: method name */
                    __( 'Rendering method %s not found in the admin class.', 'phoenix-crm' ),
                    $method
                ) )
            );
        }

        call_user_func( array( $class_name, $method ) );
    }
}