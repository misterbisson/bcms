<?php

/**
 * Pages widget class
 *
 */
class bSuite_Widget_Pages extends WP_Widget {

	function bSuite_Widget_Pages()
	{
		$widget_ops = array( 'classname' => 'widget_pages', 'description' => __( 'A buncha yo blog&#8217;s WordPress Pages' ) );
		$this->WP_Widget( 'pages', __( 'Pages' ), $widget_ops );
	}

	function widget( $args, $instance )
	{
		if ( $instance['startpage'] < 0 ||  $instance['startpage'] === 'c' )
		{
			if ( ! is_singular() ) // can't generate a menu in this situation
				return;
		}//end if

		if ( is_singular() )
		{
			$post = get_post( get_queried_object_id() ); // getting the post for use later

			if( $post->post_parent && ( ! isset( $post->ancestors ) || ! count( $post->ancestors ) ) )
			{ // the post has a parent, but the ancestors array is unset or empty
				unset( $post->ancestors );
				_get_post_ancestors( $post );
				echo '<!-- pages_widget: explicitly looked up post ancestors -->';
			}
			echo '<!-- pages_widget: this appears to be page ID '. $post->ID .' with '. count( $post->ancestors ) .' ancestors -->';
		}//end if

		if ( is_404() )
			$instance['expandtree'] = 0;

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? FALSE : $instance['title'] );
		$homelink = empty( $instance['homelink'] ) ? '' : $instance['homelink'];
		$sortby = empty( $instance['sortby'] ) ? 'menu_order' : $instance['sortby'];
		$exclude = empty( $instance['exclude'] ) ? '' : $instance['exclude'];
		$depth = isset( $instance['depth'] ) ? $instance['depth'] : 1;

