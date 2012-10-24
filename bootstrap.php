<?php
/*
Plugin Name: bCMS: bSuite CMS Tools
Plugin URI: http://maisonbisson.com/bsuite/
Description: Make WordPress a better CMS. Create a post loop in a widget. Lazy load widgets. More shortcodes. More good.
Version: 5.0
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/

// include the core components
require_once __DIR__ . '/components/postloops.php';
require_once __DIR__ . '/components/wijax.php';
require_once __DIR__ . '/components/late-enqueue.php';

// override the URL path by setting it in the object as such:
// $postloops->path_web =
$postloops->path_web = plugin_dir_url( __DIR__ . '/components/postloops.php' );
$mywijax->path_web   = $postloops->path_web;

// include the CMS convenience features
require_once __DIR__ . '/components/cms-widgets.php';
