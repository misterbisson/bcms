<?php

class bSuite_List_Children
{
	
	function __construct()
	{
		add_shortcode('pagemenu', array( $this, 'list_pages' ));
		add_shortcode('list_pages', array( $this, 'list_pages' ));
		add_shortcode('attachmentsmenu', array( $this, 'list_attachments' ));
		add_shortcode('list_attachments', array( $this, 'list_attachments' ));
	}

	function list_pages( $arg )
	{
		// [pagemenu ]
		global $id;
	
		$arg = shortcode_atts( array(
			'title' => 'Contents',
			'div_class' => 'contents pagemenu list_pages',
			'ul_class' => 'contents pagemenu list_pages',
			'ol_class' => FALSE,
			'excerpt'   => FALSE,
			'icon'   => FALSE,
			'echo' => 0,
			'show_parent' => FALSE,
			'child_of' => $id,
			'depth' => 1,
			'sort_column' => 'menu_order, post_title',
			'title_li' => '',
			'show_date'   => '',
			'date_format' => get_option('date_format'),
			'exclude'     => '',
			'authors'     => '',
		), $arg );
	
		$prefix = $suffix = '';
		if( $arg['div_class'] ){
			$prefix .= '<div class="'. esc_attr( $arg['div_class'] ) .'">';
			$suffix .= '</div>';
			if( $arg['title'] )
				$prefix .= '<h3>'. esc_html( $arg['title'] ) .'</h3>';
			if( $arg['ul_class'] ){
				$prefix .= '<ul>';
				$suffix = '</ul>'. $suffix;
			}else if( $arg['ol_class'] ){
				$prefix .= '<ol>';
				$suffix = '</ol>'. $suffix;
			}
		}else{
			if( $arg['title'] )
				$prefix .= '<h3 class="'. esc_attr( $arg['ul_class'] .' '. $arg['ol_class'] ) .'">'. $arg['title'] .'</h3>';
			if( $arg['ul_class'] ){
				$prefix .= '<ul class="'. esc_attr( $arg['ul_class'] ).'">';
				$suffix = '</ul>'. $suffix;
			}else if( $arg['ol_class'] ){
				$prefix .= '<ol class="'. esc_attr( $arg['ol_class'] ) .'">';
				$suffix = '</ol>'. $suffix;
			}
		}
	
		if(( $arg['excerpt'] ) || ( $arg['icon'] )){
			$this->list_pages->show_excerpt = $arg['excerpt'];
			$this->list_pages->show_icon = $arg['icon'];
			return( $prefix . ( $arg['show_parent'] ? '<li class="page_item page_item-parent"><a href="'. get_permalink( $arg['child_of'] ) .'">'. get_the_title( $arg['child_of'] ) .'</a></li>' : '' ) . preg_replace_callback( '/<li class="page_item page-item-([0-9]*)"><a(.*)<\/a>/i', array( &$this, 'list_pages_callback'), wp_list_pages( $arg )) . $suffix );
		}
		return( $prefix . ( $arg['show_parent'] ? '<li class="page_item page_item-parent"><a href="'. get_permalink( $arg['child_of'] ) .'">'. get_the_title( $arg['child_of'] ) .'</a></li>' : '' ) . wp_list_pages( $arg ) . $suffix );
	}
	
	function list_pages_callback( $arg )
	{
		global $id, $post , $bsuite;
	
		if( $this->list_pages->show_excerpt ){
			$post_orig = unserialize( serialize( $post )); // how else to prevent passing object by reference?
			$id_orig = $id;
	
			$post = get_post( $arg[1] );
			$id = $post->ID;
	
			$content = ( $this->list_pages->show_icon ? '<a href="'. get_permalink( $arg[1] ) .'" class="bsuite_post_icon_link" rel="bookmark" title="Permanent Link to '. attribute_escape( get_the_title( $arg[1] )) .'">'. $bsuite->icon_get_h( $arg[1] , 's' ) .'</a>' : '' ) . apply_filters( 'the_content', get_post_field( 'post_excerpt', $arg[1] ));
	
			$post = $post_orig;
			$id = $id_orig;
	
			if( 5 < strlen( $content ))
				return( $arg[0] .'<ul><li class="page_excerpt page_excerpt-'. $arg[1] .'">'. $content .'</li></ul>' );
			return( $arg[0] );
	
		}else{
			$content = apply_filters( 'the_content', get_post_field( 'post_excerpt', $arg[1] ));
			return( $arg[0] .'<ul><li class="page_icon page_icon-'. $arg[1] .'"><a href="'. get_permalink( $arg[1] ) .'" class="bsuite_post_icon_link" rel="bookmark" title="Permanent Link to '. attribute_escape( get_the_title( $arg[1] )) .'">'. $bsuite->icon_get_h( $arg[1] , 's' ) .'</a></li></ul>' );
	
		}
	
	}
	
	function list_attachments($attr)
	{
		global $post;

/*	
		// Allow plugins/themes to override the default gallery template.
		$output = apply_filters('post_gallery', '', $attr);
		if ( $output != '' )
			return $output;
*/	
		// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
		if ( isset( $attr['orderby'] ) ) {
			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
			if ( !$attr['orderby'] )
				unset( $attr['orderby'] );
		}

		extract( shortcode_atts( array(
			'order'      => 'ASC',
			'orderby'    => 'menu_order ID',
			'id'         => $post->ID,
			'post_mime_type' => FALSE ,
/*
			'itemtag'    => 'dl',
			'icontag'    => 'dt',
			'captiontag' => 'dd',
			'columns'    => 3,
			'size' => 'thumbnail'
			'post_mime_type' => 'image',
*/
		), $attr ));
	
		$id = absint($id);
		$attachments = get_children( array( 
			'post_parent' => $id , 
			'post_status' => 'inherit' , 
			'post_type' => 'attachment' ,
			'order' => $order,
			'orderby' => $orderby,
		));
	
		if ( empty( $attachments ))
			return '';
	
		$output = "\n";
		foreach ( $attachments as $att_id => $attachment )
			$output .= '<li>'. wp_get_attachment_link( $att_id, FALSE, FALSE ) . "</li>\n";
		return '<ul>'. $output .'</ul>';
	
	}
}

$bsuite_list_children = new bSuite_List_Children;