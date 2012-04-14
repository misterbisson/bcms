<?php
/*
Template Name: 9spot Icon & Tiny Excerpt
*/

global $bsuite;
?>

<div <?php post_class() ?> id="post-<?php the_ID(); ?>">
<?php
	if ( has_post_thumbnail() )
	{
		$thumb = wp_get_attachment_image_src( get_post_thumbnail_id() , 'thumbnail' );
?>
		<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>" class="wrapper-image attachment-thumbnail-link wp-post-image-link"><img width="60" height="60" src="<?php echo $thumb[0]; ?>" alt="Example Image" class="thumbnail-default attachment-thumbnail wp-post-image" alt="<?php the_title_attribute(); ?>" title="<?php the_title_attribute(); ?>" /></a>
<?php
	}
	else
	{
?>
		<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>" class="wrapper-image thumbnail-default attachment-thumbnail-link wp-post-image-link"><img width="60" height="60" src="<?php echo $bsuite->path_web; ?>/img/thumbnail-missing.png" alt="Example Image" class="thumbnail-default attachment-thumbnail wp-post-image" alt="<?php the_title_attribute(); ?>" title="<?php the_title_attribute(); ?>" /></a>
<?php
	}
?>
	<div class="wrapper-text">
		<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
		<div class="entry excerpt">
			<?php the_excerpt(); ?>
		</div>
	</div>
</div>