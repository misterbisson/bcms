<?php
/**
 * PostLoops class
 *
 */
class bSuite_PostLoops {

	// instances
	var $instances;

	// posts matched by various instances of the widget
	var $posts; // $posts[ $loop_id ][ $blog_id ] = $post_id

	// terms from the posts in each instance
	var $terms; // $tags[ $loop_id ][ $blog_id ][ $taxonomy ][ $term_id ] = $count

	var $thumbnail_size = 'nines-thumbnail-small'; // the default thumbnail size

	function bSuite_PostLoops()
	{
		global $bsuite;

		$this->path_web = is_object( $bsuite ) ? $bsuite->path_web : get_template_directory_uri();

		add_action( 'init', array( &$this, 'init' ));

		add_action( 'preprocess_comment' , array( &$this, 'preprocess_comment' ), 1 );
		add_action( 'bsuite_response_sendmessage' , array( &$this, 'sendmessage' ), 1, 2 );

		add_action( 'template_redirect' , array( &$this, 'get_default_posts' ), 0 );
	}

	function init()
	{
		if( function_exists( 'add_image_size' ))
		{
			add_image_size( 'nines-thumbnail-small' , 100 , 100 , TRUE );
			add_image_size( 'nines-thumbnail-wide' , 200 , 150 , TRUE );
		}

		$this->get_instances();

//		$this->get_templates( 'post' );
//		$this->get_templates( 'response' );

		add_action( 'admin_init', array(&$this, 'admin_init' ));
//		add_filter( 'posts_request' , array( &$this , 'posts_request' ));
	}

	function admin_init()
	{
		wp_register_script( 'postloop-editwidgets', $this->path_web . '/components/js/edit_widgets.js', array('jquery'), '2' );
		wp_enqueue_script( 'postloop-editwidgets' );

		add_action( 'admin_footer', array( &$this, 'footer_activatejs' ));
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
		global $wp_query, $blog_id;

		foreach( $wp_query->posts as $post )
		{
			// get the matching post IDs for the $postloops object
			$this->posts[-1][ $blog_id ][] = $post->ID;
			
			// get the matching terms by taxonomy
			$terms = get_object_term_cache( $post->ID, (array) get_object_taxonomies( $post->post_type ) );
			if ( empty( $terms ))
				$terms = wp_get_object_terms( $post->ID, (array) get_object_taxonomies( $post->post_type ) );

			// get the term taxonomy IDs for the $postloops object
			foreach( $terms as $term )
			{
				if( ! isset( $this->terms[-1][$term->taxonomy] ) ) // initialize
					$this->terms[-1][ $term->taxonomy ] = array();

				if( ! isset( $this->terms[-1][$term->taxonomy][ $term->term_id ] )) // initialize
					$this->terms[-1][ $term->taxonomy ][ $term->term_id ] = 0;

				$this->terms[-1][ $term->taxonomy ][ $term->term_id ]++; // increment
			}


		}
	}

	function get_instances()
	{
		global $blog_id;

		$options = get_option( 'widget_postloop' );

		// add an entry for the default conent
		$options[-1] = array( 
			'title' => 'The default content',
			'blog' => absint( $blog_id ),
		);

		foreach( $options as $number => $option )
		{
			if( is_integer( $number ))
			{
				$option['title'] = empty( $option['title'] ) ? 'Instance #'. $number : wp_filter_nohtml_kses( $option['title'] );
				$this->instances[ $number ] = $option;
			}
		}

		return $this->instances;
	}

	function get_instances_response()
	{
		global $blog_id;

		$options = get_option( 'widget_responseloop' );

		// add an entry for the default conent
		$options[-1] = array( 
			'title' => 'The default content',
			'blog' => absint( $blog_id ),
		);

		foreach( $options as $number => $option )
		{
			if( is_integer( $number ))
			{
				$option['title'] = empty( $option['title'] ) ? 'Instance #'. $number : wp_filter_nohtml_kses( $option['title'] );
				$this->instances_response[ md5( (string) $number . $option['template'] . $option['email'] ) ] = $option;
			}
		}

		return $this->instances_response;
	}

	function get_templates_readdir( $template_base )
	{
		$page_templates = array();
		$template_dir = @ dir( $template_base );
		if ( $template_dir ) 
		{
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

	function do_template( $name , $event , $query_object = FALSE , $postloop_object = FALSE )
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

		return apply_filters( 'bsuite_postloop_actions' , $actions );
	}

	function do_action( $type , $name , $event , $query_object )
	{

		$actions = $this->get_actions( $type );

		if( isset( $actions[ $name ] ) && is_callable( $actions[ $name ]['callback'] ))
			call_user_func( $actions[ $name ]['callback'] , $name , $event , $query_object , $this );
	}

	function preprocess_comment( $comment )
	{
		$this->get_instances_response();

		do_action(
			'bsuite_response_'. sanitize_title_with_dashes( preg_replace( '/\.[^\.]*$/' , '', $this->instances_response[ $_REQUEST['bsuite_responsekey'] ]['template'] )),
			$comment,
			$this->instances_response[ $_REQUEST['bsuite_responsekey'] ]
		);

		return( $comment );
	}

	function sendmessage( $comment , $input )
	{
		add_action( 'comment_post', array( &$this, '_sendmessage' ));
		add_filter( 'pre_comment_approved', create_function( '$a', 'return \'message\';'), 1 );
	}

	function _sendmessage( $comment_id , $approved )
	{
		if ( 'spam' == $approved )
			return;

		$also_notify = sanitize_email( $this->instances_response[ $_REQUEST['bsuite_responsekey'] ]['email'] );

		$comment = get_comment( $comment_id );
		$post    = get_post( $comment->comment_post_ID );
		$user    = get_userdata( $post->post_author );
		$current_user = wp_get_current_user();
	
		if(( '' == $also_notify ) && ('' == $user->user_email )) return false; // If there's no email to send the comment to
	
		$comment_author_domain = @gethostbyaddr( $comment->comment_author_IP );
	
		$blogname = get_option('blogname');
	
		/* translators: 1: post id, 2: post title */
		$notify_message  = sprintf( __('New message on %2$s (#%1$s)'), $comment->comment_post_ID, $post->post_title ) . "\r\n\r\n";
   		$notify_message .= $comment->comment_content . "\r\n\r\n";
		/* translators: 1: comment author, 2: author IP, 3: author domain */
		$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "\r\n";
		$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "\r\n";
		$notify_message .=  __('Network location:') . "\r\nhttp://ws.arin.net/cgi-bin/whois.pl?queryinput=$comment->comment_author_IP\r\n\r\n";
//		$notify_message .= __('You can see all messages on this post here: ') . "\r\n";
//		$notify_message .= admin_url( '/wp-admin/edit-comments.php?p='. $post->ID ) ."\r\n\r\n";

		/* translators: 1: blog name, 2: post title */
		$subject = sprintf( __('[%1$s] Message on "%2$s"'), $blogname, $post->post_title );
	
	
		$wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
	
		if ( '' == $comment->comment_author )
		{
			$from = "From: \"$blogname\" <$wp_email>";
			if ( '' != $comment->comment_author_email )
				$reply_to = "Reply-To: $comment->comment_author_email";
		} else {
			$from = "From: \"$comment->comment_author\" <$wp_email>";
			if ( '' != $comment->comment_author_email )
				$reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
		}
	
		$message_headers = "$from\n"
			. "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
	
		if ( isset( $reply_to ))
			$message_headers .= $reply_to . "\n";
	
		$notify_message = apply_filters('comment_notification_text', $notify_message, $comment_id);
		$subject = apply_filters('comment_notification_subject', $subject, $comment_id);
		$message_headers = apply_filters('comment_notification_headers', $message_headers, $comment_id);

		if( '' <> $also_notify )
			@wp_mail( $also_notify , $subject , $notify_message , $message_headers );

		if( $user->user_email )
			@wp_mail( $user->user_email , $subject , $notify_message , $message_headers );

		die( wp_redirect( get_comment_link( $comment_id )));
	}

	function restore_current_blog()
	{
		if ( function_exists('restore_current_blog') )
			return restore_current_blog();
		return TRUE;
	}

	function posts_where_comments_yes_once( $sql )
	{
		remove_filter( 'posts_where', array( &$this , 'posts_where_comments_yes_once' ), 10 );
		return $sql . ' AND comment_count > 0 ';
	}
	function posts_where_comments_no_once( $sql )
	{
		remove_filter( 'posts_where', array( &$this , 'posts_where_comments_no_once' ), 10 );
		return $sql . ' AND comment_count < 1 ';
	}

	function posts_where_date_since_once( $sql )
	{
		remove_filter( 'posts_where', array( &$this , 'posts_where_date_since_once' ), 10 );
		return $sql . ' AND post_date > "'. $this->date_since .'"';
	}

	function posts_where_date_before_once( $sql )
	{
		remove_filter( 'posts_where', array( &$this , 'posts_where_date_since_once' ), 10 );
		return $sql . ' AND post_date < "'. $this->date_before .'"';
	}

	function posts_join_recently_popular_once( $sql )
	{
		global $wpdb, $blog_id, $bsuite;

		remove_filter( 'posts_join', array( &$this , 'posts_join_recently_popular_once' ), 10 );
		return " INNER JOIN $bsuite->hits_pop AS popsort ON ( popsort.blog_id = $blog_id AND popsort.hits_recent > 0 AND $wpdb->posts.ID = popsort.post_ID ) ". $sql;
	}

	function posts_orderby_recently_popular_once( $sql )
	{
		remove_filter( 'posts_orderby', array( &$this , 'posts_orderby_recently_popular_once' ), 10 );
		return ' popsort.hits_recent DESC, '. $sql;
	}

	function posts_fields_recently_commented_once( $sql )
	{
		remove_filter( 'posts_fields', array( &$this , 'posts_fields_recently_commented_once' ), 10 );
		return $sql. ', MAX( commentsort.comment_date_gmt ) AS commentsort_order ';
	}

	function posts_join_recently_commented_once( $sql )
	{
		global $wpdb;

		remove_filter( 'posts_join', array( &$this , 'posts_join_recently_commented_once' ), 10 );
		return " INNER JOIN $wpdb->comments AS commentsort ON ( commentsort.comment_approved = 1 AND $wpdb->posts.ID = commentsort.comment_post_ID ) ". $sql;
	}

	function posts_groupby_recently_commented_once( $sql )
	{
		global $wpdb;

		remove_filter( 'posts_groupby', array( &$this , 'posts_groupby_recently_commented_once' ), 10 );
		return $wpdb->posts .'.ID' . ( empty( $sql ) ? '' : ', ' );
	}

	function posts_orderby_recently_commented_once( $sql )
	{
		remove_filter( 'posts_orderby', array( &$this , 'posts_orderby_recently_commented_once' ), 10 );
		return ' commentsort_order DESC, '. $sql;
	}

	function posts_request( $request )
	{
		echo $request;
		return $request;
	}

} //end bSuite_PostLoops

// initialize that class
global $postloops;
$postloops = new bSuite_PostLoops();


/**
 * PostLoop Scroller class
 *
 */
class bSuite_PostLoop_Scroller
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
		global $bsuite;
		$this->path_web = is_object( $bsuite ) ? $bsuite->path_web : get_template_directory_uri();

