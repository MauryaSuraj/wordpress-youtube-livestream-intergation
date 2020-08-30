<?php 

/*
Plugin Name: YouLive
Plugin URI:  https://www.hakaba.com
Description: All Youtube activity
Version:     1.0
Author:      hakaba
Author URI: https://www.hakaba.com
*/


/**
 * Register a custom menu page.
 */



  if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}

require_once __DIR__ . '/vendor/autoload.php';
session_start();

// Auth here


if ( ! defined( 'ABSPATH' ) ) exit;
class youlive_oAuth_Section { 
  private $dir;
  private $file;
  private $token;

  public function __construct( $file ) {
    $this->dir = dirname( $file );
    $this->file = $file;
    $this->token = 'youlive_oAuth_Section';

    // Register plugin settings
    add_action( 'admin_init' , array( $this , 'register_settings' ) );
    // Add settings page to menu
    add_action( 'admin_menu' , array( $this , 'add_menu_item' ) );
    // Add settings link to plugins page
    add_filter( 'plugin_action_links_' . plugin_basename( $this->file ) , array( $this , 'add_settings_link' ) );


    // NEW: setup the wp ajax action for oAuth code exchange
    add_action( 'wp_ajax_youlive_finish_code_exchange', array($this, 'finish_code_exchange') );
    // NEW: setup the wp ajax action to logout from oAuth
    add_action( 'wp_ajax_youlive_logout_from_google', array($this, 'logout_from_google') );
  }

  /* The next few functions set up the settings page */
  
  public function add_menu_item() {
    add_options_page( 'youlive oAuth Demo Settings' , 'youlive oAuth Demo Settings' , 'manage_options' , 'youlive_oAuth_Section_settings' ,  array( $this , 'settings_page' ) );
  }

