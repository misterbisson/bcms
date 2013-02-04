<?php
class bSuite_bSuggestive {

	function bSuite_bSuggestive( $ids ) {
		self::getposts( $ids );
	}

	function query( $ids ) {
		global $wpdb;
	
		$ids = array_filter( array_map( 'absint' , (array ) $ids ));
	
	
		if( count( $ids ))
		{
			// TODO: this should get the default related taxonomies based on the registered taxonomies for the post types in $ids
			$taxonomies = ( array_filter( apply_filters( 'bsuite_suggestive_taxonomies', array( 'post_tag', 'category' ))));
	
			$taxonomies = array_filter( array_map( array( &$wpdb, 'escape' ), (array) $taxonomies ));
	
			$ignore_ids = ( array_filter( apply_filters( 'bsuite_suggestive_ignoreposts', $ids )));
			$ignore_ids = implode( ',', array_filter( array_map( 'absint' , (array) $ignore_ids )));
	
			if( count( $taxonomies ))
				return( apply_filters('bsuite_suggestive_query',
					"SELECT t_r.object_id AS post_id, COUNT(t_r.object_id) AS hits
					FROM ( SELECT t_ra.term_taxonomy_id
						FROM $wpdb->term_relationships t_ra
						LEFT JOIN $wpdb->term_taxonomy t_ta ON t_ta.term_taxonomy_id = t_ra.term_taxonomy_id
						WHERE t_ra.object_id  IN (". implode( ',', $ids ) .")
						AND t_ta.taxonomy IN ('". implode( $taxonomies, "','") ."')
					) ttid
					LEFT JOIN $wpdb->term_relationships t_r ON t_r.term_taxonomy_id = ttid.term_taxonomy_id
					LEFT JOIN $wpdb->posts p ON t_r.object_id  = p.ID
					WHERE p.ID NOT IN( $ignore_ids )
					AND p.post_status = 'publish'
					GROUP BY p.ID
					ORDER BY hits DESC, p.post_date_gmt DESC
					LIMIT 300", $ids)
				);
		}
		return FALSE;
	}
	
	function getposts( $ids )
	{
		global $wpdb;
	
		if ( !$related_posts = wp_cache_get( implode( ',', (array) $ids ), 'bsuite_related_posts' ) ) {
			if( $the_query = self::query( $ids ) ){
				$related_posts = $wpdb->get_col($the_query);
				wp_cache_set( implode( ',', (array) $ids ), $related_posts, 'bsuite_related_posts', time() + 900000 ); // cache for 25 days
				return( $related_posts ); // if we have to go to the DB to get the posts, then this will get returned
			}
			return( FALSE ); // if there's nothing in the cache and we've got no query
		}
		return $related_posts; // if the cache is still warm, then we return this
	}
}