		// register scripts and styles
		wp_register_script( 'scrollable', $this->path_web . '/components/js/scrollable.min.js', array('jquery'), TRUE );
		wp_register_style( 'scrollable', $this->path_web .'/components/css/scrollable.css' );

		// register our hook to the named action
		add_action( $this->settings->actionname , array( &$this, 'do_postloop' ) , 5 , 3 );
	}

	function do_postloop( $action , $ourposts , $postloops )
	{
		switch( $action )
		{
			case 'before':
				late_enqueue_script( 'scrollable' );
				if( $this->settings->css )
					late_enqueue_style( 'scrollable' );
				add_filter( 'print_footer_scripts', array( $this, 'print_js' ));
				break;
		}
	}

	function print_js()
	{
//$(".scroller").scrollable({circular: true}).navigator("#myNavi").autoscroll({interval: 4000});
//navigator(".navi");
?>
<script type="text/javascript">	
	;(function($){
		$(window).load(function(){
			// set the size of some items
			$('.items div').width( $('<?php echo $this->settings->selector; ?>').width() );
			$('<?php echo $this->settings->selector; ?>').height( $('.items div').height() );

			// initialize scrollable
			$('<?php echo $this->settings->selector; ?>').scrollable({ circular: true }).navigator().autoscroll(<?php echo json_encode( $this->settings->autoscroll ); ?>)
		});
	})(jQuery);
</script>
<?php
	}
}
new bSuite_PostLoop_Scroller();



/**
 * PostLoop widget class
 *
 */
class bSuite_Widget_PostLoop extends WP_Widget {

	function bSuite_Widget_PostLoop() {
		$widget_ops = array('classname' => 'widget_postloop', 'description' => __( 'Build your own post loop') );
		$this->WP_Widget('postloop', __('Post Loop'), $widget_ops);

		global $postloops;

		add_filter( 'wijax-actions' , array( $this , 'wjiax_actions' ) );
	}

	function wjiax_actions( $actions )
	{
		global $postloops, $mywijax;
		foreach( $postloops->instances as $k => $v )
			$actions[ $mywijax->encoded_name( 'postloop-'. $k ) ] = (object) array( 'key' => 'postloop-'. $k , 'type' => 'widget');

		return $actions;
	}

	function widget( $args, $instance ) {
		global $bsuite, $postloops, $wpdb, $blog_id, $mywijax;

		$this->wijax_varname = $mywijax->encoded_name( $this->id );

		extract( $args );

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? '' : $instance['title']);

		if( 'normal' == $instance['what'] ){
			wp_reset_query();
			global $wp_query;

			$ourposts = &$wp_query;

		}else{
//			$criteria['suppress_filters'] = TRUE;

			$criteria['post_type'] = array_values( array_intersect( (array) $this->get_post_types() , (array) $instance['what'] ));

			if( in_array( $instance['what'], array( 'attachment', 'revision' )))
				$criteria['post_status'] = 'inherit';

			if( !empty( $instance['categories_in'] ))
				$criteria['category__'. ( in_array( $instance['categoriesbool'], array( 'in', 'and', 'not_in' )) ? $instance['categoriesbool'] : 'in' ) ] = array_keys( (array) $instance['categories_in'] );

			if( $instance['categories_in_related'] )
				$criteria['category__'. ( in_array( $instance['categoriesbool'], array( 'in', 'and', 'not_in' )) ? $instance['categoriesbool'] : 'in' ) ] = array_merge( (array) $criteria['category__'. ( in_array( $instance['categoriesbool'], array( 'in', 'and', 'not_in' )) ? $instance['categoriesbool'] : 'in' ) ], (array) array_keys( (array) $postloops->terms[ $instance['categories_in_related'] ]['category'] ) );

			if( !empty( $instance['categories_not_in'] ))
				$criteria['category__not_in'] = array_keys( (array) $instance['categories_not_in'] );

			if( $instance['categories_not_in_related'] )
				$criteria['category__not_in'] = array_merge( (array) $criteria['category__not_in'] , (array) array_keys( (array) $postloops->terms[ $instance['categories_not_in_related'] ]['category'] ));

			if( !empty( $instance['tags_in'] ))
				$criteria['tag__'. ( in_array( $instance['tagsbool'], array( 'in', 'and', 'not_in' )) ? $instance['tagsbool'] : 'in' ) ] = $instance['tags_in'];

			if( $instance['tags_in_related'] )
				$criteria['tag__'. ( in_array( $instance['tagsbool'], array( 'in', 'and', 'not_in' )) ? $instance['tagsbool'] : 'in' ) ] = array_merge( (array) $criteria['tag__'. ( in_array( $instance['tagsbool'], array( 'in', 'and', 'not_in' )) ? $instance['tagsbool'] : 'in' ) ], (array) array_keys( (array) $postloops->terms[ $instance['tags_in_related'] ]['post_tag'] ) );

			if( !empty( $instance['tags_not_in'] ))
				$criteria['tag__not_in'] = $instance['tags_not_in'];

			if( $instance['tags_not_in_related'] )
				$criteria['tag__not_in'] = array_merge( (array) $criteria['tag__not_in'] , (array) array_keys( (array) $postloops->terms[ $instance['tags_not_in_related'] ]['post_tag'] ));

			$tax_query = array();

/*
			if( $instance['tags_in_related'] )
				$instance['tags_in'] = array_merge( 
					(array) $instance['tags_in'] ,
					(array) array_keys( (array) $postloops->terms['post_tag'][ $taxonomy ] )
				);

			if( count( $instance['tags_in'] ))
			{
				$tax_query[] = array(
					'taxonomy' => 'post_tag',
					'field' => 'term_id',
					'terms' => $instance['tags_in'],
					'operator' => strtoupper( $instance['tagsbool'] ),
				);
			}

			if( $instance['tags_not_in_related'] )
				$instance['tags_not_in'] = array_merge( 
					(array) $instance['tags_not_in'] , 
					(array) array_keys( (array) $postloops->terms['post_tag'][ $taxonomy ] )
				);

			if( count( $instance['tags_not_in'] ))
			{
				$tax_query[] = array(
					'taxonomy' => 'post_tag',
					'field' => 'term_id',
					'terms' => $instance['tags_not_in'],
					'operator' => 'NOT IN',
				);
			}
*/
			foreach( get_object_taxonomies( $criteria['post_type'] ) as $taxonomy )
			{
				if( $taxonomy == 'category' || $taxonomy == 'post_tag' )
					continue;

				if( $instance['tax_'. $taxonomy .'_in_related'] )
					$instance['tax_'. $taxonomy .'_in'] = array_merge( 
						(array) $instance['tax_'. $taxonomy .'_in'] ,
						(array) array_keys( (array) $postloops->terms[ $instance['tax_'. $taxonomy .'_in_related'] ][ $taxonomy ] )
					);

				if( count( $instance['tax_'. $taxonomy .'_in'] ))
				{
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field' => 'term_id',
						'terms' => $instance['tax_'. $taxonomy .'_in'],
						'operator' => strtoupper( $instance['tax_'. $taxonomy .'_bool'] ),
					);
				}

				if( $instance['tax_'. $taxonomy .'_not_in_related'] )
					$instance['tax_'. $taxonomy .'_not_in'] = array_merge( 
						(array) $instance['tax_'. $taxonomy .'_not_in'] , 
						(array) array_keys( (array) $postloops->terms[ $instance['tax_'. $taxonomy .'_not_in_related'] ][ $taxonomy ] )
					);

				if( count( $instance['tax_'. $taxonomy .'_not_in'] ))
				{
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field' => 'term_id',
						'terms' => $instance['tax_'. $taxonomy .'_not_in'],
						'operator' => 'NOT IN',
					);
				}
			}
			if( count( $tax_query ))
				$criteria['tax_query'] = $tax_query;

			if( !empty( $instance['post__in'] ))
				$criteria['post__in'] = $instance['post__in'];
	
			if( !empty( $instance['post__not_in'] ))
				$criteria['post__not_in'] = $instance['post__not_in'];

			switch( $instance['comments'] )
			{
				case 'yes':
					add_filter( 'posts_where', array( &$postloops , 'posts_where_comments_yes_once' ), 10 );
					break;
				case 'no':
					add_filter( 'posts_where', array( &$postloops , 'posts_where_comments_no_once' ), 10 );
					break;
				default:
					break;
			}

			foreach ( get_object_taxonomies('post') as $taxonomy ) {
				$criteria[$taxonomy] = apply_filters('ploop_taxonomy_'. $taxonomy, $criteria[$taxonomy]);
			}

			if( 0 < $instance['age_num'] )
			{
				$postloops->date_before = $postloops->date_since = date( 'Y-m-d' , strtotime( $instance['age_num'] .' '. $instance['age_unit'] .' ago' ));
				if( $instance['age_bool'] == 'older' )
					add_filter( 'posts_where', array( &$postloops , 'posts_where_date_before_once' ), 10 );
				else
					add_filter( 'posts_where', array( &$postloops , 'posts_where_date_since_once' ), 10 );
			}

			if( isset( $_GET['wijax'] ) && absint( $_GET['paged'] ))
				$criteria['paged'] = absint( $_GET['paged'] );
			$criteria['showposts'] = absint( $instance['count'] );

			switch( $instance['order'] ){
				case 'age_new':
					$criteria['orderby'] = 'date';
					$criteria['order'] = 'DESC';
					break;

				case 'age_old':
					$criteria['orderby'] = 'date';
					$criteria['order'] = 'ASC';
					break;

				case 'title_az':
					$criteria['orderby'] = 'title';
					$criteria['order'] = 'ASC';
					break;

				case 'title_za':
					$criteria['orderby'] = 'title';
					$criteria['order'] = 'DESC';
					break;

				case 'comment_new':
					add_filter( 'posts_fields',		array( &$postloops , 'posts_fields_recently_commented_once' ), 10 );
					add_filter( 'posts_join',		array( &$postloops , 'posts_join_recently_commented_once' ), 10 );
					add_filter( 'posts_groupby',	array( &$postloops , 'posts_groupby_recently_commented_once' ), 10 );
					add_filter( 'posts_orderby',	array( &$postloops , 'posts_orderby_recently_commented_once' ), 10 );
					break;

				case 'pop_recent':
					if( is_object( $bsuite ))
					{
						add_filter( 'posts_join',		array( &$postloops , 'posts_join_recently_popular_once' ), 10 );
						add_filter( 'posts_orderby',	array( &$postloops , 'posts_orderby_recently_popular_once' ), 10 );
						break;
					}

				case 'rand':
					$criteria['orderby'] = 'rand';
					break;

				default:
					$criteria['orderby'] = 'post_date';
					$criteria['order'] = 'DESC';
					break;
			}

			if( 'excluding' == $instance['relationship'] && count( (array) $instance['relatedto'] ))
			{
				foreach( $instance['relatedto'] as $related_loop => $temp )
				{
					if( isset( $postloops->posts[ $related_loop ] ) && $instance['blog'] == key( $postloops->posts[ $related_loop ] ))
						$criteria['post__not_in'] = array_merge( (array) $criteria['post__not_in'] , $postloops->posts[ $related_loop ][ $instance['blog'] ] );
					else
						echo '<!-- error: related post loop is not available or not from this blog -->';
				}
			}
			else if( 'similar' == $instance['relationship'] && count( (array) $instance['relatedto'] ))
			{
				if( ! class_exists( 'bSuite_bSuggestive' ) )
					require_once( dirname( __FILE__) .'/bsuggestive.php' );

				foreach( $instance['relatedto'] as $related_loop => $temp )
				{
					if( isset( $postloops->posts[ $related_loop ] ) && $instance['blog'] == key( $postloops->posts[ $related_loop ] ))
						$posts_for_related = array_merge( (array) $posts_for_related , $postloops->posts[ $related_loop ][ $instance['blog'] ] );
					else
						echo '<!-- error: related post loop is not available or not from this blog -->';
				}

				$count = ceil( 1.5 * $instance['count'] );
				if( 10 > $count )
					$count = 10;

				$criteria['post__in'] = array_merge( 
					(array) $instance['post__in'] , 
					array_slice( (array) bSuite_bSuggestive::getposts( $posts_for_related ) , 0 , $count )
				);
			}


