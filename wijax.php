<?php
/**
 * Wijax class
 *
 */
class bSuite_Wijax {
	var $ep_name = 'wijax';
	var $salt = '';
	var $allow_plaintext = TRUE;

	function bSuite_Wijax()
	{
		global $bsuite;

		$this->path_web = is_object( $bsuite ) ? $bsuite->path_web : get_template_directory_uri();

		add_action( 'init', array( &$this, 'init' ));
		add_action( 'widgets_init', array( &$this , 'widgets_init' ) , 11 );
		add_filter( 'query_vars', array( &$this, 'add_query_var' ));
	}

	function init()
	{

		add_rewrite_endpoint( $this->ep_name , EP_ALL );
		add_filter( 'request' , array( &$this, 'request' ));

		if( ! is_admin())
		{
			wp_register_script( 'waypoints', $this->path_web . '/components/js/waypoints.min.js', array('jquery'), '1' );
			wp_enqueue_script( 'waypoints' );
			add_filter( 'print_footer_scripts', array( &$this, 'print_js' ));
		}
	}

	function add_query_var( $qvars )
	{
		$qvars[] = $this->ep_name;
		return $qvars;
	}

	function normalize_url( $url , $local = true )
	{
		if( $local )
		{
			// trim the host component from the given url
			$home_path = parse_url( home_url() , PHP_URL_PATH );
			$home_host = str_replace( $home_path , '' , home_url() ); // easier to get the host by subtraction than reconstructing it from parse_url()
			$path = '/'. ltrim( str_replace( $home_host , '' , $url ) , '/' );
		}
		else
		{
			$path_parts = parse_url( $url );
			$path = $path_parts['path'] . ( isset( $path_parts['query'] ) ? '?'. $path_parts['query'] : '' );
		}
		return $path;
	}

	function varname( $url = '' , $local = true )
	{
		if( $url )
		{
			$base = $this->normalize_url( $url , $local );
		}
		else
		{
			$base = $_SERVER['REQUEST_URI'];
		}

		return 'wijax_'. md5( $base . date('W') . $this->salt );
	}

	function encoded_name( $name )
	{
		return md5( $name . date('W') . $this->salt );
	}

	function widgets_init()
	{
		register_widget( 'Wijax_Widget' );

		register_sidebar( array(
			'name' => __( 'Wijax Widgets', 'Bsuite' ),
			'id' => 'wijax-area',
			'description' => __( 'Place widgets here to configure them for lazy loading using the Wijax widget.', 'Bsuite' ),
		) );
	}

	public function request( $request )
	{
		if( isset( $request[ $this->ep_name ] ))
		{
			add_filter( 'template_redirect' , array( &$this, 'redirect' ), 0 );
			define( 'IS_WIJAX' , TRUE );
			do_action( 'do_wijax' );
		}

		return $request;
	}

	function redirect()
	{
		global $postloops, $wp_registered_widgets;

		$requested_widgets = array_filter( array_map( 'trim' , (array) explode( ',' , get_query_var( $this->ep_name ) )));

		if( 1 > count( $requested_widgets ))
			die;

		// establish the available actions
		$actions = array();

		// get the available postloop templates
		if( is_object( $postloops ) && ( $postloop_templates = $postloops->get_templates( 'post' )))
		{
			foreach( $postloop_templates as $k => $v )
				$actions[ $this->encoded_name( 'templates-post-'. trim( $k , '.php' )) ] = (object ) array( 'key' => $k , 'type' => 'postloop');
		}

		// get the available widgets in the wijax area
		if( ( $widgets = wp_get_sidebars_widgets() ) && is_array( $widgets['wijax-area'] ))
		{
			foreach( $widgets['wijax-area'] as $k)
				$actions[ $this->encoded_name( $k ) ] = (object ) array( 'key' => $k , 'type' => 'widget');
		}

		// filter to allow lazy-loading of widgets without them being in the wijax area
		$actions = apply_filters( 'wijax-actions', $actions );

		foreach( $requested_widgets as $key )
		{
			// try the requested key against the md5 list
			if( ! isset( $actions[ $key ] ))
			{
				// allow plaintext queries, md5 the key and try that against the list
				$key = $this->encoded_name( $key );
				if(( ! $this->allow_plaintext ) || ( ! isset( $actions[ $key ] )))
					die;
			}

			// start output buffering
			ob_start();

			// identify and execute the matching action
			$do = 'do_'. $actions[ $key ]->type;
			$this->$do( $actions[ $key ]->key );

			// close the output buffer and send it to the client as a JS var
			Wijax_Encode::out( ob_get_clean() , $this->varname() );

			die; // only doing one widget now
		}//end foreach
		die;
	}

	function do_postloop( $template )
	{
		global $postloops, $wp_query;

		if( ( ! is_object( $postloops )) || ( ! is_single() ))
			return FALSE;

		$postloop_templates = $postloops->get_templates( 'post' );

		$ourposts = &$wp_query;
		if( $ourposts->have_posts() )
		{
			while( $ourposts->have_posts() )
			{
				$ourposts->the_post();
				 @include $postloop_templates[ $template ]['fullpath'];
			}
		}
	}

