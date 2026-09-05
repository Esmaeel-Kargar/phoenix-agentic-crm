<?php
/**
 * Phoenix Agentic CRM - External Data Collector
 *
 * Collects external signals and stores them into wp_phoenix_raw_data.
 *
 * Data sources:
 *   - Google Trends data (scraper-based, no official API required)
 *   - RSS / Atom news feeds for engineering and 3D-printing keywords
 *   - Competitor update detection (common changelog / blog feeds)
 *
 * @package    Phoenix_Agentic_CRM
 * @subpackage Collectors
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Phoenix_CRM_Collector_External
 */
class Phoenix_CRM_Collector_External {

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
	 * Run every external collection method.
	 */
	public function collect_all() {
		$this->collect_google_trends();
		$this->collect_rss_feeds();
		$this->collect_competitor_updates();
	}

	/**
	 * Store a single data point into the raw-data table.
	 *
	 * @param string $data_type  Top-level category (e.g. 'google_trends').
	 * @param string $data_key   Metric / item name.
	 * @param mixed  $data_value Value (scalar — cast to string).
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
	 * Store multiple data points at once from a keyed array.
	 *
	 * @param string $data_type Top-level category.
	 * @param array  $kv_pairs  Associative array of key => value.
	 */
	protected function store_batch( $data_type, array $kv_pairs ) {
		foreach ( $kv_pairs as $key => $value ) {
			$this->store( $data_type, $key, $value );
		}
	}


	/* ------------------------------------------------------------------ *
	 *  Google Trends
	 * ------------------------------------------------------------------ */

	/**
	 * Collect Google Trends interest-over-time data for configured keywords.
	 *
	 * Uses the unofficial, free Google Trends scrape endpoint (no API key
	 * required).  WordPress HTTP API handles the request so it respects
	 * the server's proxy/firewall configuration.
	 */
	protected function collect_google_trends() {
		$keywords = apply_filters(
			'phoenix_trends_keywords',
			array(
				'3D printing',
				'additive manufacturing',
				'CAD software',
				'CNC machining',
				'engineering design',
			)
		);

		foreach ( $keywords as $keyword ) {
			$interest = $this->fetch_trends_interest( $keyword );
			$this->store( 'google_trends', sanitize_title( $keyword ), $interest );
		}
	}

