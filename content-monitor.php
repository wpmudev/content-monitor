<?php
/*
Plugin Name: Content Monitor
Plugin URI: https://premium.wpmudev.org/project/content-monitor/
Description: Allows you to monitor your entire site for set words that you define (and get an email whenever they are used) - perfect for educational or high profile sites.
Version: 1.4
Author: WPMU DEV
Author URI: https://premium.wpmudev.org/
Text Domain: contentmon
Domain Path: /languages/
Network: true
WDP ID: 12
*/

/* 
Copyright 2007-2015 Incsub (http://incsub.com)
Developers: Andrew Billits, Aaron Edwards

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//force multisite
if ( ! is_multisite() ) {
	die( __( 'Content Monitor is only compatible with Multisite installs.', 'contentmon' ) );
}

class Content_Monitor {

	public function __construct() {
		add_action( 'plugins_loaded', array( &$this, 'localization' ) );
		add_action( 'network_admin_menu', array( &$this, 'plug_pages' ) );

		if ( get_site_option( 'content_monitor_post_monitoring' ) ) {
			add_action( 'publish_post', array( &$this, 'post_monitor', 10, 2 ) );
			add_action( 'publish_page', array( &$this, 'post_monitor', 10, 2 ) );
		}
	}

	public function localization() {
		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's "languages" folder and name it "contentmon-[value in wp-config].mo"
		load_plugin_textdomain( 'contentmon', false, '/content-monitor/languages/' );
	}

	public function send_email( $post_permalink, $post_type ) {
		global $current_site, $content_monitor_message_subject, $content_monitor_message_content;

		$content_monitor_message_subject = __( "SITE_NAME: Content Notification", 'contentmon' );

		$content_monitor_message_content = __( "Dear EMAIL,

The following TYPE on SITE_NAME has been flagged as possibly containing a non-allowed word:

PERMALINK", 'contentmon' );

		$send_to_email = get_site_option( 'content_monitor_email' );
		if ( $send_to_email == '' ) {
			$send_to_email = get_site_option( "admin_email" );
		}

		$message_content = $content_monitor_message_content;
		$message_content = str_replace( "SITE_NAME", $current_site->site_name, $message_content );
		$message_content = str_replace( "SITE_URL", 'http://' . $current_site->domain . '', $message_content );
		$message_content = str_replace( "PERMALINK", $post_permalink, $message_content );
		$message_content = str_replace( "TYPE", $post_type, $message_content );
		$message_content = str_replace( "EMAIL", $send_to_email, $message_content );
		$message_content = str_replace( "\'", "'", $message_content );

		$subject_content = $content_monitor_message_subject;
		$subject_content = str_replace( "SITE_NAME", $current_site->site_name, $subject_content );

		wp_mail( $send_to_email, $subject_content, $message_content );
	}

	public function post_monitor( $post_id, $post ) {
		//get bad words array
		$bad_words       = get_site_option( 'content_monitor_bad_words' );
		$bad_words_array = explode( ",", $bad_words );
		$bad_words_array = array_map( 'trim', $bad_words_array );

		//get post content words array
		$post_content = $post->post_title . ' ' . $post->post_content;
		$post_content = wp_filter_nohtml_kses( $post_content );

		$bad_word_count = 0;
		foreach ( $bad_words_array as $bad_word ) {
			if ( false !== mb_stripos( $post_content, $bad_word, 0, 'UTF-8' ) ) {
				$bad_word_count ++;
			}

			if ( $bad_word_count > 0 ) {
				break;
			}
		}

		if ( $bad_word_count > 0 ) {
			$post_permalink = get_permalink( $post_id );
			$this->send_email( $post_permalink, $post->post_type );
		}
	}

	public function plug_pages() {
		add_submenu_page( 'settings.php', __( 'Content Monitor', 'contentmon' ), __( 'Content Monitor', 'contentmon' ), 'manage_network_options', 'content-monitor', array( &$this, 'page_main_output' ) );
	}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

	public function page_main_output() {
		global $wpdb, $wp_roles, $current_user, $cm_admin_url;

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( 'Nice Try...' );  //If accessed properly, this message doesn't appear.
		}

		echo '<div class="wrap">';

		if ( isset( $_POST['content_monitor_email'] ) ) {
			update_site_option( "content_monitor_email", stripslashes( $_POST['content_monitor_email'] ) );
			update_site_option( "content_monitor_post_monitoring", (int) $_POST['content_monitor_post_monitoring'] );
			update_site_option( "content_monitor_bad_words", stripslashes( $_POST['content_monitor_bad_words'] ) );
			?>
			<div id="message" class="updated fade"><p><?php _e( 'Settings saved.', 'contentmon' ) ?></p></div><?php
		}

		?>
		<h2><?php _e( 'Content Monitor', 'contentmon' ) ?></h2>
		<form method="post" action="">
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Email Address', 'contentmon' ) ?></th>
					<td>
						<?php $email = get_site_option( 'content_monitor_email' );
						$email       = is_email( $email ) ? $email : get_site_option( "admin_email" );
						?>
						<input name="content_monitor_email" type="text" id="content_monitor_email" style="width: 95%"
						       value="<?php echo esc_attr( $email ); ?>" size="45"/>
						<br/><?php _e( 'Content notifications will be sent to this address.', 'contentmon' ) ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Post/Page Monitoring', 'contentmon' ) ?></th>
					<td>
						<select name="content_monitor_post_monitoring" id="content_monitor_post_monitoring">
							<?php
							$enabled = (bool) get_site_option( 'content_monitor_post_monitoring' );
							?>
							<option
								value="1"<?php selected( $enabled, true ); ?>><?php _e( 'Enabled', 'contentmon' ) ?></option>
							<option
								value="0"<?php selected( $enabled, false ); ?>><?php _e( 'Disabled', 'contentmon' ) ?></option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Bad Words', 'contentmon' ) ?></th>
					<td>
					<textarea name="content_monitor_bad_words" type="text" rows="5" wrap="soft"
					          id="content_monitor_bad_words"
					          style="width: 95%"/><?php echo esc_textarea( get_site_option( 'content_monitor_bad_words' ) ); ?></textarea>
						<br/><?php _e( 'Place a comma between each word (ex bad, word).', 'contentmon' ) ?></td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="Submit" class="button-primary"
				       value="<?php _e( 'Save Changes', 'contentmon' ) ?>"/>
			</p>
		</form>
		<?php

		echo '</div>';
	}
}
new Content_Monitor();

global $wpmudev_notices;
$wpmudev_notices[] = array( 'id'      => 12,
                            'name'    => 'Content Monitor',
                            'screens' => array( 'settings_page_content-monitor-network' )
);
include_once( dirname( __FILE__ ) . '/dash-notice/wpmudev-dash-notification.php' );