  public function add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=youlive_oAuth_Section_settings">Settings</a>';
    array_push( $links, $settings_link );
    return $links;
  }

  public function register_settings() {
    register_setting( 'youlive_oAuth_Section_group', 'youlive_oAuth_Section_settings' );
    add_settings_section('settingssection1', 'Google App Settings', array( $this, 'settings_section_callback'), 'youlive_oAuth_Section_settings');
    // you can define EVERYTHING to create, display, and process each settings field as one line per setting below.  And all settings defined in this function are stored as a single serialized object.
    add_settings_field( 'google_app_client_id', 'Google App Client ID', array( $this, 'settings_field'), 'youlive_oAuth_Section_settings', 'settingssection1', array('setting' => 'youlive_oAuth_Section_settings', 'field' => 'google_app_client_id', 'label' => '', 'class' => 'regular-text') );
    add_settings_field( 'google_app_client_secret', 'Google App Client Secret', array( $this, 'settings_field'), 'youlive_oAuth_Section_settings', 'settingssection1', array('setting' => 'youlive_oAuth_Section_settings', 'field' => 'google_app_client_secret', 'label' => '', 'class' => 'regular-text') );
    add_settings_field( 'google_app_redirect_uri', 'Google App Redirect URI', array( $this, 'settings_field'), 'youlive_oAuth_Section_settings', 'settingssection1', array('setting' => 'youlive_oAuth_Section_settings', 'field' => 'google_app_redirect_uri', 'label' => '', 'class' => 'regular-text') );
  }

  public function settings_section_callback() { echo ' '; }

  public function settings_field( $args ) {
    // This is the default processor that will handle standard text input fields.  Because it accepts a class, it can be styled or even have jQuery things (like a calendar picker) integrated in it.  Pass in a 'default' argument only if you want a non-empty default value.
    $settingname = esc_attr( $args['setting'] );
    $setting = get_option($settingname);
    $field = esc_attr( $args['field'] );
    $label = esc_attr( $args['label'] );
    $class = esc_attr( $args['class'] );
    $default = ($args['default'] ? esc_attr( $args['default'] ) : '' );
    $value = (($setting[$field] && strlen(trim($setting[$field]))) ? $setting[$field] : $default);
    echo '<input type="text" name="' . $settingname . '[' . $field . ']" id="' . $settingname . '[' . $field . ']" class="' . $class . '" value="' . $value . '" /><p class="description">' . $label . '</p>';
  }

  public function settings_page() {
    if (!current_user_can('manage_options')) {
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }
    ?>
    <div class="wrap">
      <h2>youlive oAuth  Settings</h2>
      <p>You'll need to go to the <a href="https://console.developers.google.com">Google Developer Console</a> to setup your project and setup the values below.</p>
      <form action="options.php" method="POST">
        <?php settings_fields( 'youlive_oAuth_Section_group' ); ?>
        <?php do_settings_sections( 'youlive_oAuth_Section_settings' ); ?>
        <?php submit_button(); ?>
      </form>
    <!-- We handle the login process on the settings page now -->
    <?php $this->write_out_oAuth_JavaScript(); ?>
    </div>
    <?php
  }

  // This function is the clearest way to get the oAuth JavaScript onto a page as needed.
  private function write_out_oAuth_JavaScript() {
    $settings = get_option('youlive_oAuth_Section_settings', true);
    ?>
  <script language=javascript>
  // we declare this variable at the top level scope to make it easier to pass around
  var google_access_token = "<?php echo $this->get_google_access_token(); ?>";

  jQuery(document).ready(function($) {
    var GOOGLECLIENTID = "<?php echo $settings['google_app_client_id']; ?>";
    var GOOGLECLIENTREDIRECT = "<?php echo $settings['google_app_redirect_uri']; ?>";
    // we don't need the client secret for this, and should not expose it to the web.

  function requestGoogleoAuthCode() {
    var OAUTHURL = 'https://accounts.google.com/o/oauth2/auth';
    var SCOPE = 'profile email openid https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/youtube.force-ssl';
    var popupurl = OAUTHURL + '?scope=' + SCOPE + '&client_id=' + GOOGLECLIENTID + '&redirect_uri=' + GOOGLECLIENTREDIRECT + '&response_type=code&access_type=offline&prompt=select_account consent';
    var win =   window.open(popupurl, "googleauthwindow", 'width=800, height=600'); 
    var pollTimer = window.setInterval(function() { 
      try {
        if (win.document.URL.indexOf(GOOGLECLIENTREDIRECT) != -1) {
          window.clearInterval(pollTimer);
          var response_url = win.document.URL;
          var auth_code = gup(response_url, 'code');
          console.log(response_url);
          win.close();
          // We don't have an access token yet, have to go to the server for it
          var data = {
            action: 'youlive_finish_code_exchange',
            auth_code: auth_code
          };
          $.post(ajaxurl, data, function(response) {
            console.log(response);
            google_access_token = response;
            getGoogleUserInfo(google_access_token);
          });
        }
      } catch(e) {}    
    }, 500);
  }

  // helper function to parse out the query string params
  function gup(url, name) {
    name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
    var regexS = "[\\?#&]"+name+"=([^&#]*)";
    var regex = new RegExp( regexS );
    var results = regex.exec( url );
    if( results == null )
      return "";
    else
      return results[1];
  }

  function getGoogleUserInfo(google_access_token) {
    $.ajax({
      url: 'https://www.googleapis.com/plus/v1/people/me/openIdConnect',
      data: {
        access_token: google_access_token
      },
      success: function(resp) {
        var user = resp;
        console.log(user);
        $('#googleUserName').text('You are logged in as ' + user.name);
        loggedInToGoogle = true;
        $('#google-login-block').hide();
        $('#google-logout-block').show();
      },
      dataType: "jsonp"
    });
  }

  function logoutFromGoogle() {
    $.ajax({
      url: ajaxurl,
      data: {
        action: 'youlive_logout_from_google'
      },
      success: function(resp) {
        console.log(resp);
        $('#googleUserName').text(resp);
        $('#google-login-block').show();
        $('#google-logout-block').hide();
        google_access_token = '';
      }
    });
  }

  // We also want to setup the initial click event and page status on document.ready
   $(function() {
    $('#google-login-block').click(requestGoogleoAuthCode);
    $('#google-logout-block').hide();
    $('#google-logout-block').click(logoutFromGoogle);
    // now lets show that they're logged in if they are
    if (google_access_token) {
      getGoogleUserInfo(google_access_token);
    }
   });  
  });
  </script>
  <a id="google-login-block">Login to Google </a>
  <span id="googleUserName">You are not logged in </span>
  <span id="google-logout-block"><a>Logout from Google</a></span>
  <iframe id="googleAuthIFrame" style="visibility:hidden;" width=1 height=1></iframe>
  <?php
  // END inlined JavaScript and HTML
  }

  private function write_out_youtube_js_html() {
    ?>
  <script language=javascript>
  var google_access_token = "<?php echo $this->get_google_access_token(); ?>";

  jQuery(document).ready(function($) {
    function getYouTubeVidInfo() {
      var video_id = $('#youtubevidid').val();
      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/videos',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet',
          id: video_id
        }
      }).done(function(response) {
        if (response.items[0].snippet){
          var thisdata = response.items[0].snippet;
          $('#youtubevideodata').html('<b>' + thisdata.title + '</b><br />' + thisdata.description);
        } 
      }); 
    }


    $(function() {
      $('#youtube-get-vidinfo').click(getYouTubeVidInfo);
      if (! google_access_token ) {
        $('#youtube-get-vidinfo').hide();
      }
    });
  }); 
  </script>
  <input id="youtubevidid" type=text value="5ywjpbThDpE" /><a id="youtube-get-vidinfo">Get Video Info</a>
  <div id="youtubevideodata"></div>
  <?php

  }


  public function finish_code_exchange() {
    $auth_code = ( isset( $_POST['auth_code'] ) ) ? $_POST['auth_code'] : '';
    echo $this->set_google_oauth2_token($auth_code, 'auth_code');
    wp_die(); 
  }

  private function set_google_oauth2_token($grantCode, $grantType) {
 
    $settings = get_option('youlive_oAuth_Section_settings', true);
    $success = true;  
    $oauth2token_url = "https://accounts.google.com/o/oauth2/token";
    $clienttoken_post = array(
      "client_id" => $settings['google_app_client_id'],
      "client_secret" => $settings['google_app_client_secret']
    );

    if ($grantType === "auth_code"){
      $clienttoken_post["code"] = $grantCode; 
      $clienttoken_post["redirect_uri"] = $settings['google_app_redirect_uri'];
      $clienttoken_post["grant_type"] = "authorization_code";
    }
    if ($grantType === "refresh_token"){
      $clienttoken_post["refresh_token"] = get_option('youlive_google_refresh_token', true);
      $clienttoken_post["grant_type"] = "refresh_token";
    }
    $postargs = array(
      'body' => $clienttoken_post
     );
    $response = wp_remote_post($oauth2token_url, $postargs );
    $authObj = json_decode(wp_remote_retrieve_body( $response ), true);
    if (isset($authObj['refresh_token'])){
      $refreshToken = $authObj['refresh_token'];
      $success = update_option('youlive_google_refresh_token', $refreshToken, false); 
      // the final 'false' is so we don't autoload this value into memory on every page load
    }
    if ($success) {
      $success = update_option('youlive_google_access_token_expires',  strtotime("+" . $authObj['expires_in'] . " seconds"));
    }
    if ($success) {
      $success = update_option('youlive_google_access_token', $authObj[access_token], false);
      if ($success) {
        $success = $authObj[access_token];
      }
    }
    // if there were any errors $success will be false, otherwise it'll be the access token
    if (!$success) { $success=false; }
    return $success;
  }

  public function get_google_access_token() {
    $expiration_time = get_option('youlive_google_access_token_expires', true);
    if (! $expiration_time) {
      return false;
    }
    // Give the access token a 5 minute buffer (300 seconds)
    $expiration_time = $expiration_time - 300;
    if (time() < $expiration_time) {
      return get_option('youlive_google_access_token', true);
    }
    // at this point we have an expiration time but it is in the past or will be very soon
    return $this->set_google_oauth2_token(null, 'refresh_token');
  }

  public function revoke_google_tokens() {

    $return = '';
    $token = get_option('youlive_google_access_token', true);
    $expiration_time = get_option('youlive_google_access_token_expires', true);
    if (!$token || (time() > $expiration_time)){
      $token = get_option('youlive_google_refresh_token', true);
    }
    if ($token) {
      $return = wp_remote_retrieve_response_code(wp_remote_get("https://accounts.google.com/o/oauth2/revoke?token=" . $token));
    } else {
      $return = "no tokens found";
    }
    if ($return == 200) {
      delete_option('youlive_google_access_token');
      delete_option('youlive_google_refresh_token');
      delete_option('youlive_google_access_token_expires');
      return true;
    } else {
      return $return; 
    }
  }

  // wrapper for wp_ajax to point to reusable function
  public function logout_from_google() {
    $response = $this->revoke_google_tokens();
    if ($response === true) {
      $response = "success";
    }
    echo $response;
    wp_die(); 
  }


  public function submit_youtube_expire_request($videoid){
    $access_token = $this->get_google_access_token();
    if (! $access_token) {
      error_log("no access token for $videoid");
      return false;
    }
    $bodyargs = array(
        "id" => $videoid,
        "kind" => "youtube#video",
        "status" => array(
          "privacyStatus" => "private"
        )
      );
    $body = json_encode($bodyargs);
    $url = "https://www.googleapis.com/youtube/v3/videos?part=status&fields=status";
    $args = array(
      "method" => "PUT",
      "headers" => array(
        "Authorization" => "Bearer " . $access_token,
        "Content-Type" => "application/json"
      ),
      "body" => $body
    );
    $request = wp_remote_request($url, $args);
    if (wp_remote_retrieve_response_code($request) != 200){
      error_log("privacy set failed : " . wp_remote_retrieve_body($request));
      return false;
    }
    return json_decode(wp_remote_retrieve_body($request));
  }


  