	function do_widget( $key )
	{
		global $wp_registered_widgets;

		if( ! $widget_data = $wp_registered_widgets[ $key ] )
			return;

		preg_match( '/\-([0-9]+)$/' , $key , $instance_number );
		$instance_number = absint( $instance_number[1] );
		if( ! $instance_number )
			return;

		$widget_data['widget'] = $key;

		// Substitute HTML id and class attributes into before_widget
		$classname_ = '';
		foreach ( (array) $wp_registered_widgets[ $key ]['classname'] as $cn ) {
			if ( is_string($cn) )
				$classname_ .= '_' . $cn;
			elseif ( is_object($cn) )
				$classname_ .= '_' . get_class($cn);
		}
		$classname_ = ltrim($classname_, '_');

		$widget_data['params'][0] = array(
			'name' => $wp_registered_widgets[ $key ]['name'],
			'id' => $key,
			'before_widget' => '<span id="widget-%1$s" class="wijax-widgetclasses '. $classname_ .' %2$s"></span>'."\n",
			'after_widget'  => '',
			'before_title'  => '<span class="wijax-widgettitle">',
			'after_title'   => "</span>\n",
			'widget_id' => $key,
			'widget_name' => $wp_registered_widgets[ $key ]['name'],
		);

//print_r( $widget_data['callback'][0]->number );
//print_r( $widget_data['params'][0] );

		$widget_data['params'][1] = array(
			'number' => absint( $instance_number ),
		);

		$widget_data['params'][0]['before_widget'] = sprintf($widget_data['params'][0]['before_widget'], $widget_data['widget'], ( isset( $widget_data['size'] ) ? 'grid_' . $widget_data['size'] .' ' : '' ) .$widget_data['class'] . ' ' . $widget_data['id'] . ' ' . $extra_classes);

		call_user_func_array( $widget_data['callback'], $widget_data['params'] );
	}

	function print_js(){
?>
<script type="text/javascript">	
	var wijax_widget_reload = true;	
	;(function($){
		$.fn.myWijaxLoader = function()
		{
			var widget_source = $(this).attr('href');
			var widget_area = $(this).parent();
			var widget_parent = $(this).parent().parent();
			var widget_wrapper = $(this).parents('.widget_wijax');
			var opts = $.parseJSON( $(widget_parent).find('span.wijax-opts').text() );
			var varname = opts.varname;
			var title_before = unescape( opts.title_before );
			var title_after = unescape( opts.title_after );
			
			$.ajax({ 
				url: widget_source, 
				dataType: 'script',
				cache: true,
				success: function() {
					// insert the fetched markup
					$( widget_area ).replaceWith( window[varname] );
			
					// find the widget title, add it to the DOM, remove the temp span
					var widget_title_el = $(widget_parent).find('span.wijax-widgettitle');
					var widget_title = $(widget_title_el).text();

					//don't set a widget title div if there is no title text
					if(widget_title)
						$( widget_parent ).prepend(title_before + widget_title + title_after);
					
					$(widget_title_el).remove();
			
					// find and set the widget ID and classes
					var widget_attr_el = $( widget_parent ).find( 'span.wijax-widgetclasses' );
					var widget_id = $( widget_attr_el ).attr( 'id' );
					var widget_classes = $( widget_attr_el ).attr( 'class' );
					$( widget_wrapper ).attr( 'id' , widget_id );
					$( widget_wrapper ).addClass( widget_classes );
					$( widget_wrapper ).removeClass( 'widget_wijax' );
					$(widget_attr_el).remove();
				}
			});
		};

		// do the onload widgets
		$(window).load(function(){
			// find and load the widgets
			$('a.wijax-source.wijax-onload').each(function() {
				$(this).myWijaxLoader();
			});	

			// if we've already scrolled or there is a hash in the url,
			// fire the scroll event and get the excerpts and widgets	
			if( ( document.location.hash ) || ( window.pageYOffset > 25 ) || ( document.body.scrollTop > 25 ) )
				$( document ).trigger( 'scroll' );
		});	

		// do the onscroll actions
		$(document).one('scroll', function(){
			// widgets
			$('a.wijax-source.wijax-onscroll').each(function() {
				$(this).myWijaxLoader();
			});
		});
	})(jQuery);
</script>
<?php
	}

} //end bSuite_Wijax

// initialize that class
global $mywijax;
$mywijax = new bSuite_Wijax();

/**
 * Wijax widget class
 *
 */
class Wijax_Widget extends WP_Widget
{

	function Wijax_Widget()
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
			<img src="<?php echo $mywijax->path_web  .'/components/img/loading-gray.gif'; ?>" alt="loading external resource" />
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
		foreach( (array) $sidebars_widgets['wijax-area'] as $item )
		{
			if( $number == $this->number )
				continue;

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

		foreach( (array) $loadtimes as $k => $v )
			$list .= '<option value="'. $k .'" '. selected( $instance[ $whichfield ] , $k , FALSE ) .'>'. $v .'</option>';

		return '<p><label for="'. $this->get_field_id( $whichfield ) .'">Loadtime</label><select name="'. $this->get_field_name( $whichfield ) .'" id="'. $this->get_field_id( $whichfield ) .'" class="widefat">'. $list . '</select><br /><small>Consider waiting to load content below the fold</small></p>';
	}
}// end Wijax_Widget



class Wijax_Encode
{
	public static function out( $content , $varname )
	{
		if ( function_exists( 'status_header' ) )
			status_header( 200 );
		header('X-Robots-Tag: noindex' , TRUE );
		header('Content-type: text/javascript');
		echo self::encode( $content , $varname );
	}//end out

	public static function encode( $content , $varname )
	{
		//create a variable to put the page content into
		$output='var varname = "'. $varname .'"; window[varname]='. json_encode( $content ) .";\n";

		return $output;
	}//end out
}//end class Channel


function is_wijax()
{
	return defined( 'IS_WIJAX' ) && IS_WIJAX;
}