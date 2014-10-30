<?php

class bCMS_Search_Test extends WP_UnitTestCase
{
	/**
	 * which tests the constructor, the init action, etc...
	 */
	public function test_singleton()
	{
		$this->assertTrue( is_object( bcms_search() ) );
	}//end test_singleton

	/**
	 * test bCMS reindex to make sure it gets both the post title and
	 * post content
	 */
	public function test_reindex()
	{
		global $wpdb;

		bcms_search()->create_table();

		// add a new post so we know it's not in the *bcms_search table
		$post_title = 'Title of the bCMS Post';
		$post_content = 'this is a test post for bcms!';
		$post_id = wp_insert_post(
			array(
				'post_content' => $post_content,
				'post_name' => 'bcms-test-post',
				'post_title' => $post_title,
				'post_status' => 'publish',
			)
		);
		$this->assertTrue( 0 < $post_id );

		// this new post should not be in the bcms search table yet
		$posts = $wpdb->get_col(
			'SELECT a.post_id
				FROM ' . bcms_search()->search_table . ' a
				WHERE a.post_id = ' . $post_id
		);

		$this->assertTrue( empty( $posts ) );

		$reindexed = bcms_search()->reindex();

		$this->assertTrue( 0 < $reindexed );

		// check the content of the bcms_search entry
		$bcms_content = $wpdb->get_col(
			'SELECT content
				FROM ' . bcms_search()->search_table . '
				WHERE post_id = ' . $post_id
		);

		$this->assertTrue( 1 == count( $bcms_content ) );
		$this->assertTrue( FALSE !== strpos( $bcms_content[0], $post_title ) );
		$this->assertTrue( FALSE !== strpos( $bcms_content[0], $post_content ) );
	}//end test_reindex
}//end class
