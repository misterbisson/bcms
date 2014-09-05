<?php
/*
Plugin Name: bCMS: bSuite CMS Tools
Plugin URI: http://wordpress.org/plugins/bcms/
Description: Make WordPress a better CMS. Create a post loop in a widget. Lazy load widgets. More shortcodes. More good.
Version: 5.3
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/

// the admin menu
if ( is_admin() )
{
	require_once dirname( __FILE__ ) . '/admin.php';
}

// include the core components
require_once( dirname( __FILE__ ) .'/components/class-bcms-postloop.php' );
bcms_postloop();
require_once( dirname( __FILE__ ) .'/components/class-bcms-wijax.php' );
bcms_wijax();
require_once( dirname( __FILE__ ) .'/components/class-bcms-postloop-widget.php' );
require_once( dirname( __FILE__ ) .'/components/class-bcms-wijax-widget.php' );
require_once( dirname( __FILE__ ) .'/components/functions.php' );
require_once( dirname( __FILE__ ) .'/components/late-enqueue.php' );
add_action( 'widgets_init', 'bcms_widgets_init', 1 );


// override the URL path by setting it in the object as such:
// $postloops->path_web = 

// include the CMS convenience features
require_once( dirname( __FILE__ ) .'/components/cms-widgets.php' );
require_once( dirname( __FILE__ ) .'/components/innerindex.php');
require_once( dirname( __FILE__ ) .'/components/listchildren.php' );
require_once( dirname( __FILE__ ) .'/components/privacy.php' );

// optionally include the mysql-based full text indexing
if( get_option( 'bcms_searchsmart' ))
{
	require_once( dirname( __FILE__ ) .'/components/class-bcms-search.php' );
	bcms_search();
}
