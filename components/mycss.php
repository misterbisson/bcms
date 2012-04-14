<?php
class bCMS_CSS
{

	function __construct()
	{
		if ( isset( $_GET['bsuite_mycss'] ) && ! is_admin() )
			add_action( 'init', array( &$this, 'bsuite_mycss_printstyles' ));
	}

	function init()
	{
		if( get_option( 'bsuite_mycss_replacethemecss' ) && ! is_admin() )
		{
			add_filter( 'stylesheet_uri', array( $this, 'bsuite_mycss_hidesstylesheet' ), 11 );
			add_filter( 'locale_stylesheet_uri', array( $this, 'bsuite_mycss_hidesstylesheet' ), 11 );
		}

		if(( get_option( 'bsuite_mycss' ) || get_option( 'bsuite_mycss_replacethemecss' )) && ! is_admin() )
		{
			wp_register_style( 'bsuite-mycss', get_option('home') .'/index.php?bsuite_mycss=print' );
			wp_enqueue_style( 'bsuite-mycss' );
		}

		if( 0 < get_option( 'bsuite_mycss_maxwidth' ))
			$GLOBALS['content_width'] = absint( get_option( 'bsuite_mycss_maxwidth' ));
		if( !isset( $GLOBALS['content_width'] ))
			$GLOBALS['content_width'] = 500;
	}

	function admin_menu_hook()
	{
		// the custom css page
		add_theme_page( __('Custom CSS'), __('Custom CSS'), 'switch_themes', plugin_basename( dirname( __FILE__ )) .'/ui_mycss.php' );
	}

	function bsuite_mycss_printstyles()
	{
		header( 'Content-Type: text/css; charset=' . get_option( 'blog_charset' ));

		echo get_option( 'bsuite_mycss' );
		die;
	}

	function bsuite_mycss_hidesstylesheet( $input )
	{
		return $this->path_web . '/css/empty.css';
	}

	function mycss_sanitize( $input )
	{
		$input = wp_filter_nohtml_kses( $input );
		$input = preg_replace('/\/\*.*?\*\//sm', '', $input); // strip comments

		$safecss = '';
		foreach( explode( "\n", $input ) as $line )
			$safecss .= $this->mycss_cleanline( $line );

		return $safecss;
	}

	function mycss_cleanline( $input )
	{
		$evil = 0;

		$filtered = wp_kses_decode_entities( $input );
		$filtered = preg_replace('/expression[^\(]?\(.*?\)/i', '', $filtered, -1, $flag ); // strip expressions
		if( $flag ) $evil++;

		$filtered = preg_replace('/@import/i', '', $filtered, -1, $flag ); // strip @import
		if( $flag ) $evil++;

		$filtered = preg_replace('/about:/i', '', $filtered, -1, $flag ); // strip about: uris
		if( $flag ) $evil++;

		$filtered = preg_replace_callback('/([\w]*?):\/\//si', array( $this, 'mycss_cleanuri' ), $filtered, -1, $flag ); // strip non http uris
		if( $flag ) $evil++;

		return $evil ? $filtered : $input;
	}

	function mycss_cleanuri( $input )
	{
		if( ! preg_match( '/^http:\/\//', $input[0] ))
			return '';

		return $input[0];
	}

}