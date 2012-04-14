<?php
/*
Template Name: 9spot Photo
*/

global $post;
?>

<div <?php post_class() ?> id="post-<?php the_ID(); ?>">
	<div class="attachment">
		<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php echo wp_get_attachment_image( $post->ID, 'thumbnail' ); ?></a>
	</div>
</div>