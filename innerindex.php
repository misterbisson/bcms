<?php
class bSuite_Innerindex
{
	function __construct()
	{
		add_shortcode('innerindex', array($this, 'shortcode'));
		add_filter('content_save_pre', array($this, 'nametags'));
		add_filter('save_post', array($this, 'delete_cache'));
		$this->allowedposttags(); // allow IDs on H1-H6 tags
	}

	function shortcode( $arg )
	{
		// [innerindex ]
		global $id;
	
		$arg = shortcode_atts( array(
			'title' => 'Contents',
			'div_class' => 'contents innerindex',
		), $arg );
	
		$prefix = $suffix = '';
		if( $arg['div_class'] ){
			$prefix .= '<div class="'. $arg['div_class'] .'">';
			$suffix .= '</div>';
			if( $arg['title'] )
				$prefix .= '<h3>'. $arg['title'] .'</h3>';
		}else{
			if( $arg['title'] )
				$prefix .= '<h3>'. $arg['title'] .'</h3>';
		}
	
		if ( !$menu = wp_cache_get( $id, 'bsuite_innerindex' )) {
			$menu = $this->build( get_post_field( 'post_content', $id ));
			wp_cache_add( $id, $menu, 'bsuite_innerindex', 864000 );
		}
	
		return( $prefix . str_replace( '%%the_permalink%%', get_permalink( $id ), $menu ) . $suffix );
	}

	function build($content)
	{
		// find <h*> tags with IDs in the content and build an index of them
		preg_match_all(
			'|<h[^>]+>.+?</h[^>]+>|U',
			$content,
			$things
			);

		$menu = '<ol>';
		$closers = $count = 0;
		foreach($things[0] as $thing)
		{
			preg_match('|<h([0-9])|U', $thing, $h);
			preg_match('|id="([^"]*)"|U', $thing, $anchor);

			if(!$last)
				$last = $low = $h[1];

			if($anchor[1])
			{
				if($h[1] > $last)
				{
					$menu .= '<ol>';
					$closers++;

					if( 1 < ( $h[1] - $last ))
					{
						$menu .= str_repeat( '<li><ol>', $h[1] - $last -1 );
						$closers = $closers + ( $h[1] - $last -1 );
					}
				}else if($count){
					$menu .= '</li>';
				}

				if(($h[1] < $last) && ($h[1] >= $low))
				{
					$menu .= str_repeat( '</ol></li>', $last - $h[1] );
					$closers = $closers - ( $last - $h[1] );
				}

				$last = $h[1];

				$menu .= '<li><a href="%%the_permalink%%#'. $anchor[1] .'">'. strip_tags($thing) .'</a>';
				$count++;
			}
		}
		$menu .= '</li>'. str_repeat('</ol></li>', $closers) . '</ol>';
		return($menu);
	}

	function delete_cache($id)
	{
		$id = (int) $id;
		wp_cache_delete( $id, 'bsuite_innerindex' );
	}

	function nametags($content)
	{
		// find <h*> tags in the content
		$content = preg_replace_callback(
			"/(\<h([0-9])?([^\>]*)?\>)(.*?)(\<\/h[0-9]\>)/",
			array(&$this,'nametags_callback'),
			$content
			);
		return($content);
	}

	function nametags_callback( $content )
	{
		// receive <h*> tags and insert the ID
		static $slugs;
		$slugs[] = $slug = substr( sanitize_title_with_dashes( $content[4] ), 0, 30);
		$count = count( array_keys( $slugs, $slug ));
		$content = '<h'. $content[2] .' id="'. $slug . ( 1 < $count ? $count : '' ) .'" '. trim( preg_replace( '/id[^"]*"[^"]*"/', '', $content[3] )) .'>'. $content[4] . $content[5];
		return($content);
	}
	// end innerindex-related

	function allowedposttags() {
		global $allowedposttags;
		$allowedposttags['h1']['id'] = array();
		$allowedposttags['h1']['class'] = array();
		$allowedposttags['h2']['id'] = array();
		$allowedposttags['h2']['class'] = array();
		$allowedposttags['h3']['id'] = array();
		$allowedposttags['h3']['class'] = array();
		$allowedposttags['h4']['id'] = array();
		$allowedposttags['h4']['class'] = array();
		$allowedposttags['h5']['id'] = array();
		$allowedposttags['h5']['class'] = array();
		$allowedposttags['h6']['id'] = array();
		$allowedposttags['h6']['class'] = array();
		return(TRUE);
	}
}
$bbuite_innerindex = new bSuite_Innerindex;