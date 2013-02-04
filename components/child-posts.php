<?php
class bSuite_ChildPosts
{

	// options that can be changed (it's best to change them in the bootstrapper)
	// no options can be changed...for now

	// don't mess with these
	var $id_base = 'bsuite-childpost';
	var $post_type_name = 'bsuite-childpost';
	var $meta_key = 'bsuite-childpost';

	function __construct()
	{
		add_action( 'init' , array( $this, 'register_post_type' ) , 11 );
	}

	function nonce_field()
	{
		wp_nonce_field( plugin_basename( __FILE__ ) , $this->id_base .'-nonce' );
	}

	function verify_nonce()
	{
		return wp_verify_nonce( $_POST[ $this->id_base .'-nonce' ] , plugin_basename( __FILE__ ));
	}

	function get_field_name( $field_name )
	{
		return $this->id_base . '[' . $field_name . ']';
	}

	function get_field_id( $field_name )
	{
		return $this->id_base . '-' . $field_name;
	}

	function get_post_meta( $post_id )
	{
		$this->instance = get_post_meta( $post_id , $this->post_meta_key , TRUE );
		return $this->instance;
	}

	function update_post_meta( $post_id , $meta )
	{
		// save it
		update_post_meta( $post_id , $this->post_meta_key , $meta );
	}

	function filter_post_class( $classes )
	{
		if( get_post( get_the_ID() )->post_type == $this->post_type_name )
			$classes[] = 'post';

		return $classes;
	}

	function register_post_type()
	{
		$taxonomies = get_taxonomies( array( 'public' => true ));

		register_post_type( $this->post_type_name,
			array(
				'labels' => array(
					'name' => __( 'Child Posts' ),
					'singular_name' => __( 'Child Post' ),
				),
				'supports' => array(
					'title',
					'editor',
					'author',
					'thumbnail',
					'excerpt',
					'trackbacks',
					'custom-fields',
					'comments',
					'revisions',
					'page-attributes',
					'post-formats',
				),
				'register_meta_box_cb' => array( $this , 'register_metaboxes' ),
				'public' => TRUE,
				'show_ui' => TRUE,
				'show_in_menu' => FALSE,
				'show_in_nav_menus' => FALSE,
				'hierarchical' => TRUE,
				'taxonomies' => $taxonomies,
			)
		);
	}

	function metabox( $post )
	{
	}

	function register_metaboxes()
	{
		// add metaboxes
		add_meta_box( $id_base , 'Featured Comment' , array( $this , 'metabox' ) , $this->post_type_name , 'normal', 'high' );
	}

}//end bSuite_ChildPosts class

new bSuite_ChildPosts;