//end of class  
}

// Instantiate our class
global $plugin_obj;
$plugin_obj = new youlive_oAuth_Section( __FILE__ );


// always cleanup after yourself
register_deactivation_hook(__FILE__, 'youlive_deactivation');

function youlive_deactivation() {
  // delete the google tokens
  $plugin_obj = new youlive_oAuth_Section( __FILE__ );
  $plugin_obj->revoke_google_tokens();
  error_log('youlive has been deactivated');
}

// Auth here
function wp_register_youlive_menu_page(){
    add_menu_page( 
        __( 'YouLive', 'textdomain' ),
        'YouLive',
        'manage_options',
        'youlive',
        'youlive_menu_page',
        plugins_url( '/youlive/images/youtube.png' ),
        6
    );
    add_submenu_page(
        'youlive',
        'All Video', //page title
        'All Video', //menu title
        'manage_options', //capability,
        'all-video',//menu slug
        'youlive_all_video' //callback function
    ); 
    // add_submenu_page(
    //     'youlive',
    //     'Channel Setting', //page title
    //     'channel Setting', //menu title
    //     'manage_options', //capability,
    //     'channel-setting',//menu slug
    //     'youlive_channel_settings' //callback function
    // ); 
    add_submenu_page(
        'youlive',
        'channel Monetization', //page title
        'channel Monetization', //menu title
        'manage_options', //capability,
        'channel-monetization',//menu slug
        'youlive_channel_Monetization' //callback function
    );
    add_submenu_page(
        'youlive',
        'channel search', //page title
        'channel search', //menu title
        'manage_options', //capability,
        'channel-search',//menu slug
        'youlive_channel_search' //callback function
    ); 
    add_submenu_page(
        'youlive',
        'Manage Comments', //page title
        'Manage Comments', //menu title
        'manage_options', //capability,
        'manage-comments',//menu slug
        'youlive_manage_comments' //callback function
    ); 
    add_submenu_page(
        'youlive',
        'Enrolled Users', //page title
        'Enrolled Users', //menu title
        'manage_options', //capability,
        'enrolled-users-course',//menu slug
        'youlive_enrolled_users_course' //callback function
    );
}
add_action( 'admin_menu', 'wp_register_youlive_menu_page' );





