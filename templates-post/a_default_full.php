<?php
/*
Template Name: 9spot Full
*/

global $post;
?>

<div <?php post_class() ?> id="post-<?php the_ID(); ?>">
	<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
	<?php if( 'page' <> $post->post_type ): ?><small><?php the_time('F jS, Y') ?> by <?php the_author() ?> </small><?php endif; ?>

	<div class="entry">
		<?php the_content('Read the rest of this page &raquo;'); ?>
		
		<?php wp_link_pages(array('before' => '<p><strong>Pages:</strong> ', 'after' => '</p>', 'next_or_number' => 'number')); ?>
	</div>

	<p class="postmetadata"><?php if( 'page' <> $post->post_type ): ?><?php the_tags('Tags: ', ', ', '<br />'); ?> Posted in <?php the_category(', ') ?> | <?php endif; ?><?php edit_post_link('Edit', '', ' | '); ?>  <?php if ( 'open' == $post->comment_status ) { ?><a href="<?php the_permalink() ?>#comments"><?php comments_number(__( 'Leave a Comment' ), __( '1 Comment' ), __( '% Comments' )); ?></a><?php } ?></p>
</div>