//echo '<pre>'. print_r( $instance , TRUE ) .'</pre>';
//echo '<pre>'. print_r( $criteria , TRUE ) .'</pre>';
			if( 0 < $instance['blog'] && $instance['blog'] !== $blog_id )
				switch_to_blog( $instance['blog'] ); // switch to the other blog

			$ourposts = new WP_Query( $criteria );
//print_r( $ourposts );
//echo '<pre>'. print_r( $ourposts , TRUE ) .'</pre>';
		}

		if( $ourposts->have_posts() )
		{

			$this->post_templates = (array) $postloops->get_templates('post');

			$postloops->current_postloop = $instance;

			$postloops->thumbnail_size = isset( $instance['thumbnail_size'] ) ? $instance['thumbnail_size'] : 'nines-thumbnail-small';

			$extra_classes = array();

			$extra_classes[] = str_replace( '9spot', 'nines' , sanitize_title_with_dashes( $this->post_templates[ $instance['template'] ]['name'] ));
			$extra_classes[] = 'widget-post_loop-'. sanitize_title_with_dashes( $instance['title'] );

			echo str_replace( 'class="', 'class="'. implode( ' ' , $extra_classes ) .' ' , $before_widget );
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'] );
			if ( $instance['title_show'] && $title )
				echo $before_title . $title . $after_title .'<div class="widget_subtitle">'. $instance['subtitle'] .'</div>';

			$offset_run = $offset_now = 1;

			// old actions
			$action_name = sanitize_title( basename( $instance['template'] , '.php' ));
			do_action( $action_name , 'before' , $ourposts , $postloops );

			// new actions
			$postloops->do_action( 'post' , $instance['template'], 'before' , $ourposts );

			while( $ourposts->have_posts() )
			{
				unset( $GLOBALS['pages'] ); // to address ticket: http://core.trac.wordpress.org/ticket/12651
				
				$ourposts->the_post();

				// weird feature to separate a single postloop into multiple widgets
				// set where in the loop we start the output
				if( ! empty( $instance['offset_start'] ) && ($instance['offset_start'] > $offset_now) )
				{
					$offset_now ++;
					continue;
				}
				// set how many we display
				if( ! empty( $instance['offset_run'] ) && ($instance['offset_run'] < $offset_run) )
				{
					continue;
				}
				
				$offset_run ++;

				global $id, $post;

				$instance['blog'] = absint( $instance['blog'] );

				// get the matching post IDs for the $postloops object
				$postloops->posts[ $this->number ][ $instance['blog'] ][] = $id;

				// get the matching terms by taxonomy
				$terms = get_object_term_cache( $id, (array) get_object_taxonomies( $post->post_type ) );
				if ( empty( $terms ))
					$terms = wp_get_object_terms( $id, (array) get_object_taxonomies( $post->post_type ) );

				// get the term taxonomy IDs for the $postloops object
				foreach( $terms as $term )
					$postloops->terms[ $this->number ][ $term->taxonomy ][ $term->term_id ]++;

				// old actions
				do_action( $action_name , 'post' , $ourposts , $postloops );

				// new actions
				$postloops->do_action( 'post' , $instance['template'] , '' , $ourposts );

			}

			// old actions
			do_action( $action_name , 'after' , $ourposts , $postloops );

			// new actions
			$postloops->do_action( 'post' , $instance['template'] , 'after' , $ourposts );

			echo $after_widget;
		}

		$postloops->restore_current_blog();

		unset( $postloops->current_postloop );