function youlive_all_video(){
global $plugin_obj;
$plugin_obj = new youlive_oAuth_Section( __FILE__ );
	echo"<br><h1> All Video  </h1>";

?>
<div id="channel_details"> <h3 id="channel_name"></h3> <p id="channel_description"></p> <div id="channel_thumbnail"></div> </div>
  <script language=javascript>
  var google_access_token = "<?php echo $plugin_obj->get_google_access_token(); ?>";

  jQuery(document).ready(function($) {
    function getYouTubeVidInfo() {
      var video_id = $('#youtubevidid').val();
      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/channels',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet,contentDetails',
          'mine':'true'
        }
      }).done(function(response) {
        console.log(response);
        $.each(response.items, function(key, value){
          var channel_id = value.id;
          var channel_name = value.snippet.title;
          var channel_description = value.snippet.description;
          var channel_thumbnail = value.snippet.thumbnails.high.url;
          var playlistId = value.contentDetails.relatedPlaylists.uploads;

          $("#channel_name").append(channel_name); $("#channel_description").append(channel_description); $("#channel_thumbnail").append("<img width='100' src="+channel_thumbnail+" >");
          console.log(playlistId);

      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/playlistItems',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet,contentDetails',
          'playlistId': playlistId, 
          'maxResults': 50
        }
      }).done(function(response) {
        console.log(response);
        $.each(response.items, function(key, value){
          var videoid = value.contentDetails.videoId;
          var videoName = value.snippet.title;
          var html_frame = "<iframe  width='300' height='300' src='https://www.youtube.com/embed/"+videoid+"' frameborder='0' allow='accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe> <br> <p>"+videoName+"</p>";
              $("#youtubevideodata").append(html_frame);
        });

      }); 

        });
      }); 
    }
    //write out a click event for to trigger the youtube request
  getYouTubeVidInfo();
  }); 
  </script>
  <div id="youtubevideodata"></div>
  <?php

}

