<?php

/**
 * bCMS_PostLoop_Widget class
 *
 */
class bCMS_PostLoop_Widget extends WP_Widget {
	public $slug = 'postloop';
	public $title = 'Post Loop';
	public $description = 'Build your own post loop';
	public $ttl = 307; // a prime number slightly longer than five minutes
	public $use_cache = TRUE;

	public function __construct()
	{
		$widget_ops = array(
			'classname' => 'widget_' . $this->slug,
			'description' => __( $this->description ),
		);
		parent::__construct( $this->slug, __( $this->title ), $widget_ops );

		add_filter( 'wijax-actions', array( $this, 'wjiax_actions' ) );
	}//end __construct

	public function wjiax_actions( $actions )
	{
		global $mywijax;
		foreach( bcms_postloop()->instances as $k => $v )
		{
			$actions[ $mywijax->encoded_name( 'postloop-'. $k ) ] = (object) array( 'key' => 'postloop-'. $k , 'type' => 'widget');
		}

		return $actions;
	}//end wjiax_actions

	public function widget( $args, $instance )
	{
		global $bsuite, $wpdb, $mywijax;

		$cached = new stdClass();

		$this->wijax_varname = $mywijax->encoded_name( $this->id );

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? '' : $instance['title']);

		if( 'normal' == $instance['query'] || 'normal' == $instance['what'] )
		{
			wp_reset_query();
			global $wp_query;

			$ourposts = $wp_query;

		}// end if
		elseif( preg_match( '/^predefined_/' , $instance['query'] ))
		{
			// get the predefined query object
			$ourposts = apply_filters( 'postloop_query_'. preg_replace( '/^predefined_/' , '' , $instance['query'] ) , FALSE, $instance );

			// check that we got something
			if( ! $ourposts || ! is_object( $ourposts ))
			{
				echo '<!-- error: the predefined query is invalid -->';
				return FALSE;
			}
		}// end elseif
		else
		{
//			$criteria['suppress_filters'] = TRUE;

			// don't enumerate the entire query
			$criteria['no_found_rows'] = TRUE;

			// post_type / what ('what' is for backwards compatibility)
			$criteria['post_type'] = array_values( array_intersect( (array) $this->get_post_types() , (array) $instance['what'] ));

			// status
			if( ! isset( $instance['status'] ) || ! is_array( $instance['status'] ) || empty( $instance['status'] ))
			{
				$criteria['post_status'] = array( 'publish' );
			}
			else
			{
				$criteria['post_status'] = array_keys( array_intersect_key( (array) $this->get_post_statuses() , $instance['status'] ));
			}

			if( in_array( $instance['what'], array( 'attachment', 'revision' )))
			{
				$criteria['post_status'] = 'inherit';
			}

			if( ! empty( $instance['categories_in'] ))
			{
				$criteria['category__'. ( in_array( $instance['categoriesbool'], array( 'in', 'and', 'not_in' )) ? $instance['categoriesbool'] : 'in' ) ] = array_keys( (array) $instance['categories_in'] );
			}

			if( isset( $instance['categories_in_related'] ) && $instance['categories_in_related'] )
			{
				$criteria['category__'. ( in_array( $instance['categoriesbool'], array( 'in', 'and', 'not_in' )) ? $instance['categoriesbool'] : 'in' ) ] = array_merge( (array) $criteria['category__'. ( in_array( $instance['categoriesbool'], array( 'in', 'and', 'not_in' )) ? $instance['categoriesbool'] : 'in' ) ], (array) array_keys( (array) bcms_postloop()->terms[ $instance['categories_in_related'] ]['category'] ) );
			}

			if( ! empty( $instance['categories_not_in'] ))
			{
				$criteria['category__not_in'] = array_keys( (array) $instance['categories_not_in'] );
			}

			if( isset( $instance['categories_not_in_related'] ) && $instance['categories_not_in_related'] )
			{
				$criteria['category__not_in'] = array_merge( (array) $criteria['category__not_in'] , (array) array_keys( (array) bcms_postloop()->terms[ $instance['categories_not_in_related'] ]['category'] ));
			}

			if( ! empty( $instance['tags_in'] ))
			{
				$criteria['tag__'. ( in_array( $instance['tagsbool'], array( 'in', 'and', 'not_in' )) ? $instance['tagsbool'] : 'in' ) ] = $instance['tags_in'];
			}

			if( isset( $instance['tags_in_related'] ) && $instance['tags_in_related'] )
			{
				$criteria['tag__'. ( in_array( $instance['tagsbool'], array( 'in', 'and', 'not_in' )) ? $instance['tagsbool'] : 'in' ) ] = array_merge( (array) $criteria['tag__'. ( in_array( $instance['tagsbool'], array( 'in', 'and', 'not_in' )) ? $instance['tagsbool'] : 'in' ) ], (array) array_keys( (array) bcms_postloop()->terms[ $instance['tags_in_related'] ]['post_tag'] ) );
			}

			if( ! empty( $instance['tags_not_in'] ))
			{
				$criteria['tag__not_in'] = $instance['tags_not_in'];
			}

			if( isset( $instance['tags_not_in_related'] ) && $instance['tags_not_in_related'] )
			{
				$criteria['tag__not_in'] = array_merge( (array) $criteria['tag__not_in'] , (array) array_keys( (array) bcms_postloop()->terms[ $instance['tags_not_in_related'] ]['post_tag'] ));
			}

			$tax_query = array();

			foreach( get_object_taxonomies( $criteria['post_type'] ) as $taxonomy )
			{
				if( $taxonomy == 'category' || $taxonomy == 'post_tag' )
				{
					continue;
				}

				$instance['tax_' . $taxonomy . '_in_related'] = isset( $instance['tax_' . $taxonomy . '_in_related'] ) ? $instance['tax_' . $taxonomy . '_in_related'] : '';
				$instance['tax_' . $taxonomy . '_in'] = isset( $instance['tax_' . $taxonomy . '_in'] ) ? $instance['tax_' . $taxonomy . '_in'] : array();
				$instance['tax_'. $taxonomy .'_bool'] = isset( $instance['tax_'. $taxonomy .'_bool'] ) ? $instance['tax_'. $taxonomy .'_bool'] : 'IN';
				$instance['tax_' . $taxonomy . '_not_in_related'] = isset( $instance['tax_' . $taxonomy . '_not_in_related'] ) ? $instance['tax_' . $taxonomy . '_not_in_related'] : '';
				$instance['tax_' . $taxonomy . '_not_in'] = isset( $instance['tax_' . $taxonomy . '_not_in'] ) ? $instance['tax_' . $taxonomy . '_not_in'] : array();

				if( $instance['tax_'. $taxonomy .'_in_related'] )
				{
					$instance['tax_'. $taxonomy .'_in'] = array_merge(
						(array) $instance['tax_'. $taxonomy .'_in'] ,
						(array) array_keys( (array) bcms_postloop()->terms[ $instance['tax_'. $taxonomy .'_in_related'] ][ $taxonomy ] )
					);
				}//end if

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
				{
					$instance['tax_'. $taxonomy .'_not_in'] = array_merge(
						(array) $instance['tax_'. $taxonomy .'_not_in'] ,
						(array) array_keys( (array) bcms_postloop()->terms[ $instance['tax_'. $taxonomy .'_not_in_related'] ][ $taxonomy ] )
					);
				}//end if

				if( count( $instance['tax_'. $taxonomy .'_not_in'] ))
				{
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field' => 'term_id',
						'terms' => $instance['tax_'. $taxonomy .'_not_in'],
						'operator' => 'NOT IN',
					);
				}
			}// end foreach