//print_r( $postloops );
	}

	function update( $new_instance, $old_instance ) {
		global $blog_id;

		$instance = $old_instance;

		$instance['title'] = wp_filter_nohtml_kses( $new_instance['title'] );
		$instance['subtitle'] = wp_filter_nohtml_kses( $new_instance['subtitle'] );
		$instance['title_show'] = absint( $new_instance['title_show'] );

		$instance['query'] = in_array( $new_instance['query'] , array( 'normal' , 'custom' )) ? $new_instance['query'] : 'normal';

		$instance['what'] = (array) array_intersect( (array) $this->get_post_types() , array_keys( $new_instance['what'] ));

		if( $this->control_blogs( $instance , FALSE , FALSE )) // check if the user has permissions to the previously set blog
		{

			$new_instance['blog'] = absint( $new_instance['blog'] );
			if( $this->control_blogs( $new_instance , FALSE , FALSE )) // check if the user has permissions to the wished-for blog
				$instance['blog'] = $new_instance['blog'];

			$instance['categoriesbool'] = in_array( $new_instance['categoriesbool'], array( 'in', 'and', 'not_in') ) ? $new_instance['categoriesbool']: '';
			$instance['categories_in'] = array_filter( array_map( 'absint', $new_instance['categories_in'] ));
			$instance['categories_in_related'] = (int) $new_instance['categories_in_related'];
			$instance['categories_not_in'] = array_filter( array_map( 'absint', $new_instance['categories_not_in'] ));
			$instance['categories_not_in_related'] = (int) $new_instance['categories_not_in_related'];
			$instance['tagsbool'] = in_array( $new_instance['tagsbool'], array( 'in', 'and', 'not_in') ) ? $new_instance['tagsbool']: '';
			$tag_name = '';
			$instance['tags_in'] = array();
			foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tags_in'] )))) as $tag_name )
			{
				if( $temp = is_term( $tag_name, 'post_tag' ))
					$instance['tags_in'][] = $temp['term_id'];
			}
			$instance['tags_in_related'] = (int) $new_instance['tags_in_related'];
			$tag_name = '';
			$instance['tags_not_in'] = array();
			foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tags_not_in'] )))) as $tag_name )
			{
				if( $temp = is_term( $tag_name, 'post_tag' ))
					$instance['tags_not_in'][] = $temp['term_id'];
			}
			$instance['tags_not_in_related'] = (int) $new_instance['tags_not_in_related'];

			if( $instance['what'] <> 'normal' )
			{
				foreach( get_object_taxonomies( $instance['what'] ) as $taxonomy )
				{
					if( $taxonomy == 'category' || $taxonomy == 'post_tag' )
						continue;

					$instance['tax_'. $taxonomy .'_bool'] = in_array( $new_instance['tax_'. $taxonomy .'_bool'], array( 'in', 'and', 'not_in') ) ? $new_instance['tax_'. $taxonomy .'_bool']: '';
					$tag_name = '';
					$instance['tax_'. $taxonomy .'_in'] = array();
					foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tax_'. $taxonomy .'_in'] )))) as $tag_name )
					{
						if( $temp = is_term( $tag_name, $taxonomy ))
							$instance['tax_'. $taxonomy .'_in'][] = $temp['term_id'];
					}

					$instance['tax_'. $taxonomy .'_in_related'] = (int) $new_instance['tax_'. $taxonomy .'_in_related'];

					$tag_name = '';
					$instance['tax_'. $taxonomy .'_not_in'] = array();
					foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tax_'. $taxonomy .'_not_in'] )))) as $tag_name )
					{
						if( $temp = is_term( $tag_name, $taxonomy ))
							$instance['tax_'. $taxonomy .'_not_in'][] = $temp['term_id'];
					}

					$instance['tax_'. $taxonomy .'_not_in_related'] = (int) $new_instance['tax_'. $taxonomy .'_not_in_related'];
				}
			}

			$instance['post__in'] = array_filter( array_map( 'absint', explode( ',', $new_instance['post__in'] )));
			$instance['post__not_in'] = array_filter( array_map( 'absint', explode( ',', $new_instance['post__not_in'] )));
			$instance['comments'] = in_array( $new_instance['comments'], array( 'unset', 'yes', 'no' ) ) ? $new_instance['comments']: '';
		}
		$instance['activity'] = in_array( $new_instance['activity'], array( 'pop_most', 'pop_least', 'pop_recent', 'comment_recent', 'comment_few') ) ? $new_instance['activity']: '';
		$instance['age_bool'] = in_array( $new_instance['age_bool'], array( 'newer', 'older') ) ? $new_instance['age_bool']: '';
		$instance['age_num'] = absint( $new_instance['age_num'] );
		$instance['age_unit'] = in_array( $new_instance['age_unit'], array( 'day', 'month', 'year') ) ? $new_instance['age_unit']: '';
		$instance['agestrtotime'] = strtotime( $new_instance['agestrtotime'] ) ? $new_instance['agestrtotime'] : '';
		$instance['relationship'] = in_array( $new_instance['relationship'], array( 'similar', 'excluding') ) ? $new_instance['relationship']: '';
		$instance['relatedto'] = array_filter( (array) array_map( 'intval', (array) $new_instance['relatedto'] ));
		$instance['count'] = absint( $new_instance['count'] );
		$instance['order'] = in_array( $new_instance['order'], array( 'age_new', 'age_old', 'title_az', 'title_za', 'comment_new', 'pop_recent', 'rand' ) ) ? $new_instance['order']: '';
		$instance['template'] = wp_filter_nohtml_kses( $new_instance['template'] );
		$instance['offset_run'] = empty( $new_instance['offset_run'] ) ? '' : absint( $new_instance['offset_run'] );
		$instance['offset_start'] = empty( $new_instance['offset_start'] ) ? '' : absint( $new_instance['offset_start'] );
in_array( $new_instance['thumbnail_size'], (array) get_intermediate_image_sizes() ) ? $new_instance['thumbnail_size']: '';
		if( function_exists( 'get_intermediate_image_sizes' ))
			$instance['thumbnail_size'] = in_array( $new_instance['thumbnail_size'], (array) get_intermediate_image_sizes() ) ? $new_instance['thumbnail_size']: '';
		$instance['columns'] = absint( $new_instance['columns'] );

		$this->justupdated = TRUE;

/*
var_dump( $new_instance['categories_in_related'] );
var_dump( $instance['categories_in_related'] );
die;
*/
		return $instance;
	}

	function form( $instance ) {
		global $blog_id, $postloops, $bsuite;

		// reset the instances var, in case a new widget was added
		$postloops->get_instances();

		//Defaults

		$instance = wp_parse_args( (array) $instance, 
			array( 
				'what' => 'normal', 
				'template' => 'a_default_full.php',
				'blog' => $blog_id,
				) 
			);

		$title = esc_attr( $instance['title'] );
		$subtitle = esc_attr( $instance['subtitle'] );

?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			<label for="<?php echo $this->get_field_id( 'title_show' ) ?>"><input id="<?php echo $this->get_field_id( 'title_show' ) ?>" name="<?php echo $this->get_field_name( 'title_show' ) ?>" type="checkbox" value="1" <?php echo ( $instance[ 'title_show' ] ? 'checked="checked"' : '' ) ?>/> Show Title?</label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('subtitle'); ?>"><?php _e('Sub-title'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('subtitle'); ?>" name="<?php echo $this->get_field_name('subtitle'); ?>" type="text" value="<?php echo $subtitle; ?>" />
		</p>

		<!-- Query type -->
		<div id="<?php echo $this->get_field_id('query'); ?>-container" class="postloop container querytype_normal posttype_normal">
			<label for="<?php echo $this->get_field_id('query'); ?>"><?php _e( 'What to show' ); ?></label>
			<div id="<?php echo $this->get_field_id('query'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('query'); ?>" id="<?php echo $this->get_field_id('query'); ?>" class="widefat postloop querytype_selector">
						<option value="normal" <?php selected( $instance['query'], 'normal' ); ?>><?php _e('The default content'); ?></option>
						<option value="custom" <?php selected( $instance['query'], 'custom' ); ?>><?php _e('Custom content'); ?></option>
					</select>
				</p>
			</div>
		</div>

		<!-- Post type -->
		<div id="<?php echo $this->get_field_id('what'); ?>-container" class="postloop container querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('what'); ?>"><?php _e( 'Selecting what kind of content' ); ?></label>
			<div id="<?php echo $this->get_field_id('what'); ?>-contents" class="contents hide-if-js">
				<p>
					<ul>
						<?php foreach( (array) $this->get_post_types() as $type ) : $type = get_post_type_object( $type ); ?>
							<li><label for="<?php echo $this->get_field_id( 'what-'. esc_attr( $type->name )); ?>"><input id="<?php echo $this->get_field_id( 'what-'. esc_attr( $type->name )); ?>" name="<?php echo $this->get_field_name( 'what' ) .'['. esc_attr( $type->name ) .']'; ?>" type="checkbox" value="1" <?php echo ( isset( $instance[ 'what' ][ $type->name ] ) ? 'checked="checked" class="open-on-value" ' : 'class="checkbox"' ); ?>/> <?php echo $type->labels->name; ?></label></li>
						<?php endforeach; ?>

					</ul>
				</p>
			</div>
		</div>
<?php
		// from what blog?
		if( $this->control_blogs( $instance )):
?>

		<div id="<?php echo $this->get_field_id('categories'); ?>-container" class="postloop container hide-if-js <?php echo $this->tax_posttype_classes('category'); ?>">
			<label for="<?php echo $this->get_field_id('categoriesbool'); ?>"><?php _e( 'Categories' ); ?></label>
			<div id="<?php echo $this->get_field_id('categories'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('categoriesbool'); ?>" id="<?php echo $this->get_field_id('categoriesbool'); ?>" class="widefat">
						<option value="in" <?php selected( $instance['categoriesbool'], 'in' ); ?>><?php _e('Any of these categories'); ?></option>
						<option value="and" <?php selected( $instance['categoriesbool'], 'and' ); ?>><?php _e('All of these categories'); ?></option>
					</select>
					<ul><?php echo $this->control_categories( $instance , 'categories_in' ); ?></ul>
				</p>
		
				<p>
					<label for="<?php echo $this->get_field_id('categories_not_in'); ?>"><?php _e( 'Not in any of these categories' ); ?></label>
					<ul><?php echo $this->control_categories( $instance , 'categories_not_in' ); ?></ul>
				</p>
			</div>
		</div>

		<div id="<?php echo $this->get_field_id('tags'); ?>-container" class="postloop container hide-if-js <?php echo $this->tax_posttype_classes('post_tag'); ?>">
			<label for="<?php echo $this->get_field_id('tagsbool'); ?>"><?php _e( 'Tags' ); ?></label>
			<div id="<?php echo $this->get_field_id('tags'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('tagsbool'); ?>" id="<?php echo $this->get_field_id('tagsbool'); ?>" class="widefat">
						<option value="in" <?php selected( $instance['tagsbool'], 'in' ); ?>><?php _e('Any of these tags'); ?></option>
						<option value="and" <?php selected( $instance['tagsbool'], 'and' ); ?>><?php _e('All of these tags'); ?></option>
					</select>
		
					<?php
					$tags_in = array();
					foreach( (array) $instance['tags_in'] as $tag_id ){
						$temp = get_term( $tag_id, 'post_tag' );
						$tags_in[] = $temp->name;
					}
					?>
					<input type="text" value="<?php echo implode( ', ', (array) $tags_in ); ?>" name="<?php echo $this->get_field_name('tags_in'); ?>" id="<?php echo $this->get_field_id('tags_in'); ?>" class="widefat <?php if( count( (array) $tags_in )) echo 'open-on-value'; ?>" />
					<br />
					<small><?php _e( 'Tags, separated by commas.' ); ?></small>

					<br />And terms from<br /><select name="<?php echo $this->get_field_name( 'tags_in_related' ); ?>" id="<?php echo $this->get_field_id( 'tags_in_related' ); ?>" class="widefat <?php if( $instance[ 'tags_in_related' ] ) echo 'open-on-value'; ?>">
						<option value="0" '. <?php selected( (int) $instance[ 'tags_in_related' ] , 0 ) ?> .'></option>
<?php
						foreach( $postloops->instances as $number => $loop ){
							if( $number == $this->number )
								continue;
				
							echo '<option value="'. $number .'" '. selected( (int) $instance[ 'tags_in_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
						}
?>

					</select></li>
				</p>
		
				<p>
					<label for="<?php echo $this->get_field_id('tags_not_in'); ?>"><?php _e( 'With none of these tags' ); ?></label>
					<?php
					$tags_not_in = array();
					foreach( (array) $instance['tags_not_in'] as $tag_id ){
						$temp = get_term( $tag_id, 'post_tag' );
						$tags_not_in[] = $temp->name;
					}
					?>
					<input type="text" value="<?php echo implode( ', ', (array) $tags_not_in ); ?>" name="<?php echo $this->get_field_name('tags_not_in'); ?>" id="<?php echo $this->get_field_id('tags_not_in'); ?>" class="widefat <?php if( count( (array) $tags_not_in )) echo 'open-on-value'; ?>" />
					<br />
					<small><?php _e( 'Tags, separated by commas.' ); ?></small>

					<br />And terms from<br /><select name="<?php echo $this->get_field_name( 'tags_not_in_related' ); ?>" id="<?php echo $this->get_field_id( 'tags_not_in_related' ); ?>" class="widefat <?php if( $instance[ 'tags_not_in_related' ] ) echo 'open-on-value'; ?>">
						<option value="0" '. <?php selected( (int) $instance[ 'tags_not_in_related' ] , 0 ) ?> .'></option>
<?php
						foreach( $postloops->instances as $number => $loop ){
							if( $number == $this->number )
								continue;
				
							echo '<option value="'. $number .'" '. selected( (int) $instance[ 'tags_not_in_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
						}
?>

					</select></li>
				</p>
			</div>
		</div>

		<?php $this->control_taxonomies( $instance , $instance['what'] ); ?>

		<div id="<?php echo $this->get_field_id('post__in'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('post__in'); ?>"><?php _e( 'Matching any post ID' ); ?></label>
			<div id="<?php echo $this->get_field_id('post__in'); ?>-contents" class="contents hide-if-js">
				<p>
					<input type="text" value="<?php echo implode( ', ', (array) $instance['post__in'] ); ?>" name="<?php echo $this->get_field_name('post__in'); ?>" id="<?php echo $this->get_field_id('post__in'); ?>" class="widefat <?php if( count( (array) $instance['post__in'] )) echo 'open-on-value'; ?>" />
					<br />
					<small><?php _e( 'Page IDs, separated by commas.' ); ?></small>
				</p>
		
				<p>
					<label for="<?php echo $this->get_field_id('post__not_in'); ?>"><?php _e( 'Excluding all these post IDs' ); ?></label> <input type="text" value="<?php echo implode( ', ', (array) $instance['post__not_in'] ); ?>" name="<?php echo $this->get_field_name('post__not_in'); ?>" id="<?php echo $this->get_field_id('post__not_in'); ?>" class="widefat <?php if( count( (array) $instance['post__not_in'] )) echo 'open-on-value'; ?>" />
					<br />
					<small><?php _e( 'Page IDs, separated by commas.' ); ?></small>
				</p>
			</div>
		</div>

		<div id="<?php echo $this->get_field_id('comments'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('comments'); ?>"><?php _e( 'Comments' ); ?></label>
			<div id="<?php echo $this->get_field_id('comments'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('comments'); ?>" id="<?php echo $this->get_field_id('comments'); ?>" class="widefat <?php if( 'unset' <> $instance['comments'] ) echo 'open-on-value'; ?>">
						<option value="unset" <?php selected( $instance['comments'], 'unset' ); ?>><?php _e(''); ?></option>
						<option value="yes" <?php selected( $instance['comments'], 'yes' ); ?>><?php _e('Has comments'); ?></option>
						<option value="no" <?php selected( $instance['comments'], 'no' ); ?>><?php _e('Does not have comments'); ?></option>
					</select>
				</p>
			</div>
		</div>

<?php 
		// go back to the other blog
		endif;
		$postloops->restore_current_blog(); 
?>

		<div id="<?php echo $this->get_field_id('age'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('age_num'); ?>"><?php _e('Date published'); ?></label>
			<div id="<?php echo $this->get_field_id('age'); ?>-contents" class="contents hide-if-js">
				<p>
					<select id="<?php echo $this->get_field_id('age_bool'); ?>" name="<?php echo $this->get_field_name('age_bool'); ?>">
						<option value="newer" <?php selected( $instance['age_bool'], 'newer' ) ?>>Newer than</option>
						<option value="older" <?php selected( $instance['age_bool'], 'older' ) ?>>Older than</option>
					</select>
					<input type="text" value="<?php echo $instance['age_num']; ?>" name="<?php echo $this->get_field_name('age_num'); ?>" id="<?php echo $this->get_field_id('age_num'); ?>" size="1" class="<?php if( 0 < $instance['age_num'] ) echo 'open-on-value'; ?>" />
					<select id="<?php echo $this->get_field_id('age_unit'); ?>" name="<?php echo $this->get_field_name('age_unit'); ?>">
						<option value="day" <?php selected( $instance['age_unit'], 'day' ) ?>>Day(s)</option>
						<option value="month" <?php selected( $instance['age_unit'], 'month' ) ?>>Month(s)</option>
						<option value="year" <?php selected( $instance['age_unit'], 'year' ) ?>>Year(s)</option>
					</select>
				</p>
			</div>
		</div>

		<?php if( $other_instances = $this->control_instances( $instance['relatedto'] )): ?>
			<div id="<?php echo $this->get_field_id('relationship'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
				<label for="<?php echo $this->get_field_id('relationship'); ?>"><?php _e('Related to other posts'); ?></label>
				<div id="<?php echo $this->get_field_id('relationship'); ?>-contents" class="contents hide-if-js">
					<p>
						<select id="<?php echo $this->get_field_id('relationship'); ?>" name="<?php echo $this->get_field_name('relationship'); ?>">
							<option value="excluding" <?php selected( $instance['relationship'], 'excluding' ) ?>>Excluding those</option>
							<option value="similar" <?php selected( $instance['relationship'], 'similar' ) ?>>Similar to</option>
						</select>
						<?php _e('items shown in'); ?>
						<ul>
						<?php echo $other_instances; ?>
						</ul>
					</p>
				</div>
			</div>
		<?php endif; ?>

		<div id="<?php echo $this->get_field_id('count'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('count'); ?>"><?php _e( 'Number of items to show' ); ?></label>
			<div id="<?php echo $this->get_field_id('count'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('count'); ?>" id="<?php echo $this->get_field_id('count'); ?>" class="widefat">
					<?php for( $i = 1; $i < 51; $i++ ){ ?>
						<option value="<?php echo $i; ?>" <?php selected( $instance['count'], $i ); ?>><?php echo $i; ?></option>
					<?php } ?>
					</select>
				</p>
			</div>
		</div>

		<div id="<?php echo $this->get_field_id('order'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('order'); ?>"><?php _e( 'Ordered by' ); ?></label>
			<div id="<?php echo $this->get_field_id('order'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('order'); ?>" id="<?php echo $this->get_field_id('order'); ?>" class="widefat">
							<option value="age_new" <?php selected( $instance['order'], 'age_new' ); ?>><?php _e('Newest first'); ?></option>
							<option value="age_old" <?php selected( $instance['order'], 'age_old' ); ?>><?php _e('Oldest first'); ?></option>
							<option value="comment_new" <?php selected( $instance['order'], 'comment_new' ); ?>><?php _e('Recently commented'); ?></option>
							<option value="title_az" <?php selected( $instance['order'], 'title_az' ); ?>><?php _e('Title A-Z'); ?></option>
							<option value="title_za" <?php selected( $instance['order'], 'title_za' ); ?>><?php _e('Title Z-A'); ?></option>
							<?php if( is_object( $bsuite )): ?>
								<option value="pop_recent" <?php selected( $instance['order'], 'pop_recent' ); ?>><?php _e('Recently Popular'); ?></option>
							<?php endif; ?>
							<option value="rand" <?php selected( $instance['order'], 'rand' ); ?>><?php _e('Random'); ?></option>
					</select>
				</p>
			</div>
		</div>

		<div id="<?php echo $this->get_field_id('template'); ?>-container" class="postloop container querytype_normal posttype_normal">
			<label for="<?php echo $this->get_field_id('template'); ?>"><?php _e( 'Template' ); ?></label>
			<div id="<?php echo $this->get_field_id('template'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('template'); ?>" id="<?php echo $this->get_field_id('template'); ?>" class="widefat">
						<?php $this->control_template_dropdown( $instance['template'] ); ?>
					</select>
				</p>
			</div>
		</div>

		<?php
		// weird feature to separate a single postloop into multiple widgets
		?>
		<div id="<?php echo $this->get_field_id('offset'); ?>-container" class="postloop container querytype_normal posttype_normal">
			<label for="<?php echo $this->get_field_id('offset'); ?>"><?php _e( 'Loop offset' ); ?></label>
			<div id="<?php echo $this->get_field_id('offset'); ?>-contents" class="contents hide-if-js">
				<p>
					<label for="<?php echo $this->get_field_id('offset_run'); ?>"><?php _e( 'From items in the loop, show N items' ); ?></label>
					<select name="<?php echo $this->get_field_name('offset_run'); ?>" id="<?php echo $this->get_field_id('offset_run'); ?>" class="widefat">
					<option value="" <?php selected( $instance['offset_run'], $i ); ?>></option>
					<?php for( $i = 1; $i < 51; $i++ ){ ?>
						<option value="<?php echo $i; ?>" <?php selected( $instance['offset_run'], $i ); ?>><?php echo $i; ?></option>
					<?php } ?>
					</select>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id('offset_start'); ?>"><?php _e( 'Starting with the item' ); ?></label>
					<select name="<?php echo $this->get_field_name('offset_start'); ?>" id="<?php echo $this->get_field_id('offset_start'); ?>" class="widefat">
					<option value="" <?php selected( $instance['offset_start'], $i ); ?>></option>
					<?php for( $i = 1; $i < 51; $i++ ){ ?>
						<option value="<?php echo $i; ?>" <?php selected( $instance['offset_start'], $i ); ?>><?php echo $i; ?></option>
					<?php } ?>
					</select>
				</p>
			</div>
		</div>
<?php
		if( function_exists( 'get_intermediate_image_sizes' ))
		{
?>
			<div id="<?php echo $this->get_field_id('thumbnail_size'); ?>-container" class="postloop container querytype_normal posttype_normal">
				<label for="<?php echo $this->get_field_id('thumbnail_size'); ?>"><?php _e( 'Thumbnail Size' ); ?></label>
				<div id="<?php echo $this->get_field_id('thumbnail_size'); ?>-contents" class="contents hide-if-js">
					<p>
						<select name="<?php echo $this->get_field_name('thumbnail_size'); ?>" id="<?php echo $this->get_field_id('thumbnail_size'); ?>" class="widefat">
							<?php $this->control_thumbnails( $instance['thumbnail_size'] ); ?>
						</select>
					</p>
				</div>
			</div>
<?php
		}
?>


<?php
		if( $this->justupdated )
		{
?>
<script type="text/javascript">
	postloops_widgeteditor_update( '<?php echo $this->get_field_id('title'); ?>' );
</script>

<?php
		}
	}



	function control_blogs( $instance , $do_output = TRUE , $switch = TRUE ){
		/*
		Return values:
		TRUE: The user has permission to the currently selected blog
		FALSE: The user does not have permission to the currently selected blog. This disables post selection criteria so that the unprivileged user can't reveal more posts than the privileged user had previously elected to show.
		
		Output:
		If $do_output is TRUE the function will echo out a select list of blogs available to the user.
		
		Blog switching:
		If $switch is TRUE and the user has permission to the selected blog (and the selected blog is not the current blog), the function will switch to that blog before returning TRUE.
		*/

		// define( 'BSUITE_ALLOW_BLOG_SWITCH' , FALSE ); to prevent any blog switching
		if( defined( 'BSUITE_ALLOW_BLOG_SWITCH' ) && ! BSUITE_ALLOW_BLOG_SWITCH )
			return TRUE; // We might be in MU, but switch_to_blog() isn't allowed

		global $current_user, $blog_id, $bsuite;

		if( is_object( $bsuite ) && ! $bsuite->is_mu )
			return TRUE; // The user has permission by virtue of it not being MU

		$blogs = $this->get_blog_list( $current_user->ID );

		if( ! $blogs )
			return TRUE; // There was an error, but we assume the user has permission

		if( ! $instance['blog'] ) // the blog isn't set, so we assume it's the current blog
			$instance['blog'] = $blog_id;

		foreach( (array) $blogs as $item )
		{
			if( $item['blog_id'] == $instance['blog'] ) 
			{
				// The user has permisson in here, any return will be TRUE
				if( count( $blogs ) < 2 ) // user has permission, but there's only one choice
					return TRUE; // there's only one choice, and the user has permssion to it

				if( $do_output )
				{
					echo '<div id="'. $this->get_field_id('blog') .'-container" class="postloop container hide-if-js querytype_custom posttype_normal"><label for="'. $this->get_field_id('blog') .'">'. __( 'From' ) .'</label><div id="'. $this->get_field_id('blog') .'-contents" class="contents hide-if-js"><p><select name="'. $this->get_field_name('blog') .'" id="'. $this->get_field_id('blog') .'" class="widefat">';
					foreach( $this->get_blog_list( $current_user->ID ) as $blog )
					{
							?><option value="<?php echo $blog['blog_id']; ?>" <?php selected( $instance['blog'], $blog['blog_id'] ); ?>><?php echo $blog['blog_id'] == $blog_id ? __('This blog') : $blog['blogname']; ?></option><?php
					}
					echo '</select></p></div></div>';
				}

				if( $switch && ( $instance['blog'] <> $blog_id ))
					switch_to_blog( $instance['blog'] ); // switch to the other blog

				return TRUE; // the user has permission, and many choices
			}
		}

?>
		<div id="<?php echo $this->get_field_id('blog'); ?>-container" class="postloop container">
		<p>
			<label for="<?php echo $this->get_field_id('blog'); ?>"><?php _e( 'From' ); ?></label>
			<input type="text" value="<?php echo attribute_escape( get_blog_details( $instance['blog'] )->blogname ); ?>" name="<?php echo $this->get_field_name('blog'); ?>" id="<?php echo $this->get_field_id('blog'); ?>" class="widefat" disabled="disabled" />
		</p>
		</div>
<?php

		return FALSE; // the user doesn't have permission to the selected blog
	}

	function get_post_types()
	{
		return get_post_types( array( 'public' => TRUE , 'publicly_queryable' => TRUE , ) , 'names' , 'or' ); // trivia: 'pages' are public, but not publicly queryable
	}

	function get_blog_list( $current_user_id ){
		global $current_site, $wpdb;

		if( isset( $this->bloglist ))
			return $this->bloglist;

		if( is_super_admin() )
		{
			// I have to do this because get_blog_list() doesn't allow me to select private blogs
			// This query only executes for superadmins , and then only if BSUITE_ALLOW_BLOG_SWITCH isn't false
			foreach( (array) $wpdb->get_results( $wpdb->prepare("SELECT blog_id, public FROM $wpdb->blogs WHERE site_id = %d AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid), ARRAY_A ) as $k => $v )
			{
				$this->bloglist[ get_blog_details( $v['blog_id'] )->blogname . $k ] = array( 'blog_id' => $v['blog_id'] , 'blogname' => get_blog_details( $v['blog_id'] )->blogname . ( 1 == $v['public'] ? '' : ' ('. __('private') .')' ) );
			}
		}
		else
		{
			foreach( (array) get_blogs_of_user( $current_user_id ) as $k => $v )
			{
				$this->bloglist[ get_blog_details( $v->userblog_id )->blogname . $k ] = array( 'blog_id' => $v->userblog_id , 'blogname' => $v->blogname );
			}
		}

		ksort( $this->bloglist );
		return $this->bloglist;
	}



	function control_thumbnails( $default = 'nines-thumbnail-small' )
	{
		if( ! function_exists( 'get_intermediate_image_sizes' ))
			return;

		foreach ( (array) get_intermediate_image_sizes() as $size ) :
			if ( $default == $size )
				$selected = " selected='selected'";
			else
				$selected = '';
			echo "\n\t<option value=\"". $size .'" '. $selected .'>'. $size .'</option>';
		endforeach;
	}

	function control_categories( $instance , $whichfield = 'categories_in' )
	{

		// get the regular category list
		$list = array();
		$items = get_categories( array( 'style' => FALSE, 'echo' => FALSE, 'hierarchical' => FALSE ));
		foreach( $items as $item )
		{
			$list[] = '<li>
				<label for="'. $this->get_field_id( $whichfield .'-'. $item->term_id) .'"><input id="'. $this->get_field_id( $whichfield .'-'. $item->term_id) .'" name="'. $this->get_field_name( $whichfield ) .'['. $item->term_id .']" type="checkbox" value="1" '. ( isset( $instance[ $whichfield ][ $item->term_id ] ) ? 'checked="checked" class="open-on-value" ' : 'class="checkbox"' ) .'/> '. $item->name .'</label>
			</li>';
		}

		// get the select list to choose categories from items shown in another instance
		global $postloops;

		$related_instance_select = '<option value="0" '. selected( (int) $instance[ $whichfield .'_related' ] , 0 , FALSE ) .'></option>';
		foreach( $postloops->instances as $number => $loop ){
			if( $number == $this->number )
				continue;

			$related_instance_select .= '<option value="'. $number .'" '. selected( (int) $instance[ $whichfield .'_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
		}

		$list[] = '<li>Categories from items shown in<br /><select name="'. $this->get_field_name( $whichfield .'_related' ) .'" id="'. $this->get_field_id( $whichfield .'_related' ) .'" class="widefat '. ( $instance[ $whichfield .'_related' ] ?  'open-on-value' : '' ) .'">'. $related_instance_select . '</select></li>';
	
		return implode( "\n", $list );
	}
	
	function control_taxonomies( $instance , $post_type )
	{
		global $postloops;

		if( $post_type == 'normal' )
			return;

		foreach( get_object_taxonomies( $post_type ) as $taxonomy )
		{

			if( $taxonomy == 'category' || $taxonomy == 'post_tag' )
				continue;

			$tax = get_taxonomy( $taxonomy );
			$tax_name = $tax->label;
?>
			<div id="<?php echo $this->get_field_id( 'tax_'. $taxonomy ); ?>-container" class="postloop container hide-if-js <?php echo $this->tax_posttype_classes($taxonomy); ?>">
				<label for="<?php echo $this->get_field_id( 'tax_'. $taxonomy .'_bool' ); ?>"><?php echo $tax_name; ?></label>
				<div id="<?php echo $this->get_field_id( 'tax_'. $taxonomy ); ?>-contents" class="contents hide-if-js">
					<p>
						<select name="<?php echo $this->get_field_name('tax_'. $taxonomy .'_bool'); ?>" id="<?php echo $this->get_field_id('tax_'. $taxonomy .'_bool'); ?>" class="widefat">
							<option value="in" <?php selected( $instance['tax_'. $taxonomy .'_bool'], 'in' ); ?>><?php _e('Any of these terms'); ?></option>
							<option value="and" <?php selected( $instance['tax_'. $taxonomy .'_bool'], 'and' ); ?>><?php _e('All of these terms'); ?></option>
						</select>
			
						<?php
						$tags_in = array();
						foreach( (array) $instance['tax_'. $taxonomy .'_in'] as $tag_id ){
							$temp = get_term( $tag_id, $taxonomy );
							$tags_in[] = $temp->name;
						}
						?>
						<input type="text" value="<?php echo implode( ', ', (array) $tags_in ); ?>" name="<?php echo $this->get_field_name('tax_'. $taxonomy .'_in'); ?>" id="<?php echo $this->get_field_id('tax_'. $taxonomy .'_in'); ?>" class="widefat <?php if( count( (array) $tags_in )) echo 'open-on-value'; ?>" />
						<br />
						<small><?php _e( 'Terms, separated by commas.' ); ?></small>

						<br />And terms from<br /><select name="<?php echo $this->get_field_name( 'tax_'. $taxonomy .'_in_related' ); ?>" id="<?php echo $this->get_field_id( 'tax_'. $taxonomy .'_in_related' ); ?>" class="widefat <?php if( $instance[ 'tax_'. $taxonomy .'_in_related' ] ) echo 'open-on-value'; ?>">
							<option value="0" '. <?php selected( (int) $instance[ 'tax_'. $taxonomy .'_in_related' ] , 0 ) ?> .'></option>
<?php
							foreach( $postloops->instances as $number => $loop ){
								if( $number == $this->number )
									continue;
					
								echo '<option value="'. $number .'" '. selected( (int) $instance[ 'tax_'. $taxonomy .'_in_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
							}
?>
	
						</select></li>
					</p>
		
					<p>
						<label for="<?php echo $this->get_field_id('tax_'. $taxonomy .'_not_in'); ?>"><?php _e( 'With none of these terms' ); ?></label>
						<?php
						$tags_not_in = array();
						foreach( (array) $instance['tax_'. $taxonomy .'_not_in'] as $tag_id ){
							$temp = get_term( $tag_id, $taxonomy );
							$tags_not_in[] = $temp->name;
						}
						?>
						<input type="text" value="<?php echo implode( ', ', (array) $tags_not_in ); ?>" name="<?php echo $this->get_field_name('tax_'. $taxonomy .'_not_in'); ?>" id="<?php echo $this->get_field_id('tax_'. $taxonomy .'_not_in'); ?>" class="widefat <?php if( count( (array) $tags_not_in )) echo 'open-on-value'; ?>" />
						<br />
						<small><?php _e( 'Terms, separated by commas.' ); ?></small>

						<br />And terms from<br /><select name="<?php echo $this->get_field_name( 'tax_'. $taxonomy .'_not_in_related' ); ?>" id="<?php echo $this->get_field_id( 'tax_'. $taxonomy .'_not_in_related' ); ?>" class="widefat <?php if( $instance[ 'tax_'. $taxonomy .'_not_in_related' ] ) echo 'open-on-value'; ?>">
							<option value="0" '. <?php selected( (int) $instance[ 'tax_'. $taxonomy .'_not_in_related' ] , 0 ) ?> .'></option>
<?php
							foreach( $postloops->instances as $number => $loop ){
								if( $number == $this->number )
									continue;
					
								echo '<option value="'. $number .'" '. selected( (int) $instance[ 'tax_'. $taxonomy .'_not_in_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
							}
?>

						</select></li>
					</p>
				</div>
			</div>
<?php
		}
	}
	
	function control_instances( $selected = array() )
	{
		global $postloops;

		$list = array();
		foreach( $postloops->instances as $number => $instance )
		{
			if( $number == $this->number )
				continue;

			$list[] = '<li>
				<label for="'. $this->get_field_id( 'relatedto-'. $number ) .'"><input type="checkbox" value="'. $number .'" '.( in_array( $number, (array) $selected ) ? 'checked="checked" class="checkbox open-on-value"' : 'class="checkbox"' ) .' id="'. $this->get_field_id( 'relatedto-'. $number) .'" name="'. $this->get_field_name( 'relatedto' ) .'['. $number .']" /> '. $instance['title'] .'<small> (id:'. $number .')</small></label>
			</li>';
		}
	
		return implode( "\n", $list );
	}
	
	function control_template_dropdown( $default = '' )
	{
		global $postloops;

		foreach ( $postloops->get_actions('post') as $template => $info ) :
			if ( $default == $template )
				$selected = " selected='selected'";
			else
				$selected = '';
			echo "\n\t<option value=\"" .$template .'" '. $selected .'>'. $info['name'] .'</option>';
		endforeach;
	}

	function tax_posttype_classes( $taxonomy ) {
		$tax = get_taxonomy($taxonomy);

		if( ! $tax || count( $tax->object_type ) == 0 ) {
			return '';
		}

		return 'querytype_custom ' . implode( ' posttype_', $tax->object_type );
	}
}// end bSuite_Widget_Postloop




/**
 * PostLoop widget class
 *
 */
class bSuite_Widget_ResponseLoop extends WP_Widget {

	function bSuite_Widget_ResponseLoop() {
		$widget_ops = array('classname' => 'widget_responseloop', 'description' => __( 'Show comments and response tools') );
		$this->WP_Widget('responseloop', __('Comment/Response Loop'), $widget_ops);
/*
		global $postloops;
		if( ! is_array( $postloops->templates_response ))
			$postloops->get_templates( 'response' );

		$this->response_templates = &$postloops->templates_response;
*/
	}

	function widget( $args, $instance ) {
		global $wp_query, $postloops;

		$instance['id'] = absint( str_replace( 'responseloop-' , '' , $args['widget_id'] ));
		$instance['md5id'] = md5( $instance['id'] . $instance['template'] . $instance['email'] );

		$old_wp_query = clone $wp_query;

		extract( $args );

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? '' : $instance['title']);

		if( -1 == $instance['relatedto'] )
		{
			if( ! $wp_query->is_singular )
				return;

			$ourposts = &$wp_query;
		}
		else if( is_array( $postloops->posts[ $instance['relatedto'] ] ))
		{
			$post_ids = reset( $postloops->posts[ $instance['relatedto'] ] );

			if( 1 <> count( $post_ids ))
				return;

			$criteria['post_type'] = 'any';
			$criteria['post__in'] = $post_ids;

			$wp_query = new WP_Query( $criteria );

			if( 'page' == $wp_query->post->post_type )
				$wp_query->is_page = TRUE;
			else
				$wp_query->is_single = TRUE;

			$wp_query->is_singular = TRUE;

			$ourposts = &$wp_query;
		}
		else
		{
			return;
		}
	
		if( $ourposts->have_posts() ){
			echo str_replace( 'class="widget ', 'class="widget widget-response_loop-'. sanitize_title_with_dashes( $instance['title'] ) .' ' , $before_widget );

			if( ! empty( $instance['template'] ));
				$comments_template_function = create_function( '$a', "return '{$postloops->templates_response[ $instance['template'] ]['fullpath']}';" );
//				$comments_template_function = create_function( '$a', "return bsuite_comments_template_filter( '{$postloops->templates_response[ $instance['template'] ]['fullpath']}' );" );

			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'] );
			if ( $instance['title_show'] && $title )
				echo $before_title . $title . $after_title;

			while( $ourposts->have_posts() ){
				$ourposts->the_post();
				global $id, $post;

				$postloops->current_responseloop = $instance;

				if( ! empty( $instance['template'] ));
					add_filter( 'comments_template' , $comments_template_function );

				comments_template();

				if( ! empty( $instance['template'] ));
					remove_filter( 'comments_template' , $comments_template_function );
			}
			echo $after_widget;
		}

		unset( $postloops->current_responseloop );
		$wp_query = clone $old_wp_query;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = wp_filter_nohtml_kses( $new_instance['title'] );
		$instance['relatedto'] = intval( $new_instance['relatedto'] );
		$instance['template'] = wp_filter_nohtml_kses( $new_instance['template'] );
		$instance['email'] = sanitize_email( $new_instance['email'] );

/*
echo "<pre>";
//print_r($old_instance);
//print_r($new_instance);
print_r($instance);
echo "</pre>";
//die;
*/
		return $instance;
	}

	function form( $instance ) {
		global $postloops;
		//Defaults

		$instance = wp_parse_args( (array) $instance, 
			array( 
				'title' => __('Comments'),
				'relatedto' => -1,
				'template' => 'a_default_full.php',
				'email' => '',
				) 
			);

		$title = esc_attr( $instance['title'] );
?>

		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>


		<p>
			<label for="<?php echo $this->get_field_id('relatedto'); ?>"><?php _e( 'Show comments/response tools for:' ); ?></label>
			<select name="<?php echo $this->get_field_name('relatedto'); ?>" id="<?php echo $this->get_field_id('relatedto'); ?>" class="widefat">
				<?php $this->control_instances( $instance['relatedto'] ); ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('template'); ?>"><?php _e( 'Template:' ); ?></label>
			<select name="<?php echo $this->get_field_name('template'); ?>" id="<?php echo $this->get_field_id('template'); ?>" class="widefat">
				<?php $this->control_template_dropdown( $instance['template'] ); ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('email'); ?>"><?php _e('Email responses:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('email'); ?>" name="<?php echo $this->get_field_name('email'); ?>" type="text" value="<?php echo $instance['email']; ?>" />
		</p>

<?php
	}
	
	function control_instances( $default = -1 ){
		global $postloops, $blog_id;

		$blog_id = absint( $blog_id );

		// reset the instances var, in case a new widget was added
		$postloops->get_instances();

		$list = array();
		foreach( $postloops->instances as $number => $instance ){
			if( $instance['blog'] <> $blog_id )
				continue;

			if ( $default == $number )
				$selected = " selected='selected'";
			else
				$selected = '';

			$list[] = '<option value="'. $number .'" '. $selected .'>'. $instance['title'] .' (id:'. $number .')</option>';
		}
	
		echo implode( "\n\t", $list );
	}
	
	function control_template_dropdown( $default = '' )
	{
		global $postloops;
		$templates = $postloops->templates_response;
		array_unshift( $templates , 
			array( 
	            'name' => 'Default Comment Form',
	            'file' => '',
	            'fullpath' => '',
			)
		);

		foreach ( $templates as $template => $info ) :
			if ( $default == $template )
				$selected = " selected='selected'";
			else
				$selected = '';
			echo "\n\t<option value=\"" .$info['file'] .'" '. $selected .'>'. $info['name'] .'</option>';
		endforeach;
	}
}// end bSuite_Widget_ResponseLoop



/**
 * Pages widget class
 *
 */
class bSuite_Widget_Pages extends WP_Widget {

	function bSuite_Widget_Pages() {
		$widget_ops = array('classname' => 'widget_pages', 'description' => __( 'A buncha yo blog&#8217;s WordPress Pages') );
		$this->WP_Widget('pages', __('Pages'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );
		
		if( $instance['startpage'] < 0 ||  $instance['startpage'] === 'c' )
		{
			if( ! is_singular() ) // can't generate a menu in this situation
				return;
		}

		if( is_singular() )
		{
			$post = get_post( get_queried_object_id() ); // getting the post for use later

			if( $post->post_parent && ( ! isset( $post->ancestors ) || ! count( $post->ancestors )))
			{ // the post has a parent, but the ancestors array is unset or empty
				unset( $post->ancestors );
				_get_post_ancestors( $post );
				echo '<!-- pages_widget: explicitly looked up post ancestors -->';
			}
			echo '<!-- pages_widget: this appears to be page ID '. $post->ID .' with '. count( $post->ancestors ) .' ancestors -->';
		}

		if( is_404() )
			$instance['expandtree'] = 0;

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? FALSE : $instance['title']);
		$homelink = empty( $instance['homelink'] ) ? '' : $instance['homelink'];
		$sortby = empty( $instance['sortby'] ) ? 'menu_order' : $instance['sortby'];
		$exclude = empty( $instance['exclude'] ) ? '' : $instance['exclude'];
		$depth = isset( $instance['depth'] ) ? $instance['depth'] : 1;
		
		if( $instance['startpage'] < 0 )
		{
			// get the ancestor tree, including the current page
			$ancestors = $post->ancestors;
			array_unshift( $ancestors , $post->ID ); //append the current page to the ancestors array in the correct order

			// reverse the array so the slice can return empty if startpage is larger than the array
			$startpage = current( array_slice( array_reverse( (array) $ancestors ) , absint( $instance['startpage'] ) -1 , 1 ));
			if( ! $startpage )
				return;
		}
		else if( $instance['startpage'] >= 0 )
		{
			$startpage = $instance['startpage'];
		}
		else if( $instance['startpage'] == 'c' )
		{
			$startpage = $post->ID;
		}

		if ( $sortby == 'menu_order' )
			$sortby = 'menu_order, post_title';

		$out = wp_list_pages( array(
				'child_of' => $startpage, 
				'title_li' => '', 
				'echo' => 0, 
				'sort_column' => $sortby, 
				'exclude' => $exclude, 
				'depth' => $depth 
		));

		if( $instance['expandtree'] && ( $instance['startpage'] >= 0 ) && is_page() )
		{
			// get the ancestor tree, including the current page
			$ancestors = $post->ancestors;
			$ancestors[] = $post->ID;
			$pages = get_pages( array( 'include' => implode( ',', $ancestors )));

			if ( !empty( $pages )){
				$subtree .= walk_page_tree( $pages, 0, $post->ID, array() );

				// get any siblings, insert them into the tree
				if( count( $post->ancestors ) && ( $siblings = wp_list_pages( array( 'child_of' => array_shift( $ancestors ), 'title_li' => '', 'echo' => 0, 'sort_column' => $sortby, 'exclude' => $exclude, 'depth' => 1 )))){
					$subtree = preg_replace( '/<li.+?current_page_item.+?<\/li>/i', $siblings, $subtree );
				}

				// get any children, insert them into the tree
				if( $children = wp_list_pages( array( 'child_of' => $post->ID, 'title_li' => '', 'echo' => 0, 'sort_column' => $sortby, 'exclude' => $exclude, 'depth' => $depth ))){		
					$subtree = preg_replace( '/current_page_item[^<]*<a([^<]*)/i', 'current_page_item"><a\1<ul>'. $children .'</ul>', $subtree );
				}

				// insert this extended page tree into the larger list
				if( !empty( $subtree )){
					$out = preg_replace( '/<li[^>]*page-item-'. ( count( $post->ancestors ) ? end( $post->ancestors ) : $post->ID ) .'[^0-9][^>]*.*?<\/li>.*?($|<li)/si', $subtree .'\1', $out );
					reset( $post->ancestors );
				}
			}
		}
		
		if ( !empty( $out ) ) {
			echo $before_widget;
			if ( $title )
				echo $before_title . $title . $after_title;
		?>
		<ul>
			<?php if ( $homelink )
				echo '<li class="page_item page_item-home"><a href="'. get_option('home') .'">'. $homelink .'</a></li>';
			?>
			<?php echo $out; ?>
		</ul>
		<?php
			echo $after_widget;
		}
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['homelink'] = strip_tags( $new_instance['homelink'] );
		if ( in_array( $new_instance['sortby'], array( 'post_title', 'menu_order', 'ID' ))) {
			$instance['sortby'] = $new_instance['sortby'];
		} else {
			$instance['sortby'] = 'menu_order';
		}
		$instance['depth'] = absint( $new_instance['depth'] );
		$instance['startpage'] = $new_instance['startpage'] == 'c' ? 'c' : intval( $new_instance['startpage'] ) ;
		$instance['expandtree'] = absint( $new_instance['expandtree'] );
		$instance['exclude'] = strip_tags( $new_instance['exclude'] );

		return $instance;
	}

	function form( $instance ) {
		//Defaults
		$instance = wp_parse_args( (array) $instance, 
			array( 
				'sortby' => 'post_title', 
				'title' => '', 
				'exclude' => '', 
				'depth' => 1, 
				'startpage' => 0,
				'expandtree' => 1,
				'homelink' => sprintf( __('%s Home', 'Bsuite ') , get_bloginfo('name') ),
			)
		);

		$title = esc_attr( $instance['title'] );
		$homelink = esc_attr( $instance['homelink'] );
		$exclude = esc_attr( $instance['exclude'] );
	?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>
		<p>
			<label for="<?php echo $this->get_field_id('sortby'); ?>"><?php _e( 'Sort by:' ); ?></label>
			<select name="<?php echo $this->get_field_name('sortby'); ?>" id="<?php echo $this->get_field_id('sortby'); ?>" class="widefat">
				<option value="post_title"<?php selected( $instance['sortby'], 'post_title' ); ?>><?php _e('Page title'); ?></option>
				<option value="menu_order"<?php selected( $instance['sortby'], 'menu_order' ); ?>><?php _e('Page order'); ?></option>
				<option value="ID"<?php selected( $instance['sortby'], 'ID' ); ?>><?php _e( 'Page ID' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('depth'); ?>"><?php _e( 'Depth:' ); ?></label>
			<select name="<?php echo $this->get_field_name('depth'); ?>" id="<?php echo $this->get_field_id('depth'); ?>" class="widefat">
				<option value="1"<?php selected( $instance['depth'], '1' ); ?>><?php _e( '1' ); ?></option>
				<option value="2"<?php selected( $instance['depth'], '2' ); ?>><?php _e( '2' ); ?></option>
				<option value="3"<?php selected( $instance['depth'], '3' ); ?>><?php _e( '3' ); ?></option>
				<option value="4"<?php selected( $instance['depth'], '4' ); ?>><?php _e( '4' ); ?></option>
				<option value="5"<?php selected( $instance['depth'], '5' ); ?>><?php _e( '5' ); ?></option>
				<option value="6"<?php selected( $instance['depth'], '6' ); ?>><?php _e( '6' ); ?></option>
				<option value="7"<?php selected( $instance['depth'], '7' ); ?>><?php _e( '7' ); ?></option>
				<option value="0"<?php selected( $instance['depth'], '0' ); ?>><?php _e( 'All' ); ?></option>
			</select>
		</p>

		<p><label for="<?php echo $this->get_field_id('homelink'); ?>"><?php _e('Link to blog home:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('homelink'); ?>" name="<?php echo $this->get_field_name('homelink'); ?>" type="text" value="<?php echo $homelink; ?>" /><br /><small><?php _e( 'Optional, leave empty to hide.' ); ?></small></p>

		<p>
			<label for="<?php echo $this->get_field_id('startpage'); ?>"><?php _e( 'Start page hierarchy at:' ); ?></label>
			<?php echo preg_replace( 
				'#<select.*?>#i',

				'<select name="'. $this->get_field_name('startpage') .'" id="'. $this->get_field_id('startpage') .'" class="widefat">
				<option value="0"'. selected( $instance['startpage'], '0', FALSE ) .'>'. __( 'Root' ) .'</option>
				<option value="-1"'. selected( $instance['startpage'], '-1', FALSE ) .'>'. __( 'Root -1' ) .'</option>
				<option value="-2"'. selected( $instance['startpage'], '-2', FALSE ) .'>'. __( 'Root -2' ) .'</option>
				<option value="0">---------------------</option>
				<option value="c"'. selected( $instance['startpage'], 'c', FALSE ) .'>'. __( 'Current Page' ) .'</option>
				<option value="0">---------------------</option>',

				wp_dropdown_pages( array( 'echo' => 0 , 'selected' => ( $instance['startpage'] > 0  ? absint( $instance['startpage'] ) : 0 ) ))); ?>
		</p>

		<p><input id="<?php echo $this->get_field_id('expandtree'); ?>" name="<?php echo $this->get_field_name('expandtree'); ?>" type="checkbox" value="1" <?php if ( $instance['expandtree'] ) echo 'checked="checked"'; ?>/>
		<label for="<?php echo $this->get_field_id('expandtree'); ?>"><?php _e('Expand current page tree?'); ?></label></p>

		<p>
			<label for="<?php echo $this->get_field_id('exclude'); ?>"><?php _e( 'Exclude:' ); ?></label> <input type="text" value="<?php echo $exclude; ?>" name="<?php echo $this->get_field_name('exclude'); ?>" id="<?php echo $this->get_field_id('exclude'); ?>" class="widefat" />
			<br />
			<small><?php _e( 'Page IDs, separated by commas.' ); ?></small>
		</p>
<?php
	}
}// end bSuite_Widget_Pages



/**
 * Crumbs widget class
 *
 */
class bSuite_Widget_CategoryDescription extends WP_Widget {

	function bSuite_Widget_CategoryDescription() {
		$widget_ops = array('classname' => 'widget_categorydescription', 'description' => __( 'Displays the description for the currently displayed category, tag, or taxonomy archive page') );
		$this->WP_Widget('categorydescription', __('Category Description'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );

		if( is_tax() || is_tag() || is_category() ) 
			$category_description = term_description();
		else
			return;

		global $wp_query;
		$term = $wp_query->get_queried_object();
		$my_tag = &get_term( $term->term_id , $term->taxonomy , OBJECT , 'display' );
		
		if ( is_wp_error( $my_tag ) )
			return false;
		
		$my_tag_name =  $my_tag->name;
//		$my_tag_name = apply_filters( 'single_tag_title' , $my_tag->name );

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'] );
		$title = str_ireplace( '%term_name%', '<span class="term-name">'. $my_tag_name .'</span>', $title );

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		if ( ! empty( $category_description ) )
			echo '<div class="archive-meta">' . $category_description . '</div>';
		echo '<div class="clear"></div>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = wp_filter_nohtml_kses( $new_instance['title'] );

		return $instance;
	}

	function form( $instance ) {
		//Defaults
		$instance = wp_parse_args( (array) $instance, 
			array( 
				'title' => '%term_name% Archives', 
			)
		);

		$title = esc_attr( $instance['title'] );
?>

		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /><br /><small><?php _e( '<code>%term_name%</code> will be replaced.' ); ?></small>
		</p>
<?php
	}
}// end bSuite_Widget_CategoryDescription



/**
 * Crumbs widget class
 *
 */
class bSuite_Widget_Crumbs extends WP_Widget {

	function bSuite_Widget_Crumbs() {
		$widget_ops = array('classname' => 'widget_breadcrumbs', 'description' => __( 'A breadcrumb navigation path') );
		$this->WP_Widget('breadcrumbs', __('Breadcrumbs'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );

		wp_reset_query();

		global $wp_query;

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? '' : $instance['title']);
		$maxchars = absint( $instance['maxchars'] ) > 10 ? absint( $instance['maxchars'] ) : 10;

		$crumbs = array();

		if( !empty( $instance['homelink'] ))
			$crumbs[] = '<li class="bloghome"><a href="'. get_option('home') .'">'. $instance['homelink'] .'</a></li>';

		if( is_singular() ){
			setup_postdata( $wp_query->post );
			global $post, $page, $multipage;

			// get the ancestor tree, if exists
			$ancestors = array();
			if( is_array( $post->ancestors )){
				foreach( array_reverse( $post->ancestors ) as $post_id ){
					$crumbs[] = '<li><a href="'. get_permalink( $post_id ) .'"
					rel="bookmark" title="'. sprintf( __('Permanent Link to %s') , esc_attr( strip_tags( get_the_title( $post_id )))) .' ">'. ( strlen( get_the_title( $post_id )) > $maxchars ? trim( substr( get_the_title( $post_id ), 0, $maxchars )) .'&#8230;' : get_the_title( $post_id ) ) .'</a></li>';
				}
			}

			// add the current page to the tree
			$crumbs[] = '<li class="'. $post->post_type .'_item '. $post->post_type .'-item-'. $post->ID .' current_'. $post->post_type .'_item" ><a href="'. get_permalink( $post->ID ) .'" rel="bookmark" title="'. sprintf( __('Permanent Link to %s') , esc_attr( strip_tags( get_the_title( $post->ID )))) .'">'. ( strlen( get_the_title( $post->ID )) > $maxchars ? trim( substr( get_the_title( $post->ID ), 0, $maxchars )) .'&#8230;' : get_the_title( $post->ID ) ) .'</a></li>';

			//if this is a multi-page post/page...
			if( $multipage ){

				// generate a permalink to this page
				if ( 1 == $page ) {
					$link = get_permalink( $post->ID );
				} else {
					if ( '' == get_option('permalink_structure') || in_array($post->post_status, array('draft', 'pending')) )
						$link = get_permalink( $post->ID ) . '&amp;page='. $page;
					else
						$link = trailingslashit( get_permalink( $post->ID )) . user_trailingslashit( $page, 'single_paged' );
				}

				// add it to the crumbs
				$crumbs[] = '<li class="'. $post->post_type .'_item '. $post->post_type .'-item-'. $post->ID .' current_'. $post->post_type .'_item" ><a href="'. $link .'" rel="bookmark" title="'. sprintf( __('Permanent Link to page %d of %s') , (int) $page , esc_attr( strip_tags( get_the_title( $post->ID ))) ) .'">'. sprintf( __('Page %d') , (int) $page ) .'</a></li>';
			}
		}else{

			if( is_search() )
				$crumbs[] = '<li><a href="'. $link .'">'. __('Search') .'</a></li>';

//			if( is_paged() && $wp_query->query_vars['paged'] > 1 )
//				$page_text = sprintf( __('Page %d') , $wp_query->query_vars['paged'] );
		}

		if ( count( $crumbs ) ) {
			echo $before_widget;
//			if ( $title )
//				echo $before_title . $title . $after_title;
		?>
			<ul>
				<?php echo implode( "\n", $crumbs ); ?>
			</ul>
			<div class="clear"></div>
		<?php
			echo $after_widget;
		}
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['homelink'] = strip_tags( $new_instance['homelink'] );
		$instance['maxchars'] = absint( $new_instance['maxchars'] );

		return $instance;
	}

	function form( $instance ) {
		//Defaults
		$instance = wp_parse_args( (array) $instance, 
			array( 
				'title' => '', 
				'homelink' => get_option('blogname'),
				'maxchars' => 35,
			)
		);

		$title = esc_attr( $instance['title'] );
		$homelink = esc_attr( $instance['homelink'] );
?>

		<p>
			<label for="<?php echo $this->get_field_id('homelink'); ?>"><?php _e('Link to blog home:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('homelink'); ?>" name="<?php echo $this->get_field_name('homelink'); ?>" type="text" value="<?php echo $homelink; ?>" /><br /><small><?php _e( 'Optional, leave empty to hide.' ); ?></small>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('maxchars'); ?>"><?php _e('Maximum crumb length:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('maxchars'); ?>" name="<?php echo $this->get_field_name('maxchars'); ?>" type="text" value="<?php echo absint( $instance['maxchars'] ); ?>" /><br /><small><?php _e( 'Maximum number of characters per crumb.' ); ?></small>
		</p>
<?php
	}
}// end bSuite_Widget_Crumbs



/**
 * Pagednav widget class
 *
 */
class bSuite_Widget_Pagednav extends WP_Widget {

	function bSuite_Widget_Pagednav() {
		$widget_ops = array('classname' => 'widget_pagednav', 'description' => __( 'Prev/Next page navigation') );
		$this->WP_Widget('pagednav', __('Paged Navigation Links'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );

		wp_reset_query();

		global $wp_query, $wp_rewrite;

		if( ! $wp_query->is_singular )
		{
			$urlbase = preg_replace( '#/page/[0-9]+?(/+)?$#' , '/', remove_query_arg( 'paged' ) );
			$prettylinks = ( $wp_rewrite->using_permalinks() && ( !strpos( $urlbase , '?' )));
			
			$page_links = paginate_links( array(
				'base' => $urlbase . '%_%',
				'format' => $prettylinks ? user_trailingslashit( trailingslashit( 'page/%#%' )) : ( strpos( $urlbase , '?' ) ? '&paged=%#%' : '?paged=%#%' ),
				'total' => absint( $wp_query->max_num_pages ),
				'current' => absint( $wp_query->query_vars['paged'] ) ? absint( $wp_query->query_vars['paged'] ) : 1,
			));
			
			if ( $page_links )
				echo $before_widget . $page_links .'<div class="clear"></div>'. $after_widget;
		}
		else
		{
			echo $before_widget;
?>
			<div class="alignleft"><?php previous_post_link('&laquo; %link') ?></div>
			<div class="alignright"><?php next_post_link('%link &raquo;') ?></div>
			<div class="clear"></div>
<?php
			echo $after_widget;
		}
	}

}// end bSuite_Widget_Pagednav


function late_enqueue_script( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false )
{
	global $wp_scripts;

	// enqueue the named script
	wp_enqueue_script( $handle , $src , $deps , $ver , $in_footer );

	// resolve dependencies and place everything in the array of items to put in the footer
	$to_do_orig = (array) $wp_scripts->to_do;
	$wp_scripts->all_deps( array( $handle ));
	$wp_scripts->in_footer = array_merge( (array) $wp_scripts->in_footer , (array) array_diff( (array) $wp_scripts->to_do , $to_do_orig ) );
}

function late_enqueue_style( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' )
{
	global $wp_styles;

	// enqueue the named script
	wp_enqueue_style( $handle, $src, $deps, $ver, $media );

	// resolve dependencies and place everything in the array of items to put in the footer
	$to_do_orig = (array) $wp_styles->to_do;
	$wp_styles->all_deps( array( $handle ));
	$wp_styles->in_footer = array_merge( (array) $wp_styles->in_footer , (array) array_diff( (array) $wp_styles->to_do , $to_do_orig ) );

	add_filter( 'print_footer_scripts', 'bsuite_print_late_styles' );
}

function bsuite_print_late_styles()
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
		return;

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
}


// register these widgets
function bsuite_widgets_init() {
	register_widget( 'bSuite_Widget_PostLoop' );
	register_widget( 'bSuite_Widget_ResponseLoop' );


	register_widget( 'bSuite_Widget_CategoryDescription' );

	register_widget( 'bSuite_Widget_Crumbs' );

	register_widget( 'bSuite_Widget_Pagednav' );

	unregister_widget('WP_Widget_Pages');
	register_widget( 'bSuite_Widget_Pages' );
}
add_action('widgets_init', 'bsuite_widgets_init', 1);

/*
Reminder to self: the widget objects and their vars can be found in here:

global $wp_widget_factory;
print_r( $wp_widget_factory );

*/
