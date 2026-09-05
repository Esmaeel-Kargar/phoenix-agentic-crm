<?php
/**
 * Phoenix Agentic CRM - Cron Manager
 *
 * Manages WP-Cron hooks for collecting internal and external data.
 * Registers custom schedules, schedules recurring events, and
 * displays admin notices explaining cron behaviour.
 *
 * @package    Phoenix_Agentic_CRM
 * @subpackage Cron
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Phoenix_CRM_Cron_Manager
 *
 * Central registration and lifecycle for all plugin cron hooks.
 */
class Phoenix_CRM_Cron_Manager {

	/**
	 * Hook name for internal data collection.
	 */
	const HOOK_INTERNAL = 'phoenix_collect_internal';

	/**
	 * Hook name for external data collection.
	 */
	const HOOK_EXTERNAL = 'phoenix_collect_external';

	/**
	 * Option key that stores the timestamp of the last admin-notice dismissal.
	 */
	const NOTICE_DISMISS_OPTION = 'phoenix_cron_notice_dismissed';

	/**
	 * How long (seconds) to re-show the notice after dismissal (30 days).
	 */
	const NOTICE_REMINDER_INTERVAL = 2592000;

	/**
	 * Initialize the cron manager: register schedules, hooks, and admin UI.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		// Register the action hooks that collectors will listen on.
		add_action( self::HOOK_INTERNAL, array( __CLASS__, 'run_internal_collect' ) );
		add_action( self::HOOK_EXTERNAL, array( __CLASS__, 'run_external_collect' ) );

		// Admin notice about cron scheduling.
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	/**
	 * Register custom WP-Cron intervals.
	 *
	 * @param  array $schedules Existing cron schedules.
	 * @return array
	 */
	public static function add_cron_schedules( $schedules ) {
		$internal_interval = apply_filters( 'phoenix_internal_collect_interval', 6 * HOUR_IN_SECONDS );
		$external_interval = apply_filters( 'phoenix_external_collect_interval', 12 * HOUR_IN_SECONDS );

		$schedules['phoenix_every_six_hours'] = array(
			'interval' => max( $internal_interval, 300 ), // floor 5 minutes.
			'display'  => __( 'Every 6 hours (Phoenix Internal)', 'phoenix-agentic-crm' ),
		);

		$schedules['phoenix_every_twelve_hours'] = array(
			'interval' => max( $external_interval, 300 ),
			'display'  => __( 'Every 12 hours (Phoenix External)', 'phoenix-agentic-crm' ),
		);

		return $schedules;
	}

	/**
	 * Schedule recurring events on plugin activation.
	 *
	 * Safe to call multiple times — WP-Cron ignores duplicates.
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::HOOK_INTERNAL ) ) {
			wp_schedule_event( time(), 'phoenix_every_six_hours', self::HOOK_INTERNAL );
		}

		if ( ! wp_next_scheduled( self::HOOK_EXTERNAL ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'phoenix_every_twelve_hours', self::HOOK_EXTERNAL );
		}
	}

	/**
	 * Clear scheduled events on plugin deactivation.
	 */
	public static function clear_scheduled_events() {
		$internal_ts = wp_next_scheduled( self::HOOK_INTERNAL );
		if ( $internal_ts ) {
			wp_unschedule_event( $internal_ts, self::HOOK_INTERNAL );
		}

		$external_ts = wp_next_scheduled( self::HOOK_EXTERNAL );
		if ( $external_ts ) {
			wp_unschedule_event( $external_ts, self::HOOK_EXTERNAL );
		}
	}

	/**
	 * Callback fired by the phoenix_collect_internal cron hook.
	 */
	public static function run_internal_collect() {
		$collector = new Phoenix_CRM_Collector_Internal();
		$collector->collect_all();
	}

	/**
	 * Callback fired by the phoenix_collect_external cron hook.
	 */
	public static function run_external_collect() {
		$collector = new Phoenix_CRM_Collector_External();
		$collector->collect_all();
	}


