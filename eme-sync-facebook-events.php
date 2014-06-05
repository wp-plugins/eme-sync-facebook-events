<?php
/*
Plugin Name: EME Sync Facebook Events
Plugin URI: http://www.e-dynamics.be/wordpress
Description: Sync Facebook Events to The Events Made Easy Plugin 
Author: Franky Van Liedekerke
Version: 1.0.4
Author URI: http://www.e-dynamics.be
*/
 
/*  Copyright 2014 Franky Van Liedekerke

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
// for media upload
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/media.php' );

// include required files form Facebook SDK
require_once( 'Facebook/FacebookSession.php' );
require_once( 'Facebook/FacebookRedirectLoginHelper.php' );
require_once( 'Facebook/FacebookRequest.php' );
require_once( 'Facebook/FacebookResponse.php' );
require_once( 'Facebook/FacebookSDKException.php' );
require_once( 'Facebook/FacebookRequestException.php' );
require_once( 'Facebook/FacebookOtherException.php' );
require_once( 'Facebook/FacebookAuthorizationException.php' );
require_once( 'Facebook/GraphObject.php' );
require_once( 'Facebook/GraphUser.php' );
require_once( 'Facebook/GraphSessionInfo.php' );

use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookOtherException;
use Facebook\FacebookAuthorizationException;
use Facebook\GraphObject;
use Facebook\GraphUser;
use Facebook\GraphSessionInfo;

register_activation_hook(__FILE__,'activate_eme_sfe');
register_deactivation_hook(__FILE__,'deactivate_eme_sfe');
function activate_eme_sfe() { wp_schedule_event(time(), 'daily', 'eme_sfe_execute_sync'); }
function deactivate_eme_sfe() { wp_clear_scheduled_hook('eme_sfe_execute_sync'); }
add_action('eme_sfe_execute_sync', 'eme_sfe_process_events');
add_action('init','eme_sfe_load_textdomain');

function eme_sfe_load_textdomain() {
   $thisDir = dirname( plugin_basename( __FILE__ ) );
   load_plugin_textdomain('eme_sfe', false, $thisDir.'/langs');
}

function update_schedule($eme_sfe_frequency) {
   wp_clear_scheduled_hook('eme_sfe_execute_sync');
   if ($eme_sfe_frequency!="none") {
      wp_schedule_event(time(), $eme_sfe_frequency, 'eme_sfe_execute_sync');
   }
}

function eme_sfe_media_sideload_image($url,$event_name) {
   // from media_sideload_image
   if ( ! empty($url) ) {
      // Download file to temp location
      $tmp = download_url( $url );

      // Set variables for storage
      // fix file filename for query strings
      preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $url, $matches );
      $file_array['name'] = basename($matches[0]);
      $file_array['tmp_name'] = $tmp;

      // If error storing temporarily, unlink
      if ( is_wp_error( $tmp ) ) {
         @unlink($file_array['tmp_name']);
         $file_array['tmp_name'] = '';
      }

      // do the validation and storage stuff
      $post_id=0;
      $desc="Cover image for EME event '$event_name'";
      $id = media_handle_sideload( $file_array, $post_id, $desc );
      // If error storing permanently, unlink
      if ( is_wp_error($id) ) {
         @unlink($file_array['tmp_name']);
         return false;
      }

      $src = wp_get_attachment_url( $id );
      return array(0=>$id,1=>$src);
   } else {
      return false;
   }
}

function eme_sfe_add_page() { add_options_page('EME Sync FB Events', 'EME Sync FB Events', 'activate_plugins', __FILE__, 'eme_sfe_options_page'); }
add_action('admin_menu', 'eme_sfe_add_page');

function eme_sfe_process_events() {
   // Get option values
   $eme_sfe_api_key = get_option('eme_sfe_api_key');
   $eme_sfe_api_secret = get_option('eme_sfe_api_secret');
   $eme_sfe_api_uid = get_option('eme_sfe_api_uid');
   $eme_sfe_api_uids = get_option('eme_sfe_api_uids');	
   $eme_sfe_frequency = get_option('eme_sfe_frequency');

   $events = eme_sfe_get_events($eme_sfe_api_key, $eme_sfe_api_secret, $eme_sfe_api_uids);
   if ($events !== false && is_array($events))
      eme_sfe_send_events($events);
}

function eme_sfe_get_events($eme_sfe_api_key, $eme_sfe_api_secret, $eme_sfe_uids) {
   if (empty($eme_sfe_api_key) || empty($eme_sfe_api_secret) || empty($eme_sfe_uids))
      return false;

   FacebookSession::setDefaultApplication($eme_sfe_api_key,$eme_sfe_api_secret);
   $facebook_session = FacebookSession::newAppSession();

   $ret = array();
   foreach ($eme_sfe_uids as $key => $value) {
      if ($value!='') {
         if (is_numeric($value)) {
            $events = (new FacebookRequest( $facebook_session, 'GET', '/'.$value.'/events'))->execute();
         } else {
            //$response = (new FacebookRequest( $facebook_session, 'GET', '/'.$value))->execute()->getGraphObject()->asArray();
            $response = (new FacebookRequest( $facebook_session, 'GET', '/'.$value,array("fields"=>"id")))->execute()->getResponse();
            $api_uid=$response->id;
            $events = (new FacebookRequest( $facebook_session, 'GET', '/'.$api_uid.'/events'))->execute();
         }

         foreach ($events->getGraphObjectList() as $graphobject) {
            $event_id = $graphobject->getProperty('id');
            // the following works, but doesn't return the cover (api bug?), so we specify the fields we want
            //$event = (new FacebookRequest( $facebook_session, 'GET', '/'.$event_id))->execute()->getGraphObject()->asArray();
            $fields=array("fields"=>"id,name,location,venue,start_time,end_time,is_date_only,description,cover");
            $event = (new FacebookRequest( $facebook_session, 'GET', '/'.$event_id, $fields))->execute()->getGraphObject()->asArray();
            if (isset($event['cover']) && !empty($event['cover'])) {
               $event['event_picture_url']=$event['cover']->source;
            } else {
               $picture = (new FacebookRequest( $facebook_session, 'GET', '/'.$event_id.'/picture', array ('redirect' => false,'type' => 'normal')))->execute()->getGraphObject()->asArray();
               if ($picture->url) {
                  $event['event_picture_url']=$picture->url;
               }
            }
            $offsetStart = strtotime($event['start_time']);
            if($offsetStart > time())
               $ret[]=$event;
         }
      }
   }
   return $ret;
}

function eme_sfe_segments($url='') {
   $parsed_url = parse_url($url);
   $path = trim($parsed_url['path'],'/');
   return explode('/',$path);
}

function eme_sfe_check_image_id($id) {
   global $wpdb;
   // returns false if the ID doesn't exist
   return $wpdb->get_var("SELECT id FROM wp_posts WHERE id = '" . $id . "'", 'ARRAY_A');
}

function eme_sfe_check_event_fbid($id) {
   global $wpdb;
   $table_name = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT event_id FROM $table_name WHERE event_external_ref = %s","fb_".$id);
   return $wpdb->get_var($sql);
}

function eme_sfe_check_location_fbid($id) {
   global $wpdb;
   $table_name = $wpdb->prefix . LOCATIONS_TBNAME;
   $sql = $wpdb->prepare("SELECT location_id FROM $table_name WHERE location_external_ref = %s","fb_".$id);
   return $wpdb->get_var($sql);
}

function eme_sfe_check_location_coord($lat,$long) {
   global $wpdb;
   $table_name = $wpdb->prefix . LOCATIONS_TBNAME;
   $sql = $wpdb->prepare("SELECT location_id FROM $table_name WHERE location_latitude = %f AND location_longitude = %f",$lat,$long);
   return $wpdb->get_var($sql);
}

function eme_sfe_send_events($events) {
   $offset = get_option('gmt_offset')*3600-date('Z');

   foreach($events as $fb_event) {
      $add_location_info=0;
      if (isset($fb_event['location'])) {
         $add_location_info=1;
         $location_id=eme_sfe_check_location_fbid($fb_event['venue']->id);
         if (!$location_id && get_option('eme_sfe_use_loc_coord'))
            $location_id=eme_sfe_check_location_coord($fb_event['venue']->latitude,$fb_event['venue']->longitude);
         if ($location_id)
            $location = eme_get_location($location_id);
         else
            $location = eme_new_location();
         $location['location_name'] = $fb_event['location'];
         $location['location_address'] = $fb_event['venue']->street;
         $location['location_town'] = $fb_event['venue']->zip." ".$fb_event['venue']->city." ".$fb_event['venue']->country;
         $location['location_latitude'] = $fb_event['venue']->latitude;
         $location['location_longitude'] = $fb_event['venue']->longitude;
         $location['location_description'] = '';
         $location['location_external_ref'] = 'fb_'.$fb_event['venue']->id;

         if ($location_id) {
            if (get_option('eme_sfe_skip_synced')) {
               echo "<br />Skipping already synchronized location: $location_id";
            } else {
               eme_update_location($location);
               echo "<br />Updating location: $location_id";
            }
         } else {
            $location = eme_insert_location($location);
            $location_id = $location['location_id'];
            echo "<br />Inserting location: $location_id";
         }
      }

      $event_id=eme_sfe_check_event_fbid($fb_event['id']);
      if ($event_id)
         $event = eme_get_event($event_id);
      else
         $event = eme_new_event();

      $event['event_name']=$fb_event['name'];
      $offsetStart = strtotime($fb_event['start_time'])+$offset;
      $event['event_start_date']=date("Y-m-d", $offsetStart);
      $event['event_start_time']=date("H:i", $offsetStart);

      if (isset($fb_event['end_time'])) {
         $offsetEnd = strtotime($fb_event['end_time'])+$offset;
         $event['event_end_date']=date("Y-m-d", $offsetEnd);
         $event['event_end_time']=date("H:i", $offsetEnd);
      }

      $event['event_status'] = get_option('eme_sfe_event_initial_state');
      $event['event_notes'] = $fb_event['description'];
      $event['event_external_ref'] = 'fb_'.$fb_event['id'];
      if ($add_location_info)
         $event['location_id']=$location_id;
      if ($event_id) {
         if (get_option('eme_sfe_skip_synced')) {
            echo "<br />Skipping already synchronized event: $event_id";
         } else {
            if (isset($fb_event['event_picture_url'])) {
               if (basename($fb_event['event_picture_url']) != basename($event['event_image_url']) ||
                   (!empty($event['event_image_id']) && !eme_sfe_check_image_id($event['event_image_id']))) {
                  // only upload if needed
                  $res=eme_sfe_media_sideload_image($fb_event['event_picture_url'],$fb_event['name']);
                  if ($res && is_array($res)) {
                     $event['event_image_id']=$res[0];
                     $event['event_image_url']=$res[1];
                  }
               }
            }
            eme_db_update_event($event,$event_id);
            echo "<br />Updating event: ".$event_id;
         }
      } else {
         if (isset($fb_event['event_picture_url'])) {
            $res=eme_sfe_media_sideload_image($fb_event['event_picture_url'],$fb_event['name']);
            if ($res && is_array($res)) {
               $event['event_image_id']=$res[0];
               $event['event_image_url']=$res[1];
            }
         }
         $event_id=eme_db_insert_event($event);
         echo "<br />Inserting event: ".$event_id;
      }
   }
}

function eme_sfe_options_page() {
   // Get option values
   $eme_sfe_api_key = get_option('eme_sfe_api_key');
   $eme_sfe_api_secret = get_option('eme_sfe_api_secret');
   $eme_sfe_api_uid = get_option('eme_sfe_api_uid');
   $eme_sfe_api_uids = get_option('eme_sfe_api_uids');
   if (!$eme_sfe_api_uids)
      $eme_sfe_api_uids = array();
   $eme_sfe_frequency = get_option('eme_sfe_frequency');

   $events=false;
   // Get new updated option values, and save them
   if( !empty($_POST['update']) ) {

      $eme_sfe_api_key = $_POST['eme_sfe_api_key'];
      update_option('eme_sfe_api_key', $eme_sfe_api_key);

      $eme_sfe_api_secret = $_POST['eme_sfe_api_secret'];
      update_option('eme_sfe_api_secret', $eme_sfe_api_secret);

      $eme_sfe_api_uid = $_POST['eme_sfe_api_uid'];
      update_option('eme_sfe_api_uid', $eme_sfe_api_uid);

      $eme_sfe_frequency = $_POST['eme_sfe_frequency'];
      update_option('eme_sfe_frequency', $eme_sfe_frequency);

      $eme_sfe_event_initial_state = $_POST['eme_sfe_event_initial_state'];
      update_option('eme_sfe_event_initial_state', $eme_sfe_event_initial_state);

      $eme_sfe_skip_synced = $_POST['eme_sfe_skip_synced'];
      update_option('eme_sfe_skip_synced', $eme_sfe_skip_synced);

      $eme_sfe_use_loc_coord = $_POST['eme_sfe_use_loc_coord'];
      update_option('eme_sfe_use_loc_coord', $eme_sfe_use_loc_coord);

      $events = eme_sfe_get_events($eme_sfe_api_key, $eme_sfe_api_secret, $eme_sfe_api_uids);

      update_schedule($eme_sfe_frequency);

      $msg = __("Synchronization of Facebook events to Events Made Easy complete.",'eme_sfe');
      ?>
         <div id="message" class="updated fade"><p><strong><?php echo $msg; ?></strong></p></div>
      <?php

   } elseif( !empty($_POST['add-uid']) ) {
      if(!in_array($_POST['eme_sfe_api_uid'], $eme_sfe_api_uids)) {
         $eme_sfe_api_uids[] = $_POST['eme_sfe_api_uid'];
         update_option('eme_sfe_api_uids', $eme_sfe_api_uids);
      }

   } elseif( !empty($_GET['r']) ) {
      foreach ($eme_sfe_api_uids as $key => $value)
         if($eme_sfe_api_uids[$key] == $_GET['r'])
            unset($eme_sfe_api_uids[$key]);
      update_option('eme_sfe_api_uids', $eme_sfe_api_uids);
   }	
   $this_page_url=$_SERVER['REQUEST_URI'];

   if (!function_exists("eme_options_input_text") || !function_exists("eme_options_input_text")) {
      ?>
      <div id="message" class="error"><p><?php _e("This plugin requires 'Events Made Easy' to be installed.",'eme_sfe'); ?> </p></div>
      <?php
      return;
   }

   ?>
   <div class="wrap">
   <br /><div class="icon32" id="icon-plugins"><br /></div>
   <h2 style="margin-bottom:10px;">Events Made Easy Sync Facebook Events</h2>
   <form method="post" action="">
   <table class="form-table"> 
   <input type="hidden" name="update" />
   <?php
   eme_options_input_text ( __('Facebook App ID', 'eme_sfe' ), 'eme_sfe_api_key', '');
   eme_options_input_text ( __('Facebook App Secret', 'eme_sfe' ), 'eme_sfe_api_secret', '');
   $eme_sfe_frequencies=array('daily'=>__("Daily",'eme_sfe'),"twicedaily"=>__("Twice Daily",'eme_sfe'),"hourly"=>__("Hourly",'eme_sfe'),"none"=>__("None",'eme_sfe'));
   eme_options_select (__('Update Fequency','eme_sfe'), 'eme_sfe_frequency', $eme_sfe_frequencies, '');
   eme_options_select (__('State for new event','eme_sfe'), 'eme_sfe_event_initial_state', eme_status_array(), '');
   eme_options_radio_binary (__('Use coordinates for locations','eme_sfe'), 'eme_sfe_use_loc_coord', __("Normally, the facebook location ID is used to check wether a location has been synchronized or not. Sometimes you want to use own locations with the same coordinates (latitude and longitude), so select 'Yes' to check for matching locations using coordinates.",'eme_sfe'));
   eme_options_radio_binary (__('Skip synced events and locations','eme_sfe'), 'eme_sfe_skip_synced', __("Select 'Yes' to skip already synchronized events and locations, otherwise these will be overwritten with every sync",'eme_sfe'));
   eme_options_input_text ( __('Add Facebook Page', 'eme_sfe' ), 'eme_sfe_api_uid', '<input type="submit" value="Add" class="button-secondary" name="add-uid" /><br />'.__("Can be a Facebook Page name like 'webtrends' or the Facebook Page ID for it (results in one less call to facebook).",'eme_sfe'));
   ?>
   <tr><td style="vertical-align:top;"></td><td>
   <?php
   foreach ($eme_sfe_api_uids as $value) {
      if ($value!='')
         echo '&nbsp;&nbsp;'.$value.'&nbsp;&nbsp;<a href="'.add_query_arg(array('r'=>$value),$this_page_url).'">remove</a><br />';
      }
   ?>
   </td></tr>
   <tr><td colspan="2"><input type="submit" value="<?php _e('Update','eme_sfe'); ?>" class="button-primary" name="update" /></td></tr>
   </table>
   </form>
   </div>
   <?php
   if ($events !== false && is_array($events)) { ?>
      <div style="margin-top:20px;font-size:14px;color:#444;border:1px solid #999;padding:15px;width:95%;font-face:couriernew;">
      <span style="color:red;"><?php _e('Updating all facebook events...','eme_sfe'); ?></span><br />
      <?php eme_sfe_send_events($events); ?><br />
      <span style="color:red;"><?php _e('Events Made Easy updated with current Facebook events.','eme_sfe'); ?></span><br /><br />
      </div>
  <?php
  }
}
?>
