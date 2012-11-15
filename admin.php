<?php
/*  admin.php

	This file allows you to view/change options for bCMS


	Copyright 2004 - 2008  Casey Bisson

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


add_action( 'admin_menu', 'bcms_config_page' );
function bcms_config_page()
{
	add_submenu_page( 'plugins.php' , __('bCMS Configuration') , __('bCMS Configuration') , 'manage_options' , 'bcms-options' , 'bcms_options' );
}

add_filter( 'plugin_action_links', 'bcms_plugin_action_links', 10, 2 );
function bcms_plugin_action_links( $links, $file )
{
	if ( $file == plugin_basename( dirname(__FILE__) .'/bcms.php' ))
		$links[] = '<a href="plugins.php?page=bcms-options">'. __('Settings') .'</a>';

	return $links;
}

add_action( 'admin_init' , 'bcms_admin_init' );
function bcms_admin_init()
{
	register_setting( 'bcms-options', 'bcms_insert_related', 'absint' );
	register_setting( 'bcms-options', 'bcms_searchsmart', 'absint' );
	register_setting( 'bcms-options', 'bcms_swhl', 'absint' );
	register_setting( 'bcms-options', 'bcms_managefocus_month', 'absint' );
	register_setting( 'bcms-options', 'bcms_managefocus_author', 'absint' );
}

function bcms_options()
{


//  apply new settings if form submitted
if($_REQUEST['Options'] == __('Show rewrite rules'))
{
	echo '<div class="updated"><p><strong>' . __('The current rewrite rules (permlink settings)') . ':</strong></p></div><div class="wrap" style="overflow:auto;"><pre>';
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
	print_r( $wp_rewrite->rewrite_rules() );
	echo '</pre></div>';
}
else if($_REQUEST['Options'] == __('PHP Info'))
{
	phpinfo();
}


//  output settings/configuration form
?>
<div class="wrap">

<form method="post" action="options.php">
<?php settings_fields('bcms-options'); ?>


<h3><?php _e('Options'); ?></h3>

<table class="form-table">

<!--
<tr>
<th scope="row" class="th-full">
<label for="bcms_insert_related">
<input name="bcms_insert_related" type="checkbox" id="bcms_insert_related" value="1" <?php checked('1', get_option('bcms_insert_related')); ?> />
<?php _e('Insert related posts links at bottom of each post') ?>
</label>
</th>
</tr>
<tr>
-->

<tr>
<th scope="row" class="th-full">
<label for="bcms_searchsmart">
<input name="bcms_searchsmart" type="checkbox" id="bcms_searchsmart" value="1" <?php checked('1', get_option('bcms_searchsmart')); ?> />
<?php _e('Enhance WordPress search with full text keyword indexing') ?>
</label>
</th>
</tr>
<tr>

<!--
<tr>
<th scope="row" class="th-full">
<label for="bcms_swhl">
<input name="bcms_swhl" type="checkbox" id="bcms_swhl" value="1" <?php checked('1', get_option('bcms_swhl')); ?> />
<?php _e('Highlight search words for users who arrive at this site from recognized search engines') ?>
</label>
</th>
</tr>
<tr>
-->

</table>

<!--
<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('Management focus') ?></th>
<td>
<label for="bcms_managefocus_author">
<input name="bcms_managefocus_author" type="checkbox" id="bcms_managefocus_author" value="1" <?php checked('1', get_option('bcms_managefocus_author')); ?> />
<?php _e('Focus default management view on current user') ?>
</label> &nbsp; 
<label for="bcms_managefocus_month">
<input name="bcms_managefocus_month" type="checkbox" id="bcms_managefocus_month" value="1" <?php checked('1', get_option('bcms_managefocus_month')); ?> />
<?php _e('Focus default management view on current month') ?>
</label>
</td>
</tr>
</table>
-->

<p class="submit">
<input type="submit" name="Submit" value="<?php esc_attr_e('Save Changes') ?>" class="button" />
</p>
</form>


<h3>&nbsp;</h3>


<h3><?php _e('Documentation') ?></h3>
<p><?php _e('More information about bCMS is available at <a href="http://maisonbisson.com/bsuite/">MaisonBisson.com</a>.') ?></p>


<!--
<h3><?php _e('bCMS Commands') ?></h3>
<p><?php _e('bCMS will do these things automatically; these buttons are here for the impatient.') ?></p>
<table class="form-table submit">
<tr>
<th scope="row" class="th-full">
<form method="post">
<input type="submit" name="Options" value="<?php esc_attr_e('Rebuild bCMS search index') ?>" /> &nbsp; 
</th>
</tr>
</table>
-->

<h3><?php _e('Debugging Tools') ?></h3>
<p><?php _e('Easy access to information about WordPress and PHP.') ?></p>
<table class="form-table submit">
<tr>
<th scope="row" class="th-full">
<form method="post">
<input type="submit" name="Options" value="<?php esc_attr_e('Show rewrite rules') ?>" /> &nbsp; 
<input type="submit" name="Options" value="<?php esc_attr_e('PHP Info') ?>" /></form>
</th>
</tr>
</table>
</div>
<?php
}