	/* ------------------------------------------------------------------ *
	 *  Admin notice
	 * ------------------------------------------------------------------ */

	/**
	 * Display an admin notice explaining how Phoenix cron scheduling works.
	 * Users can dismiss the notice for 30 days.
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Respect dismissal.
		$dismissed = get_option( self::NOTICE_DISMISS_OPTION, 0 );
		if ( time() - $dismissed < self::NOTICE_REMINDER_INTERVAL ) {
			return;
		}

		// Only show on plugin-related pages to avoid noise.
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'phoenix' ) ) {
			return;
		}

		$internal_next = wp_next_scheduled( self::HOOK_INTERNAL );
		$external_next = wp_next_scheduled( self::HOOK_EXTERNAL );

		$internal_str = $internal_next
			? sprintf(
				/* translators: %s: human-readable time diff */
				__( '%s from now', 'phoenix-agentic-crm' ),
				human_time_diff( time(), $internal_next )
			)
			: __( 'Not scheduled', 'phoenix-agentic-crm' );

		$external_str = $external_next
			? sprintf(
				/* translators: %s: human-readable time diff */
				__( '%s from now', 'phoenix-agentic-crm' ),
				human_time_diff( time(), $external_next )
			)
			: __( 'Not scheduled', 'phoenix-agentic-crm' );

		?>
		<div class="notice notice-info is-dismissible phoenix-cron-notice">
			<p>
				<strong><?php esc_html_e( 'Phoenix Agentic CRM — Cron Schedule', 'phoenix-agentic-crm' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'The plugin automatically collects data on a recurring schedule:', 'phoenix-agentic-crm' ); ?>
			</p>
			<ul style="list-style:disc;padding-left:2em;">
				<li>
					<?php
					printf(
						/* translators: 1: next run relative time */
						esc_html__( 'Internal data (site stats, EDD sales, YouTube, social) — every 6 hours. Next run: %s', 'phoenix-agentic-crm' ),
						esc_html( $internal_str )
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: 1: next run relative time */
						esc_html__( 'External data (Google Trends, RSS feeds, competitors) — every 12 hours. Next run: %s', 'phoenix-agentic-crm' ),
						esc_html( $external_str )
					);
					?>
				</li>
			</ul>
			<p>
				<?php esc_html_e( 'Intervals can be changed via the', 'phoenix-agentic-crm' ); ?>
				<code>phoenix_internal_collect_interval</code> /
				<code>phoenix_external_collect_interval</code>
				<?php esc_html_e( 'filters.', 'phoenix-agentic-crm' ); ?>
			</p>
			<p>
				<em>
				<?php
				printf(
					/* translators: %s: link to WordPress cron system info */
					esc_html__( 'Cron depends on site traffic. For reliable execution, consider setting up a system cron job. %s', 'phoenix-agentic-crm' ),
					'<a href="https://developer.wordpress.org/plugins/cron/" target="_blank" rel="noopener noreferrer">' .
					esc_html__( 'Learn more →', 'phoenix-agentic-crm' ) .
					'</a>'
				);
				?>
				</em>
			</p>
		</div>
		<script>
		jQuery( document ).on( 'click', '.phoenix-cron-notice .notice-dismiss', function() {
			jQuery.post( ajaxurl, {
				action: 'phoenix_dismiss_cron_notice',
				_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'phoenix_dismiss_cron_notice' ) ); ?>'
			} );
		} );
		</script>
		<?php
	}

	/**
	 * AJAX handler to persist notice dismissal.
	 */
	public static function ajax_dismiss_notice() {
		check_ajax_referer( 'phoenix_dismiss_cron_notice' );

		if ( current_user_can( 'manage_options' ) ) {
			update_option( self::NOTICE_DISMISS_OPTION, time(), false );
		}

		wp_die( '1' );
	}
}