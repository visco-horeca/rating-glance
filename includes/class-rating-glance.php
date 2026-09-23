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
			'icon'        => array(
				'mono'  => __( 'Logo in text color', 'rating-glance' ),
				'color' => __( 'Logo in brand colors', 'rating-glance' ),
				'none'  => __( 'No logo', 'rating-glance' ),
			),
		);
	}

	/**
	 * Inline SVG logo for a source.
	 *
	 * Mono icons: Simple Icons (CC0). Google color "G": Wikimedia Commons (public domain).
	 * Logos are trademarks of their owners and are only used to link to the business's own listing.
	 */
	public static function icon( $source, $variant ) {
		$paths = array(
			'google'      => array(
				'mono'  => '<path fill="currentColor" d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>',
				'color' => '<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>',
			),
			'tripadvisor' => array(
				'mono'  => '<path fill="currentColor" d="%s"/>',
				'color' => '<circle cx="12" cy="12" r="12" fill="#34E0A1"/><path fill="#000" transform="translate(3.6 3.6) scale(.7)" d="%s"/>',
			),
		);
		$owl = 'M12.006 4.295c-2.67 0-5.338.784-7.645 2.353H0l1.963 2.135a5.997 5.997 0 0 0 4.04 10.43 5.976 5.976 0 0 0 4.075-1.6L12 19.705l1.922-2.09a5.972 5.972 0 0 0 4.072 1.598 6 6 0 0 0 6-5.998 5.982 5.982 0 0 0-1.957-4.432L24 6.648h-4.35a13.573 13.573 0 0 0-7.644-2.353zM12 6.255c1.531 0 3.063.303 4.504.903C13.943 8.138 12 10.43 12 13.1c0-2.671-1.942-4.962-4.504-5.942A11.72 11.72 0 0 1 12 6.256zM6.002 9.157a4.059 4.059 0 1 1 0 8.118 4.059 4.059 0 0 1 0-8.118zm11.992.002a4.057 4.057 0 1 1 .003 8.115 4.057 4.057 0 0 1-.003-8.115zm-11.992 1.93a2.128 2.128 0 0 0 0 4.256 2.128 2.128 0 0 0 0-4.256zm11.992 0a2.128 2.128 0 0 0 0 4.256 2.128 2.128 0 0 0 0-4.256z';

		if ( ! isset( $paths[ $source ][ $variant ] ) ) {
			return '';
		}
		$inner = 'tripadvisor' === $source ? sprintf( $paths[ $source ][ $variant ], $owl ) : $paths[ $source ][ $variant ];

		return '<span class="rating-glance__icon rating-glance__icon--' . esc_attr( $source ) . '"><svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">' . $inner . '</svg></span>';
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
					'icon'            => array( 'type' => 'string', 'default' => 'mono' ),
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

	/** A rating as a float, or null. Localized results may use a decimal comma ("4,5"). */
	public static function parse_rating( $value ) {
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', trim( $value ) );
		}
		return is_numeric( $value ) && $value > 0 ? round( (float) $value, 1 ) : null;
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
			if ( $ref['domain'] && ( is_wp_error( $body ) || null === self::parse_rating( isset( $body['place_result']['rating'] ) ? $body['place_result']['rating'] : null ) ) ) {
				// Unsupported regional domain or no rating there: retry on the default one.
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

		$rating = self::parse_rating( isset( $place['rating'] ) ? $place['rating'] : null );
		if ( null === $rating ) {
			return new WP_Error( 'rating_glance_no_rating', __( 'No rating found for this place. Check the place ID.', 'rating-glance' ) );
		}

		return array(
			'name'   => $name,
			'rating' => $rating,
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
				'icon'        => 'mono',
				'show_label'  => true,
				'new_tab'     => null,
				'class'       => '',
				'block'       => false,
			)
		);
		$choices = self::choices();
		foreach ( array( 'display', 'layout', 'align', 'count_style', 'icon' ) as $opt ) {
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
				$items .= self::render_item( $key, $labels[ $key ], $values, $args, $new_tab );
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

	private static function render_item( $key, $label, array $values, array $args, $new_tab ) {
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

		$html = self::icon( $key, $args['icon'] );
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
				'icon'        => 'mono',
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
				'icon'        => $atts['icon'],
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
				'icon'        => $attributes['icon'],
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
