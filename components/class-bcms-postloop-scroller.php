<?php
/**
 * PostLoop Scroller class
 *
 */
class bCMS_PostLoop_Scroller
{
	function __construct( $args = '' )
	{

		// get settings
		$defaults = array(
			// configuration
			'actionname' => 'postloop_f_default_scroller',
			'selector' => '.scrollable',
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
		);
		$this->settings = (object) wp_parse_args( (array) $args , (array) $defaults );

		// get the path to our scripts and styles
		$this->path_web = plugins_url( plugin_basename( dirname( __FILE__ )));

		// register scripts and styles
		wp_register_script( 'scrollable', $this->path_web . '/js/scrollable.min.js', array('jquery'), TRUE );
		wp_enqueue_script( 'scrollable' );
		add_filter( 'print_footer_scripts', array( $this, 'print_js' ), 10, 1 );

		if( $this->settings->css )
		{
			wp_register_style( 'scrollable', $this->path_web .'/css/scrollable.css' );
			wp_enqueue_style( 'scrollable' );
		}
	}

	function print_js( $finish_print )
	{
?>
<script type="text/javascript">
	;(function($){
		$(window).load(function(){
			var $child = $('<?php echo $this->settings->child_selector; ?>');
			var $parent = $('<?php echo $this->settings->parent_selector; ?>');

			// set the size of some items
			$child.width( $parent.width() );
			$parent.height( $child.height() );

			// initialize scrollable
			$parent
				.scrollable({ circular: true })
				.navigator()
				.autoscroll(<?php echo json_encode( $this->settings->autoscroll ); ?>);

			//show the .items divs now that the scrollable is initialized
			$child.width( $(window).width() ).show();
		});
	})(jQuery);
</script>
<?php
		return $finish_print;
	}
}