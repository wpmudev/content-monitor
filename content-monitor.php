<?php
/*
Plugin Name: Content Monitor
Plugin URI: http://premium.wpmudev.org/project/content-monitor
Description: Allows you to monitor your entire site for set words that you define (and get an email whenever they are used) - perfect for educational or high profile sites.
Version: 1.2.2
Author: Andrew Billits (Incsub)
Author URI: http://premium.wpmudev.org
Network: true
WDP ID: 12
*/

/* 
Copyright 2007-2011 Incsub (http://incsub.com)

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
if ( !is_multisite() )
  die( __('Content Monitor is only compatible with Multisite installs.', 'contentmon') );


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

add_action( 'plugins_loaded', 'content_monitor_localization' );
add_action( 'admin_menu', 'content_monitor_plug_pages' );
add_action( 'network_admin_menu', 'content_monitor_plug_pages' ); //for 3.1

if ( get_site_option('content_monitor_post_monitoring') ) {
	add_action( 'save_post', 'content_monitor_post_monitor', 10, 2 );
}

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function content_monitor_localization() {
  // Load up the localization file if we're using WordPress in a different language
	// Place it in this plugin's "languages" folder and name it "contentmon-[value in wp-config].mo"
  load_plugin_textdomain( 'contentmon', false, '/content-monitor/languages/' );
}
    
function content_monitor_send_email($post_permalink, $post_type) {
	global $wpdb, $wp_roles, $current_user, $user_ID, $current_site, $content_monitor_message_subject, $content_monitor_message_content;

	$send_to_email = get_site_option('content_monitor_email');
	if ($send_to_email == '') {
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

	wp_mail($send_to_email, $subject_content, $message_content);
}

function content_monitor_post_monitor($post_id, $post) {
	global $wpdb, $wp_roles, $current_user;

  // Don't record this if it's not a post
	if ( !('post' == $post->post_type || 'page' == $post->post_type) )
		return false;

	if ( 'publish' != $post->post_status || !empty( $post->post_password ) )
  	return false;
  	
	//get bad words array
	$bad_words = get_site_option('content_monitor_bad_words');
	$bad_words = ',,' . $bad_words . ',,';
	$bad_words = str_replace( " ", '', $bad_words );
	$bad_words_array = explode(",", $bad_words);
	//get post content words array
	$post_content = $post->post_content;
	$post_content = strip_tags($post_content);
	$post_content = preg_replace('/[^a-zA-Z0-9-\s]/', '', $post_content); 
	$post_content_words_array = explode(" ", $post_content);
	//get post title words array
	$post_title = $post->post_title;
	$post_title = strip_tags($post_title);
	$post_title = preg_replace('/[^a-zA-Z0-9-\s]/', '', $post_title); 
	$post_title_words_array = explode(" ", $post_title);
	
	$post_permalink = get_permalink($post_id);

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
		content_monitor_send_email($post_permalink, $post->post_type);
	}
}

function content_monitor_plug_pages() {
	global $wp_version, $cm_admin_url;

	if ( version_compare($wp_version, '3.0.9', '>') ) {
    add_submenu_page('settings.php', __('Content Monitor', 'contentmon'), __('Content Monitor', 'contentmon'), 10, 'content-monitor', 'content_monitor_page_main_output');
    $cm_admin_url = admin_url('network/settings.php?page=content-monitor');
  } else {
    add_submenu_page('ms-admin.php', __('Content Monitor', 'contentmon'), __('Content Monitor', 'contentmon'), 10, 'content-monitor', 'content_monitor_page_main_output');
    $cm_admin_url = admin_url('ms-admin.php?page=content-monitor');
  }
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function content_monitor_page_main_output() {
	global $wpdb, $wp_roles, $current_user, $cm_admin_url;

	if( !is_super_admin() ) {
		echo "<p>" . __('Nice Try...') . "</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	
	echo '<div class="wrap">';
	
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php echo urldecode($_GET['updatedmsg']) ?></p></div><?php
	}
	

	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
			<h2><?php _e('Content Monitor', 'contentmon') ?></h2>
        <form method="post" action="<?php echo $cm_admin_url; ?>&action=update">
        <table class="form-table">
        <tr valign="top">
        <th scope="row"><?php _e('Email Address', 'contentmon') ?></th>
        <td>
        <input name="content_monitor_email" type="text" id="content_monitor_email" style="width: 95%" value="<?php echo get_site_option('content_monitor_email') ?>" size="45" />
        <br /><?php _e('Content notifications will be sent to this address. If left blank the site admin email will be used.', 'contentmon') ?></td>
        </tr>
        <tr valign="top">
        <th scope="row"><?php _e('Post/Page Monitoring', 'contentmon') ?></th>
        <td>
        <select name="content_monitor_post_monitoring" id="content_monitor_post_monitoring">
        <?php
        if (get_site_option('content_monitor_post_monitoring') == '1'){
        ?>
        <option value="1" selected="selected"><?php _e('Enabled', 'contentmon') ?></option>
        <option value="0"><?php _e('Disabled', 'contentmon') ?></option>
        <?php
        } else {
        ?>
        <option value="1"><?php _e('Enabled', 'contentmon') ?></option>
        <option value="0" selected="selected"><?php _e('Disabled', 'contentmon') ?></option>
        <?php
        }
        ?>
        </select>
        </td>
        </tr>
        <tr valign="top">
        <th scope="row"><?php _e('Bad Words', 'contentmon') ?></th>
        <td>
        <textarea name="content_monitor_bad_words" type="text" rows="5" wrap="soft" id="content_monitor_bad_words" style="width: 95%"/><?php echo get_site_option('content_monitor_bad_words') ?></textarea>
        <br /><?php _e('Place a comma between each word (ex bad, word).', 'contentmon') ?></td>
        </tr>
        </table>
        
        <p class="submit">
        <input type="submit" name="Submit" value="<?php _e('Save Changes', 'contentmon') ?>" />
        </p>
        </form>
			<?php
		break;
		//---------------------------------------------------//
		case "update":
			update_site_option( "content_monitor_email", stripslashes($_POST[ 'content_monitor_email' ]) );
			update_site_option( "content_monitor_post_monitoring", $_POST[ 'content_monitor_post_monitoring' ] );
			update_site_option( "content_monitor_bad_words", stripslashes($_POST[ 'content_monitor_bad_words' ]) );

			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='$cm_admin_url&updated=true&updatedmsg=" . urlencode(__('Settings saved.', 'contentmon')) . "';
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


///////////////////////////////////////////////////////////////////////////
/* -------------------- Update Notifications Notice -------------------- */
if ( !function_exists( 'wdp_un_check' ) ) {
  add_action( 'admin_notices', 'wdp_un_check', 5 );
  add_action( 'network_admin_notices', 'wdp_un_check', 5 );
  function wdp_un_check() {
    if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
      echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
  }
}
/* --------------------------------------------------------------------- */
?>