// Get all enrolled user in learn press plugin

function youlive_enrolled_users_course(){
	echo "<h2> Enrolled User  </h2>";
	global $wpdb;

	$enrolled_user_data = $wpdb->get_results("SELECT * from wp_learnpress_user_items WHERE status = 'enrolled' && item_type ='lp_course' ");
		?>
	<table class="table table-hover" style="width: 100%">
    <thead style="width: 100%"> 
      <tr style="width: 100%">
        <th>Name</th>
        <th>Email </th>
        <th>Course </th>
        <th>Enrolled  at </th>
      </tr>
    </thead>
    <tbody style="width: 100%">

	<?php
	foreach ($enrolled_user_data as $key => $value) {
		$user_detail = get_userdata($value->user_id);
		$course_details = get_post($value->item_id);
		// print_r($course_details->post_name);
	?>
      <tr style="width: 100%">
        <td> <?php echo $user_detail->first_name ." ".$user_detail->last_name; ?>  </td>
        <td><?php echo $user_detail->user_email; ?></td>
        <td><?php echo "<a href=".$course_details->guid.">". $course_details->post_name ."</a>"; ?></td>
        <td> <?php echo date('n-j-Y', strtotime($value->start_time)); ?> </td>
      </tr>
       <?
	}
	?>
    </tbody>
  </table>
 <?php
}


