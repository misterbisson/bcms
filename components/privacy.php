<?php
/*  Copyright 2009  Casey Bisson

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


	* 
	* Acknowldgements
	*
	This component includes code copyrighted by D Sader (http://www.snowotherway.org/) 
	and relased under the terms of the GPL version 2.
	D Sader's code is available here: http://wpmudev.org/project/More-Privacy-Options/

*/
class bSuite_options_privacy {

	function bSuite_options_privacy() {
		global $current_blog;

		// SiteAdmin->Options if running in WPMU
		add_action( 'update_wpmu_options', array( &$this, 'sitewide_privacy_update' ));
		add_action( 'wpmu_options', array( &$this, 'sitewide_privacy_options_page' ));

		// hook into options-privacy.php Dashboard->Settings->Privacy.
		add_action('blog_privacy_selector', array( &$this, 'privacy_options' ));

		if( '-1' == get_site_option( 'bsuite_site_privacy' )){
				add_action('template_redirect', array( &$this, 'check_authz' ));
				$this->set_privacy_filters();
		}else{
			switch( get_option( 'blog_public' )){
				case '-3':
				case '-2':
				case '-1':
					add_action('template_redirect', array( &$this, 'check_authz' ));
				case '0':
					$this->set_privacy_filters();
			}
		}
	}

	function set_privacy_filters() {
		// fixes robots.txt rules 
		remove_action('do_robots', 'do_robots');
		add_action('do_robots', array( &$this, 'do_robots' ), 1 );

		// the default noindex function doesn't understand these extended options, so we remove and replace it
		remove_action( 'login_head', 'noindex' );
		remove_action( 'wp_head', 'noindex',1 );//priority 1
		add_action('wp_head', array( &$this, 'noindex' ), 1 );
		add_action('login_head', array( &$this, 'noindex' ), 1 );

		// the default privacy ping filter doesn't understand these extended options, so we remove and replace it
		remove_filter( 'option_ping_sites', 'privacy_ping_filter' );
		add_filter('option_ping_sites', array( &$this, 'privacy_ping_filter' ),1);
	}

	function do_robots() {
		header( 'Content-Type: text/plain; charset=utf-8' );
		do_action( 'do_robotstxt' );

		echo "User-agent: *\n";
		echo "Disallow:\n";
		echo "Disallow: /wp-admin\n";
		echo "Disallow: /wp-includes\n";
		echo "Disallow: /wp-login.php\n";
		echo "Disallow: /wp-content/plugins\n";
		echo "Disallow: /wp-content/cache\n";
		echo "Disallow: /wp-content/themes\n";
		echo "Disallow: /trackback\n";
		echo "Disallow: /comments\n";
	}

	function noindex() {
		echo "<meta name='robots' content='noindex,nofollow' />\n";
	}

	function privacy_ping_filter($sites) {
		return '';
	}

	function check_authz () {
		if ( !is_user_logged_in() )
			auth_redirect();

		switch( get_option( 'blog_public' )){
			case '-3':
				if( !current_user_can( 'manage_options' ))
					$this->fail_authz();
			case '-2':
				if( !current_user_can( 'read' ))
					$this->fail_authz();
		}
	}


	function fail_authz() { 
		nocache_headers();
//		header( 'WWW-Authenticate: Basic realm="' . $_SERVER['SERVER_NAME'] . '"' );
		header( 'HTTP/1.0 401 Unauthorized' );
?>
<h1>HTTP/1.0 401 Unauthorized</h1>
<?php
		die;
	}

	function privacy_options($options) { 
		global $current_site;

		if( is_object( $current_site )): // blog community only appropriate if running WPMU ?>
			<p>
				<input id="blog-private" type="radio" name="blog_public" value="-1" <?php checked('-1', get_option('blog_public')); ?> />
				<label for="blog-private"><?php _e('I would like my blog to be visible only to registered members of any blog on this server.'); ?></label>
			</p>
		<?php endif; ?>
		<p>
			<input id="blog-private" type="radio" name="blog_public" value="-2" <?php checked('-2', get_option('blog_public')); ?> />
			<label for="blog-private"><?php printf(__('I would like my blog to be visible only to <a href="%s">registered members</a> of this blog.'), admin_url( 'users.php' )); ?></label>
		</p>
		<p>
			<input id="blog-private" type="radio" name="blog_public" value="-3" <?php checked('-3', get_option('blog_public')); ?> />
			<label for="blog-private"><?php printf(__('I would like my blog to be visible only to <a href="%s">administrators</a> of this blog.'), admin_url( 'users.php?role=administrator' )); ?></label>
		</p>
	<?php 
	}

	function sitewide_privacy_options_page() {
		$number = intval(get_site_option('bsuite_site_privacy'));
		if ( !isset($number) ) {
			$number = '1';
		}
		echo '<h3>Sitewide Privacy Selector</h3>';
		echo '
		<table class="form-table">
		<tr valign="top"> 
			<th scope="row">' . __('Site Privacy') . '</th>';
			$checked = ( $number == "-1" ) ? " checked=''" : "";
		echo '<td><input type="radio" name="bsuite_site_privacy" id="bsuite_site_privacy" value="-1" ' . $checked . '/>
			<br />
			<small>
			' . __('Site can be viewed by registered users of this community only.') . '
			</small></td>';
			$checked = ( $number == "1" ) ? " checked=''" : "";
		echo '<td><input type="radio" name="bsuite_site_privacy" id="bsuite_site_privacy_1" value="1" ' . $checked . '/>
			<br />
			<small>
			' . __('Default: privacy managed per blog.') . '
			</small></td>
		</tr>
		</table>'; 
	}

	function sitewide_privacy_update() {
		update_site_option('bsuite_site_privacy', (int) $_POST['bsuite_site_privacy'] );
	}
}

// instantiate the class
$bsuite_options_privacy = new bSuite_options_privacy();