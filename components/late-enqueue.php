<?php


function bcms_late_enqueue_script( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false )
{
	global $wp_scripts;

	// enqueue the named script
	wp_enqueue_script( $handle , $src , $deps , $ver , $in_footer );

	// resolve dependencies and place everything in the array of items to put in the footer
	$to_do_orig = (array) $wp_scripts->to_do;
	$wp_scripts->all_deps( array( $handle ));
	$wp_scripts->in_footer = array_merge( (array) $wp_scripts->in_footer , (array) array_diff( (array) $wp_scripts->to_do , $to_do_orig ) );
}

function bcms_late_enqueue_style( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' )
{
	global $wp_styles;

	// enqueue the named script
	wp_enqueue_style( $handle, $src, $deps, $ver, $media );

	// resolve dependencies and place everything in the array of items to put in the footer
	$to_do_orig = (array) $wp_styles->to_do;
	$wp_styles->all_deps( array( $handle ));
	$wp_styles->in_footer = array_merge( (array) $wp_styles->in_footer , (array) array_diff( (array) $wp_styles->to_do , $to_do_orig ) );

	add_filter( 'print_footer_scripts', 'bcms_print_late_styles', 10, 1 );
}

function bcms_print_late_styles( $finish_print )
{
	global $wp_styles;

	$tags = array();
	foreach( (array) $wp_styles->to_do as $handle )
	{
		if ( isset($wp_styles->registered[$handle]->args) )
			$media = esc_attr( $wp_styles->registered[$handle]->args );
		else
			$media = 'all';

		$href = $wp_styles->_css_href( $wp_styles->registered[$handle]->src, $ver, $handle );
		$rel = isset($wp_styles->registered[$handle]->extra['alt']) && $wp_styles->registered[$handle]->extra['alt'] ? 'alternate stylesheet' : 'stylesheet';
		$title = isset($wp_styles->registered[$handle]->extra['title']) ? "title='" . esc_attr( $wp_styles->registered[$handle]->extra['title'] ) . "'" : '';

		$tags[] = "$('head').append(\"<link rel='$rel' id='$handle-css' $title href='$href' type='text/css' media='$media' />\");\n";
	}		

	if( ! array( $tags ))
		return $finish_print;

?>
<script type="text/javascript">	
	;(function($){
		$(window).load(function(){
			// print the style includes
<?php foreach( $tags as $tag )
{
	echo "			$tag";
} ?>

		});
	})(jQuery);
</script>
<?php
	return $finish_print;
}

