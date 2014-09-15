<?php
/**
 * bCMS_Wijax class
 *
 */
class bCMS_Wijax
{
	var $ep_name_ajax = 'wijax';
	var $ep_name_iframe = 'wiframe';
	var $ep_name_iframe_source = 'bcms-wiframe';
	var $salt = '';
	var $allow_plaintext = TRUE;

	function __construct()
	{
		$this->path_web = plugins_url( plugin_basename( dirname( __FILE__ )));

		add_action( 'init', array( $this, 'init' ));
		add_action( 'widgets_init', array( $this , 'widgets_init' ) , 11 );
		add_filter( 'query_vars', array( $this, 'add_query_var' ));
	}

	function init()
	{

		add_rewrite_endpoint( $this->ep_name_ajax , EP_ALL );
		add_rewrite_endpoint( $this->ep_name_iframe , EP_ALL );
		add_rewrite_endpoint( $this->ep_name_iframe_source , EP_ALL );

		add_filter( 'request' , array( $this, 'request' ));

		if ( ! is_admin() )
		{
			wp_register_script( 'waypoints', $this->path_web . '/js/waypoints.min.js', array('jquery'), '1' );
			wp_enqueue_script( 'waypoints' );
			add_filter( 'print_footer_scripts', array( $this, 'print_js' ), 10, 1 );
		}
	}

	function add_query_var( $qvars )
	{
		$qvars[] = $this->ep_name_ajax;
		$qvars[] = $this->ep_name_iframe;
		return $qvars;
	}

	function normalize_url( $url , $local = true )
	{
		if ( $local )
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
		if ( $url )
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
		register_widget( 'bCMS_Wijax_Widget' );

		register_sidebar( array(
			'name' => __( 'Wijax Widgets', 'bCMS' ),
			'id' => 'wijax-area',
			'description' => __( 'Place widgets here to configure them for lazy loading using the Wijax widget.', 'bCMS' ),
		) );
	}

	public function request( $request )
	{
		// is this a Wijax request?
		if ( isset( $request[ $this->ep_name_ajax ] ))
		{
			$this->method = $this->ep_name_ajax;
		}

		// or is this a Wiframe request?
		elseif ( isset( $request[ $this->ep_name_iframe ] ))
		{
			include_once dirname( __FILE__ ) . '/class-bcms-wiframe-encode.php';
			$this->method = $this->ep_name_iframe;
		}

		// or is this a Wiframe source request?
		elseif ( isset( $request[ $this->ep_name_iframe_source ] ))
		{
			$js = file_get_contents( dirname( __FILE__ ) . '/js/bcms-wiframe.js' );

			header('Content-Type: application/javascript');
			echo $js;
			die;
		}

		// or should I just give up?
		else
		{
			return $request;
		}

		// this is a Wijax or Wiframe request, let's handle it
		add_filter( 'template_redirect' , array( $this, 'redirect' ), 0 );
		define( 'IS_WIJAX' , TRUE );
		do_action( 'do_' . $this->method );

		return $request;
	}

	function redirect()
	{
		global $wp_registered_widgets;

		$requested_widgets = array_filter( array_map( 'trim' , (array) explode( ',' , get_query_var( $this->method ) )));

		if ( 1 > count( $requested_widgets ))
		{
			die;
		}

		// establish the available actions
		$actions = array();

		// get the available postloop templates
		if ( $postloop_templates = bcms_postloop()->get_templates( 'post' ) )
		{
			foreach ( $postloop_templates as $k => $v )
			{
				$actions[ $this->encoded_name( 'templates-post-'. basename( $k , '.php' ) ) ] = (object ) array( 'key' => $k , 'type' => 'postloop');
			}
		}

		// get the available widgets in the wijax area
		if ( ( $widgets = wp_get_sidebars_widgets() ) && is_array( $widgets['wijax-area'] ))
		{
			foreach ( $widgets['wijax-area'] as $k )
			{
				$actions[ $this->encoded_name( $k ) ] = (object ) array( 'key' => $k , 'type' => 'widget');
			}
		}

		// filter to allow lazy-loading of widgets without them being in the wijax area
		$actions = apply_filters( 'wijax-actions', $actions );

		foreach ( $requested_widgets as $key )
		{
			// try the requested key against the md5 list
			if ( ! isset( $actions[ $key ] ))
			{
				// allow plaintext queries when handling wiframe queries or if allowed in the config
				if ( $this->method == $this->ep_name_iframe || $this->allow_plaintext )
				{
					// md5 the key and try that against the list
					$key = $this->encoded_name( $key );
					if ( ! isset( $actions[ $key ] ))
					{
						die;
					}
				}
				else
				{
					die;
				}
			}

			// identify and execute the matching action
			$do = 'do_'. $actions[ $key ]->type;

			// select a handler and send the contents of the output buffer to the client
			switch( $this->method )
			{
				case $this->ep_name_ajax:
					// start output buffering
					ob_start();

					$this->$do( $actions[ $key ]->key );

					Wijax_Encode::out( ob_get_clean() , $this->varname() );
					break;
				case $this->ep_name_iframe:
					BCMS_Wiframe_Encode::out( array( $this, $do ), $actions[ $key ]->key );
					break;
			}

			die; // only doing one widget now
		}//end foreach
		die;
	}

