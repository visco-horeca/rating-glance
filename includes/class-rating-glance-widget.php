<?php
/**
 * Classic widget, for themes whose footer uses widget areas.
 */

defined( 'ABSPATH' ) || exit;

class Rating_Glance_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'rating_glance',
			__( 'Rating Glance', 'rating-glance' ),
			array(
				'description'                 => __( 'Google and Tripadvisor rating at a glance.', 'rating-glance' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	private function defaults() {
		$defaults = array(
			'title'       => '',
			'display'     => 'stars',
			'layout'      => 'inline',
			'align'       => 'start',
			'count_style' => 'text',
			'icon'        => 'mono',
			'show_label'  => 1,
		);
		foreach ( array_keys( Rating_Glance::sources() ) as $key ) {
			$defaults[ $key ] = 1;
		}
		return $defaults;
	}

	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );

		$sources = array();
		foreach ( array_keys( Rating_Glance::sources() ) as $key ) {
			if ( ! empty( $instance[ $key ] ) ) {
				$sources[] = $key;
			}
		}

		$html = Rating_Glance::render(
			array(
				'sources'     => $sources,
				'display'     => $instance['display'],
				'layout'      => $instance['layout'],
				'align'       => $instance['align'],
				'count_style' => $instance['count_style'],
				'icon'        => $instance['icon'],
				'show_label'  => ! empty( $instance['show_label'] ),
			)
		);
		if ( '' === $html ) {
			return;
		}

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in render().
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		$choices  = Rating_Glance::choices();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title (optional):', 'rating-glance' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>
		<p>
			<?php foreach ( Rating_Glance::sources() as $key => $label ) : ?>
				<label style="margin-right:1em">
					<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $instance[ $key ] ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<?php
		$selects = array(
			'icon'        => __( 'Logo:', 'rating-glance' ),
			'display'     => __( 'Style:', 'rating-glance' ),
			'count_style' => __( 'Review count:', 'rating-glance' ),
			'layout'      => __( 'Layout:', 'rating-glance' ),
			'align'       => __( 'Alignment:', 'rating-glance' ),
		);
		foreach ( $selects as $field => $label ) :
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( $field ) ); ?>"><?php echo esc_html( $label ); ?></label>
				<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( $field ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $field ) ); ?>">
					<?php foreach ( $choices[ $field ] as $value => $text ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $instance[ $field ], $value ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endforeach; ?>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_label' ) ); ?>" value="1" <?php checked( ! empty( $instance['show_label'] ) ); ?>>
				<?php esc_html_e( 'Show source name (Google, Tripadvisor)', 'rating-glance' ); ?>
			</label>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$choices  = Rating_Glance::choices();
		$instance = array(
			'title'      => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'show_label' => empty( $new_instance['show_label'] ) ? 0 : 1,
		);
		foreach ( array_keys( Rating_Glance::sources() ) as $key ) {
			$instance[ $key ] = empty( $new_instance[ $key ] ) ? 0 : 1;
		}
		foreach ( array( 'display', 'layout', 'align', 'count_style', 'icon' ) as $field ) {
			$value              = isset( $new_instance[ $field ] ) ? $new_instance[ $field ] : '';
			$instance[ $field ] = isset( $choices[ $field ][ $value ] ) ? $value : key( $choices[ $field ] );
		}
		return $instance;
	}
}
