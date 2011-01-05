<?php
/*
Plugin Name: Content Monitor
Plugin URI: 
Description:
Author: Andrew Billits
Version: 1.2.1
Author URI:
WDP ID: 12
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

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

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

$content_monitor_message_subject = "SITE_NAME: Content Notification";

$content_monitor_message_content = "Dear EMAIL,

The following TYPE has been flagged as possibly containing a non-allowed word:

PERMALINK

Cheers,

--The Team @ SITE_NAME";

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
add_action('admin_menu', 'content_monitor_plug_pages');

if (get_site_option('content_monitor_post_monitoring') == '1'){
	add_action('save_post', 'content_monitor_post_monitor');
}

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function content_monitor_send_email($post_permalink, $post_type) {
	global $wpdb, $wp_roles, $current_user, $user_ID, $current_site, $content_monitor_message_subject, $content_monitor_message_content;

	$send_to_email = get_site_option('content_monitor_email');
	if ($send_to_email == ''){
		$send_to_email = get_site_option( "admin_email" );
	}

	$message_content = $content_monitor_message_content;
	$message_content = str_replace( "SITE_NAME", $current_site->site_name, $message_content );
	$message_content = str_replace( "SITE_URL", 'http://' . $current_site->domain . '', $message_content );
	$message_content = str_replace( "PERMALINK", $post_permalink, $message_content );
	$message_content = str_replace( "TYPE", $post_type, $message_content );
	$message_content = str_replace( "EMAIL", $send_to_email, $message_content );
	$message_content = str_replace( "\'", "'", $message_content );

	if ($invite_message == ''){
		$message_content = str_replace( "INVITE_MESSAGE", '', $message_content );
	} else {
		$message_content = str_replace( "INVITE_MESSAGE", '"' . $invite_message . '"', $message_content );
	}
	
	$subject_content = $content_monitor_message_subject;
	$subject_content = str_replace( "SITE_NAME", $current_site->site_name, $subject_content );

	$from_email = 'notifications@' . $current_site->domain;
	
	$from = get_site_option( "site_name" );
	
	if ($from == ''){
		$from = $current_site->domain;
	}
	
	$message_headers = "MIME-Version: 1.0\n" . "From: " . $from .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
	wp_mail($send_to_email, $subject_content, $message_content, $message_headers);
}

function content_monitor_post_monitor($post_id) {
	global $wpdb, $wp_roles, $current_user;

	//get bad words array
	$bad_words = get_site_option('content_monitor_bad_words');
	$bad_words = ',,' . $bad_words . ',,';
	$bad_words = str_replace( " ", '', $bad_words );
	$bad_words_array = explode(",", $bad_words);
	//get post content words array
	$post_content = $wpdb->get_var("SELECT post_content FROM " . $wpdb->posts . " WHERE ID = '" . $post_id . "'");
	$post_content = strip_tags($post_content);
	$post_content = preg_replace('/[^a-zA-Z0-9-\s]/', '', $post_content); 
	$post_content_words_array = explode(" ", $post_content);
	//get post title words array
	$post_title = $wpdb->get_var("SELECT post_title FROM " . $wpdb->posts . " WHERE ID = '" . $post_id . "'");
	$post_title = strip_tags($post_title);
	$post_title = preg_replace('/[^a-zA-Z0-9-\s]/', '', $post_title); 
	$post_title_words_array = explode(" ", $post_title);
	
	
	$post_type = $wpdb->get_var("SELECT post_type FROM " . $wpdb->posts . " WHERE ID = '" . $post_id . "'");
	$post_permalink = get_permalink($post_id);
	if ( $post_type != 'revision' ) {
		$bad_word_count = 0;
		foreach ($bad_words_array as $bad_word){
			if ( strlen( $bad_word ) != '' ) {
				foreach ($post_content_words_array as $post_content_word){
					if ( strlen( $post_content_word ) != '' ) {
						if (strtolower($post_content_word) == strtolower($bad_word)){
							$bad_word_count = $bad_word_count + 1;
						}
					}
				}
				foreach ($post_title_words_array as $post_title_word){
					if ( strlen( $post_title_word ) != '' ) {
						if (strtolower($post_title_word) == strtolower($bad_word)){
							$bad_word_count = $bad_word_count + 1;
						}
					}
				}
			}
		}
		
		if ($bad_word_count > 0){
			content_monitor_send_email($post_permalink, $post_type);
		}
	}
}

function content_monitor_plug_pages() {
	global $wpdb, $wp_roles, $current_user;
	if ( is_site_admin() ) {
		add_submenu_page('ms-admin.php', 'Content Monitor', 'Content Monitor', 10, 'content-monitor', 'content_monitor_page_main_output');
	}
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function content_monitor_page_main_output() {
	global $wpdb, $wp_roles, $current_user;
	
	if(!current_user_can('manage_options')) {
		echo "<p>" . __('Nice Try...') . "</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
			<h2><?php _e('Content Monitor') ?></h2>
            <form method="post" action="ms-admin.php?page=content-monitor&action=update">
            <table class="form-table">
            <tr valign="top">
            <th scope="row"><?php _e('Email Address') ?></th>
            <td>
            <input name="content_monitor_email" type="text" id="content_monitor_email" style="width: 95%" value="<?php echo get_site_option('content_monitor_email') ?>" size="45" />
            <br /><?php _e('Content notifications will be sent to this address. If left blank the site admin email will be used.') ?></td>
            </tr>
            <tr valign="top">
            <th scope="row"><?php _e('Post/Page Monitoring') ?></th>
            <td>
            <select name="content_monitor_post_monitoring" id="content_monitor_post_monitoring">
            <?php
            if (get_site_option('content_monitor_post_monitoring') == '1'){
            ?>
            <option value="1" selected="selected"><?php _e('Enabled') ?></option>
            <option value="0"><?php _e('Disabled') ?></option>
            <?php
            } else {
            ?>
            <option value="1"><?php _e('Enabled') ?></option>
            <option value="0" selected="selected"><?php _e('Disabled') ?></option>
            <?php
            }
            ?>
            </select>
            <br /><?php //_e('') ?></td>
            </tr>
            <tr valign="top">
            <th scope="row"><?php _e('Bad Words') ?></th>
            <td>
            <textarea name="content_monitor_bad_words" type="text" rows="5" wrap="soft" id="content_monitor_bad_words" style="width: 95%"/><?php echo get_site_option('content_monitor_bad_words') ?></textarea>
            <br /><?php _e('Place a comma between each word (ex bad, word).') ?></td>
            </tr>
            </table>
            
            <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
            </p>
            </form>
			<?php
		break;
		//---------------------------------------------------//
		case "update":
			update_site_option( "content_monitor_email", stripslashes($_POST[ 'content_monitor_email' ]) );
			update_site_option( "content_monitor_post_monitoring", $_POST[ 'content_monitor_post_monitoring' ] );
			update_site_option( "content_monitor_bad_words", stripslashes($_POST[ 'content_monitor_bad_words' ]) );
			echo "<p>" . __('Options Updated!') . "</p>";
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='ms-admin.php?page=content-monitor&updated=true&updatedmsg=" . urlencode(__('Settings saved.')) . "';
			</script>
			";
		break;
		//---------------------------------------------------//
		case "temp":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

?>