function youlive_manage_comments(){
  // Manage all comments here 
  echo "<h1>Manage Comments Here</h1>";
  global $plugin_obj;
$plugin_obj = new youlive_oAuth_Section( __FILE__ );
  echo"<br><h1> All Video  </h1>";

?>
<div id="channel_details"> <h3 id="channel_name"></h3> <p id="channel_description"></p> <div id="channel_thumbnail"></div> </div>
  <script language=javascript>
  var google_access_token = "<?php echo $plugin_obj->get_google_access_token(); ?>";

  jQuery(document).ready(function($) {
    function getYouTubeVidInfo() {
      var video_id = $('#youtubevidid').val();
      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/channels',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet,contentDetails',
          'mine':'true'
        }
      }).done(function(response) {
        console.log(response);
        $.each(response.items, function(key, value){
          var channel_id = value.id;
          var channel_name = value.snippet.title;
          var channel_description = value.snippet.description;
          var channel_thumbnail = value.snippet.thumbnails.high.url;
          var playlistId = value.contentDetails.relatedPlaylists.uploads;

          $("#channel_name").append(channel_name); $("#channel_description").append(channel_description); $("#channel_thumbnail").append("<img width='100' src="+channel_thumbnail+" >");
          console.log(playlistId);

      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/playlistItems',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet,contentDetails',
          'playlistId': playlistId, 
          'maxResults': 50
        }
      }).done(function(response) {
        console.log(response);
        $.each(response.items, function(key, value){
          var videoid = value.contentDetails.videoId;
          var videoName = value.snippet.title; 
          var videodescription = value.snippet.description;
          var videothumbnail = value.snippet.thumbnails.default.url;
          var html_data_table = "<tr><th>"+videoid+"</th><th>"+videoName+"</th><th>"+videodescription+"</th><th><img src="+videothumbnail+" /></th><th><button id='view_comment' class='view_comment'> View Comment</button></th></tr>"
          $("#youtubevideodata tbody").append(html_data_table);
        });

      }); 
        });
      }); 
    }

    $(document.body).on('click', '.view_comment', function(){
       var video_id_click =$(this).parent().siblings(":first").text();
      console.log(video_id_click);
      // Call comment Thread here
      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/commentThreads',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet,contentDetails',
          "part": "snippet,replies",
          "videoId": video_id_click
        }
      }).done(function(response) {
        
        if (response.items.length > 0) {
          $.each(response.items, function(key, value){
            console.log(value);
            var commentUserName = value.snippet.topLevelComment.snippet.authorDisplayName;
            var commentProfileImageUrl = value.snippet.topLevelComment.snippet.authorProfileImageUrl;
            var commentText = value.snippet.topLevelComment.snippet.textDisplay;
            var commentID = value.id;
            console.log(commentUserName); console.log(commentProfileImageUrl); console.log(commentText); console.log(commentID);
            var comments_html = "<div id='user_avatar'><img src="+commentProfileImageUrl+" /></div><div id='user_name'>"+commentUserName+"</div><div id='comment_text'>"+commentText+"</div>";
          $("#comment_section").append(comments_html);
          $('html, body').animate({
              scrollTop: $("#comment_section").offset().top
           }, 1000);
        });
        }else{
          $("#youtubevideodata").before("<div id='no_comment_found' class='no_comment_found'> No Comment Found </div>");
        }

      });

    });


    //write out a click event for to trigger the youtube request
  getYouTubeVidInfo();
  }); 
  </script>
  <div id="youtubevideodata">
     <table class="table table-bordered">
    <thead>
      <tr>
        <th> Video Id </th>
        <th>Video Thumbnail</th>
        <th>Video Name</th>
        <th>Video Description </th>
        <th>View Comment </th>
      </tr>
    </thead>
    <tbody>
     
    </tbody>
  </table>
  </div>
  <div id="comment_section">
    
  </div>
  <?php
}

function youlive_channel_settings(){

  echo "Channel Settings here";
}
function youlive_channel_Monetization(){
	echo " Channel youlive_channel_Monetization  ";
}

function youlive_channel_search(){
		echo"<br><h1> Search Here  </h1>";
    global $plugin_obj;
  $plugin_obj = new youlive_oAuth_Section( __FILE__ );
   ?>
  <script language=javascript>
  var google_access_token = "<?php echo $plugin_obj->get_google_access_token(); ?>";

  jQuery(document).ready(function($) {
    function getYouTubeVidInfo() {
      var video_id = $('#youtubevidid').val();
      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/search',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet',
          "maxResults": 25,
          "q": video_id
        }
      }).done(function(response) {
        console.log(response);
        $.each(response.items, function(key, value){
          var video_id = value.id.videoId;
          var video_name = value.snippet.title;
          var video_desc = value.snippet.description;
           var html_frame = "<iframe  width='auto' height='auto' src='https://www.youtube.com/embed/"+video_id+"' frameborder='0' allow='accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>";
           $('#youtubevideodata').append( html_frame+'<b>' + video_name + '</b><p>' + video_desc+'</p>');
        });
      }); 
    }

    //write out a click event for to trigger the youtube request
    $(function() {
      $('#youtube-get-vidinfo').click(getYouTubeVidInfo);
      if (! google_access_token ) {
        $('#youtube-get-vidinfo').hide();
      }
    });
  }); 
  </script>
  <input id="youtubevidid" type=text placeholder="Search by Keyword" /><a id="youtube-get-vidinfo">Search Here</a>
  <div id="youtubevideodata"></div>
  <?php

}
 

