<?php
/**
 * Core: settings, fetching via SerpApi, caching and front-end rendering.
 */

defined( 'ABSPATH' ) || exit;

final class Rating_Glance {

	const OPT_SETTINGS = 'rating_glance_settings';
	const OPT_DATA     = 'rating_glance_data';
	const CRON_HOOK    = 'rating_glance_refresh';
	const ENDPOINT     = 'https://serpapi.com/search.json';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh' ) );
		add_shortcode( 'rating_glance', array( __CLASS__, 'shortcode' ) );
	}

	/* ------------------------------------------------------------------
	 * Configuration
	 * ------------------------------------------------------------------ */

	/** Supported review sources, key => display label. */
	public static function sources() {
		return array(
			'google'      => 'Google',
			'tripadvisor' => 'Tripadvisor',
		);
	}

	/** Allowed values for the display options, shared by shortcode, widget and block. */
	public static function choices() {
		return array(
			'display'     => array(
				'stars'   => __( 'Five stars + score', 'rating-glance' ),
				'compact' => __( 'One star + score', 'rating-glance' ),
				'text'    => __( 'Score only (4.6/5)', 'rating-glance' ),
			),
			'layout'      => array(
				'inline'  => __( 'Side by side', 'rating-glance' ),
				'stacked' => __( 'Stacked', 'rating-glance' ),
			),
			'align'       => array(
				'start'  => __( 'Left', 'rating-glance' ),
				'center' => __( 'Center', 'rating-glance' ),
				'end'    => __( 'Right', 'rating-glance' ),
			),
			'count_style' => array(
				'text'   => __( '1,234 reviews', 'rating-glance' ),
				'number' => __( '(1,234)', 'rating-glance' ),
				'none'   => __( 'Hide review count', 'rating-glance' ),
			),
		);
	}

	public static function defaults() {
		$manual = array();
		foreach ( array_keys( self::sources() ) as $key ) {
			$manual[ $key ] = array(
				'rating' => '',
				'count'  => '',
				'url'    => '',
			);
		}
		return array(
			'api_key'        => '',
			'google_id'      => '',
			'tripadvisor_id' => '',
			'refresh'        => 'daily',
			'new_tab'        => 1,
			'manual'         => $manual,
		);
	}

	public static function settings() {
		$defaults = self::defaults();
		$saved    = get_option( self::OPT_SETTINGS, array() );
		$settings = array_merge( $defaults, is_array( $saved ) ? $saved : array() );

		$settings['manual'] = array_replace_recursive( $defaults['manual'], (array) $settings['manual'] );
		return $settings;
	}

	public static function data() {
		$data = get_option( self::OPT_DATA, array() );
		return is_array( $data ) ? $data : array();
	}

	/* ------------------------------------------------------------------
	 * Registration and scheduling
	 * ------------------------------------------------------------------ */

	public static function register() {
		wp_register_style( 'rating-glance', RATING_GLANCE_URL . 'assets/rating-glance.css', array(), RATING_GLANCE_VERSION );
		wp_register_script(
			'rating-glance-block',
			RATING_GLANCE_URL . 'assets/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			RATING_GLANCE_VERSION,
			true
		);

		register_block_type(
			'rating-glance/ratings',
			array(
				'api_version'           => 3,
				'editor_script_handles' => array( 'rating-glance-block' ),
				'style_handles'         => array( 'rating-glance' ),
				'render_callback'       => array( __CLASS__, 'render_block' ),
				'attributes'            => array(
					'showGoogle'      => array( 'type' => 'boolean', 'default' => true ),
					'showTripadvisor' => array( 'type' => 'boolean', 'default' => true ),
					'display'         => array( 'type' => 'string', 'default' => 'stars' ),
					'layout'          => array( 'type' => 'string', 'default' => 'inline' ),
					'align'           => array( 'type' => 'string', 'default' => 'start' ),
					'countStyle'      => array( 'type' => 'string', 'default' => 'text' ),
					'showLabel'       => array( 'type' => 'boolean', 'default' => true ),
				),
				'supports'              => array(
					'html'       => false,
					'color'      => array(
						'text'       => true,
						'background' => false,
						'link'       => false,
					),
					'typography' => array( 'fontSize' => true ),
					'spacing'    => array( 'margin' => true ),
				),
			)
		);

		self::ensure_schedule();
	}

	public static function register_widget() {
		register_widget( 'Rating_Glance_Widget' );
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::settings()['refresh'], self::CRON_HOOK );
		}
	}

	public static function reschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		self::ensure_schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/* ------------------------------------------------------------------
	 * Fetching
	 * ------------------------------------------------------------------ */

	/**
	 * Accepts a Tripadvisor place ID ("12345678") or any Tripadvisor URL containing "-d12345678".
	 *
	 * @return array|null { id, domain, url }
	 */
	public static function parse_tripadvisor( $input ) {
		$input = trim( (string) $input );
		if ( '' === $input ) {
			return null;
		}
		if ( ctype_digit( $input ) ) {
			return array( 'id' => $input, 'domain' => '', 'url' => '' );
		}
		if ( ! preg_match( '~-d(\d+)~', $input, $m ) ) {
			return null;
		}

		$host   = strtolower( (string) wp_parse_url( $input, PHP_URL_HOST ) );
		$domain = '';
		if ( preg_match( '~(^|\.)tripadvisor\.[a-z.]+$~', $host ) ) {
			$domain = 0 === strpos( $host, 'www.' ) ? $host : 'www.' . $host;
		}

		return array(
			'id'     => $m[1],
			'domain' => $domain,
			'url'    => $domain ? esc_url_raw( strtok( $input, '?#' ) ) : '',
		);
	}

	/** What to fetch for a source, or null when it is not configured. */
	public static function source_ref( $key, $settings = null ) {
		$settings = $settings ? $settings : self::settings();

		if ( 'google' === $key ) {
			$id = trim( $settings['google_id'] );
			return '' === $id ? null : array( 'id' => $id, 'domain' => '', 'url' => '' );
		}
		if ( 'tripadvisor' === $key ) {
			return self::parse_tripadvisor( $settings['tripadvisor_id'] );
		}
		return null;
	}

	/** Call SerpApi and return the decoded body or a WP_Error. */
	public static function request( array $params, $api_key = null ) {
		$api_key = null === $api_key ? self::settings()['api_key'] : $api_key;
		if ( '' === $api_key ) {
			return new WP_Error( 'rating_glance_no_key', __( 'No SerpApi key configured.', 'rating-glance' ) );
		}

		$params['api_key'] = $api_key;
		$response          = wp_remote_get(
			self::ENDPOINT . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ),
			array( 'timeout' => 30 )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'rating_glance_bad_response', sprintf( __( 'Unexpected response from SerpApi (HTTP %d).', 'rating-glance' ), wp_remote_retrieve_response_code( $response ) ) );
		}
		if ( ! empty( $body['error'] ) ) {
			return new WP_Error( 'rating_glance_api', (string) $body['error'] );
		}
		return $body;
	}

	/** Fetch the current rating for one source. */
	public static function fetch( $key, array $ref ) {
		if ( 'google' === $key ) {
			$body = self::request( array( 'engine' => 'google_maps', 'type' => 'place', 'place_id' => $ref['id'] ) );
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$place = isset( $body['place_results'] ) ? (array) $body['place_results'] : array();
			$name  = isset( $place['title'] ) ? (string) $place['title'] : '';
			$url   = 'https://www.google.com/maps/search/?' . http_build_query(
				array(
					'api'            => 1,
					'query'          => '' !== $name ? $name : 'Google',
					'query_place_id' => $ref['id'],
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			);
		} elseif ( 'tripadvisor' === $key ) {
			$params = array( 'engine' => 'tripadvisor_place', 'place_id' => $ref['id'] );
			if ( $ref['domain'] ) {
				$params['tripadvisor_domain'] = $ref['domain'];
			}
			$body = self::request( $params );
			if ( is_wp_error( $body ) && $ref['domain'] ) {
				// Unsupported regional domain: retry on the default one.
				unset( $params['tripadvisor_domain'] );
				$body = self::request( $params );
			}
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$place = isset( $body['place_result'] ) ? (array) $body['place_result'] : array();
			$name  = isset( $place['name'] ) ? (string) $place['name'] : '';
			$url   = $ref['url'] ? $ref['url'] : ( isset( $place['link'] ) ? (string) $place['link'] : '' );
		} else {
			return new WP_Error( 'rating_glance_source', __( 'Unknown source.', 'rating-glance' ) );
		}

		if ( ! isset( $place['rating'] ) || ! is_numeric( $place['rating'] ) ) {
			return new WP_Error( 'rating_glance_no_rating', __( 'No rating found for this place. Check the place ID.', 'rating-glance' ) );
		}

		return array(
			'name'   => $name,
			'rating' => round( (float) $place['rating'], 1 ),
			'count'  => isset( $place['reviews'] ) ? (int) preg_replace( '/\D/', '', (string) $place['reviews'] ) : null,
			'url'    => esc_url_raw( $url ),
		);
	}

	/**
	 * Refresh all configured sources. A failed fetch keeps the last good value.
	 */
	public static function refresh() {
		$settings = self::settings();
		$old      = self::data();
		$new      = array(
			'checked' => time(),
			'errors'  => array(),
		);

		foreach ( array_keys( self::sources() ) as $key ) {
			$ref = self::source_ref( $key, $settings );
			if ( ! $ref ) {
				continue;
			}

			$result = self::fetch( $key, $ref );
			if ( is_wp_error( $result ) ) {
				$new['errors'][ $key ] = $result->get_error_message();
				if ( isset( $old[ $key ]['id'] ) && $old[ $key ]['id'] === $ref['id'] ) {
					$new[ $key ] = $old[ $key ];
				}
				continue;
			}

			$new[ $key ] = $result + array(
				'id'      => $ref['id'],
				'fetched' => time(),
			);
		}

		update_option( self::OPT_DATA, $new );
		return $new;
	}

	/**
	 * Search for places to find the right ID (used by the settings page).
	 *
	 * @return array|WP_Error List of { title, value, detail }.
	 */
	public static function search( $source, $query, $api_key = null ) {
		$results = array();

		if ( 'google' === $source ) {
			$body = self::request( array( 'engine' => 'google_maps', 'type' => 'search', 'q' => $query ), $api_key );
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$list = ! empty( $body['place_results'] ) ? array( $body['place_results'] ) : ( isset( $body['local_results'] ) ? (array) $body['local_results'] : array() );
			foreach ( $list as $place ) {
				if ( empty( $place['place_id'] ) ) {
					continue;
				}
				$detail = array();
				if ( ! empty( $place['address'] ) ) {
					$detail[] = $place['address'];
				}
				if ( isset( $place['rating'] ) ) {
					$detail[] = '★ ' . $place['rating'] . ( isset( $place['reviews'] ) ? ' (' . $place['reviews'] . ')' : '' );
				}
				$results[] = array(
					'title'  => isset( $place['title'] ) ? (string) $place['title'] : $place['place_id'],
					'value'  => (string) $place['place_id'],
					'detail' => implode( ' · ', $detail ),
				);
			}
		} elseif ( 'tripadvisor' === $source ) {
			$body = self::request( array( 'engine' => 'tripadvisor', 'q' => $query, 'ssrc' => 'r' ), $api_key );
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			foreach ( isset( $body['places'] ) ? (array) $body['places'] : array() as $place ) {
				if ( empty( $place['place_id'] ) ) {
					continue;
				}
				$results[] = array(
					'title'  => isset( $place['title'] ) ? (string) $place['title'] : $place['place_id'],
					'value'  => ! empty( $place['link'] ) ? (string) $place['link'] : (string) $place['place_id'],
					'detail' => isset( $place['location'] ) ? (string) $place['location'] : '',
				);
			}
		} else {
			return new WP_Error( 'rating_glance_source', __( 'Unknown source.', 'rating-glance' ) );
		}

		return array_slice( $results, 0, 6 );
	}

	/* ------------------------------------------------------------------
	 * Output
	 * ------------------------------------------------------------------ */

	/** Values to display for a source: manual overrides win over fetched data. */
	public static function values( $key ) {
		$settings = self::settings();
		$data     = self::data();
		$manual   = $settings['manual'][ $key ];
		$live     = isset( $data[ $key ] ) ? $data[ $key ] : array();

		$rating = '' !== $manual['rating'] ? (float) $manual['rating'] : ( isset( $live['rating'] ) ? (float) $live['rating'] : null );
		if ( null === $rating ) {
			return null;
		}

		return array(
			'rating' => $rating,
			'count'  => '' !== $manual['count'] ? (int) $manual['count'] : ( isset( $live['count'] ) ? (int) $live['count'] : null ),
			'url'    => '' !== $manual['url'] ? $manual['url'] : ( isset( $live['url'] ) ? $live['url'] : '' ),
		);
	}

	/**
	 * Render the ratings.
	 *
	 * @param array $args sources, display, layout, align, count_style, show_label, new_tab, class, block.
	 */
	public static function render( array $args ) {
		$args    = wp_parse_args(
			$args,
			array(
				'sources'     => array_keys( self::sources() ),
				'display'     => 'stars',
				'layout'      => 'inline',
				'align'       => 'start',
				'count_style' => 'text',
				'show_label'  => true,
				'new_tab'     => null,
				'class'       => '',
				'block'       => false,
			)
		);
		$choices = self::choices();
		foreach ( array( 'display', 'layout', 'align', 'count_style' ) as $opt ) {
			if ( ! isset( $choices[ $opt ][ $args[ $opt ] ] ) ) {
				$args[ $opt ] = key( $choices[ $opt ] );
			}
		}
		$new_tab = null === $args['new_tab'] ? ! empty( self::settings()['new_tab'] ) : (bool) $args['new_tab'];

		$labels = self::sources();
		$items  = '';
		foreach ( (array) $args['sources'] as $key ) {
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}
			$values = self::values( $key );
			if ( $values ) {
				$items .= self::render_item( $labels[ $key ], $values, $args, $new_tab );
			}
		}
		if ( '' === $items ) {
			return '';
		}

		wp_enqueue_style( 'rating-glance' );

		$classes = array(
			'rating-glance',
			'rating-glance--' . $args['layout'],
			'rating-glance--' . $args['display'],
			'rating-glance--align-' . $args['align'],
		);
		foreach ( preg_split( '/\s+/', (string) $args['class'], -1, PREG_SPLIT_NO_EMPTY ) as $extra ) {
			$classes[] = sanitize_html_class( $extra );
		}
		$class_attr = implode( ' ', $classes );

		$wrapper = $args['block']
			? get_block_wrapper_attributes( array( 'class' => $class_attr ) )
			: 'class="' . esc_attr( $class_attr ) . '"';

		return '<div ' . $wrapper . '>' . $items . '</div>';
	}

	private static function render_item( $label, array $values, array $args, $new_tab ) {
		$rating = max( 0, min( 5, $values['rating'] ) );
		$score  = number_format_i18n( $rating, 1 );
		$count  = $values['count'];

		if ( null !== $count ) {
			/* translators: 1: source name, 2: score, 3: number of reviews */
			$aria = sprintf( _n( '%1$s: rated %2$s out of 5 from %3$s review', '%1$s: rated %2$s out of 5 from %3$s reviews', $count, 'rating-glance' ), $label, $score, number_format_i18n( $count ) );
		} else {
			/* translators: 1: source name, 2: score */
			$aria = sprintf( __( '%1$s: rated %2$s out of 5', 'rating-glance' ), $label, $score );
		}

		$html = '';
		if ( $args['show_label'] ) {
			$html .= '<span class="rating-glance__label">' . esc_html( $label ) . '</span>';
		}
		if ( 'stars' === $args['display'] ) {
			$html .= '<span class="rating-glance__stars" style="--rg-fill:' . esc_attr( number_format( $rating / 5 * 100, 1, '.', '' ) ) . '%"></span>';
		} elseif ( 'compact' === $args['display'] ) {
			$html .= '<span class="rating-glance__star">&#9733;</span>';
		}
		$html .= '<span class="rating-glance__score">' . esc_html( $score );
		if ( 'text' === $args['display'] ) {
			$html .= '<span class="rating-glance__max">/5</span>';
		}
		$html .= '</span>';

		if ( null !== $count && 'none' !== $args['count_style'] ) {
			$formatted = number_format_i18n( $count );
			$text      = 'number' === $args['count_style']
				? '(' . $formatted . ')'
				/* translators: %s: number of reviews */
				: sprintf( _n( '%s review', '%s reviews', $count, 'rating-glance' ), $formatted );
			$html     .= '<span class="rating-glance__count">' . esc_html( $text ) . '</span>';
		}

		$inner = '<span class="rating-glance__content" aria-hidden="true">' . $html . '</span>';

		if ( '' === $values['url'] ) {
			return '<span class="rating-glance__item" role="img" aria-label="' . esc_attr( $aria ) . '">' . $inner . '</span>';
		}

		$target = '';
		if ( $new_tab ) {
			$aria  .= ' ' . __( '(opens in a new tab)', 'rating-glance' );
			$target = ' target="_blank" rel="noopener"';
		}

		return '<a class="rating-glance__item" href="' . esc_url( $values['url'] ) . '"' . $target . ' aria-label="' . esc_attr( $aria ) . '">' . $inner . '</a>';
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'sources'     => implode( ',', array_keys( self::sources() ) ),
				'display'     => 'stars',
				'layout'      => 'inline',
				'align'       => 'start',
				'count'       => 'text',
				'label'       => 'yes',
				'new_tab'     => '',
				'class'       => '',
			),
			$atts,
			'rating_glance'
		);

		return self::render(
			array(
				'sources'     => array_filter( array_map( 'trim', explode( ',', strtolower( $atts['sources'] ) ) ) ),
				'display'     => $atts['display'],
				'layout'      => $atts['layout'],
				'align'       => $atts['align'],
				'count_style' => $atts['count'],
				'show_label'  => self::truthy( $atts['label'] ),
				'new_tab'     => '' === $atts['new_tab'] ? null : self::truthy( $atts['new_tab'] ),
				'class'       => $atts['class'],
			)
		);
	}

	public static function render_block( $attributes ) {
		$sources = array();
		if ( ! empty( $attributes['showGoogle'] ) ) {
			$sources[] = 'google';
		}
		if ( ! empty( $attributes['showTripadvisor'] ) ) {
			$sources[] = 'tripadvisor';
		}

		$html = self::render(
			array(
				'sources'     => $sources,
				'display'     => $attributes['display'],
				'layout'      => $attributes['layout'],
				'align'       => $attributes['align'],
				'count_style' => $attributes['countStyle'],
				'show_label'  => ! empty( $attributes['showLabel'] ),
				'block'       => true,
			)
		);

		if ( '' === $html && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p ' . get_block_wrapper_attributes() . '><em>' . esc_html__( 'No ratings to show yet. Add your places under Settings → Rating Glance.', 'rating-glance' ) . '</em></p>';
		}
		return $html;
	}

	private static function truthy( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on' ), true );
	}
}
