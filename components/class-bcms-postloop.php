<?php
/**
 * bCMS_PostLoop class
 *
 */
class bCMS_PostLoop
{
	//cache ttl
	var $ttl = 293; // a prime number slightly less than five minutes

	// instances
	var $instances;

	// posts matched by various instances of the widget
	var $posts; // $posts[ $loop_id ] = $post_id

	// terms from the posts in each instance
	var $terms; // $tags[ $loop_id ][ $taxonomy ][ $term_id ] = $count

	var $thumbnail_size = 'nines-thumbnail-small'; // the default thumbnail size

	function __construct()
	{
		$this->path_web = plugins_url( plugin_basename( dirname( __FILE__ )));

		add_action( 'init', array( $this, 'init' ));

		add_action( 'template_redirect' , array( $this, 'get_default_posts' ) , 0 );
	}

	function init()
	{
		if( function_exists( 'add_image_size' ))
		{
			add_image_size( 'nines-thumbnail-small' , 100 , 100 , TRUE );
			add_image_size( 'nines-thumbnail-wide' , 200 , 150 , TRUE );
		}

		$this->get_instances();

		add_action( 'admin_init', array( $this, 'admin_init' ));
	}

	function admin_init()
	{
		wp_register_script( 'postloop-editwidgets', $this->path_web . '/js/edit_widgets.js', array('jquery'), '2' );
		wp_enqueue_script( 'postloop-editwidgets' );

		add_action( 'admin_footer', array( $this, 'footer_activatejs' ));
	}

	public function footer_activatejs(){
?>
		<script type="text/javascript">
			postloops_widgeteditor();
		</script>
<?php
	}

	function get_default_posts()
	{
		global $wp_query;

		// test the cache first
		// this is about 100 to 1000 times faster than the nested loops below
		$cachekey = md5( serialize( $wp_query->posts ));
		if( $cached = wp_cache_get( $cachekey , 'bcmsdefaultposts' ))
		{
			$this->posts[-1] = $cached['posts'];
			$this->terms[-1] = $cached['terms'];

			return;
		}

		// process each post to capture post IDs and terms
		foreach( $wp_query->posts as $post )
		{
			// get the matching post IDs for the $postloops object
			$this->posts[-1][] = $post->ID;

			// get the matching terms by taxonomy
			$terms = wp_get_object_terms( $post->ID, (array) get_object_taxonomies( $post->post_type ) );

			// get the term taxonomy IDs for the $postloops object
			foreach( $terms as $term )
			{
				if( ! isset( $this->terms[-1][ $term->taxonomy ] ) ) // initialize
					$this->terms[-1][ $term->taxonomy ] = array();

				if( ! isset( $this->terms[-1][ $term->taxonomy ][ $term->term_id ] )) // initialize
					$this->terms[-1][ $term->taxonomy ][ $term->term_id ] = 0;

				$this->terms[-1][ $term->taxonomy ][ $term->term_id ]++; // increment
			}
		}

		// set the cache if we get here
		wp_cache_set(
			$cachekey ,
			array(
				'posts' => $this->posts[-1] ,
				'terms' => $this->terms[-1] ,
				'time' => time()
			) ,
			'bcmsdefaultposts' ,
			$this->ttl
		);
	}

	function get_instances()
	{
		$options = get_option( 'widget_postloop' );

		// add an entry for the default conent
		$options[-1] = array(
			'title' => 'The default content',
		);

		foreach( $options as $number => $option )
		{
			if( is_integer( $number ))
			{
				$option['title'] = empty( $option['title'] ) ? 'Instance #'. $number : wp_kses( $option['title'], array() );
				$this->instances[ $number ] = $option;
			}
		}

		return $this->instances;
	}

	function get_instances_response()
	{
		$options = get_option( 'widget_responseloop' );

		// add an entry for the default conent
		$options[-1] = array(
			'title' => 'The default content',
		);

		foreach( $options as $number => $option )
		{
			if( is_integer( $number ))
			{
				$option['title'] = empty( $option['title'] ) ? 'Instance #'. $number : wp_kses( $option['title'], array() );
				$this->instances_response[ md5( (string) $number . $option['template'] . $option['email'] ) ] = $option;
			}
		}

		return $this->instances_response;
	}

