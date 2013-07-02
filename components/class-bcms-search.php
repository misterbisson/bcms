<?php

class bCMS_Search
{

	public $version = 1;
	public $reindex_limit = 25;

	function __construct()
	{
		add_action( 'init' , array( $this , 'init' ));
	}

	function init()
	{
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->search_table = $wpdb->prefix . 'bcms_search';

		// delete posts from the search index when saving them
		add_filter( 'save_post' , array( $this , 'delete_index_for_post' ));

		// update the search index via cron
		add_action( 'hourly' , array( $this, 'reindex_passive' ) );

		if ( is_admin() )
		{
			$this->upgrade();
			add_action( 'wp_ajax_bcms-search-reindex', array( $this, 'reindex_ajax' ));
			add_action( 'wp_ajax_bcms-search-reset', array( $this, 'reset_ajax' ));
		}
		else
		{
			// attach an action to apply filters to queries, except from the dashboard
			add_action( 'parse_query' , array( $this , 'parse_query' ) , 1 );
		}

	}

	public function upgrade()
	{
		$options = get_option( 'bcms_search_options' );

		// initial activation and default options
		if( ! isset( $options['version'] ) )
		{
			// create the table
			$this->reset_table();

			// set the options
			$options['active'] = TRUE;
			$options['version'] = $this->version;
		}

		// replace the old options with the new ones
		update_option( 'bcms_search_options' , $options );
	}

