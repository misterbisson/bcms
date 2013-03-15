<?php
/**
 * bCMS_Wijax_Widget class
 *
 */
class bCMS_Wijax_Widget extends WP_Widget
{

	function __construct()
	{
		$widget_ops = array('classname' => 'widget_wijax', 'description' => __( 'Lazy load widgets after DOMDocumentReady') );
		$this->WP_Widget('wijax', __('Wijax Widget Lazy Loader'), $widget_ops);

		add_filter( 'wijax-base-current' , array( $this , 'base_current' ) , 5 );
		add_filter( 'wijax-base-home' , array( $this , 'base_home' ) , 5 );
	}

	function widget( $args, $instance )
	{
		global $mywijax;

		extract( $args );

		if( 'remote' != $instance['base'] )
		{
			$base = apply_filters( 'wijax-base-'. $instance['base'] , '' );
			if( ! $base )
				return;
			$wijax_source = $base . $mywijax->encoded_name( $instance['widget'] );
			$wijax_varname = $mywijax->varname( $wijax_source );
		}
		else
		{
			$wijax_source = $instance['base-remote'] . $mywijax->encoded_name( $instance['widget-custom'] );
			$wijax_varname = $mywijax->varname( $wijax_source , FALSE );
		}

		echo $before_widget;

		preg_match( '/<([\S]*)/' , $before_title , $title_element );
		$title_element = trim( (string) $title_element[1] , '<>');

		preg_match( '/class.*?=.*?(\'|")(.+?)(\'|")/' , $before_title , $title_class );
		$title_class = (string) $title_class[2];

		$loadtime = ($instance['loadtime']) ? $instance['loadtime'] : 'onload';
?>
		<span class="wijax-loading">
			<img src="<?php echo $mywijax->path_web  .'/img/loading-gray.gif'; ?>" alt="loading external resource" />
			<a href="<?php echo $wijax_source; ?>" class="wijax-source <?php echo 'wijax-' . $loadtime;?>" rel="nofollow"></a>
			<span class="wijax-opts" style="display: none;">
				<?php echo json_encode( array( 
					'source' => $wijax_source ,
					'varname' => $wijax_varname , 
					'title_element' => $title_element ,
					'title_class' => $title_class ,
					'title_before' => rawurlencode( $before_title ),
					'title_after' => rawurlencode( $after_title ),
				)); ?>
			</span>
		</span>
<?php
		echo $after_widget;
	}

	function base_home()
	{

		return trailingslashit( home_url() ) .'wijax/';
	}

	function base_current()
	{

		$home_path = parse_url( home_url() , PHP_URL_PATH );
		return esc_url_raw( trailingslashit( home_url() . str_replace( $home_path , '' , parse_url( $_SERVER['REQUEST_URI'] , PHP_URL_PATH ))) .'wijax/' );
	}

	function update( $new_instance, $old_instance )
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['widget'] = sanitize_title( $new_instance['widget'] );
		$instance['widget-custom'] = sanitize_title( $new_instance['widget-custom'] );
		$instance['base'] = sanitize_title( $new_instance['base'] );
		$instance['base-remote'] = esc_url_raw( $new_instance['base-remote'] );
		$instance['loadtime'] = in_array( $new_instance['loadtime'], array( 'onload', 'onscroll') ) ? $new_instance['loadtime'] : 'onscroll';

