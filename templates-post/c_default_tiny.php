<?php
/*
Template Name: 9spot Excerpt Tiny
*/
?>

<div <?php post_class() ?> id="post-<?php the_ID(); ?>">
	<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>

	<div class="entry excerpt">
		<?php the_excerpt(); ?>
	</div>
</div>