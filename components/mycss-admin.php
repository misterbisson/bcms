<?php
if( isset( $_POST['bsuite_mycss_save'] )){

	update_option( 'bsuite_mycss', $bsuite->mycss_sanitize( $_POST['bsuite_mycss'] ));
	update_option( 'bsuite_mycss_replacethemecss', absint( $_POST['bsuite_mycss_replacethemecss'] ));
	if( $GLOBALS['content_width'] <> absint( $_POST['bsuite_mycss_maxwidth'] ))
		update_option( 'bsuite_mycss_maxwidth', absint( $_POST['bsuite_mycss_maxwidth'] ));
	else
		update_option( 'bsuite_mycss_maxwidth', 0 );

	?><div class="updated"><p><strong><?php _e('Your custom CSS has been saved.'); ?></strong></p></div><?php
}

?>
<style type="text/css">
.wrap form.bsuite_mycss_form {
	margin-right: 1.5em;
}
.wrap textarea#bsuite_mycss {
	width: 100%;
	height: 40em;
}
</style>
<?php if ($updated) { ?>
	<div class="updated"><p><strong><?php _e( 'Options saved.' ); ?></strong></p></div>
<?php } ?>
<div class="wrap">
<?php screen_icon(); ?>
<h2>Custom CSS Editor</h2>
<p>The CSS editor lets you modify the visual style of your blog. For help with CSS try <a href="http://www.w3schools.com/css/default.asp">W3Schools</a>, <a href="http://alistapart.com/">A List Apart</a>, <a href="http://www.thinkvitamin.com/features/CSS">Think Vitamin</a>, and the WordPress.com <a href="http://support.wordpress.com/editing-css/">CSS documentation</a> and <a href="http://en.forums.wordpress.com/forum/css-customization">CSS Forum</a>.</p>
<form id="bsuite_mycss_form" action="" method="post">
<p><textarea id="bsuite_mycss" name="bsuite_mycss"><?php echo strlen( get_option( 'bsuite_mycss' )) ? format_to_edit( get_option( 'bsuite_mycss' )) : '/* Welcome to Custom CSS!

You can select to add this stylesheet to your theme, or entirely replace it. If you add this to your theme, it will be loaded last, which means that your rules can take precedence and override the theme CSS rules.

Things we strip out include:
 * CSS comments
 * HTML code
 * @import rules
 * expressions
 * invalid and unsafe code
 * URLs not using the http: protocol

Things we encourage include:
 * @media blocks!
 * sharing your CSS!
 * testing in several browsers!
 * helping others!

Please use the contact form if you believe there is something wrong with the way the CSS Editor filters your code.
*/'; ?></textarea></p>
	<h4>Do you want to make changes to your current theme's stylesheet, or do you want to start from scratch?</h4>
	<p><label><input type="radio" name="bsuite_mycss_replacethemecss" value="0" <?php checked('0', get_option('bsuite_mycss_replacethemecss')); ?> /> Add this to the <?php echo attribute_escape( get_template() ); ?> theme's CSS stylesheet (<a href="<?php echo attribute_escape( get_stylesheet_uri() ); ?>">view original stylesheet</a>)</label><br />
	<label><input type="radio" name="bsuite_mycss_replacethemecss" value="1" <?php checked('1', get_option('bsuite_mycss_replacethemecss')); ?>/> Start from scratch and just use this </label>
	</p>

	<h4>If you change the width of your main content column, make sure your media files fit. Enter the maximum width for media files in your new CSS below.</h4>
	<p class="custom_content_width"><label for="custom_content_width">Limit width to</label>
	<input type="text" name="bsuite_mycss_maxwidth" id="bsuite_mycss_maxwidth" value="<?php echo 0 < absint( get_option( 'bsuite_mycss_maxwidth' )) ? absint( get_option( 'bsuite_mycss_maxwidth' )) : $GLOBALS['content_width']; ?>" size=5 /> pixels for videos, full size images, and other shortcodes. (<a href="http://support.wordpress.com/editing-css/#limited-width">more info from WordPress.com</a>)</p>
	<p class="submit">
		<?php wp_nonce_field('bsuite-mycss'); ?>
		<input type="hidden" name="bsuite_mycss_save" value="TRUE" />
		<input type="submit" name="submit" value="<?php _e('Save'); ?>" />
	</p>
</form>
</div>

<pre>