			if( count( $tax_query ))
			{
				$criteria['tax_query'] = $tax_query;
			}

			if( ! empty( $instance['post__in'] ))
			{
				$criteria['post__in'] = $instance['post__in'];
			}

			$criteria['post__not_in'] = isset( $instance['post__not_in'] ) ? (array) $instance['post__not_in'] : array();

			$instance['comments'] = isset( $instance['comments'] ) ? $instance['comments'] : '';
			switch( $instance['comments'] )
			{
				case 'yes':
					add_filter( 'posts_where', array( bcms_postloop() , 'posts_where_comments_yes_once' ), 10 );
					break;
				case 'no':
					add_filter( 'posts_where', array( bcms_postloop() , 'posts_where_comments_no_once' ), 10 );
					break;
				default:
					break;
			}// end switch

			$instance['age_num'] = isset( $instance['age_num'] ) ? $instance['age_num'] : 0;
			if( 0 < $instance['age_num'] )
			{
				bcms_postloop()->date_before = bcms_postloop()->date_since = date( 'Y-m-d' , strtotime( $instance['age_num'] .' '. $instance['age_unit'] .' ago' ));
				if( $instance['age_bool'] == 'older' )
				{
					add_filter( 'posts_where', array( bcms_postloop() , 'posts_where_date_before_once' ), 10 );
				}
				else
				{
					add_filter( 'posts_where', array( bcms_postloop() , 'posts_where_date_since_once' ), 10 );
				}
			}// end if

			if( isset( $_GET['wijax'] ) && absint( $_GET['paged'] ))
			{
				$criteria['paged'] = absint( $_GET['paged'] );
			}

			$criteria['showposts'] = absint( $instance['count'] );

			switch( $instance['order'] )
			{
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
					add_filter( 'posts_fields',		array( bcms_postloop() , 'posts_fields_recently_commented_once' ), 10 );
					add_filter( 'posts_join',		array( bcms_postloop() , 'posts_join_recently_commented_once' ), 10 );
					add_filter( 'posts_groupby',	array( bcms_postloop() , 'posts_groupby_recently_commented_once' ), 10 );
					add_filter( 'posts_orderby',	array( bcms_postloop() , 'posts_orderby_recently_commented_once' ), 10 );
					break;

				case 'comment_count':
					$criteria['orderby'] = 'comment_count';
					$criteria['order'] = 'DESC';
					break;

				case 'pop_recent':
					if( is_object( $bsuite ))
					{
						add_filter( 'posts_join',		array( bcms_postloop() , 'posts_join_recently_popular_once' ), 10 );
						add_filter( 'posts_orderby',	array( bcms_postloop() , 'posts_orderby_recently_popular_once' ), 10 );
					}
					break;

				case 'rand':
					$criteria['orderby'] = 'rand';
					break;

				case 'menu_order':
					$criteria['orderby'] = 'menu_order';
					$criteria['order'] = 'ASC';
					break;

				default:
					$criteria['orderby'] = 'post_date';
					$criteria['order'] = 'DESC';
					break;
			}// end switch

			$instance['relationship'] = isset( $instance['relationship'] ) ? $instance['relationship'] : '';
			if( 'excluding' == $instance['relationship'] && count( (array) $instance['relatedto'] ))
			{
				foreach( $instance['relatedto'] as $related_loop => $temp )
				{
					if( isset( bcms_postloop()->posts[ $related_loop ] ) )
					{
						$criteria['post__not_in'] = array_merge( $criteria['post__not_in'] , bcms_postloop()->posts[ $related_loop ] );
					}// end if
					else
					{
						echo '<!-- error: related post loop is not available -->';
					}// end else
				}// end foreach
			}// end if
			elseif( 'similar' == $instance['relationship'] && count( (array) $instance['relatedto'] ))
			{
				if( ! class_exists( 'bSuite_bSuggestive' ) )
				{
					require_once( dirname( __FILE__) .'/bsuggestive.php' );
				}

				foreach( $instance['relatedto'] as $related_loop => $temp )
				{
					if( isset( bcms_postloop()->posts[ $related_loop ] ))
						$posts_for_related = array_merge( (array) $posts_for_related , bcms_postloop()->posts[ $related_loop ] );
					else
						echo '<!-- error: related post loop is not available -->';
				}

				$count = ceil( 1.5 * $instance['count'] );
				if( 10 > $count )
					$count = 10;

				$criteria['post__in'] = array_merge(
					(array) $instance['post__in'] ,
					array_slice( (array) bSuite_bSuggestive::getposts( $posts_for_related ) , 0 , $count )
				);
			}// end elseif