	function get_templates_readdir( $template_base )
	{
		$page_templates = array();
		if( file_exists( $template_base ) )
		{
			$template_dir = dir( $template_base );
			while ( ( $file = $template_dir->read() ) !== false )
			{
				if ( preg_match('|^\.+$|', $file ))
					continue;
				if ( preg_match('|\.php$|', $file ))
				{
					$template_data = implode( '', file( $template_base . $file ));

					$name = '';
					if ( preg_match( '|Template Name:(.*)$|mi', $template_data, $name ))
						$name = _cleanup_header_comment( $name[1] );

					$wrapper = FALSE;
					if ( preg_match( '|Wrapper:(.*)$|mi', $template_data )) // any value here will set it true
						$wrapper = TRUE;

					if ( !empty( $name ) )
					{
						$file = basename( $file );
						$page_templates[ $file ]['name'] = trim( $name );
						$page_templates[ $file ]['file'] = basename( $file );
						$page_templates[ $file ]['fullpath'] = $template_base . $file;
						$page_templates[ $file ]['wrapper'] = $wrapper;
					}
				}
			}
			@$template_dir->close();
		}

		return $page_templates;
	}

	function get_templates( $type = 'post' )
	{
		$type = sanitize_file_name( $type );
		$type_var = "templates_$type";

		if( isset( $this->$type_var ))
			return $this->$type_var;

		$this->$type_var = array_merge
		(
			(array) $this->get_templates_readdir( dirname( dirname( __FILE__ )) .'/templates-'. $type .'/' ),
			(array) $this->get_templates_readdir( TEMPLATEPATH . '/templates-'. $type .'/' ),
			(array) $this->get_templates_readdir( STYLESHEETPATH . '/templates-'. $type .'/' )
		);

		$this->$type_var = apply_filters( 'bcms_postloop_templates', $this->$type_var );

		return $this->$type_var;
	}


	function _missing_template()
	{
?><!-- ERROR: the required template file is missing or unreadable. A default template is being used instead. -->
<div <?php post_class() ?> id="post-<?php the_ID(); ?>">
	<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
	<small><?php the_time('F jS, Y') ?> <!-- by <?php the_author() ?> --></small>

	<div class="entry">
		<?php the_content('Read the rest of this entry &raquo;'); ?>
	</div>

	<p class="postmetadata"><?php the_tags('Tags: ', ', ', '<br />'); ?> Posted in <?php the_category(', ') ?> | <?php edit_post_link('Edit', '', ' | '); ?>  <?php comments_popup_link('No Comments &#187;', '1 Comment &#187;', '% Comments &#187;'); ?></p>
</div>
<?php
	}

	function do_template( $name , $event , $query_object = FALSE , $postloop_object = FALSE , $widget = FALSE, $instance = array() )
	{

		// get the post templates
		$templates = $this->get_templates( 'post' );

		// check that we have a template by this name
		if( ! isset( $templates[ $name ] ))
		{
			$this->_missing_template();
			return;
		}

		// do it
		switch( $event )
		{
			case 'before':
				if( isset( $templates[ $name ]['wrapper'] ) && ( ! @include preg_replace( '/\.php$/', '_before.php', $templates[ $name ]['fullpath'] )))
					echo '<!-- ERROR: the required template wrapper file is missing or unreadable. -->';

				break;

			case 'after':
				if( isset( $templates[ $name ]['wrapper'] ) && ( ! @include preg_replace( '/\.php$/', '_after.php', $templates[ $name ]['fullpath'] )))
					echo '<!-- ERROR: the required template wrapper file is missing or unreadable. -->';

				break;

			default:
				if( ! @include $templates[ $name ]['fullpath'] )
					$this->_missing_template();

		}
	}