		return $instance;
	}

	function form( $instance )
	{
		//Defaults
		$instance = wp_parse_args( (array) $instance, 
			array( 
				'title' => '', 
				'homelink' => get_option('blogname'),
				'maxchars' => 35,
			)
		);

		$title = esc_attr( $instance['title'] );
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /><br />
			<small>For convenience, not shown publicly</small
		</p>
<?php
		echo $this->control_widgets( $instance );
		echo $this->control_base( $instance );
		echo $this->control_loadtime( $instance );
	}

	function control_widgets( $instance , $whichfield = 'widget' )
	{
		// get the available widgets
		$sidebars_widgets = wp_get_sidebars_widgets();
		$list = '';
		$instance[ $whichfield ] = isset( $instance[ $whichfield ] ) ? $instance[ $whichfield ] : '';
		$instance[ $whichfield . '-custom' ] = isset( $instance[ $whichfield . '-custom' ] ) ? $instance[ $whichfield . '-custom' ] : '';

		foreach( (array) $sidebars_widgets['wijax-area'] as $item )
		{
			$list .= '<option value="'. $item .'" '. selected( $instance[ $whichfield ] , $item , FALSE ) .'>'. $item .'</option>';
		}
		$list .= '<option value="custom" '. selected( $instance[ $whichfield ] , 'custom' , FALSE ) .'>Custom widget</option>';

		return '<p><label for="'. $this->get_field_id( $whichfield ) .'">Widget</label><select name="'. $this->get_field_name( $whichfield ) .'" id="'. $this->get_field_id( $whichfield ) .'" class="widefat">'. $list . '</select></p><p><label for="'. $this->get_field_id( $whichfield .'-custom' ) .'">Custom Widget Name</label><input name="'. $this->get_field_name( $whichfield .'-custom' ) .'" id="'. $this->get_field_id( $whichfield .'-custom' ) .'" class="widefat" type="text" value="'. sanitize_title( $instance[ $whichfield .'-custom' ] ).'"></p>';
	}

	function control_base( $instance , $whichfield = 'base' )
	{

		$bases = apply_filters( 'wijax-bases' , array(
			'current' => 'The currently requested URL',
			'home' => 'The blog home URL',
			'remote' => 'Remote base URL',
		));

		$instance[ $whichfield ] = isset( $instance[ $whichfield ] ) ? $instance[ $whichfield ] : '';
		$instance[ $whichfield . '-remote' ] = isset( $instance[ $whichfield . '-remote' ] ) ? $instance[ $whichfield . '-remote' ] : '';

		$list = '';
		foreach( (array) $bases as $k => $v )
			$list .= '<option value="'. $k .'" '. selected( $instance[ $whichfield ] , $k , FALSE ) .'>'. $v .'</option>';

		return '<p><label for="'. $this->get_field_id( $whichfield ) .'">Base URL</label><select name="'. $this->get_field_name( $whichfield ) .'" id="'. $this->get_field_id( $whichfield ) .'" class="widefat">'. $list . '</select><br /><small>The base URL affects widget content and caching</small></p><p><label for="'. $this->get_field_id( $whichfield .'-remote' ) .'">Remote Base URL</label><input name="'. $this->get_field_name( $whichfield .'-remote' ) .'" id="'. $this->get_field_id( $whichfield .'-remote' ) .'" class="widefat" type="text" value="'. esc_url( $instance[ $whichfield .'-remote' ] ).'"></p>';
	}

	function control_loadtime( $instance , $whichfield = 'loadtime' )
	{

		$loadtimes = apply_filters( 'wijax-loadtime' , array(
			'onload' 	=> 'Load content immediately when page loads',
			'onscroll' 	=> 'Wait for user to scroll page to load content',
		));

		$instance[ $whichfield ] = isset( $instance[ $whichfield ] ) ? $instance[ $whichfield ] : '';

		$list = '';
		foreach( (array) $loadtimes as $k => $v )
			$list .= '<option value="'. $k .'" '. selected( $instance[ $whichfield ] , $k , FALSE ) .'>'. $v .'</option>';

		return '<p><label for="'. $this->get_field_id( $whichfield ) .'">Loadtime</label><select name="'. $this->get_field_name( $whichfield ) .'" id="'. $this->get_field_id( $whichfield ) .'" class="widefat">'. $list . '</select><br /><small>Consider waiting to load content below the fold</small></p>';
	}
}// end bCMS_Wijax_Widget