		if ( $instance['startpage'] < 0 )
		{
			// get the ancestor tree, including the current page
			$ancestors = $post->ancestors;
			array_unshift( $ancestors, $post->ID ); //append the current page to the ancestors array in the correct order

			// reverse the array so the slice can return empty if startpage is larger than the array
			$startpage = current( array_slice( array_reverse( (array) $ancestors ), absint( $instance['startpage'] ) -1, 1 ) );
			if ( ! $startpage )
				return;
		}//end if
		elseif ( $instance['startpage'] >= 0 )
		{
			$startpage = $instance['startpage'];
		}
		elseif ( $instance['startpage'] == 'c' )
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
				'depth' => $depth,
		) );

		if ( $instance['expandtree'] && ( $instance['startpage'] >= 0 ) && is_page() )
		{
			// get the ancestor tree, including the current page
			$ancestors = $post->ancestors;
			$ancestors[] = $post->ID;
			$pages = get_pages( array( 'include' => implode( ',', $ancestors ) ) );

			if ( ! empty( $pages ) )
			{
				$subtree .= walk_page_tree( $pages, 0, $post->ID, array() );

				// get any siblings, insert them into the tree
				if ( count( $post->ancestors ) && ( $siblings = wp_list_pages( array( 'child_of' => array_shift( $ancestors ), 'title_li' => '', 'echo' => 0, 'sort_column' => $sortby, 'exclude' => $exclude, 'depth' => 1 ) ) ) )
				{
					$subtree = preg_replace( '/<li.+?current_page_item.+?<\/li>/i', $siblings, $subtree );
				}

				// get any children, insert them into the tree
				if ( $children = wp_list_pages( array( 'child_of' => $post->ID, 'title_li' => '', 'echo' => 0, 'sort_column' => $sortby, 'exclude' => $exclude, 'depth' => $depth ) ) )
				{
					$subtree = preg_replace( '/current_page_item[^<]*<a([^<]*)/i', 'current_page_item"><a\1<ul>'. $children .'</ul>', $subtree );
				}

				// insert this extended page tree into the larger list
				if ( ! empty( $subtree ) )
				{
					$out = preg_replace( '/<li[^>]*page-item-'. ( count( $post->ancestors ) ? end( $post->ancestors ) : $post->ID ) .'[^0-9][^>]*.*?<\/li>.*?($|<li)/si', $subtree .'\1', $out );
					reset( $post->ancestors );
				}
			}//end if
		}//end if

		if ( ! empty( $out ) )
		{
			echo $args['before_widget'];
			if ( $title )
				echo $args['before_title'] . $title . $args['after_title'];
		?>
		<ul>
			<?php if ( $homelink )
				echo '<li class="page_item page_item-home"><a href="'. get_option( 'home' ) .'">'. $homelink .'</a></li>';
			?>
			<?php echo $out; ?>
		</ul>
		<?php
			echo $args['after_widget'];
		}//end if
	}//end widget

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
	}//end update

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
	}//end form
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

	function widget( $args, $instance )
	{

		if ( is_tax() || is_tag() || is_category() )
			$category_description = term_description();
		else
			return;

		global $wp_query;
		$term = $wp_query->get_queried_object();
		$my_tag = &get_term( $term->term_id, $term->taxonomy, OBJECT, 'display' );

		if ( is_wp_error( $my_tag ) )
			return false;

		$my_tag_name = $my_tag->name;
//		$my_tag_name = apply_filters( 'single_tag_title' , $my_tag->name );

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'] );
		$title = str_ireplace( '%term_name%', '<span class="term-name">'. $my_tag_name .'</span>', $title );

		echo $args['before_widget'];
		if ( $title )
			echo $args['before_title'] . $title . $args['after_title'];
		if ( ! empty( $category_description ) )
			echo '<div class="archive-meta">' . $category_description . '</div>';
		echo '<div class="clear"></div>';
		echo $args['after_widget'];
	}//end widget

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = wp_kses( $new_instance['title'], array() );

		return $instance;
	}//end update

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
	}//end form
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

	function widget( $args, $instance )
	{
		wp_reset_query();

		global $wp_query;

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'] );
		$maxchars = absint( $instance['maxchars'] ) > 10 ? absint( $instance['maxchars'] ) : 10;

		$crumbs = array();

		if ( ! empty( $instance['homelink'] ) )
			$crumbs[] = '<li class="bloghome"><a href="'. get_option( 'home' ) .'">'. $instance['homelink'] .'</a></li>';

		if ( is_singular() )
		{
			setup_postdata( $wp_query->post );
			global $post, $page, $multipage;

			// get the ancestor tree, if exists
			$ancestors = array();
			if ( is_array( $post->ancestors ) )
			{
				foreach ( array_reverse( $post->ancestors ) as $post_id )
				{
					$crumbs[] = '<li><a href="'. get_permalink( $post_id ) .'"
					rel="bookmark" title="'. sprintf( __( 'Permanent Link to %s' ), esc_attr( strip_tags( get_the_title( $post_id ) ) ) ) .' ">'. ( strlen( get_the_title( $post_id ) ) > $maxchars ? trim( substr( get_the_title( $post_id ), 0, $maxchars ) ) .'&#8230;' : get_the_title( $post_id ) ) .'</a></li>';
				}
			}

			// add the current page to the tree
			$crumbs[] = '<li class="'. $post->post_type .'_item '. $post->post_type .'-item-'. $post->ID .' current_'. $post->post_type .'_item" ><a href="'. get_permalink( $post->ID ) .'" rel="bookmark" title="'. sprintf( __( 'Permanent Link to %s' ), esc_attr( strip_tags( get_the_title( $post->ID ) ) ) ) .'">'. ( strlen( get_the_title( $post->ID ) ) > $maxchars ? trim( substr( get_the_title( $post->ID ), 0, $maxchars ) ) .'&#8230;' : get_the_title( $post->ID ) ) .'</a></li>';

			//if this is a multi-page post/page...
			if ( $multipage )
			{
				// generate a permalink to this page
				if ( 1 == $page )
				{
					$link = get_permalink( $post->ID );
				}
				else
				{
					if ( '' == get_option( 'permalink_structure' ) || in_array( $post->post_status, array( 'draft', 'pending' ) ) )
						$link = get_permalink( $post->ID ) . '&amp;page='. $page;
					else
						$link = trailingslashit( get_permalink( $post->ID ) ) . user_trailingslashit( $page, 'single_paged' );
				}

				// add it to the crumbs
				$crumbs[] = '<li class="'. $post->post_type .'_item '. $post->post_type .'-item-'. $post->ID .' current_'. $post->post_type .'_item" ><a href="'. $link .'" rel="bookmark" title="'. sprintf( __( 'Permanent Link to page %d of %s' ), (int) $page, esc_attr( strip_tags( get_the_title( $post->ID ) ) ) ) .'">'. sprintf( __( 'Page %d' ), (int) $page ) .'</a></li>';
			}//end if
		}//end if
		else
		{
			if ( is_search() )
				$crumbs[] = '<li><a href="'. $link .'">'. __( 'Search' ) .'</a></li>';

//			if( is_paged() && $wp_query->query_vars['paged'] > 1 )
//				$page_text = sprintf( __('Page %d') , $wp_query->query_vars['paged'] );
		}//end else

		if ( count( $crumbs ) )
		{
			echo $args['before_widget'];
//			if ( $title )
//				echo $args['before_title'] . $title . $args['after_title'];
		?>
			<ul>
				<?php echo implode( "\n", $crumbs ); ?>
			</ul>
			<div class="clear"></div>
		<?php
			echo $args['after_widget'];
		}//end if
	}//end widget

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['homelink'] = strip_tags( $new_instance['homelink'] );
		$instance['maxchars'] = absint( $new_instance['maxchars'] );

		return $instance;
	}//end update

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
	}//end form
}// end bSuite_Widget_Crumbs

