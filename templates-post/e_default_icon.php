<?php
/*
Template Name: 9spot Icon & Excerpt
*/

global $bsuite;
?>



<div <?php post_class() ?> id="post-<?php the_ID(); ?>">
<?php
	if ( has_post_thumbnail() )
	{
?>
		<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"class="wrapper-image attachment-thumbnail-link wp-post-image-link"><?php the_post_thumbnail( 'thumbnail' ); ?></a>
<?php
	}
	else
	{
?>
		<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>" class="wrapper-image thumbnail-default attachment-thumbnail-link wp-post-image-link"><img width="100" height="100" src="<?php echo $bsuite->path_web; ?>/img/thumbnail-missing.png" alt="Example Image" class="thumbnail-default attachment-thumbnail wp-post-image" alt="<?php the_title_attribute(); ?>" title="<?php the_title_attribute(); ?>" /></a>
<?php
	}
?>

	<div class="wrapper-text">
		<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
		<?php if( 'page' <> $post->post_type ): ?><small><?php the_time('F jS, Y') ?> by <?php the_author() ?> </small><?php endif; ?>
	
		<div class="entry excerpt">
			<?php the_excerpt(); ?>
		</div>

		<p class="postmetadata"><?php if( 'page' <> $post->post_type ): ?><?php the_tags('Tags: ', ', ', '<br />'); ?> Posted in <?php the_category(', ') ?> | <?php endif; ?><?php edit_post_link('Edit', '', ' | '); ?>  <?php if ( 'open' == $post->comment_status ) { ?><a href="<?php the_permalink() ?>#comments"><?php comments_number(__( 'Leave a Comment' ), __( '1 Comment' ), __( '% Comments' )); ?></a><?php } ?></p>
	</div>
</div>