<?php

class bSuite_Search
{
	function __construct()
	{
		if( get_option( 'bsuite_searchsmart' ))
			add_action( 'init' , array( $this , 'init' ));
	}

	function init()
	{
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->search_table = $wpdb->prefix . 'bsuite4_search';

		// delete posts from the search index when saving them
		add_filter( 'content_save_pre' , array( $this , 'content_save_pre' ));

		// update the search index via cron
		add_action( 'bsuite_interval' , array( $this, 'upindex_passive' ));

		// attach an action to apply filters to queries, except from the dashboard
		if( ! is_admin() )
			add_action( 'parse_query' , array( $this , 'parse_query' ) , 1 );
	}

	function create_table()
	{
		$charset_collate = '';
		if ( version_compare( mysql_get_server_info() , '4.1.0', '>=' ))
		{
			if ( ! empty( $this->wpdb->charset ))
				$charset_collate = 'DEFAULT CHARACTER SET '. $this->wpdb->charset;
			if ( ! empty( $this->wpdb->collate ))
				$charset_collate .= ' COLLATE '. $this->wpdb->collate;
		}

		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

		dbDelta("
			CREATE TABLE $this->search_table (
				post_id bigint(20) NOT NULL,
				content text,
				title text,
				PRIMARY KEY  (post_id),
				FULLTEXT KEY search (content, title)
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
		if( ! $query->is_search() )
			return $query;

		// get and clean the search string
		$this->search_string = urldecode( trim( $query->query_vars['s'] ));

		// only works for search strings longer than MySQL's ft min word length
		if( 4 > strlen( _search_terms_tidy( $this->search_string )))
			return $query;

		// apply filters
		add_filter( 'posts_search' , 			array( $this , 'posts_search' ) , 5 ); // filter the where clause for the search string, though the actual query is done in the join
		add_filter( 'posts_join_request' , 		array( $this , 'posts_join_request' ) , 5 ); // join derived table with full text results
		add_filter( 'posts_fields_request' , 	array( $this , 'posts_fields_request' ) , 5 ); // add field we use to sort results
		add_filter( 'posts_orderby_request' , 	array( $this , 'posts_orderby_request' ) , 5 ); // apply sort order

		return $query;
	}

	function content_save_pre( $content )
	{
		// called when posts are edited or saved
		if( (int) $_POST['post_ID'] )
			$this->delete_post( (int) $_POST['post_ID'] );

		return $content;
	}

	function delete_post( $post_id )
	{
		$post_id = absint( $post_id );
		if( $post_id )
			return FALSE;

		$this->wpdb->get_results( "DELETE FROM $this->search_table WHERE post_id = $post_id" );
	}

	function filter_content_for_index( $content , $post_id )
	{
		// simple cleaning
		$content = stripslashes( html_entity_decode( $content ));

		// replace some html with newlines to prevent irrelevant proximity results
		$content = preg_replace( '/\<\/?(p|br|li|h[1-9])[^\>]*\>/i' , "\n" , $content );

		// strip all html
		$content = stripslashes( wp_filter_nohtml_kses( $content ));

		// strip shortcodes
		$content = preg_replace( '/\[.*?\]/', '', $content );

		// apply filters
		$content = apply_filters( 'bsuite_searchsmart_content' , $content , $post_id );

		// find words with accented characters, create transliterated versions of them
		$unaccented = array_diff( str_word_count( $content , 1 ), str_word_count( remove_accents( $content ) , 1 ));
		$content ."\n\n". implode( ' ', $unaccented );

		return $content;
	}

	function upindex()
	{

		// grab a batch of posts to work with
		$posts = $this->wpdb->get_results(
			"SELECT a.ID, a.post_content, a.post_title
				FROM ". $this->wpdb->posts ." a
				LEFT JOIN $this->search_table b ON a.ID = b.post_id
				WHERE a.post_status = 'publish'
				AND b.post_id IS NULL
				LIMIT 25"
		);

		// get the filtered content and construct an insert statement for each
		if( count( $posts ))
		{
			$insert = array();

			foreach( $posts as $post )
			{
				$insert[] = '('. (int) $post->ID .', "'. $this->wpdb->escape( $this->filter_content_for_index( $post->post_title ."\n\n". $post->post_content , $post->ID )) .'", "'. $this->wpdb->escape( $post->post_title ) .'")';
			}
		}
		else
		{
			return FALSE;
		}

		// insert into the search table
		if( count( $insert ))
		{
			$this->wpdb->get_results( 
				'REPLACE INTO '. $this->search_table .'
					(post_id, content, title) 
					VALUES '. implode( ',', $insert )
			);
		}

		return count( $posts );
	}

	function upindex_passive()
	{
		global $bsuite;

		if( ! $bsuite->get_lock( 'ftindexer' ))
			return;

		$this->upindex();

		return;
	}

	function posts_search( $sql )
	{
		// the real search is done in the join statement
		return '';
	}

	function posts_join_request( $sql )
	{

		// the formula used to rank the posts
		$this->relevancy_formula = $this->wpdb->prepare( "MATCH ( content, title ) AGAINST ( %s ) + MATCH ( content, title ) AGAINST ( %s IN BOOLEAN MODE ) + MATCH ( title ) AGAINST ( %s IN BOOLEAN MODE )",
			$this->search_string,
			$this->search_string,
			$this->search_string
		);

		// join a derived table generated from a full text query
		$this->posts_join = $this->wpdb->prepare( " INNER JOIN (
			SELECT post_id, ( $this->relevancy_formula ) AS ftscore
			FROM $this->search_table
			WHERE ( MATCH ( content, title ) AGAINST ( %s $boolean ))
			ORDER BY ftscore DESC
			LIMIT 0, 1250
		) bsuite_ftsearch ON ( bsuite_ftsearch.post_id = ". $this->wpdb->posts .".ID )",
			$this->search_string
		);

		$sql .= "\n $this->posts_join \n";
		return $sql;
	}

	function posts_fields_request( $sql )
	{
		$sql .= ', bsuite_ftsearch.ftscore ';
		return $sql;
	}

	function posts_orderby_request( $sql )
	{
		$sql = 'ftscore DESC , ' . $sql;
		return $sql;
	}

}
$bsuite_search = new bSuite_Search;
