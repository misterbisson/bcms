<?php
/**
 * bCMS_Wijax_Widget class
 *
 */
class bCMS_Wijax_Widget extends WP_Widget
{

	public function __construct()
	{
		$widget_ops = array( 'classname' => 'widget_wijax', 'description' => __( 'Lazy load widgets after DOMDocumentReady' ) );
		$this->WP_Widget( 'wijax', __( 'Wijax Widget Lazy Loader' ), $widget_ops );

		add_filter( 'wijax-base-current', array( $this, 'base_current' ), 5 );
		add_filter( 'wijax-base-home', array( $this, 'base_home' ), 5 );
	}// end __construct

	public function widget( $args, $instance )
	{
		global $mywijax;

		// passed to bCMS_Wijax::varname() to determine if the URL should be normalized or not.
		// You'll find the call to that method below.
		$is_local = TRUE;

		if ( 'remote' != $instance['base'] )
		{
			$base = apply_filters( 'wijax-base-' . $instance['base'], '' );
			if ( ! $base )
			{
				return;
			}//end if

			$wijax_source = $base . $mywijax->encoded_name( $instance['widget'] );
		}//end if
		else
		{
			$is_local = FALSE;
			$wijax_source = $instance['base-remote'] . $mywijax->encoded_name( $instance['widget-custom'] );
		}//end else

		// if there is a query string, let's make the request with it in place
		// these variables are needed in the downstream request, this will be escaped on output
		if ( $_SERVER['QUERY_STRING'] )
		{
			$wijax_source .= "?{$_SERVER['QUERY_STRING']}";
		}//end if

		// wijax doesn't work with paging. Let's strip out the "page/X/" section of the URL, which should help with cached wijax widgets
		$wijax_source = preg_replace( '#/page/[0-9]+/wijax#', '/wijax', $wijax_source );

		$wijax_varname = $mywijax->varname( $wijax_source, $is_local );

		echo $args['before_widget'];

		preg_match( '/<([\S]*)/', $args['before_title'], $title_element );
		$title_element = trim( (string) $title_element[1], '<>' );

		preg_match( '/class.*?=.*?(\'|")(.+?)(\'|")/', $args['before_title'], $title_class );
		$title_class = (string) $title_class[2];

		$loadtime = ( $instance['loadtime'] ) ? $instance['loadtime'] : 'onload';

		$classes = isset( $instance['classes'] ) ? $instance['classes'] : '';
		?>
		<span class="wijax-loading <?php echo esc_attr( $classes ); ?>">
			<img src="<?php echo /* @INSANE */ esc_url( $mywijax->path_web  . '/img/loading-gray.gif' ); ?>" alt="loading external resource" />
			<a href="<?php echo esc_url( $wijax_source ); ?>" class="wijax-source <?php echo esc_attr( 'wijax-' . $loadtime ); ?>" rel="nofollow"></a>
			<span class="wijax-opts" style="display: none;">
				<?php
				echo json_encode( array(
					'source' => esc_url( $wijax_source ),
					'varname' => $wijax_varname,
					'title_element' => $title_element,
					'title_class' => $title_class,
					'title_before' => rawurlencode( $args['before_title'] ),
					'title_after' => rawurlencode( $args['after_title'] ),
				) );
				?>
			</span>
		</span>
		<?php
		echo $args['after_widget'];
	}//end widget

	public function base_home()
	{
		return trailingslashit( home_url() ) .'wijax/';
	}//end base_home

	public function base_current()
	{
		$home_path = parse_url( home_url(), PHP_URL_PATH );
		return esc_url_raw( trailingslashit( home_url() . str_replace( $home_path, '', parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ) ) . 'wijax/' );
	}//end base_current

	public function update( $new_instance, $old_instance )
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['widget'] = sanitize_title( $new_instance['widget'] );
		$instance['widget-custom'] = sanitize_title( $new_instance['widget-custom'] );
		$instance['base'] = sanitize_title( $new_instance['base'] );
		$instance['base-remote'] = esc_url_raw( $new_instance['base-remote'] );
		$instance['loadtime'] = in_array( $new_instance['loadtime'], array( 'onload', 'onscroll' ) ) ? $new_instance['loadtime'] : 'onscroll';
		$instance['classes'] = sanitize_text_field( $new_instance['classes'] );