function youlive_menu_page(){
global $plugin_obj;
$plugin_obj = new youlive_oAuth_Section( __FILE__ );

global $wpdb;
echo"<br><h1>Add Live Stream to Course</h1>";
echo "<div class='row'> <div class='col-md-6' >";

$args = array('post_type' => 'course');

$query = new WP_Query($args);
// $post_id = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE (post_type = 'course' AND post_status = '". $from ."')");


$courses = $query->posts;
	if (!empty($courses)) {
		echo "<p> Coursess </p>";
		echo "<select id='youlive_course'>";
		foreach ($courses as $course) {
			echo "<option  value=".$course->ID."> ". $course->post_title ." </option>";
		}
		echo "</select>";

	}
	echo "<select id='youlive_section_lesson'><option></option></select>";
	echo "<a class='btn btn-primary' id='add_live_video'> Add Live video </a>";
  echo "</div>";


echo "</div>";
echo "</div>";

    ?>
  <script language=javascript>
  var google_access_token = "<?php echo $plugin_obj->get_google_access_token(); ?>";

  jQuery(document).ready(function($) {
    function getYouTubeVidInfo() {
      var video_id = $('#youtubevidid').val();
      $.ajax({ 
        url: 'https://www.googleapis.com/youtube/v3/liveBroadcasts',
        method: 'GET',
        headers: {
          Authorization: 'Bearer ' + google_access_token
        },
        data: {
          part: 'snippet',
          "broadcastStatus":"active",
           "broadcastType": "all",
        }
      }).done(function(response) {
        if (response.items.length !== 0){
          var thisdata = response.items[0].snippet;
          // $('#youtubevideodata').html('<b>' + thisdata.title + '</b><br />' + thisdata.description);
          $.each(response.items, function(key, value){
              var live_broadcast_id = value.id;
              var html_frame = "<iframe  width='500' height='500' src='https://www.youtube.com/embed/"+live_broadcast_id+"' frameborder='0' allow='accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>";
              $("#youtubevideodata").append(html_frame);
          });
        }else{
          $("#youtubevideodata").append("<h1> No Live Broadcast Found </h1>");
        }
      }); 
    }

    //write out a click event for to trigger the youtube request
    $(function() {
      $('#youtube-get-vidinfo').click(getYouTubeVidInfo);
      if (! google_access_token ) {
        $('#youtube-get-vidinfo').hide();
      }
    });
  }); 
  </script>
  <a id="youtube-get-vidinfo">Get live Broadcast</a>
  <div id="youtubevideodata"></div>
  <?php

}


function get_ajax_posts(){
	global $wpdb;
	$course_id = $_POST['course_id'];
	 $result = $wpdb->get_results("SELECT wp_posts.ID, wp_posts.post_title, wp_posts.post_content FROM wp_posts INNER JOIN wp_postmeta ON wp_postmeta.post_id = wp_posts.ID WHERE wp_postmeta.meta_value = '$course_id'");
	 // if (!empty($result)) {
	 // 	$return_data = [ 'message' => 'success', 'data' => $result ];
	 // }else{  $return_data = ['message' => 'error' ];      }
	echo json_encode($result);
	exit;
}
function get_ajax_posts_lesson(){  
	global $wpdb;
	$section_id = $_POST['section_id'];
	$result = $wpdb->get_results("SELECT wp_posts.ID, wp_posts.post_title, wp_posts.post_content FROM wp_posts INNER JOIN wp_postmeta ON wp_postmeta.post_id = wp_posts.ID WHERE wp_postmeta.meta_value = '$section_id'");

	echo json_encode($result);
	exit;
}
function get_ajax_section_meta(){
	global $wpdb;
	$section_id = $_POST['section_id'];
	 $result = $wpdb->get_results("SELECT * FROM wp_learnpress_section_items WHERE section_id = '$section_id'");
	echo json_encode($result);
	exit;
}

function get_ajax_posts_lesson_update(){
	$lesson_id =  $_POST['lesson_id']; 
	$live_data_html =  $_POST['live_data_html']; 

	global $wpdb;
	$content_post = get_post($lesson_id);
	$content = $content_post->post_content;
	$content = apply_filters('the_content', $content);
	$content = str_replace(']]>', ']]&gt;', $content);
	$content = $content." ".$live_data_html;
	$wpdb->query($wpdb->prepare("UPDATE wp_posts SET post_content='$content' WHERE id=$lesson_id"));
	echo json_encode(['message' => 'success']);
	exit;
}

// register jquery and style on initialization
add_action('admin_enqueue_scripts', 'youlive_styles');

function youlive_styles() {
    wp_enqueue_style('youlive', plugins_url('/style.css', __FILE__));
}