	function do_postloop( $template )
	{
		global $wp_query;

		$postloop_templates = bcms_postloop()->get_templates( 'post' );

		$ourposts = $wp_query;
		if ( $ourposts->have_posts() )
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

		if ( ! $widget_data = $wp_registered_widgets[ $key ] )
			return;

		preg_match( '/\-([0-9]+)$/' , $key , $instance_number );
		$instance_number = absint( $instance_number[1] );
		if ( ! $instance_number )
		{
			return;
		}

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

		$widget_data['params'][1] = array(
			'number' => absint( $instance_number ),
		);

		$arg2 = isset( $widget_data['size'] ) ? 'grid_' . $widget_data['size'] . ' ' : '';
		$arg2 .= isset( $widget_data['class'] ) ? $widget_data['class'] . ' ' : '';
		$arg2 .= isset( $widget_data['id'] ) ? $widget_data['id'] . ' ' : '';
		$arg2 .= isset( $extra_classes ) ? $extra_classes : '';

		$widget_data['params'][0]['before_widget'] = sprintf( $widget_data['params'][0]['before_widget'], $widget_data['widget'], $arg2 );

		call_user_func_array( $widget_data['callback'], $widget_data['params'] );
	}

	function print_js( $finish_print ){
?>
<script type="text/javascript">
	var wijax_widget_reload = true;
	var wijax_queue = {
		max_allowed_requests: 3,
		timer: false,
		processing: [],
		processed: [],
		queued: [],
		process: function( set_timer ) {
			if ( false !== set_timer ) {
				set_timer = true;
			}//end if

			// allow X wijax requests to process
			while (
				wijax_queue.processing.length < wijax_queue.max_allowed_requests
				&& wijax_queue.queued.length > 0
			) {
				var item = wijax_queue.queued.shift();

				jQuery.ajax( item );

				wijax_queue.processing.push( item );
			}//end while

			if ( ! wijax_queue.queued.length ) {
				wijax_queue.timer = false;
				return;
			}//end if

			if ( set_timer ) {
				wijax_queue.timer = setTimeout( wijax_queue.process, 300 );
			}//end if
		},// end process
		mark_as_processed: function( url ) {
			// find the wijax request that completed
			for ( var i in wijax_queue.processing ) {
				// if the URLs don't match, then this wasn't the request that just completed
				if ( wijax_queue.processing[ i ].url != url ) {
					continue;
				}//end if

				// stick the wijax request into the processed array
				wijax_queue.processed.push( Object.create( wijax_queue.processing[ i ] ) );

				// remove it from the processing array
				wijax_queue.processing.splice( i, 1 );
			}//end for
		}
	};

	;(function($){
		$.fn.myWijaxLoader = function() {
			var widget_source = $(this).attr('href');
			var $widget_area = $(this).closest('.wijax-loading');
			var $widget_parent = $widget_area.parent();
			var opts = $.parseJSON( $widget_parent.find('span.wijax-opts').text() );
			var varname = opts.varname;
			var title_before = unescape( opts.title_before );
			var title_after = unescape( opts.title_after );

			wijax_queue.queued.push({
				url: widget_source,
				dataType: 'script',
				cache: true,
				complete: function() {
					wijax_queue.mark_as_processed( widget_source );
				},
				success: function() {
					// insert the fetched markup
					$( $widget_area ).replaceWith( window[varname] );

					// find the widget title, add it to the DOM, remove the temp span
					var $widget_title_el = $widget_parent.find('span.wijax-widgettitle');
					var widget_title = $widget_title_el.text();

					// don't set a widget title div if there is no title text
					if (widget_title)
						$widget_parent.prepend(title_before + widget_title + title_after);

					$widget_title_el.remove();

					// find and set the widget ID and classes
					var $widget_attr_el = $widget_parent.find( 'span.wijax-widgetclasses' );
					var widget_id = $widget_attr_el.attr( 'id' );
					var widget_classes = $widget_attr_el.attr( 'class' );
					$widget_parent.attr( 'id' , widget_id );
					$widget_parent.addClass( widget_classes );
					$widget_parent.removeClass( 'widget_wijax' );
					$widget_attr_el.remove();

					// trigger an event in case anything else needs to know when this widget has loaded
					$( document ).trigger( 'wijax-loaded', [ widget_id ] );
				}
			});

			// for each queuing of a wijax request, pass in a boolean that indicates whether or not
			// to start a new setTimeout
			wijax_queue.process( ! wijax_queue.timer );
		};

		// do the onload widgets
		$(window).load(function(){
			// find and load the widgets
			$('a.wijax-source.wijax-onload').each(function() {
				$(this).myWijaxLoader();
			});

			// if we've already scrolled or there is a hash in the url,
			// fire the scroll event and get the excerpts and widgets
			if ( ( document.location.hash ) || ( window.pageYOffset > 25 ) || ( document.body.scrollTop > 25 ) )
				$( document ).trigger( 'scroll' );
		});

		// do the onscroll actions
		$(window).one('scroll', function(){
			// widgets
			$('a.wijax-source.wijax-onscroll').each(function() {
				$(this).myWijaxLoader();
			});
		});
	})(jQuery);
</script>
<?php
		return $finish_print;
	}

} //end bCMS_Wijax

class Wijax_Encode
{
	public static function out( $content , $varname )
	{
		if ( function_exists( 'status_header' ) )
			status_header( 200 );
		header('X-Robots-Tag: noindex, follow', TRUE );
		header('Content-Type: text/javascript');
		echo self::encode( $content , $varname );
	}//end out

	public static function encode( $content , $varname )
	{
		//create a variable to put the page content into
		$output='var varname = "'. $varname .'"; window[varname]='. json_encode( $content ) .";\n";

		return $output;
	}//end out
}//end class Channel

/**
 * Singleton
 */
function bcms_wijax()
{
	global $mywijax;

	if ( ! $mywijax )
	{
		$mywijax = new bCMS_Wijax();
	}//end if

	return $mywijax;
}//end bcms_wijax
