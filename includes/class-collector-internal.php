<?php
/**
 * Phoenix Agentic CRM - Internal Data Collector
 *
 * Collects site-level metrics and stores them into wp_phoenix_raw_data.
 *
 * Data sources:
 *   - Site stats (WP-Statistics integration or native WordPress fallback)
 *   - EDD sales (if Easy Digital Downloads is active)
 *   - YouTube stats (placeholder — ready for API key integration)
 *   - Social media counts (placeholder — ready for API key integration)
 *
 * @package    Phoenix_Agentic_CRM
 * @subpackage Collectors
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Phoenix_CRM_Collector_Internal
 */
class Phoenix_CRM_Collector_Internal {

	/**
	 * Table name (with prefix).
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'phoenix_raw_data';
	}

	/**
	 * Run every internal collection method.
	 */
	public function collect_all() {
		$this->collect_site_stats();
		$this->collect_edd_sales();
		$this->collect_youtube_stats();
		$this->collect_social_counts();
	}

	/**
	 * Store a single data point into the raw-data table.
	 *
	 * @param string $data_type Top-level category (e.g. 'site_stats').
	 * @param string $data_key  Metric name (e.g. 'total_posts').
	 * @param mixed  $data_value  Metric value (scalar — will be cast to string).
	 */
	protected function store( $data_type, $data_key, $data_value ) {
		global $wpdb;

		$collected_at = current_time( 'mysql', 1 );
		$data_json    = json_encode( array(
			'key'   => $data_key,
			'value' => $data_value,
		) );

		$wpdb->insert(
			$this->table,
			array(
				'source'       => $data_type,
				'category'     => $data_type,
				'data'         => $data_json,
				'collected_at' => $collected_at,
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Collect general site statistics.
	 *
	 * Uses WP-Statistics plugin if available, otherwise falls back to
	 * native WordPress functions.
	 */
	protected function collect_site_stats() {
		// --- Attempt WP-Statistics integration ---
		if ( function_exists( 'wp_statistics' ) ) {
			try {
				$visitors = wp_statistics()->get_visitor_count();
				$this->store( 'site_stats', 'visitors_today', (int) $visitors );
			} catch ( Exception $e ) {
				// Silently fall through to native fallback.
			}

			try {
				$visits = wp_statistics()->get_visit_count();
				$this->store( 'site_stats', 'visits_today', (int) $visits );
			} catch ( Exception $e ) {
				// Silent fallback.
			}

			try {
				$online = wp_statistics()->get_user_online();
				$this->store( 'site_stats', 'users_online', (int) $online );
			} catch ( Exception $e ) {
				// Silent fallback.
			}
		}

		// --- Native WordPress fallback (always collected) ---

		// Post counts.
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
			$this->store( 'site_stats', 'post_count_' . $pt, $published );
		}

		// User count.
		$user_count = count_users();
		$this->store( 'site_stats', 'total_users', (int) $user_count['total_users'] );

		// Comment count (approved).
		$comment_count = wp_count_comments();
		$this->store( 'site_stats', 'approved_comments', (int) $comment_count->approved );

		// Plugin count for basic environment awareness.
		$plugins = get_option( 'active_plugins', array() );
		$this->store( 'site_stats', 'active_plugins', count( $plugins ) );

		// WordPress version.
		global $wp_version;
		$this->store( 'site_stats', 'wp_version', $wp_version );
	}

	/**
	 * Collect sales data from Easy Digital Downloads.
	 *
	 * Stores totals only when EDD is active.
	 */
	protected function collect_edd_sales() {
		if ( ! function_exists( 'EDD' ) ) {
			return;
		}

		// Total lifetime earnings.
		$earnings = EDD()->get_earnings();
		$this->store( 'edd_sales', 'lifetime_earnings', round( $earnings, 2 ) );

		// Total sales count.
		$sales_count = EDD()->get_sales();
		$this->store( 'edd_sales', 'lifetime_sales', (int) $sales_count );

		// Month-to-date earnings (approximate).
		$month_earnings = edd_get_earnings_by_date( gmdate( 'Y' ), gmdate( 'm' ) );
		$this->store( 'edd_sales', 'month_earnings', round( $month_earnings, 2 ) );

		// Product count.
		$downloads = wp_count_posts( 'download' );
		$this->store( 'edd_sales', 'total_products', isset( $downloads->publish ) ? (int) $downloads->publish : 0 );

		// Customer count.
		if ( function_exists( 'edd_get_customer' ) ) {
			global $wpdb;
			$cust_count = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}edd_customers" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			$this->store( 'edd_sales', 'total_customers', (int) $cust_count );
		}
	}

	/**
	 * Collect YouTube channel statistics.
	 *
	 * This is a placeholder that stores a representative data point so the
	 * pipeline works end-to-end. Replace the stub with a real YouTube Data
	 * API v3 call once an API key is configured.
	 */
	protected function collect_youtube_stats() {
		$api_key = defined( 'PHOENIX_YOUTUBE_API_KEY' ) ? PHOENIX_YOUTUBE_API_KEY : '';
		$channel_id = apply_filters( 'phoenix_youtube_channel_id', '' );

		if ( $api_key && $channel_id ) {
			// --- REAL API integration placeholder ---
			// Example:
			// $url = add_query_arg( array(
			//     'part'     => 'statistics',
			//     'id'       => $channel_id,
			//     'key'      => $api_key,
			// ), 'https://www.googleapis.com/youtube/v3/channels' );
			// $response = wp_remote_get( $url );
			// if ( 200 === wp_remote_retrieve_response_code( $response ) ) { ... }
			//
			// For now we store a zero-value stub so the pipeline isn't broken.
			$this->store( 'youtube', 'channel_id', $channel_id );
			$this->store( 'youtube', 'subscribers', '0' );
			$this->store( 'youtube', 'total_views', '0' );
			$this->store( 'youtube', 'total_videos', '0' );
		} else {
			// No credentials — store placeholder marker.
			$this->store( 'youtube', 'status', 'not_configured' );
		}
	}

	/**
	 * Collect social-media follower counts.
	 *
	 * Placeholder — replace with real API calls for each platform when
	 * credentials are available.
	 */
	protected function collect_social_counts() {
		$networks = apply_filters(
			'phoenix_social_networks',
			array(
				'twitter'       => __( 'X / Twitter', 'phoenix-agentic-crm' ),
				'facebook'      => __( 'Facebook', 'phoenix-agentic-crm' ),
				'linkedin'      => __( 'LinkedIn', 'phoenix-agentic-crm' ),
				'instagram'     => __( 'Instagram', 'phoenix-agentic-crm' ),
				'github'        => __( 'GitHub', 'phoenix-agentic-crm' ),
			)
		);

		foreach ( $networks as $key => $label ) {
			$count = apply_filters( "phoenix_social_count_{$key}", 0 );
			$this->store( 'social', $key, (int) $count );
		}
	}
}