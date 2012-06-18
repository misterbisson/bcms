<?php
/*
Plugin Name: bCMS: bSuite CMS Tools
Plugin URI: http://maisonbisson.com/bsuite/
Description: Make WordPress a better CMS. Create a post loop in a widget. Lazy load widgets. More shortcodes. More good.
Version: 5.0
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/

// the admin menu
if ( is_admin() )
	require_once dirname( __FILE__ ) . '/admin.php';

// include the core components
require_once( dirname( __FILE__ ) .'/components/postloops.php' );
require_once( dirname( __FILE__ ) .'/components/wijax.php' );
require_once( dirname( __FILE__ ) .'/components/late-enqueue.php' );

// override the URL path by setting it in the object as such:
// $postloops->path_web = 

// include the CMS convenience features
require_once( dirname( __FILE__ ) .'/components/child-posts.php' );
require_once( dirname( __FILE__ ) .'/components/cms-widgets.php' );
require_once( dirname( __FILE__ ) .'/components/innerindex.php');
require_once( dirname( __FILE__ ) .'/components/listchildren.php' );
require_once( dirname( __FILE__ ) .'/components/privacy.php' );

// optionally include the mysql-based full text indexing
if( get_option('bcms_searchsmart'))
	require_once( dirname( __FILE__ ) .'/components/search.php' );
