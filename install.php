<?php
/**
 * This file contains the only one function wp_install().
 *
 * @package SQLite Integration
 * @author Kojima Toshiyasu, Justin Adie
 *
 */

if (!defined('ABSPATH')) {
	echo 'Thank you, but you are not allowed to access this file.';
	die();
}

/**
 * This function overrides wp_install() in wp-admin/includes/upgrade.php
 */
function wp_install($blog_title, $user_name, $user_email, $public, $deprecated = '', $user_password = '') {
	if (!empty($deprecated))
		_deprecated_argument(__FUNCTION__, '2.6');

	wp_check_mysql_version();
	wp_cache_flush();
  make_db_current_silent();
	/* changes */
	require_once PDODIR . 'schema.php';
	make_db_sqlite();
	/* changes */
	populate_options();
	populate_roles();

	update_option('blogname', $blog_title);
	update_option('admin_email', $user_email);
	update_option('blog_public', $public);

		// Freshness of site - in the future, this could get more specific about actions taken, perhaps.
		update_option( 'fresh_site', 1 );

		if ( $language ) {
			update_option( 'WPLANG', $language );
		}

		$guessurl = wp_guess_url();

		update_option( 'siteurl', $guessurl );

		// If not a public site, don't ping.
		if ( ! $public ) {
			update_option( 'default_pingback_flag', 0 );
		}

		/*
		 * Create default user. If the user already exists, the user tables are
		 * being shared among sites. Just set the role in that case.
		 */
		$user_id        = username_exists( $user_name );
		$user_password  = trim( $user_password );
		$email_password = false;
		$user_created   = false;

		if ( ! $user_id && empty( $user_password ) ) {
			$user_password = wp_generate_password( 12, false );
			$message       = __( '<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.' );
			$user_id       = wp_create_user( $user_name, $user_password, $user_email );
			update_user_option( $user_id, 'default_password_nag', true, true );
			$email_password = true;
			$user_created   = true;
		} elseif ( ! $user_id ) {
			// Password has been provided.
			$message      = '<em>' . __( 'Your chosen password.' ) . '</em>';
			$user_id      = wp_create_user( $user_name, $user_password, $user_email );
			$user_created = true;
		} else {
			$message = __( 'User already exists. Password inherited.' );
		}

		$user = new WP_User( $user_id );
		$user->set_role( 'administrator' );

		if ( $user_created ) {
			$user->user_url = $guessurl;
			wp_update_user( $user );
		}

		wp_install_defaults( $user_id );

		wp_install_maybe_enable_pretty_permalinks();

		flush_rewrite_rules();

		wp_new_blog_notification( $blog_title, $guessurl, $user_id, ( $email_password ? $user_password : __( 'The password you chose during installation.' ) ) );

		wp_cache_flush();

		/**
		 * Fires after a site is fully installed.
		 *
		 * @since 3.9.0
		 *
		 * @param WP_User $user The site owner.
		 */
		do_action( 'wp_install', $user );

		return array(
			'url'              => $guessurl,
			'user_id'          => $user_id,
			'password'         => $user_password,
			'password_message' => $message,
		);
}
?>