	/**
	 * Fetch relative search interest for a keyword via the Trends Data API.
	 *
	 * The endpoint is the same one Google Trends uses in its web front-end.
	 * If the request fails we store a negative sentinel so downstream
	 * processes can distinguish "no data" from "zero interest".
	 *
	 * @param  string $keyword Search term.
	 * @return int             0-100 interest score, or -1 on error.
	 */
	protected function fetch_trends_interest( $keyword ) {
		$url = add_query_arg(
			array(
				'hl'         => 'en-US',
				'tz'         => get_option( 'gmt_offset', 0 ) * 60,
				'req'        => wp_json_encode( array(
					'comparisonItem' => array(
						array(
							'keyword' => $keyword,
							'geo'     => '',
							'time'    => 'now 7-d',
						),
					),
					'category'       => 0,
					'property'       => '',
				) ),
			),
			'https://trends.google.com/trends/api/widgetdata/multiline'
		);

		$response = wp_remote_get( $url, array(
			'timeout'   => 15,
			'headers'   => array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return -1;
		}

		$body = wp_remote_retrieve_body( $response );

		// Google wraps the JSON in `)]}',\n` before the payload.
		$body = preg_replace( '/^\)\]\}\',?\n*/', '', $body );
		$data = json_decode( $body, true );

		if ( empty( $data['default']['timelineData'] ) ) {
			return -1;
		}

		// Average the daily interest values to produce a single 7-day score.
		$values = wp_list_pluck( $data['default']['timelineData'], 'value' );
		$flat   = array();
		foreach ( $values as $v ) {
			if ( is_array( $v ) && isset( $v[0] ) ) {
				$flat[] = (int) $v[0];
			} elseif ( is_numeric( $v ) ) {
				$flat[] = (int) $v;
			}
		}

		if ( empty( $flat ) ) {
			return -1;
		}

		return (int) round( array_sum( $flat ) / count( $flat ) );
	}


	/* ------------------------------------------------------------------ *
	 *  RSS News Feeds
	 * ------------------------------------------------------------------ */

	/**
	 * Parse configured RSS feeds for engineering / 3D-printing news.
	 *
	 * Stores the most recent article per feed so downstream processors
	 * can detect new content by comparing timestamps.
	 */
	protected function collect_rss_feeds() {
		$feeds = apply_filters(
			'phoenix_rss_feeds',
			array(
				'3d_printing'  => 'https://3dprinting.com/feed/',
				'engineering'  => 'https://www.engineering.com/feed/',
				'makezine'     => 'https://makezine.com/feed/',
				'hackaday'     => 'https://hackaday.com/blog/feed/',
				'all3dp'       => 'https://all3dp.com/feed/',
			)
		);

		require_once ABSPATH . WPINC . '/feed.php';

		foreach ( $feeds as $slug => $feed_url ) {
			$feed_key   = sanitize_title( $slug );
			$latest     = $this->fetch_latest_article( $feed_url );
			$data_group = 'rss_' . $feed_key;

			if ( empty( $latest ) ) {
				$this->store( $data_group, 'status', 'error' );
				continue;
			}

			$this->store( $data_group, 'title', mb_substr( $latest['title'], 0, 255 ) );
			$this->store( $data_group, 'url', esc_url_raw( $latest['url'] ) );
			$this->store( $data_group, 'published', $latest['published'] );
			$this->store( $data_group, 'status', 'ok' );
		}
	}

	/**
	 * Get the most recent article from an RSS feed.
	 *
	 * @param  string $feed_url Valid RSS/Atom feed URL.
	 * @return array{title:string,url:string,published:string}|null
	 */
	protected function fetch_latest_article( $feed_url ) {
		$rss = fetch_feed( $feed_url );

		if ( is_wp_error( $rss ) ) {
			return null;
		}

		$max_items = $rss->get_item_quantity( 1 );

		if ( 0 === $max_items ) {
			return null;
		}

		$items = $rss->get_items( 0, 1 );
		$item  = $items[0] ?? null;

		if ( ! $item ) {
			return null;
		}

		return array(
			'title'     => $item->get_title() ?: '',
			'url'       => $item->get_permalink() ?: '',
			'published' => $item->get_date( 'Y-m-d H:i:s' ) ?: '',
		);
	}


	/* ------------------------------------------------------------------ *
	 *  Competitor Updates
	 * ------------------------------------------------------------------ */

	/**
	 * Collect latest updates from competitor changelogs / news pages.
	 *
	 * Competitor URLs are defined via filter so they can be extended
	 * without modifying the core plugin.
	 */
	protected function collect_competitor_updates() {
		$competitors = apply_filters(
			'phoenix_competitor_sources',
			array(
				'ultimaker'     => 'https://ultimaker.com/blog/feed/',
				'formlabs'      => 'https://formlabs.com/blog/feed/',
				'bambu_lab'     => 'https://bambulab.com/en/blog/feed',
				'prusa'         => 'https://blog.prusa3d.com/feed/',
			)
		);

		require_once ABSPATH . WPINC . '/feed.php';

		foreach ( $competitors as $slug => $feed_url ) {
			$comp_key   = sanitize_title( $slug );
			$latest     = $this->fetch_latest_article( $feed_url );
			$data_group = 'competitor_' . $comp_key;

			if ( empty( $latest ) ) {
				$this->store( $data_group, 'status', 'error' );
				continue;
			}

			$this->store( $data_group, 'title', mb_substr( $latest['title'], 0, 255 ) );
			$this->store( $data_group, 'url', esc_url_raw( $latest['url'] ) );
			$this->store( $data_group, 'published', $latest['published'] );
			$this->store( $data_group, 'status', 'ok' );
		}
	}
}