	function get_actions( $type = 'post' )
	{
		$templates = $this->get_templates( $type );

		$actions = array();

		foreach( $templates as $template => $info )
		{
			$actions[ $template ] = array(
				'name' 		=> $info['name'],
				'callback' 	=> array( $this , 'do_template' ),
			);
		}

		return apply_filters( 'postloop_actions' , $actions );
	}

	function do_action( $type , $name , $event , $query_object , $widget, $instance = array() )
	{

		$this->current = new stdClass;
		$this->current->widget = $widget;
		$this->current->query = $query_object;

		$actions = $this->get_actions( $type );

		if( isset( $actions[ $name ] ) && is_callable( $actions[ $name ]['callback'] ))
			call_user_func( $actions[ $name ]['callback'] , $name , $event , $query_object , $this  , $widget, $instance );
	}

	function posts_where_comments_yes_once( $sql )
	{
		remove_filter( 'posts_where', array( $this , 'posts_where_comments_yes_once' ), 10 );
		return $sql . ' AND comment_count > 0 ';
	}
	function posts_where_comments_no_once( $sql )
	{
		remove_filter( 'posts_where', array( $this , 'posts_where_comments_no_once' ), 10 );
		return $sql . ' AND comment_count < 1 ';
	}

	function posts_where_date_since_once( $sql )
	{
		remove_filter( 'posts_where', array( $this , 'posts_where_date_since_once' ), 10 );
		return $sql . ' AND post_date > "'. $this->date_since .'"';
	}

	function posts_where_date_before_once( $sql )
	{
		remove_filter( 'posts_where', array( $this , 'posts_where_date_since_once' ), 10 );
		return $sql . ' AND post_date < "'. $this->date_before .'"';
	}

	function posts_join_recently_popular_once( $sql )
	{
		global $wpdb, $blog_id, $bsuite;

		remove_filter( 'posts_join', array( $this , 'posts_join_recently_popular_once' ), 10 );
		return " INNER JOIN $bsuite->hits_pop AS popsort ON ( popsort.blog_id = $blog_id AND popsort.hits_recent > 0 AND $wpdb->posts.ID = popsort.post_ID ) ". $sql;
	}

	function posts_orderby_recently_popular_once( $sql )
	{
		remove_filter( 'posts_orderby', array( $this , 'posts_orderby_recently_popular_once' ), 10 );
		return ' popsort.hits_recent DESC, '. $sql;
	}

	function posts_fields_recently_commented_once( $sql )
	{
		remove_filter( 'posts_fields', array( $this , 'posts_fields_recently_commented_once' ), 10 );
		return $sql. ', MAX( commentsort.comment_date_gmt ) AS commentsort_order ';
	}

	function posts_join_recently_commented_once( $sql )
	{
		global $wpdb;

		remove_filter( 'posts_join', array( $this , 'posts_join_recently_commented_once' ), 10 );
		return " INNER JOIN $wpdb->comments AS commentsort ON ( commentsort.comment_approved = 1 AND $wpdb->posts.ID = commentsort.comment_post_ID ) ". $sql;
	}

	function posts_groupby_recently_commented_once( $sql )
	{
		global $wpdb;

		remove_filter( 'posts_groupby', array( $this , 'posts_groupby_recently_commented_once' ), 10 );
		return $wpdb->posts .'.ID' . ( empty( $sql ) ? '' : ', ' );
	}

	function posts_orderby_recently_commented_once( $sql )
	{
		remove_filter( 'posts_orderby', array( $this , 'posts_orderby_recently_commented_once' ), 10 );
		return ' commentsort_order DESC, '. $sql;
	}

	function posts_request_once( $request )
	{
		// remove the filter so that it only runs once
		remove_filter( 'posts_request' , array( $this, 'posts_request_once' ));

		// insert a comment in the query so we can track it better
		return $request . ' /* ' . $this->sql_comment . ' */';
	}

} //end class

/**
 * Singleton
 */
function bcms_postloop()
{
	global $postloops;

	if ( ! $postloops )
	{
		$postloops = new bCMS_PostLoop();
	}//end if

	return $postloops;
}//end bcms_postloop
