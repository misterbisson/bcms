<?php

bcms_get_new_postloop_scroller( array(
	// configuration
	'parent_selector' => '#'. $this->current->widget->id .' .scrollable',
	'child_selector' => '#'. $this->current->widget->id .' .items div',
	'lazy' => FALSE,
	'css' => TRUE,

	// scrollable options
	'keyboard' => TRUE, // FALSE or 'static'
	'circular' => TRUE,
	'vertical' => FALSE,
	'mousewheel' => FALSE,

	// scrollable plugins
	'navigator' => TRUE,  // FALSE or selector (html id or classname)
	'autoscroll' => array(
		'interval' => 2500,
		'autoplay' => TRUE,
		'autopause' => TRUE,
		'steps' => 1,
	)
));

?>
<div class="scrollable-wrapper">   
	<!-- root element for scrollable -->
	<div class="scrollable">   
	   <!-- root element for the items -->
	   <div class="items">
