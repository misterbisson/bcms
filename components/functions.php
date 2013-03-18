<?php

function bcms_get_new_postloop_scroller( $args = '' )
{
	require_once __DIR__ . '/class-bcms-postloop-scroller.php';
	return new bCMS_PostLoop_Scroller( $args );
}//end bcms_get_new_postloop_scroller

function is_wijax()
{
	return defined( 'IS_WIJAX' ) && IS_WIJAX;
}//end is_wijax

function bcms_widgets_init()
{
	register_widget( 'bCMS_PostLoop_Widget' );

	register_widget( 'bSuite_Widget_CategoryDescription' );

	register_widget( 'bSuite_Widget_Crumbs' );

	register_widget( 'bSuite_Widget_Pagednav' );

	unregister_widget('WP_Widget_Pages');
	register_widget( 'bSuite_Widget_Pages' );
}
