<?php
/**
 * Settings page: API key, place lookup, manual overrides, status and usage.
 */

defined( 'ABSPATH' ) || exit;

final class Rating_Glance_Admin {

	const PAGE  = 'rating-glance';
	const GROUP = 'rating_glance';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'update_option_' . Rating_Glance::OPT_SETTINGS, array( __CLASS__, 'after_update' ), 10, 2 );
		add_action( 'add_option_' . Rating_Glance::OPT_SETTINGS, array( __CLASS__, 'after_add' ) );
		add_action( 'admin_post_rating_glance_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'wp_ajax_rating_glance_lookup', array( __CLASS__, 'ajax_lookup' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RATING_GLANCE_FILE ), array( __CLASS__, 'action_links' ) );
	}

	private static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
	}

	public static function menu() {
		add_options_page( __( 'Rating Glance', 'rating-glance' ), __( 'Rating Glance', 'rating-glance' ), 'manage_options', self::PAGE, array( __CLASS__, 'render_page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'rating-glance' ) . '</a>' );
		return $links;
	}

	public static function register_setting() {
		register_setting(
			self::GROUP,
			Rating_Glance::OPT_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$old   = Rating_Glance::settings();
		$out   = Rating_Glance::defaults();

		// Blank key field means "keep the saved key", unless removal was requested.
		$key            = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
		$out['api_key'] = '' !== $key ? $key : ( empty( $input['remove_api_key'] ) ? $old['api_key'] : '' );

		$out['google_id']      = isset( $input['google_id'] ) ? trim( sanitize_text_field( $input['google_id'] ) ) : '';
		$out['tripadvisor_id'] = isset( $input['tripadvisor_id'] ) ? trim( sanitize_text_field( $input['tripadvisor_id'] ) ) : '';
		if ( '' !== $out['tripadvisor_id'] && ! Rating_Glance::parse_tripadvisor( $out['tripadvisor_id'] ) ) {
			add_settings_error( Rating_Glance::OPT_SETTINGS, 'tripadvisor_id', __( 'The Tripadvisor field needs a Tripadvisor URL (containing "-d" followed by digits) or a numeric place ID.', 'rating-glance' ) );
		}

		$out['refresh'] = isset( $input['refresh'] ) && in_array( $input['refresh'], array( 'twicedaily', 'daily', 'weekly' ), true ) ? $input['refresh'] : 'daily';
		$out['new_tab'] = empty( $input['new_tab'] ) ? 0 : 1;

		foreach ( array_keys( Rating_Glance::sources() ) as $source ) {
			$m = isset( $input['manual'][ $source ] ) ? (array) $input['manual'][ $source ] : array();

			$rating = isset( $m['rating'] ) ? str_replace( ',', '.', trim( $m['rating'] ) ) : '';
			$out['manual'][ $source ]['rating'] = is_numeric( $rating ) ? (string) round( max( 0, min( 5, (float) $rating ) ), 1 ) : '';

			$count = isset( $m['count'] ) ? preg_replace( '/\D/', '', $m['count'] ) : '';
			$out['manual'][ $source ]['count'] = '' !== $count ? (string) absint( $count ) : '';

			$out['manual'][ $source ]['url'] = isset( $m['url'] ) ? esc_url_raw( trim( $m['url'] ) ) : '';
		}

		return $out;
	}

	public static function after_update( $old, $new ) {
		if ( ! is_array( $old ) || ! isset( $old['refresh'] ) || $old['refresh'] !== $new['refresh'] ) {
			Rating_Glance::reschedule();
		}
		Rating_Glance::refresh();
	}

	public static function after_add() {
		Rating_Glance::reschedule();
		Rating_Glance::refresh();
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rating-glance' ), 403 );
		}
		check_admin_referer( 'rating_glance_refresh' );
		Rating_Glance::refresh();
		wp_safe_redirect( self::page_url( array( 'refreshed' => 1 ) ) );
		exit;
	}

	public static function ajax_lookup() {
		check_ajax_referer( 'rating_glance_lookup' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'rating-glance' ) ), 403 );
		}

		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$query  = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$key    = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';

		if ( '' === $query ) {
			wp_send_json_error( array( 'message' => __( 'Type the business name and city to search for.', 'rating-glance' ) ) );
		}

		$results = Rating_Glance::search( $source, $query, '' !== $key ? $key : null );
		if ( is_wp_error( $results ) ) {
			wp_send_json_error( array( 'message' => $results->get_error_message() ) );
		}
		wp_send_json_success( $results );
	}

	public static function enqueue( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rating-glance' );
		wp_enqueue_style( 'rating-glance-admin', RATING_GLANCE_URL . 'assets/admin.css', array(), RATING_GLANCE_VERSION );
		wp_enqueue_script( 'rating-glance-admin', RATING_GLANCE_URL . 'assets/admin.js', array(), RATING_GLANCE_VERSION, true );
		wp_localize_script(
			'rating-glance-admin',
			'ratingGlanceAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'rating_glance_lookup' ),
				'i18n'    => array(
					'searching' => __( 'Searching…', 'rating-glance' ),
					'none'      => __( 'No matches. Try the name plus city, e.g. "Restaurant Name Amsterdam".', 'rating-glance' ),
					'use'       => __( 'Use this', 'rating-glance' ),
					'filled'    => __( 'Filled in. Click "Save changes" to fetch the rating.', 'rating-glance' ),
					'failed'    => __( 'Search failed.', 'rating-glance' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------------ */

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = Rating_Glance::settings();
		$name = Rating_Glance::OPT_SETTINGS;
		?>
		<div class="wrap rating-glance-admin">
			<h1><?php esc_html_e( 'Rating Glance', 'rating-glance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Shows your Google and Tripadvisor score, number of reviews and a link. Ratings are fetched in the background via SerpApi and cached; visitors never wait on an API.', 'rating-glance' ); ?></p>

			<?php if ( isset( $_GET['refreshed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ratings refreshed.', 'rating-glance' ); ?></p></div>
			<?php endif; ?>

			<?php self::render_status(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Connection', 'rating-glance' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rating-glance-api-key"><?php esc_html_e( 'SerpApi key', 'rating-glance' ); ?></label></th>
						<td>
							<input type="password" id="rating-glance-api-key" name="<?php echo esc_attr( $name ); ?>[api_key]" class="regular-text" autocomplete="off"
								placeholder="<?php echo esc_attr( $s['api_key'] ? __( 'Saved. Leave empty to keep it.', 'rating-glance' ) : '' ); ?>">
							<?php if ( $s['api_key'] ) : ?>
								<label class="rating-glance-inline"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[remove_api_key]" value="1"> <?php esc_html_e( 'Remove saved key', 'rating-glance' ); ?></label>
							<?php endif; ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to SerpApi */
									esc_html__( 'Get a free key at %s. Each refresh uses one search per source, so a daily refresh uses about 60 searches a month.', 'rating-glance' ),
									'<a href="https://serpapi.com/manage-api-key" target="_blank" rel="noopener">serpapi.com</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rating-glance-refresh"><?php esc_html_e( 'Refresh', 'rating-glance' ); ?></label></th>
						<td>
							<select id="rating-glance-refresh" name="<?php echo esc_attr( $name ); ?>[refresh]">
								<option value="twicedaily" <?php selected( $s['refresh'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice a day', 'rating-glance' ); ?></option>
								<option value="daily" <?php selected( $s['refresh'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'rating-glance' ); ?></option>
								<option value="weekly" <?php selected( $s['refresh'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'rating-glance' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Links', 'rating-glance' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[new_tab]" value="1" <?php checked( $s['new_tab'] ); ?>> <?php esc_html_e( 'Open review pages in a new tab', 'rating-glance' ); ?></label></td>
					</tr>
				</table>

				<?php
				self::render_source(
					'google',
					'google_id',
					__( 'Google place ID', 'rating-glance' ),
					__( 'Starts with "ChIJ". Use the search below to find it.', 'rating-glance' ),
					$s
				);
				self::render_source(
					'tripadvisor',
					'tripadvisor_id',
					__( 'Tripadvisor URL or ID', 'rating-glance' ),
					__( 'Paste the URL of your Tripadvisor page, or use the search below.', 'rating-glance' ),
					$s
				);
				?>

				<?php submit_button(); ?>
			</form>

			<?php self::render_usage(); ?>
		</div>
		<?php
	}

	private static function render_source( $source, $field, $field_label, $help, array $s ) {
		$name   = Rating_Glance::OPT_SETTINGS;
		$label  = Rating_Glance::sources()[ $source ];
		$manual = $s['manual'][ $source ];
		$id     = 'rating-glance-' . $source . '-id';
		?>
		<h2 class="title"><?php echo esc_html( $label ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field_label ); ?></label></th>
				<td>
					<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name . '[' . $field . ']' ); ?>" value="<?php echo esc_attr( $s[ $field ] ); ?>" class="large-text code">
					<p class="description"><?php echo esc_html( $help ); ?></p>
					<div class="rating-glance-finder">
						<input type="search" class="regular-text rating-glance-query" id="rating-glance-query-<?php echo esc_attr( $source ); ?>" data-source="<?php echo esc_attr( $source ); ?>"
							value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: source name */ __( 'Search %s', 'rating-glance' ), $label ) ); ?>">
						<button type="button" class="button rating-glance-find" data-source="<?php echo esc_attr( $source ); ?>" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Find', 'rating-glance' ); ?></button>
						<div class="rating-glance-results" id="rating-glance-results-<?php echo esc_attr( $source ); ?>" aria-live="polite"></div>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Manual override', 'rating-glance' ); ?></th>
				<td>
					<details <?php echo ( '' !== $manual['rating'] || '' !== $manual['count'] || '' !== $manual['url'] ) ? 'open' : ''; ?>>
						<summary><?php esc_html_e( 'Optional. Filled-in fields replace the fetched values; leave empty to use live data.', 'rating-glance' ); ?></summary>
						<div class="rating-glance-manual">
							<label><?php esc_html_e( 'Score', 'rating-glance' ); ?>
								<input type="text" inputmode="decimal" name="<?php echo esc_attr( $name . '[manual][' . $source . '][rating]' ); ?>" value="<?php echo esc_attr( $manual['rating'] ); ?>" class="small-text" placeholder="4.6">
							</label>
							<label><?php esc_html_e( 'Number of reviews', 'rating-glance' ); ?>
								<input type="text" inputmode="numeric" name="<?php echo esc_attr( $name . '[manual][' . $source . '][count]' ); ?>" value="<?php echo esc_attr( $manual['count'] ); ?>" class="small-text" placeholder="250">
							</label>
							<label><?php esc_html_e( 'Link', 'rating-glance' ); ?>
								<input type="url" name="<?php echo esc_attr( $name . '[manual][' . $source . '][url]' ); ?>" value="<?php echo esc_attr( $manual['url'] ); ?>" class="regular-text" placeholder="https://">
							</label>
						</div>
					</details>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_status() {
		$data    = Rating_Glance::data();
		$errors  = isset( $data['errors'] ) ? (array) $data['errors'] : array();
		$preview = Rating_Glance::render( array() );
		$next    = wp_next_scheduled( Rating_Glance::CRON_HOOK );
		?>
		<div class="card rating-glance-status">
			<h2 class="title"><?php esc_html_e( 'Current ratings', 'rating-glance' ); ?></h2>

			<div class="rating-glance-preview">
				<?php
				echo $preview ? $preview : '<em>' . esc_html__( 'Nothing to show yet. Add your API key and places below.', 'rating-glance' ) . '</em>'; // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</div>

			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Source', 'rating-glance' ); ?></th>
					<th><?php esc_html_e( 'Score', 'rating-glance' ); ?></th>
					<th><?php esc_html_e( 'Reviews', 'rating-glance' ); ?></th>
					<th><?php esc_html_e( 'Last fetched', 'rating-glance' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( Rating_Glance::sources() as $key => $label ) : ?>
					<?php
					$live   = isset( $data[ $key ] ) ? $data[ $key ] : array();
					$values = Rating_Glance::values( $key );
					?>
					<tr>
						<td>
							<?php if ( $values && $values['url'] ) : ?>
								<a href="<?php echo esc_url( $values['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $label ); ?>
							<?php endif; ?>
							<?php if ( ! empty( $live['name'] ) ) : ?>
								<br><span class="description"><?php echo esc_html( $live['name'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $values ? esc_html( number_format_i18n( $values['rating'], 1 ) ) : '—'; ?></td>
						<td><?php echo $values && null !== $values['count'] ? esc_html( number_format_i18n( $values['count'] ) ) : '—'; ?></td>
						<td>
							<?php
							if ( ! empty( $live['fetched'] ) ) {
								/* translators: %s: human time difference */
								echo esc_html( sprintf( __( '%s ago', 'rating-glance' ), human_time_diff( $live['fetched'] ) ) );
							} else {
								echo '—';
							}
							if ( ! empty( $errors[ $key ] ) ) {
								echo '<br><span class="rating-glance-error">' . esc_html( $errors[ $key ] ) . '</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rating-glance-refresh">
				<input type="hidden" name="action" value="rating_glance_refresh">
				<?php wp_nonce_field( 'rating_glance_refresh' ); ?>
				<?php submit_button( __( 'Refresh now', 'rating-glance' ), 'secondary', 'submit', false ); ?>
				<?php if ( $next ) : ?>
					<span class="description">
						<?php
						/* translators: %s: human time difference */
						echo esc_html( sprintf( __( 'Next automatic refresh in %s.', 'rating-glance' ), human_time_diff( $next ) ) );
						?>
					</span>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	private static function render_usage() {
		?>
		<h2 class="title"><?php esc_html_e( 'Adding it to your site', 'rating-glance' ); ?></h2>
		<ul class="rating-glance-usage">
			<li><strong><?php esc_html_e( 'Block themes / Site Editor:', 'rating-glance' ); ?></strong> <?php esc_html_e( 'add the "Rating Glance" block to your footer template part.', 'rating-glance' ); ?></li>
			<li><strong><?php esc_html_e( 'Classic themes:', 'rating-glance' ); ?></strong> <?php esc_html_e( 'Appearance → Widgets → add "Rating Glance" to a footer area.', 'rating-glance' ); ?></li>
			<li><strong><?php esc_html_e( 'Page builders (Elementor, Divi, …):', 'rating-glance' ); ?></strong> <?php esc_html_e( 'use a shortcode element:', 'rating-glance' ); ?> <code>[rating_glance]</code></li>
		</ul>
		<p><?php esc_html_e( 'Shortcode options:', 'rating-glance' ); ?></p>
		<table class="widefat striped rating-glance-options">
			<tbody>
				<tr><td><code>sources="google,tripadvisor"</code></td><td><?php esc_html_e( 'Which sources to show, in this order.', 'rating-glance' ); ?></td></tr>
				<tr><td><code>display="stars|compact|text"</code></td><td><?php esc_html_e( 'Five stars, one star, or plain score.', 'rating-glance' ); ?></td></tr>
				<tr><td><code>count="text|number|none"</code></td><td><?php esc_html_e( '"250 reviews", "(250)" or hidden.', 'rating-glance' ); ?></td></tr>
				<tr><td><code>layout="inline|stacked"</code></td><td><?php esc_html_e( 'Side by side or below each other.', 'rating-glance' ); ?></td></tr>
				<tr><td><code>align="start|center|end"</code></td><td><?php esc_html_e( 'Horizontal alignment.', 'rating-glance' ); ?></td></tr>
				<tr><td><code>label="yes|no"</code></td><td><?php esc_html_e( 'Show the source name.', 'rating-glance' ); ?></td></tr>
				<tr><td><code>new_tab="yes|no"</code></td><td><?php esc_html_e( 'Overrides the setting above.', 'rating-glance' ); ?></td></tr>
				<tr><td><code>class="my-class"</code></td><td><?php esc_html_e( 'Extra CSS class.', 'rating-glance' ); ?></td></tr>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'The output uses your theme\'s font and text color. Fine-tune with CSS variables on .rating-glance: --rg-gap, --rg-star-color, --rg-star-empty-opacity.', 'rating-glance' ); ?>
		</p>
		<?php
	}
}