add_action('wp_ajax_get_ajax_posts', 'get_ajax_posts');
add_action('wp_ajax_nopriv_get_ajax_posts', 'get_ajax_posts');

add_action('wp_ajax_get_ajax_posts_lesson', 'get_ajax_posts_lesson');
add_action('wp_ajax_nopriv_get_ajax_posts_lesson', 'get_ajax_posts_lesson');

add_action('wp_ajax_get_ajax_posts_lesson_update', 'get_ajax_posts_lesson_update');
add_action('wp_ajax_nopriv_get_ajax_posts_lesson_update', 'get_ajax_posts_lesson_update');

?>

<?php
add_action( 'admin_footer', 'my_action_javascript' ); 

function my_action_javascript() { ?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
// 		$('#youlive_course').on('change', function() {
// 		    console.log(this.value);
// 		  var course_id = this.value;
// 		  $("#youlive_section").empty();
// 		   $.ajax({
// 		    type: 'POST',
// 		    url: '<?php echo admin_url('admin-ajax.php');?>',
// 		    dataType: "json", // add data type
// 		    data: { action : 'get_ajax_posts' , course_id : course_id },
// 		    success: function( response ) {
// 		        $.each( response, function( key, value ) {
// 		            console.log( key, value ); 
// 		            $("#youlive_section").append("<option value="+value.ID+" >"+value.post_title+"</option>");
// 		        } );
// 		    }
// 		});
// 		});

		$('#youlive_course').on('change', function() {
		  var course_id = this.value;
		  console.log(course_id);
		  $("#youlive_section_lesson").empty();
		   $.ajax({
		    type: 'POST',
		    url: '<?php echo admin_url('admin-ajax.php');?>',
		    dataType: "json", // add data type
		    data: { action : 'get_ajax_posts_lesson' , section_id : course_id },
		    success: function( response ) {
		    	console.log(response);
		        $.each( response, function( key, value ) {
		            console.log( key, value ); 
		            $("#youlive_section_lesson").append("<option value="+value.ID+" >"+value.post_title+"</option>");
		        } );
		    }
		});
		});
		$("#add_live_video").click(function(){
			var lesson_id = $("#youlive_section_lesson").val();
			var live_data_html = $("#youtubevideodata").html();
			console.log(live_data_html);
			console.log(lesson_id);

			if (lesson_id !== "" && live_data_html !== "" ) {
				$.ajax({
					type: 'POST',
					url : '<?php echo admin_url('admin-ajax.php'); ?>',
					dataType : 'json',
					data : { action : 'get_ajax_posts_lesson_update', lesson_id : lesson_id, live_data_html : live_data_html  },
					success: function(response){
						console.log(response);
            if (response.message == 'success') {
              $("#youtubevideodata").after("<div class='success_message'> Video Added to course </div>");
            }
					}
				});
			}
		});



	});
	</script> <?php
}

// Custom Widget here for mega menu categories


// The widget class
class MegaCourse extends WP_Widget {

	// Main constructor
	public function __construct() {
		/* ... */
		parent::__construct(
			'MegaCourse',
			__('Mega Course Widget', 'text_domain'),
			array(
				'customize_selective_refresh' => true,
			)
		);
	}

	// The widget form (for the backend )
	public function form( $instance ) {	
		/* ... */
	}

	// Update widget settings
	public function update( $new_instance, $old_instance ) {
		/* ... */
	}

	// Display the widget
	public function widget( $args, $instance ) {
		global $wpdb;
		$courses_category = $wpdb->get_results("SELECT p.ID, p.post_title,p.post_type, t.term_id, t.name, t.slug
			FROM wp_posts p
			LEFT JOIN wp_term_relationships rel ON rel.object_id = p.ID
			LEFT JOIN wp_term_taxonomy tax ON tax.term_taxonomy_id = rel.term_taxonomy_id
			LEFT JOIN wp_terms t ON t.term_id = tax.term_id  WHERE p.post_type = 'lp_course' ");

    foreach($courses_category as $key => $value){
        print_r($value->name);
    }

	}

}

// Register the widget
function mega_course_custom_widget() {
	register_widget( 'MegaCourse' );
}
add_action( 'widgets_init', 'mega_course_custom_widget' );





?>