/**
 * Pagednav widget class
 *
 */
class bSuite_Widget_Pagednav extends WP_Widget {

	function bSuite_Widget_Pagednav()
	{
		$widget_ops = array( 'classname' => 'widget_pagednav', 'description' => __( 'Prev/Next page navigation' ) );
		$this->WP_Widget( 'pagednav', __( 'Paged Navigation Links' ), $widget_ops );
	}

	function widget( $args, $instance )
	{
		wp_reset_query();

		global $wp_query, $wp_rewrite;

		if ( ! $wp_query->is_singular )
		{
			$urlbase = preg_replace( '#/page/[0-9]+?(/+)?$#', '/', remove_query_arg( 'paged' ) );
			$prettylinks = ( $wp_rewrite->using_permalinks() && ( ! strpos( $urlbase, '?' ) ) );

			$opts = array(
				'base' => $urlbase . '%_%',
				'format' => $prettylinks ? user_trailingslashit( trailingslashit( 'page/%#%' ) ) : ( strpos( $urlbase, '?' ) ? '&paged=%#%' : '?paged=%#%' ),
				'total' => absint( $wp_query->max_num_pages ),
				'current' => absint( $wp_query->query_vars['paged'] ) ? absint( $wp_query->query_vars['paged'] ) : 1,
			);

			if ( $instance['prev_text'] )
				$opts['prev_text'] = $instance['prev_text'];

			if ( $instance['next_text'] )
				$opts['next_text'] = $instance['next_text'];

			$page_links = paginate_links( $opts );

			if ( $page_links )
				echo $args['before_widget'] . $page_links .'<div class="clear"></div>'. $args['after_widget'];
		}//end if
		else
		{
			echo $args['before_widget'];
?>
			<div class="alignleft"><?php previous_post_link( '&laquo; %link' ) ?></div>
			<div class="alignright"><?php next_post_link( '%link &raquo;' ) ?></div>
			<div class="clear"></div>
<?php
			echo $args['after_widget'];
		}//end else
	}//end widget

	function form( $instance ) {
		//Defaults
		$instance = wp_parse_args( (array) $instance,
			array(
				'prev_text' => null,
				'next_text' => null,
			)
		);
		$prev_text = esc_attr( $instance['prev_text'] );
		$next_text = esc_attr( $instance['next_text'] );

		?>
		<p>
			<label for="<?php echo $this->get_field_id('prev_text'); ?>"><?php _e('Previous text:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('prev_text'); ?>" name="<?php echo $this->get_field_name('prev_text'); ?>" type="text" value="<?php echo $prev_text; ?>" /><br /><small><?php _e( 'Optional, leave empty to use WP default.' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('next_text'); ?>"><?php _e('Next text:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('next_text'); ?>" name="<?php echo $this->get_field_name('next_text'); ?>" type="text" value="<?php echo $next_text; ?>" /><br /><small><?php _e( 'Optional, leave empty to use WP default.' ); ?></small>
		</p>
		<?php
	}//end form
}// end bSuite_Widget_Pagednav