			//echo '<pre>'. print_r( bcms_postloop() , TRUE ) .'</pre>';
			//echo '<pre>'. print_r( $instance , TRUE ) .'</pre>';

			// allow filtering of the criteria
			$criteria = apply_filters( 'postloop_criteria', $criteria, $instance );

			// print the post selection info for logged-in administrators
			if ( ! is_wijax() && is_user_logged_in() && current_user_can( 'edit_theme_options' ) && apply_filters( 'bcms_postloop_debug', TRUE ) )
			{
				echo "<!-- postloop criteria \n". esc_html( print_r( $criteria , TRUE )) .' -->';
			}

			// check the cache for posts
			// we only check the cache for custom post loops,
			// as the default loop is already queried and nobody wants to waste the effort
			$cachekey = md5( serialize( $criteria ) . serialize( $instance ) . 'q' );
			$cached = $this->use_cache ? wp_cache_get( $cachekey, 'bcmspostloop' ) : FALSE;

			if (
				! $cached ||
				( ! isset( $cached->time ) ) ||
				( time() > $cached->time + $this->ttl )
			)
			{
				if ( ! $cached )
				{
					$cached = new stdClass();
				}// end if

				// add a filter that inserts a comment that allows us to track the query
				bcms_postloop()->sql_comment = 'WP_Query defined in bCMS postloop widget: ' . $this->id;
				add_filter( 'posts_request', array( bcms_postloop(), 'posts_request_once' ) );

				// no cache exists, executing the query
				$ourposts = new WP_Query( $criteria );

				echo "\n<!-- postloop generated fresh on " . date( DATE_RFC822 ) . ' -->';

				// print the wp_query object for logged-in administrators
				if ( ! is_wijax() && is_user_logged_in() && current_user_can( 'edit_theme_options' ) && apply_filters( 'bcms_postloop_debug', TRUE ) )
				{
					$debug_copy = clone $ourposts;
					unset( $debug_copy->post );
					foreach ( $debug_copy->posts as $k => $v )
					{
						$debug_copy->posts[ $k ] = (object) array(
							'ID' => $v->ID,
							'post_date' => $v->post_date,
							'post_title' => $v->post_title,
						);
					}//end foreach

					echo "<!-- postloop wp_query obj (excludes posts) \n" . esc_html( print_r( $debug_copy, TRUE ) ) . ' -->';
				}//end if
			}//end if
			else
			{
				// we're loading from cache. Let's make sure the post ids are stored in bcms_postloop so
				// other widgets can reference them
				if ( isset( $cached->post_ids ) && count( $cached->post_ids ) )
				{
					bcms_postloop()->posts[ $this->number ] = $cached->post_ids;
				}//end if

				echo '<!-- postloop fetched from cache, generated on ' . date( DATE_RFC822, $cached->time ) . ' -->';
			}//end else
		}//end else

		// track whether or not the HTML is fresh.  If it is, we'll be caching
		$fresh_html = FALSE;

		// let's track the post ids in retrieved in the widget
		$post_ids = array();

		if ( ! isset( $cached->html ) && $ourposts->have_posts() )
		{
			// there isn't any cached HTML, mark as fresh
			$fresh_html = TRUE;

			// get the templates, thumbnail size, and other stuff
			$this->post_templates = (array) bcms_postloop()->get_templates('post');
			$cached->template = $this->post_templates[ $instance['template'] ];

			bcms_postloop()->current_postloop = $instance;

			bcms_postloop()->thumbnail_size = isset( $instance['thumbnail_size'] ) ? $instance['thumbnail_size'] : 'nines-thumbnail-small';

			// open an output buffer and start processing the loop
			ob_start();

			$offset_run = $offset_now = 1;

			// old actions
			$action_name = 'postloop_' . sanitize_title( basename( $instance['template'], '.php' ) );
			do_action( $action_name, 'before', $ourposts, bcms_postloop() );

			// new actions
			bcms_postloop()->do_action( 'post', $instance['template'], 'before', $ourposts, $this, $instance );

			while ( $ourposts->have_posts() )
			{
				unset( $GLOBALS['pages'] ); // to address ticket: http://core.trac.wordpress.org/ticket/12651

				$ourposts->the_post();

				global $id, $post;

				// get the matching post IDs for the bcms_postloop() object
				bcms_postloop()->posts[ $this->number ][] = $id;

				// weird feature to separate a single postloop into multiple widgets
				// set where in the loop we start the output
				if ( ! empty( $instance['offset_start'] ) && $instance['offset_start'] > $offset_now )
				{
					$offset_now++;
					continue;
				}//end if

				// set how many we display
				if ( ! empty( $instance['offset_run'] ) && $instance['offset_run'] < $offset_run )
				{
					continue;
				}//end if

				$offset_run++;

				// get the matching terms by taxonomy
				$terms = wp_get_object_terms( $id, (array) get_object_taxonomies( $post->post_type ) );

				// get the term taxonomy IDs for the bcms_postloop() object
				foreach ( $terms as $term )
				{
					if ( ! isset( bcms_postloop()->terms[ $this->number ] ) )
					{
						bcms_postloop()->terms[ $this->number ] = array( $term->taxonomy => array( $term->term_id => 0 ) );
					}//end if
					elseif ( ! isset( bcms_postloop()->terms[ $this->number ][ $term->taxonomy ] ) )
					{
						bcms_postloop()->terms[ $this->number ][ $term->taxonomy ] = array( $term->term_id => 0 );
					}//end elseif
					elseif ( ! isset( bcms_postloop()->terms[ $this->number ][ $term->taxonomy ][ $term->term_id ] ) )
					{
						bcms_postloop()->terms[ $this->number ][ $term->taxonomy ][ $term->term_id ] = 0;
					}//end elseif

					bcms_postloop()->terms[ $this->number ][ $term->taxonomy ][ $term->term_id ]++;
				}//end foreach

				// old actions
				do_action( $action_name, 'post', $ourposts, bcms_postloop() );

				// new actions
				bcms_postloop()->do_action( 'post', $instance['template'], '', $ourposts, $this, $instance );
			}//end while

			// old actions
			do_action( $action_name, 'after', $ourposts, bcms_postloop() );

			//If the template calls another postloop it can overwrite these values
			$preserve = array(
				'number' => $this->number,
				'ttl' => $this->ttl,
				'use_cache' => $this->use_cache,
			);

			// new actions
			bcms_postloop()->do_action( 'post', $instance['template'], 'after', $ourposts, $this, $instance );

			$cached->html = ob_get_clean();
			// end process the loop
		}//end if

		if ( isset( $cached->html ) )
		{
			if ( isset( $cached->instance ) )
			{
				$instance = $cached->instance;
			}//end if

			// figure out what classes to put on the widget
			$extra_classes = array();
			$extra_classes[] = str_replace( '9spot', 'nines', sanitize_title_with_dashes( $cached->template['name'] ) );
			$extra_classes[] = 'widget-post_loop-'. sanitize_title_with_dashes( $instance['title'] );
			$instance['extra_classes'] = isset( $instance['extra_classes'] ) ? (array) $instance['extra_classes'] : array();
			$extra_classes = array_merge( $extra_classes, $instance['extra_classes'] );

			// output the widget
			echo str_replace( 'class="', 'class="' . implode( ' ', $extra_classes ) .' ', $args['before_widget'] );
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'] );
			if ( isset( $instance['title_show'] ) && $instance['title_show'] && $title )
			{
				echo $args['before_title'] . $title . $args['after_title'] .'<div class="widget_subtitle">'. $instance['subtitle'] .'</div>';
			}//end if

			echo $cached->html . $args['after_widget'];

			// if there is something to cache, it is new, and we want to cache it, let's cache it.
			if ( $fresh_html && isset( $cachekey ) && $preserve['use_cache'] )
			{
				$cache_data = (object) array(
					'html' => $cached->html,
					'template' => $cached->template,
					'instance' => $instance,
					'post_ids' => bcms_postloop()->posts[ $preserve['number'] ],
					'time' => time(),
				);
				wp_cache_set( $cachekey, $cache_data, 'bcmspostloop', $preserve['ttl'] );
				unset( $cache_data );
			}//end if
		}//end if

		unset( bcms_postloop()->current_postloop );
	}//end widget

	public function update( $new_instance, $old_instance )
	{
		$instance = $old_instance;

		$instance['title'] = wp_kses( $new_instance['title'], array() );
		$instance['subtitle'] = wp_kses( $new_instance['subtitle'], array() );
		$instance['title_show'] = absint( isset( $new_instance['title_show'] ) ? $new_instance['title_show'] : 0 );

		$allowed_queries = array( 'normal' , 'custom' );
		$predefined_queries = apply_filters( 'postloop_predefined_queries' , array());
		foreach( $predefined_queries as $k => $v )
		{
			$k = preg_replace( '/[^a-zA-Z0-9_-]*/' , '' , $k );
			$allowed_queries[] = 'predefined_'. $k;
		}
		$instance['query'] = in_array( $new_instance['query'] , $allowed_queries ) ? $new_instance['query'] : 'normal';

		$instance['what'] = (array) array_intersect( (array) $this->get_post_types() , array_keys( $new_instance['what'] ));

		$instance['status'] = (array) array_intersect_key( $this->get_post_statuses(), $new_instance['status'] );

		$instance['categoriesbool'] = in_array( $new_instance['categoriesbool'], array( 'in', 'and', 'not_in') ) ? $new_instance['categoriesbool']: '';
		$instance['categories_in'] = isset( $new_instance['categories_in'] ) ? array_filter( array_map( 'absint', $new_instance['categories_in'] ) ) : array();
		$instance['categories_in_related'] = (int) $new_instance['categories_in_related'];
		$instance['categories_not_in'] = isset( $new_instance['categories_not_in'] ) ? array_filter( array_map( 'absint', $new_instance['categories_not_in'] ) ) : array();
		$instance['categories_not_in_related'] = (int) $new_instance['categories_not_in_related'];
		$instance['tagsbool'] = in_array( $new_instance['tagsbool'], array( 'in', 'and', 'not_in') ) ? $new_instance['tagsbool']: '';
		$tag_name = '';
		$instance['tags_in'] = array();
		foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tags_in'] )))) as $tag_name )
		{
			if( $temp = term_exists( $tag_name, 'post_tag' ))
			{
				$instance['tags_in'][] = $temp['term_id'];
			}
		}
		$instance['tags_in_related'] = (int) $new_instance['tags_in_related'];
		$tag_name = '';
		$instance['tags_not_in'] = array();
		foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tags_not_in'] )))) as $tag_name )
		{
			if( $temp = term_exists( $tag_name, 'post_tag' ))
			{
				$instance['tags_not_in'][] = $temp['term_id'];
			}
		}
		$instance['tags_not_in_related'] = (int) $new_instance['tags_not_in_related'];

		if( $instance['what'] <> 'normal' )
		{
			foreach( get_object_taxonomies( $instance['what'] ) as $taxonomy )
			{
				if( $taxonomy == 'category' || $taxonomy == 'post_tag' )
				{
					continue;
				}

				$instance['tax_'. $taxonomy .'_bool'] = in_array( $new_instance['tax_'. $taxonomy .'_bool'], array( 'in', 'and', 'not_in') ) ? $new_instance['tax_'. $taxonomy .'_bool']: '';
				$tag_name = '';
				$instance['tax_'. $taxonomy .'_in'] = array();
				foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tax_'. $taxonomy .'_in'] )))) as $tag_name )
				{
					if( $temp = term_exists( $tag_name, $taxonomy ))
					{
						$instance['tax_'. $taxonomy .'_in'][] = $temp['term_id'];
					}
				}

				$instance['tax_'. $taxonomy .'_in_related'] = (int) $new_instance['tax_'. $taxonomy .'_in_related'];

				$tag_name = '';
				$instance['tax_'. $taxonomy .'_not_in'] = array();
				foreach( array_filter( array_map( 'trim', array_map( 'wp_filter_nohtml_kses', explode( ',', $new_instance['tax_'. $taxonomy .'_not_in'] )))) as $tag_name )
				{
					if( $temp = term_exists( $tag_name, $taxonomy ))
					{
						$instance['tax_'. $taxonomy .'_not_in'][] = $temp['term_id'];
					}
				}

				$instance['tax_'. $taxonomy .'_not_in_related'] = (int) $new_instance['tax_'. $taxonomy .'_not_in_related'];
			}// end foreach
		}// end if

		$instance['post__in'] = array_filter( array_map( 'absint', explode( ',', $new_instance['post__in'] )));
		$instance['post__not_in'] = array_filter( array_map( 'absint', explode( ',', $new_instance['post__not_in'] )));
		$instance['comments'] = in_array( $new_instance['comments'], array( 'unset', 'yes', 'no' ) ) ? $new_instance['comments']: '';

		$instance['activity'] = isset( $new_instance['activity'] ) && in_array( $new_instance['activity'], array( 'pop_most', 'pop_least', 'pop_recent', 'comment_recent', 'comment_few') ) ? $new_instance['activity']: '';
		$instance['age_bool'] = in_array( $new_instance['age_bool'], array( 'newer', 'older') ) ? $new_instance['age_bool']: '';
		$instance['age_num'] = absint( $new_instance['age_num'] );
		$instance['age_unit'] = in_array( $new_instance['age_unit'], array( 'day', 'month', 'year') ) ? $new_instance['age_unit']: '';
		$instance['agestrtotime'] = isset( $new_instance['agestrtotime'] ) && strtotime( $new_instance['agestrtotime'] ) ? $new_instance['agestrtotime'] : '';
		$instance['relationship'] = in_array( $new_instance['relationship'], array( 'similar', 'excluding') ) ? $new_instance['relationship']: '';
		$instance['relatedto'] = isset( $new_instance['relatedto'] ) ? array_filter( array_map( 'intval', $new_instance['relatedto'] ) ) : array();
		$instance['count'] = absint( $new_instance['count'] );
		$instance['order'] = in_array( $new_instance['order'], array( 'age_new', 'age_old', 'title_az', 'title_za', 'comment_new', 'comment_count', 'pop_recent', 'rand', 'menu_order' ) ) ? $new_instance['order']: '';
		$instance['template'] = wp_kses( $new_instance['template'], array() );
		$instance['offset_run'] = empty( $new_instance['offset_run'] ) ? '' : absint( $new_instance['offset_run'] );
		$instance['offset_start'] = empty( $new_instance['offset_start'] ) ? '' : absint( $new_instance['offset_start'] );

		if( function_exists( 'get_intermediate_image_sizes' ))
		{
			$instance['thumbnail_size'] = in_array( $new_instance['thumbnail_size'], (array) get_intermediate_image_sizes() ) ? $new_instance['thumbnail_size']: '';
		}
		$instance['columns'] = isset( $new_instance['columns'] ) ? absint( $new_instance['columns'] ) : 0;

		$this->justupdated = TRUE;

		return $instance;
	}//end update

	public function form( $instance )
	{
		// reset the instances var, in case a new widget was added
		bcms_postloop()->get_instances();

		//Defaults
		$instance = wp_parse_args( (array) $instance, $this->get_instance_defaults() );

		$this->form_title( $instance );
		$this->form_query_type( $instance );
		$this->form_post_type( $instance );
		$this->form_status( $instance );
		$this->form_categories( $instance );
		$this->form_tags( $instance );
		$this->form_other_taxonomies( $instance );
		$this->form_post_ids( $instance );
		$this->form_comments( $instance );
		$this->form_date( $instance );
		$this->form_related_to( $instance );
		$this->form_count( $instance );
		$this->form_order( $instance );
		$this->form_template( $instance );
		$this->form_loop_offset( $instance );
		$this->form_multithumb( $instance );
		$this->form_update_script( $instance );
	}// end form

	public function get_instance_defaults()
	{
		return array(
			'what' => array( 'normal' ),
			'status' => array( 'publish' => __( 'Published' ) ),
			'template' => 'a_default_full.php',
			'title' => '',
			'subtitle' => '',
			'title_show' => FALSE,
			'query' => '',
			'offset_start' => 0,
			'categoriesbool' => FALSE,
			'tagsbool' => FALSE,
			'tags_in' => array(),
			'tags_in_related' => 0,
			'tags_not_in' => array(),
			'tags_not_in_related' => 0,
			'post__in' => array(),
			'post__not_in' => array(),
			'comments' => '',
			'age_bool' => FALSE,
			'age_num' => '',
			'age_unit' => 0,
			'relatedto' => '',
			'relationship' => 0,
			'count' => 0,
			'order' => 0,
			'offset_run' => 0,
			'offset_start' => 0,
			'thumbnail_size' => '',
		);
	}//end get_instance_defaults

	public function form_title( $instance )
	{
		$title = esc_attr( $instance['title'] );
		$subtitle = esc_attr( $instance['subtitle'] );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			<label for="<?php echo $this->get_field_id( 'title_show' ) ?>"><input id="<?php echo $this->get_field_id( 'title_show' ) ?>" name="<?php echo $this->get_field_name( 'title_show' ) ?>" type="checkbox" value="1" <?php echo ( $instance[ 'title_show' ] ? 'checked="checked"' : '' ) ?>/> Show Title?</label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'subtitle' ); ?>"><?php _e('Sub-title'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('subtitle'); ?>" name="<?php echo $this->get_field_name('subtitle'); ?>" type="text" value="<?php echo $subtitle; ?>" />
		</p>
		<?php
	}//end form_title

	public function form_query_type( $instance )
	{
		?>
		<!-- Query type -->
		<div id="<?php echo $this->get_field_id('query'); ?>-container" class="postloop container querytype_normal posttype_normal">
			<label for="<?php echo $this->get_field_id('query'); ?>"><?php _e( 'What to show' ); ?></label>
			<div id="<?php echo $this->get_field_id('query'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('query'); ?>" id="<?php echo $this->get_field_id('query'); ?>" class="widefat postloop querytype_selector">
						<option value="normal" <?php selected( $instance['query'], 'normal' ); ?>><?php _e('The default content'); ?></option>
						<option value="custom" <?php selected( $instance['query'], 'custom' ); ?>><?php _e('Custom content'); ?></option>
						<?php
							$predefined_queries = apply_filters( 'postloop_predefined_queries' , array());
							foreach( $predefined_queries as $k => $v )
							{
								//sanitize the value name
								$k = preg_replace( '/[^a-zA-Z0-9_-]*/' , '' , $k );

								// output the option line
								?><option value="predefined_<?php echo $k; ?>" <?php selected( $instance['query'], 'predefined_'. $k ); ?>><?php esc_attr_e( $v ); ?></option><?php
							}
						?>
					</select>
				</p>
			</div>
		</div>
		<?php
	}//end form_query_type

	public function form_post_type( $instance )
	{
		?>
		<!-- Post type -->
		<div id="<?php echo $this->get_field_id('what'); ?>-container" class="postloop container querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('what'); ?>"><?php _e( 'Selecting what kind of content' ); ?></label>
			<div id="<?php echo $this->get_field_id('what'); ?>-contents" class="contents hide-if-js">
				<p>
					<ul>
						<?php foreach( (array) $this->get_post_types() as $type ) : $type = get_post_type_object( $type ); ?>
							<li><label for="<?php echo $this->get_field_id( 'what-'. esc_attr( $type->name )); ?>"><input id="<?php echo $this->get_field_id( 'what-'. esc_attr( $type->name )); ?>" name="<?php echo $this->get_field_name( 'what' ) .'['. esc_attr( $type->name ) .']'; ?>" type="checkbox" value="1" <?php echo ( isset( $instance[ 'what' ][ $type->name ] ) ? 'checked="checked" class="" ' : 'class="checkbox"' ); ?>/> <?php echo $type->labels->name; ?></label></li>
						<?php endforeach; ?>

					</ul>
				</p>
			</div>
		</div>
		<?php
	}//end form_post_type

	public function form_status( $instance )
	{
		?>
		<!-- Status -->
		<div id="<?php echo $this->get_field_id('status'); ?>-container" class="postloop container querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('status'); ?>"><?php _e( 'With status' ); ?></label>
			<div id="<?php echo $this->get_field_id('status'); ?>-contents" class="contents hide-if-js">
				<p>
					<ul>
						<?php
						foreach( (array) $this->get_post_statuses() as $k => $v )
						{
						?>
							<li><label for="<?php echo $this->get_field_id( 'status-'. $k ); ?>"><input id="<?php echo $this->get_field_id( 'status-'. $k ); ?>" name="<?php echo $this->get_field_name( 'status' ) .'['. $k .']'; ?>" type="checkbox" value="1" <?php checked( isset( $instance['status'][ $k ] ) ? $k : '' , $k ) ?>/> <?php echo $v; ?></label></li>
						<?php
						}
						?>

					</ul>
				</p>
			</div>
		</div>
		<?php
	}//end form_status

	public function form_categories( $instance )
	{
		?>
		<!-- Categories -->
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
		<?php
	}//end form_categories

	public function form_tags( $instance )
	{
		?>
		<!-- Tags -->
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
					foreach( $instance['tags_in'] as $tag_id )
					{
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
						foreach( bcms_postloop()->instances as $number => $loop )
						{
							if( $number == $this->number )
							{
								continue;
							}

							echo '<option value="'. $number .'" '. selected( (int) $instance[ 'tags_in_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
						}
						?>
					</select></li>
				</p>

				<p>
					<label for="<?php echo $this->get_field_id('tags_not_in'); ?>"><?php _e( 'With none of these tags' ); ?></label>
					<?php
					$tags_not_in = array();
					foreach( $instance['tags_not_in'] as $tag_id )
					{
						$temp = get_term( $tag_id, 'post_tag' );
						$tags_not_in[] = $temp->name;
					}
					?>
					<input type="text" value="<?php echo implode( ', ', (array) $tags_not_in ); ?>" name="<?php echo $this->get_field_name('tags_not_in'); ?>" id="<?php echo $this->get_field_id('tags_not_in'); ?>" class="widefat <?php if( count( (array) $tags_not_in )) echo 'open-on-value'; ?>" />
					<br />
					<small><?php _e( 'Tags, separated by commas.' ); ?></small>

					<br />And terms from<br /><select name="<?php echo $this->get_field_name( 'tags_not_in_related' ); ?>" id="<?php echo $this->get_field_id( 'tags_not_in_related' ); ?>" class="widefat <?php if ( $instance['tags_not_in_related' ] ) echo 'open-on-value'; ?>">
						<option value="0" '. <?php selected( $instance['tags_not_in_related'], 0 ); ?> .'></option>
						<?php
						foreach( bcms_postloop()->instances as $number => $loop )
						{
							if( $number == $this->number )
							{
								continue;
							}

							echo '<option value="'. $number .'" '. selected( (int) $instance[ 'tags_not_in_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
						}
						?>
					</select></li>
				</p>
			</div>
		</div>
		<?php
	}//end form_tags

	public function form_other_taxonomies( $instance )
	{
		// Other taxonomies -->
		$this->control_taxonomies( $instance , $instance['what'] );
	}//end form_other_taxonomies

	public function form_post_ids( $instance )
	{
		?>
		<!-- Post IDs -->
		<div id="<?php echo $this->get_field_id('post__in'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('post__in'); ?>"><?php _e( 'Matching any post ID' ); ?></label>
			<div id="<?php echo $this->get_field_id('post__in'); ?>-contents" class="contents hide-if-js">
				<p>
					<input type="text" value="<?php echo implode( ', ', $instance['post__in'] ); ?>" name="<?php echo $this->get_field_name('post__in'); ?>" id="<?php echo $this->get_field_id('post__in'); ?>" class="widefat <?php if( count( $instance['post__in'] )) echo 'open-on-value'; ?>" />
					<br />
					<small><?php _e( 'Page IDs, separated by commas.' ); ?></small>
				</p>

				<p>
					<label for="<?php echo $this->get_field_id('post__not_in'); ?>"><?php _e( 'Excluding all these post IDs' ); ?></label> <input type="text" value="<?php echo implode( ', ', $instance['post__not_in'] ); ?>" name="<?php echo $this->get_field_name('post__not_in'); ?>" id="<?php echo $this->get_field_id('post__not_in'); ?>" class="widefat <?php if( count( $instance['post__not_in'] )) echo 'open-on-value'; ?>" />
					<br />
					<small><?php _e( 'Page IDs, separated by commas.' ); ?></small>
				</p>
			</div>
		</div>
		<?php
	}//end form_post_ids

	public function form_comments( $instance )
	{
		?>
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
	}//end form_comments

	public function form_date( $instance )
	{
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
		<?php
	}//end form_date

	public function form_related_to( $instance )
	{
		?>
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
		<?php
	}//end form_related_to

	public function form_count( $instance )
	{
		?>
		<div id="<?php echo $this->get_field_id('count'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('count'); ?>"><?php _e( 'Number of items to show' ); ?></label>
			<div id="<?php echo $this->get_field_id('count'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('count'); ?>" id="<?php echo $this->get_field_id('count'); ?>" class="widefat">
					<?php
					for( $i = 1; $i < 51; $i++ )
					{ ?>
						<option value="<?php echo $i; ?>" <?php selected( $instance['count'], $i ); ?>><?php echo $i; ?></option>
					<?php
					}
					?>
					</select>
				</p>
			</div>
		</div>
		<?php
	}//end form_count

	public function form_order( $instance )
	{
		global $bsuite;
		?>
		<div id="<?php echo $this->get_field_id('order'); ?>-container" class="postloop container hide-if-js querytype_custom posttype_normal">
			<label for="<?php echo $this->get_field_id('order'); ?>"><?php _e( 'Ordered by' ); ?></label>
			<div id="<?php echo $this->get_field_id('order'); ?>-contents" class="contents hide-if-js">
				<p>
					<select name="<?php echo $this->get_field_name('order'); ?>" id="<?php echo $this->get_field_id('order'); ?>" class="widefat">
							<option value="age_new" <?php selected( $instance['order'], 'age_new' ); ?>><?php _e('Newest first'); ?></option>
							<option value="age_old" <?php selected( $instance['order'], 'age_old' ); ?>><?php _e('Oldest first'); ?></option>
							<option value="comment_new" <?php selected( $instance['order'], 'comment_new' ); ?>><?php _e('Recently commented'); ?></option>
							<option value="comment_count" <?php selected( $instance['order'], 'comment_count' ); ?>><?php _e('Comment count'); ?></option>
							<option value="title_az" <?php selected( $instance['order'], 'title_az' ); ?>><?php _e('Title A-Z'); ?></option>
							<option value="title_za" <?php selected( $instance['order'], 'title_za' ); ?>><?php _e('Title Z-A'); ?></option>
							<?php if( is_object( $bsuite )): ?>
								<option value="pop_recent" <?php selected( $instance['order'], 'pop_recent' ); ?>><?php _e('Recently Popular'); ?></option>
							<?php endif; ?>
							<option value="menu_order" <?php selected( $instance['order'], 'menu_order' ); ?>><?php _e('Page Order'); ?></option>
							<option value="rand" <?php selected( $instance['order'], 'rand' ); ?>><?php _e('Random'); ?></option>
					</select>
				</p>
			</div>
		</div>
		<?php
	}//end form_order

	public function form_template( $instance )
	{
		?>
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
	}//end form_template

	public function form_loop_offset( $instance )
	{
		// weird feature to separate a single postloop into multiple widgets
		?>
		<div id="<?php echo $this->get_field_id('offset'); ?>-container" class="postloop container querytype_normal posttype_normal">
			<label for="<?php echo $this->get_field_id('offset'); ?>"><?php _e( 'Loop offset' ); ?></label>
			<div id="<?php echo $this->get_field_id('offset'); ?>-contents" class="contents hide-if-js">
				<p>
					<label for="<?php echo $this->get_field_id('offset_run'); ?>"><?php _e( 'From items in the loop, show N items' ); ?></label>
					<select name="<?php echo $this->get_field_name('offset_run'); ?>" id="<?php echo $this->get_field_id('offset_run'); ?>" class="widefat">
					<option value="" <?php selected( $instance['offset_run'], '' ); ?>></option>
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
	}//end form_loop_offset

	public function form_multithumb( $instance )
	{
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
		}//end if
	}//end form_multithumb

	public function form_update_script( $instance )
	{
		if ( isset( $this->justupdated ) && $this->justupdated )
		{
?>
<script type="text/javascript">
	postloops_widgeteditor_update( '<?php echo $this->get_field_id('title'); ?>' );
</script>
<?php
		}
	}//end form_update_script

	public function get_post_types()
	{
		return get_post_types( array( 'public' => TRUE , 'publicly_queryable' => TRUE , ) , 'names' , 'or' ); // trivia: 'pages' are public, but not publicly queryable
	}//end get_post_types

	public function get_post_statuses()
	{
		$statuses = get_post_statuses();

		$statuses = array_merge( $statuses, array(
			'inherit' => 'Inherit',
			'future' => 'Future',
		));

		return $statuses;
	}//end get_post_statuses

	public function control_thumbnails( $default = 'nines-thumbnail-small' )
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
	}//end control_thumbnails

	public function control_categories( $instance , $whichfield = 'categories_in' )
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
		$instance[ $whichfield .'_related' ] = isset( $instance[ $whichfield .'_related' ] ) ? $instance[ $whichfield .'_related' ] : 0;

		$related_instance_select = '<option value="0" '. selected( $instance[ $whichfield .'_related' ], 0, FALSE ) . '></option>';
		foreach( bcms_postloop()->instances as $number => $loop )
		{
			if( $number == $this->number )
			{
				continue;
			}

			$related_instance_select .= '<option value="'. $number .'" '. selected( (int) $instance[ $whichfield .'_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
		}

		$list[] = '<li>Categories from items shown in<br /><select name="'. $this->get_field_name( $whichfield .'_related' ) .'" id="'. $this->get_field_id( $whichfield .'_related' ) .'" class="widefat '. ( $instance[ $whichfield .'_related' ] ?  'open-on-value' : '' ) .'">'. $related_instance_select . '</select></li>';

		return implode( "\n", $list );
	}//end control_categories

	public function control_taxonomies( $instance , $post_type )
	{
		if( $post_type == 'normal' )
		{
			return;
		}

		foreach( get_object_taxonomies( $post_type ) as $taxonomy )
		{
			if( $taxonomy == 'category' || $taxonomy == 'post_tag' )
			{
				continue;
			}

			$instance['tax_'. $taxonomy .'_in'] = isset( $instance['tax_'. $taxonomy .'_in'] ) ? $instance['tax_'. $taxonomy .'_in'] : array();
			$instance['tax_'. $taxonomy .'_in_related'] = isset( $instance['tax_'. $taxonomy .'_in_related'] ) ? $instance['tax_'. $taxonomy .'_in_related'] : 0;
			$instance['tax_'. $taxonomy .'_not_in'] = isset( $instance['tax_'. $taxonomy .'_not_in'] ) ? $instance['tax_'. $taxonomy .'_not_in'] : array();
			$instance['tax_'. $taxonomy .'_not_in_related'] = isset( $instance['tax_'. $taxonomy .'_not_in_related'] ) ? $instance['tax_'. $taxonomy .'_not_in_related'] : 0;
			$instance['tax_'. $taxonomy .'_bool'] = isset( $instance['tax_'. $taxonomy .'_bool'] ) ? $instance['tax_'. $taxonomy .'_bool'] : '';

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
						foreach( $instance['tax_'. $taxonomy .'_in'] as $tag_id )
						{
							$temp = get_term( $tag_id, $taxonomy );
							if ( is_object( $temp ) )
							{
								$tags_in[] = $temp->name;
							}
						}
						?>
						<input type="text" value="<?php echo implode( ', ', (array) $tags_in ); ?>" name="<?php echo $this->get_field_name('tax_'. $taxonomy .'_in'); ?>" id="<?php echo $this->get_field_id('tax_'. $taxonomy .'_in'); ?>" class="widefat <?php if( count( (array) $tags_in )) echo 'open-on-value'; ?>" />
						<br />
						<small><?php _e( 'Terms, separated by commas.' ); ?></small>

						<br />And terms from<br /><select name="<?php echo $this->get_field_name( 'tax_'. $taxonomy .'_in_related' ); ?>" id="<?php echo $this->get_field_id( 'tax_'. $taxonomy .'_in_related' ); ?>" class="widefat <?php if( $instance[ 'tax_'. $taxonomy .'_in_related' ] ) echo 'open-on-value'; ?>">
							<option value="0" '. <?php selected( $instance[ 'tax_'. $taxonomy .'_in_related' ] , 0 ) ?> .'></option>
							<?php
							foreach( bcms_postloop()->instances as $number => $loop )
							{
								if( $number == $this->number )
								{
									continue;
								}

								echo '<option value="'. $number .'" '. selected( (int) $instance[ 'tax_'. $taxonomy .'_in_related' ] , (int) $number , FALSE ) .'>'. $loop['title'] .'<small> (id:'. $number .')</small></option>';
							}
							?>

						</select></li>
					</p>

					<p>
						<label for="<?php echo $this->get_field_id('tax_'. $taxonomy .'_not_in'); ?>"><?php _e( 'With none of these terms' ); ?></label>
						<?php
						$tags_not_in = array();
						foreach( (array) $instance['tax_'. $taxonomy .'_not_in'] as $tag_id )
						{
							$temp = get_term( $tag_id, $taxonomy );
							if ( is_object( $temp ) )
							{
								$tags_not_in[] = $temp->name;
							}
						}
						?>
						<input type="text" value="<?php echo implode( ', ', (array) $tags_not_in ); ?>" name="<?php echo $this->get_field_name('tax_'. $taxonomy .'_not_in'); ?>" id="<?php echo $this->get_field_id('tax_'. $taxonomy .'_not_in'); ?>" class="widefat <?php if( count( (array) $tags_not_in )) echo 'open-on-value'; ?>" />
						<br />
						<small><?php _e( 'Terms, separated by commas.' ); ?></small>

						<br />And terms from<br /><select name="<?php echo $this->get_field_name( 'tax_'. $taxonomy .'_not_in_related' ); ?>" id="<?php echo $this->get_field_id( 'tax_'. $taxonomy .'_not_in_related' ); ?>" class="widefat <?php if( $instance[ 'tax_'. $taxonomy .'_not_in_related' ] ) echo 'open-on-value'; ?>">
							<option value="0" '. <?php selected( (int) $instance[ 'tax_'. $taxonomy .'_not_in_related' ] , 0 ) ?> .'></option>
							<?php
							foreach( bcms_postloop()->instances as $number => $loop )
							{
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
		}// end foreach
	}//end control_taxonomies

	public function control_instances( $selected = array() )
	{
		$list = array();
		foreach( bcms_postloop()->instances as $number => $instance )
		{
			if( $number == $this->number )
			{
				continue;
			}

			$list[] = '<li>
				<label for="'. $this->get_field_id( 'relatedto-'. $number ) .'"><input type="checkbox" value="'. $number .'" '.( in_array( $number, (array) $selected ) ? 'checked="checked" class="checkbox open-on-value"' : 'class="checkbox"' ) .' id="'. $this->get_field_id( 'relatedto-'. $number) .'" name="'. $this->get_field_name( 'relatedto' ) .'['. $number .']" /> '. $instance['title'] .'<small> (id:'. $number .')</small></label>
			</li>';
		}// end foreach

		return implode( "\n", $list );
	}//end control_instances

	public function control_template_dropdown( $default = '' )
	{
		foreach ( bcms_postloop()->get_actions( 'post' ) as $template => $info )
		{

			echo "\n\t<option value=\"" .$template .'" '. selected( $default, $template, FALSE ) .'>'. $info['name'] .'</option>';
		}
	}

	public function tax_posttype_classes( $taxonomy )
	{
		$tax = get_taxonomy($taxonomy);

		if( ! $tax || count( $tax->object_type ) == 0 )
		{
			return '';
		}

		return 'querytype_custom ' . implode( ' posttype_', $tax->object_type );
	}//end tax_posttype_classes
}// end bCMS_PostLoop_Widget

/*
Reminder to self: the widget objects and their vars can be found in here:

global $wp_widget_factory;
print_r( $wp_widget_factory );

*/
