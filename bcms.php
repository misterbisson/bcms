<?php
/*
Plugin Name: bCMS: bSuite CMS Tools
Plugin URI: http://maisonbisson.com/bsuite/
Version: a1
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/

if ( is_admin() )
	require_once dirname( __FILE__ ) . '/admin.php';

// include required components
require_once( dirname( __FILE__ ) .'/components/cms-widgets.php' );
require_once( dirname( __FILE__ ) .'/components/wijax.php' );
require_once( dirname( __FILE__ ) .'/components/innerindex.php');
require_once( dirname( __FILE__ ) .'/components/listchildren.php' );
require_once( dirname( __FILE__ ) .'/components/privacy.php' );

// full text indexing in mysql
if( get_option('bcms_searchsmart'))
	require_once( dirname( __FILE__ ) .'/components/search.php' );