		return $instance;
	}// end update

	public function form( $instance )
	{
		//Defaults
		$instance = wp_parse_args( (array) $instance,
			array(
				'title' => '',
				'homelink' => get_option( 'blogname' ),
				'maxchars' => 35,
				'classes' => '',
			)
		);

		// @insane escaping of the get_field_id and get_field_name function calls is crazy talk but required by VIP
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			<br />
			<small>For convenience, not shown publicly</small>
		</p>
		<?php
		echo $this->control_widgets( $instance );
		echo $this->control_base( $instance );
		echo $this->control_loadtime( $instance );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'classes' ) ); ?>">CSS Classes</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'classes' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'classes' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['classes'] ); ?>"/>
		</p>
		<?php
	}// end form

	public function control_widgets( $instance, $whichfield = 'widget' )
	{
		// get the available widgets
		$sidebars_widgets = wp_get_sidebars_widgets();

		$instance[ $whichfield ] = isset( $instance[ $whichfield ] ) ? $instance[ $whichfield ] : '';
		$instance[ $whichfield . '-custom' ] = isset( $instance[ $whichfield . '-custom' ] ) ? $instance[ $whichfield . '-custom' ] : '';

		// @insane: the escaping of the scalars in the foreach and calls to get_field_id and get_field name are entirely unneeded, but required by VIP

		$list = '';
		foreach ( (array) $sidebars_widgets['wijax-area'] as $item )
		{
			$list .= '<option value="' . esc_attr( $item ) . '" ' . selected( $instance[ $whichfield ], $item, FALSE ) . '>' . esc_html( $item ) . '</option>';
		}
		$list .= '<option value="custom" ' . selected( $instance[ $whichfield ], 'custom', FALSE ) . '>Custom widget</option>';

		$return = '
		<p>
			<label for="' . esc_attr( $this->get_field_id( $whichfield ) ) . '">Widget</label>
			<select name="' . esc_attr( $this->get_field_name( $whichfield ) ) . '" id="' . esc_attr( $this->get_field_id( $whichfield ) ) . '" class="widefat">' . $list . '</select>
		</p>
		<p>
			<label for="' . esc_attr( $this->get_field_id( $whichfield . '-custom' ) ) . '">Custom Widget Name</label>
			<input name="' . esc_attr( $this->get_field_name( $whichfield . '-custom' ) ) . '" id="' . esc_attr( $this->get_field_id( $whichfield .'-custom' ) ) . '" class="widefat" type="text" value="' . sanitize_title( $instance[ $whichfield . '-custom' ] ) . '">
		</p>';

		return $return;
	}//end control_widgets

	public function control_base( $instance, $whichfield = 'base' )
	{
		$bases = apply_filters( 'wijax-bases', array(
			'current' => 'The currently requested URL',
			'home' => 'The blog home URL',
			'remote' => 'Remote base URL',
		) );

		$instance[ $whichfield ] = isset( $instance[ $whichfield ] ) ? $instance[ $whichfield ] : '';
		$instance[ $whichfield . '-remote' ] = isset( $instance[ $whichfield . '-remote' ] ) ? $instance[ $whichfield . '-remote' ] : '';

		// @insane: the escaping of the scalars in the foreach and calls to get_field_id and get_field name are entirely unneeded, but required by VIP

		$list = '';
		foreach ( (array) $bases as $k => $v )
		{
			$list .= '<option value="' . esc_attr( $k ) . '" ' . selected( $instance[ $whichfield ], $k, FALSE ) . '>' . esc_html( $v ) . '</option>';
		}// end foreach

		$return = '
		<p>
			<label for="' . esc_attr( $this->get_field_id( $whichfield ) ) . '">Base URL</label>
			<select name="' . esc_attr( $this->get_field_name( $whichfield ) ) .'" id="' . esc_attr( $this->get_field_id( $whichfield ) ) .'" class="widefat">'. $list . '</select>
			<br />
			<small>The base URL affects widget content and caching</small>
		</p>
		<p>
			<label for="' . esc_attr( $this->get_field_id( $whichfield . '-remote' ) ) . '">Remote Base URL</label>
			<input name="' . esc_attr( $this->get_field_name( $whichfield . '-remote' ) ) . '" id="' . esc_attr( $this->get_field_id( $whichfield .'-remote' ) ) . '" class="widefat" type="text" value="' . esc_url( $instance[ $whichfield .'-remote' ] ) . '">
		</p>';

		return $return;
	}// end control_base

	public function control_loadtime( $instance, $whichfield = 'loadtime' )
	{
		$loadtimes = apply_filters( 'wijax-loadtime', array(
			'onload' 	=> 'Load content immediately when page loads',
			'onscroll' 	=> 'Wait for user to scroll page to load content',
		) );

		$instance[ $whichfield ] = isset( $instance[ $whichfield ] ) ? $instance[ $whichfield ] : '';

		// @insane: the escaping of the scalars in the foreach and calls to get_field_id and get_field name are entirely unneeded, but required by VIP

		$list = '';
		foreach ( (array) $loadtimes as $k => $v )
		{
			$list .= '<option value="' . esc_attr( $k ) . '" ' . selected( $instance[ $whichfield ], $k, FALSE ) . '>' . esc_html( $v ) . '</option>';
		}// end foreach

		$return = '
		<p>
			<label for="' . esc_attr( $this->get_field_id( $whichfield ) ) . '">Loadtime</label>
			<select name="' . esc_attr( $this->get_field_name( $whichfield ) ) . '" id="' . esc_attr( $this->get_field_id( $whichfield ) ) . '" class="widefat">' . $list . '</select>
			<br />
			<small>Consider waiting to load content below the fold</small>
		</p>';

		return $return;
	}//end control_loadtime
}// end bCMS_Wijax_Widget