	function create_table()
	{
		$charset_collate = '';
		if ( version_compare( mysql_get_server_info() , '4.1.0', '>=' ))
		{
			if ( ! empty( $this->wpdb->charset ))
			{
				$charset_collate = 'DEFAULT CHARACTER SET '. $this->wpdb->charset;
			}
			if ( ! empty( $this->wpdb->collate ))
			{
				$charset_collate .= ' COLLATE '. $this->wpdb->collate;
			}
		}

		require_once ABSPATH . 'wp-admin/upgrade-functions.php';

		dbDelta("
			CREATE TABLE $this->search_table (
				post_id bigint(20) NOT NULL,
				post_age bigint(20) NOT NULL,
				content text,
				PRIMARY KEY  (post_id),
				FULLTEXT KEY search (content)
			) ENGINE=MyISAM $charset_collate
			");
	}

	function reset_table()
	{
		$this->create_table();
		$this->wpdb->get_results( 'TRUNCATE TABLE '. $this->search_table );
	}

	function parse_query( $query )
	{
		// only opeate on search queries
		if ( empty( $query->query_vars['s'] ) )
		{
			return $query;
		}

		// get and clean the search string
		$this->search_string = trim( get_search_query());

		// only works for search strings longer than MySQL's ft min word length
		// @TODO: make this configurable
		if ( 4 > strlen( $this->search_string ))
		{
			return $query;
		}

		// apply filters
		add_filter( 'posts_search' , 			array( $this , 'posts_search' ) , 5 ); // filter the where clause for the search string, though the actual query is done in the join
		add_filter( 'posts_join_request' , 		array( $this , 'posts_join_request' ) , 5 ); // join derived table with full text results
		add_filter( 'posts_fields_request' , 	array( $this , 'posts_fields_request' ) , 5 ); // add field we use to sort results
		add_filter( 'posts_orderby_request' , 	array( $this , 'posts_orderby_request' ) , 5 ); // apply sort order

		return $query;
	}

	function delete_index_for_post( $post_id )
	{
		$post_id = absint( $post_id );
		if ( ! $post_id )
		{
			return FALSE;
		}

		return $this->wpdb->get_results( "DELETE FROM $this->search_table WHERE post_id = $post_id" );
	}

	function get_post( $post_id )
	{
		// get the post via WP
		$post = get_post( $post_id );

		// keyword stuff the title at the top 
		$content = $post->post_title ."\n". $post->post_content;

		// get terms in all post taxonomies and add them to the content as well
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		$tags = wp_get_object_terms( 
			$post_id,
			$taxonomies,
			array(
				'orderby' => 'name',
				'order' => 'ASC',
				'fields' => 'names',
			)
		);
		$content .= "\n". implode( ' ', $tags );

		// simple cleaning
		$content = stripslashes( html_entity_decode( $content ));

		// replace some html with newlines to prevent irrelevant proximity results
		$content = preg_replace( '/\<\/?(p|br|li|h[1-9])[^\>]*\>/i' , "\n" , $content );

		// strip all html
		$content = wp_kses( $content , array() );

		// strip shortcodes
		$content = preg_replace( '/\[.*?\]/', '', $content );

		// apply filters
		$content = apply_filters( 'bcms_search_post_content' , $content , $post_id );

		// find words with accented characters, create transliterated versions of them
		$unaccented = array_diff( str_word_count( $content , 1 ), str_word_count( remove_accents( $content ) , 1 ));
		$content .= "\n". implode( ' ', $unaccented );

		// metaphone all the words and add those to the content to help sloppy spellers
//		$content .= "\n". $this->metaphone( $content );

		// reasign into the post object
		$post->post_content = $content;

		return $post;
	}

	function reindex()
	{

		// grab a batch of posts to work with
		$posts = $this->wpdb->get_col(
			"SELECT a.ID
				FROM ". $this->wpdb->posts ." a
				LEFT JOIN $this->search_table b ON a.ID = b.post_id
				WHERE a.post_status = 'publish'
				AND b.post_id IS NULL
				ORDER BY a.ID DESC
				LIMIT $this->reindex_limit"
		);

		// get the filtered content and construct an insert statement for each
		if( count( $posts ))
		{
			$insert = array();

			foreach( $posts as $post_id )
			{

				$post = $this->get_post( $post_id );

				if ( 
					empty( $post->ID ) || 
					empty( $post->post_content ) 
				)
				{
					continue;
				}

				$insert[] = '('. 
					(int) $post->ID .', '. 
					(int) date( 'YW', strtotime( $post->post_date_gmt ) ) .', "'. 
					$this->wpdb->escape( $post->post_content ) 
				.'")';
			}
		}
		else
		{
			return FALSE;
		}

		// insert into the search table
		if ( count( $insert ) )
		{
			$this->wpdb->get_results( 
				'REPLACE INTO '. $this->search_table .'
					(post_id, post_age,  content) 
					VALUES '. implode( ',', $insert )
			);
		}

		return count( $posts );
	}

	function reindex_passive()
	{
		$this->reindex();
		return;
	}

	function reindex_ajax()
	{
		if ( ! current_user_can( 'manage_options' ) )
		{
			return FALSE;
		}
		
		$count = $this->reindex();

		echo '<h2>bCMS Search reindex</h2><p>processed ' . $count . ' post(s) at '. date( DATE_RFC822 ) .'</p>';

		if ( $this->reindex_limit <= $count )
		{
			echo '<p>Reloading...</p>';
?>
<script type="text/javascript">
window.location = "<?php echo admin_url( 'admin-ajax.php?action=bcms-search-reindex' ); ?>";
</script>
<?php
		}
		{
			echo '<p>All done, for now.</p>';
		}

		die;
	}

	function reset_ajax()
	{
		if ( ! current_user_can( 'manage_options' ) )
		{
			return FALSE;
		}
		
		$this->reset_table();

		echo '<h2>bCMS Search index reset</h2><p>Action completed at '. date( DATE_RFC822 ) .'</p>';

		echo '<p><a href="'. admin_url( 'admin-ajax.php?action=bcms-search-reindex' ) .'">Start reindexing</a></p>';

		die;
	}

	function metaphone( $string )
	{
		foreach ( (array) str_word_count( $string , 1 ) as $word )
		{
			$metaphone = metaphone( $word, 5 );

			// MySQL's minimum word length is usually 4 chars, 
			// so we double the metaphone so short phonemes are longer
			// ...we also exclude duplicates
//			$metaphones[ $metaphone ] = $metaphone . $metaphone;
			$metaphones[ $metaphone ] = $metaphone;
		}

		return implode( ' ', $metaphones );
	}

	function posts_search( $sql )
	{
		// the real search is done in the join statement
		return '';
	}

	function posts_join_request( $sql )
	{

		// the formula used to rank the posts
		$this->relevancy_formula = $this->wpdb->prepare( "MATCH ( content ) AGAINST ( %s ) + ( MATCH ( content ) AGAINST ( %s IN BOOLEAN MODE ) * post_age)",
			$this->search_string,
			$this->search_string
		);

		// join a derived table generated from a full text query
		$this->posts_join = $this->wpdb->prepare( " INNER JOIN (
			SELECT post_id, ( $this->relevancy_formula ) AS ftscore
			FROM $this->search_table
			WHERE ( MATCH ( content ) AGAINST ( %s $boolean ))
			ORDER BY ftscore DESC
			LIMIT 0, 1250
		) bcms_search ON ( bcms_search.post_id = ". $this->wpdb->posts .".ID )",
			$this->search_string
		);

		$sql .= "\n $this->posts_join \n";
		return $sql;
	}

	function posts_fields_request( $sql )
	{
		$sql .= ', bcms_search.ftscore ';
		return $sql;
	}

	function posts_orderby_request( $sql )
	{
		$sql = 'ftscore DESC , ' . $sql;
		return $sql;
	}

}

function bcms_search()
{
	global $bcms_search;

	if( ! $bcms_search )
	{
		$bcms_search = new bCMS_Search;
	}

	return $bcms_search;
}