<?php
/*
Template Name: 9spot Scroller
Wrapper: TRUE
*/

global $postloops;
?>
<div>
	<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">
		<span class="entry thumbnail"><?php the_post_thumbnail( $postloops->thumbnail_size ); ?></span>
		<span class="entry title"><?php the_title(); ?></span>
	</a>
</div>
