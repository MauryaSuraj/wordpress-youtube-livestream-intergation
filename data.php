<?php

  if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}
require_once __DIR__ . '/vendor/autoload.php';
session_start();
$htmlBody ="";

?>

  <div class="row">
    <div class="col-md-12"> 
      <?php
          $show_buttons = 0;
          $OAUTH2_CLIENT_ID = '93426528268-711sba7tq7turc7d9k3j5dsv1sf0trl9.apps.googleusercontent.com';
          $OAUTH2_CLIENT_SECRET = 'l2mSUActAKZB1w6_yYd3-ELi';
          $client = new Google_Client();
          $client->setClientId($OAUTH2_CLIENT_ID);
          $client->setClientSecret($OAUTH2_CLIENT_SECRET);
          $client->setScopes('https://www.googleapis.com/auth/youtube');
          $redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],FILTER_SANITIZE_URL);
          $client->setRedirectUri($redirect);

          // Define an object that will be used to make all API requests.
          $youtube = new Google_Service_YouTube($client);
          // Check if an auth token exists for the required scopes
          $tokenSessionKey = 'token-' . $client->prepareScopes();
          if (isset($_GET['code'])) {
            if (strval($_SESSION['state']) !== strval($_GET['state'])) {
              die('The session state did not match.');
            }

            $client->authenticate($_GET['code']);
            $_SESSION[$tokenSessionKey] = $client->getAccessToken();
            header('Location: ' . $redirect);
          }

          if (isset($_SESSION[$tokenSessionKey])) {
            $client->setAccessToken($_SESSION[$tokenSessionKey]);
          }

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
  $htmlBody = '';
  try {
    $show_buttons = 1;

  // Main Call here 

    if (isset($_GET['show_section']) && $_GET['show_section'] == 'livestream') {
    $broadcastsResponse = $youtube->liveBroadcasts->listLiveBroadcasts(
        'id,snippet',
        array(
            'mine' => 'true',
        ));
      $service = new Google_Service_YouTube($client);
      $queryParams = [
         // 'id' => $broadcastItem['id']
         'broadcastStatus' => 'active'
      ];
    $responses = $service->liveBroadcasts->listLiveBroadcasts('snippet,contentDetails,status', $queryParams);
  //Live data response 
    $id ="";
    foreach ($responses as $response) {
        $response["id"] ."<br>";
      $id = $response["id"];
       $response['snippet']['title'] ."<br>" ;
    }
    if ($id !=="" && !empty($id)) {
      
    
    ?>
    <iframe width="944" height="531" src="https://www.youtube.com/embed/<?php echo $id; ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    <?php
  }else{
    echo "<h2 class='my-5'> No Live Broadcast Found </h2>";
  }
    }
    else if(isset($_GET['show_section']) && $_GET['show_section'] == 'all_video'){
            $channelsResponse = $youtube->channels->listChannels('contentDetails', array(
      'mine' => 'true',
    ));
    echo ' <div class="row">';
    foreach ($channelsResponse['items'] as $channel) {
      $uploadsListId = $channel['contentDetails']['relatedPlaylists']['uploads'];
      $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet', array(
        'playlistId' => $uploadsListId,
        'maxResults' => 50
      ));
      $htmlBody .= "<h3>Channel Id $uploadsListId</h3><ul>";
      foreach ($playlistItemsResponse['items'] as $playlistItem) {
        $channel_name = $playlistItem['snippet']['channelTitle'];
?>
    <div class="col-md-4">
    <iframe width="auto" height="auto" src="https://www.youtube.com/embed/<?php echo $playlistItem['snippet']['resourceId']['videoId']; ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
<?php
    }
  }

    // Main Section endss
}
elseif (isset($_GET['show_section']) && $_GET['show_section'] == 'search') {

?>
<form method="GET">
  <div>
    Search Term: <input type="search" id="q" name="q" placeholder="Enter Search Term">
  </div>
  <input type="hidden" id="show_section" name="show_section" value="search">
  <div>
    Max Results: <input type="number" id="maxResults" name="maxResults" min="1" max="50" step="1" value="25">
  </div>
  <input type="submit" value="Search">
</form>
<?php
if (isset($_GET['q']) && isset($_GET['maxResults'])) {

echo $_GET['q'];
 $searchResponse = $youtube->search->listSearch('id,snippet', array(
      'q' => $_GET['q'],
      'maxResults' => $_GET['maxResults'],
    ));

    $videos = '';
    $channels = '';
    $playlists = '';

    // Add each result to the appropriate list, and then display the lists of
    // matching videos, channels, and playlists.
    foreach ($searchResponse['items'] as $searchResult) {
      switch ($searchResult['id']['kind']) {
        case 'youtube#video':
          $videos .= sprintf('<li>%s (%s)</li>',
              $searchResult['snippet']['title'], $searchResult['id']['videoId']);
          ?> 

<iframe width="auto" height="auto" src="https://www.youtube.com/embed/<?php echo $searchResult['id']['videoId']; ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
           <?php
          break;
        case 'youtube#channel':
          $channels .= sprintf('<li>%s (%s)</li>',
              $searchResult['snippet']['title'], $searchResult['id']['channelId']);
          break;
        case 'youtube#playlist':
          $playlists .= sprintf('<li>%s (%s)</li>',
              $searchResult['snippet']['title'], $searchResult['id']['playlistId']);
          break;
      }
    }

?>
    <h3>Videos</h3>
    <ul> <?php echo $videos; ?> </ul>
    <h3>Channels</h3>
    <ul> <?php echo $channels; ?> </ul>
    <h3>Playlists</h3>
    <ul> <?php echo $playlists; ?> </ul>
<?php
  // end here 
}
}
elseif (isset($_GET['show_section']) && $_GET['show_section'] == 'search_in_channel') {

?>
<form method="GET">
  <div>
    Search Term: <input type="search" id="q" name="q" placeholder="Enter Search Term">
  </div>
  <input type="hidden" id="show_section" name="show_section" value="search_in_channel">
  <div>
    Max Results: <input type="number" id="maxResults" name="maxResults" min="1" max="50" step="1" value="25">
  </div>
  <input type="submit" value="Search">
</form>
<?php
if (isset($_GET['q']) && isset($_GET['maxResults']) && isset($_GET['show_section']) && $_GET['show_section'] == 'search_in_channel' ) {

echo $_GET['q'];
 $searchResponse = $youtube->search->listSearch('id,snippet', array(
      'q' => $_GET['q'],
      'channelId' => 'UCKgV0Ow1lmMA8NrCRf19Qlg',
      'maxResults' => $_GET['maxResults'],
    ));

    $videos = '';
    $channels = '';
    $playlists = '';

    // Add each result to the appropriate list, and then display the lists of
    // matching videos, channels, and playlists.
    foreach ($searchResponse['items'] as $searchResult) {
      switch ($searchResult['id']['kind']) {
        case 'youtube#video':
          $videos .= sprintf('<li>%s (%s)</li>',
              $searchResult['snippet']['title'], $searchResult['id']['videoId']);
            ?> 

<iframe width="auto" height="auto" src="https://www.youtube.com/embed/<?php echo $searchResult['id']['videoId']; ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
           <?php
          break;
        case 'youtube#channel':
          $channels .= sprintf('<li>%s (%s)</li>',
              $searchResult['snippet']['title'], $searchResult['id']['channelId']);
          break;
        case 'youtube#playlist':
          $playlists .= sprintf('<li>%s (%s)</li>',
              $searchResult['snippet']['title'], $searchResult['id']['playlistId']);
          break;
      }
    }

?>
    <h3>Videos</h3>
    <ul> <?php echo $videos; ?> </ul>
    <h3>Channels</h3>
    <ul> <?php echo $channels; ?> </ul>
    <h3>Playlists</h3>
    <ul> <?php echo $playlists; ?> </ul>
<?php
  // end here 
}


  //ends here 
}
else if(isset($_GET['show_section']) && $_GET['show_section'] == 'create_broadcast'){
      // Create an object for the liveBroadcast resource's snippet. Specify values
    // for the snippet's title, scheduled start time, and scheduled end time.
    $broadcastSnippet = new Google_Service_YouTube_LiveBroadcastSnippet();
    $broadcastSnippet->setTitle('New Broadcast');
    $broadcastSnippet->setScheduledStartTime('2034-01-30T00:00:00.000Z');
    $broadcastSnippet->setScheduledEndTime('2034-01-31T00:00:00.000Z');

    // Create an object for the liveBroadcast resource's status, and set the
    // broadcast's status to "private".
    $status = new Google_Service_YouTube_LiveBroadcastStatus();
    $status->setPrivacyStatus('private');

    // Create the API request that inserts the liveBroadcast resource.
    $broadcastInsert = new Google_Service_YouTube_LiveBroadcast();
    $broadcastInsert->setSnippet($broadcastSnippet);
    $broadcastInsert->setStatus($status);
    $broadcastInsert->setKind('youtube#liveBroadcast');

    // Execute the request and return an object that contains information
    // about the new broadcast.
    $broadcastsResponse = $youtube->liveBroadcasts->insert('snippet,status',
        $broadcastInsert, array());

    // Create an object for the liveStream resource's snippet. Specify a value
    // for the snippet's title.
    $streamSnippet = new Google_Service_YouTube_LiveStreamSnippet();
    $streamSnippet->setTitle('New Stream');

    // Create an object for content distribution network details for the live
    // stream and specify the stream's format and ingestion type.
    $cdn = new Google_Service_YouTube_CdnSettings();
    $cdn->setFormat("1080p");
    $cdn->setIngestionType('rtmp');

    // Create the API request that inserts the liveStream resource.
    $streamInsert = new Google_Service_YouTube_LiveStream();
    $streamInsert->setSnippet($streamSnippet);
    $streamInsert->setCdn($cdn);
    $streamInsert->setKind('youtube#liveStream');

    // Execute the request and return an object that contains information
    // about the new stream.
    $streamsResponse = $youtube->liveStreams->insert('snippet,cdn',
        $streamInsert, array());

    // Bind the broadcast to the live stream.
    $bindBroadcastResponse = $youtube->liveBroadcasts->bind(
        $broadcastsResponse['id'],'id,contentDetails',
        array(
            'streamId' => $streamsResponse['id'],
        ));

    $htmlBody .= "<h3>Added Broadcast</h3><ul>";
    $htmlBody .= sprintf('<li>%s published at %s (%s)</li>',
        $broadcastsResponse['snippet']['title'],
        $broadcastsResponse['snippet']['publishedAt'],
        $broadcastsResponse['id']);
    $htmlBody .= '</ul>';

    $htmlBody .= "<h3>Added Stream</h3><ul>";
    $htmlBody .= sprintf('<li>%s (%s)</li>',
        $streamsResponse['snippet']['title'],
        $streamsResponse['id']);
    $htmlBody .= '</ul>';

    $htmlBody .= "<h3>Bound Broadcast</h3><ul>";
    $htmlBody .= sprintf('<li>Broadcast (%s) was bound to stream (%s).</li>',
        $bindBroadcastResponse['id'],
        $bindBroadcastResponse['contentDetails']['boundStreamId']);
    $htmlBody .= '</ul>';
}
else if(isset($_GET['show_section']) && $_GET['show_section'] == 'subscribe'){

?>

<form method="POST">
  <input type="submit" name="subscribe" value="Subscribe">
</form>

<?php
if (isset($_POST['subscribe'])) {
  $resourceId = new Google_Service_YouTube_ResourceId();
    $resourceId->setChannelId('UCKgV0Ow1lmMA8NrCRf19Qlg');
    $resourceId->setKind('youtube#channel');

    // Create a snippet object and set its resource ID.
    $subscriptionSnippet = new Google_Service_YouTube_SubscriptionSnippet();
    $subscriptionSnippet->setResourceId($resourceId);

    // Create a subscription request that contains the snippet object.
    $subscription = new Google_Service_YouTube_Subscription();
    $subscription->setSnippet($subscriptionSnippet);

    // Execute the request and return an object containing information
    // about the new subscription.
    $subscriptionResponse = $youtube->subscriptions->insert('id,snippet',
        $subscription, array());

    $htmlBody .= "<h3>Subscription</h3><ul>";
    $htmlBody .= sprintf('<li>%s (%s)</li>',
        $subscriptionResponse['snippet']['title'],
        $subscriptionResponse['id']);
    $htmlBody .= '</ul>';
}

//end here .
 }
 else if(isset($_GET['show_section']) && $_GET['show_section'] == 'playlist'){
  $queryParams = [
    'channelId' => 'UCKgV0Ow1lmMA8NrCRf19Qlg',
    'maxResults' => 25
];

$response = $youtube->playlists->listPlaylists('snippet,contentDetails', $queryParams);
// print_r($response);
foreach ($response["items"] as $value) {
  echo 'Playlist Id ' .$value["id"] .' Playlist Name <strong>'.$value["snippet"]["title"] ."</strong><br>"; 
  $queryParams_play = [
    'playlistId' => $value["id"]
];

$response = $youtube->playlistItems->listPlaylistItems('snippet,contentDetails,status', $queryParams_play);
print_r($response["items"]); 
}

?>
<form method="GET" class="mt-5">
  <input type="hidden" id="show_section" name="show_section" value="playlist">
  <input type="hidden" id="playlist" name="playlist" value="add">
  <input type="submit" class="btn btn-success" value="Add New Playlist">
</form>
<?php

if (isset($_GET['playlist']) && $_GET['playlist'] == 'add') {
?>

<form method="POST" class="my-5">
  <input type="text" id="add_playlisttitle" name="add_playlisttitle" required class="form-control my-1" placeholder="Enter Title">
  <input type="text" id="add_playlisttags" name="add_playlisttags" required  class="form-control my-1" placeholder="Enter tags">
  <input type="text" id="add_playlistdescription" required name="add_playlistdescription" class="form-control my-1" placeholder="Enter description">
  <input type="submit" name="add_playlist" class="btn btn-success" value="Add Playlist">

</form>

<?php
if (isset($_POST['add_playlist'])) {
    $playlist = new Google_Service_YouTube_Playlist();
// Add 'snippet' object to the $playlist object.
$playlistSnippet = new Google_Service_YouTube_PlaylistSnippet();
$playlistSnippet->setDefaultLanguage('en');
$playlistSnippet->setDescription($_POST['add_playlistdescription']);
$playlistSnippet->setTags([$_POST['add_playlisttags']]);
$playlistSnippet->setTitle($_POST['add_playlisttitle']);
$playlist->setSnippet($playlistSnippet);

// Add 'status' object to the $playlist object.
$playlistStatus = new Google_Service_YouTube_PlaylistStatus();
$playlistStatus->setPrivacyStatus('public');
$playlist->setStatus($playlistStatus);

$response = $youtube->playlists->insert('snippet,status', $playlist);
if (!empty($response)) {
  echo "<div class='alert alert-success'> Playlist added </div>";
}


}

}

 }

  } catch (Google_Service_Exception $e) {
    $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  }

  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
} elseif ($OAUTH2_CLIENT_ID == 'REPLACE_ME') {
 echo "
  <h3>Client Credentials Required</h3>
  <p>
    You need to set <code>\$OAUTH2_CLIENT_ID</code> and
    <code>\$OAUTH2_CLIENT_ID</code> before proceeding.
  <p>
";
  } else {
    // If the user hasn't authorized the app, initiate the OAuth flow
    $state = mt_rand();
    $client->setState($state);
    $_SESSION['state'] = $state;

    $authUrl = $client->createAuthUrl();

     echo "<h3>Authorization Required</h3><p>You need to <a href='$authUrl'>authorize access</a> before proceeding.<p>";
   }

      ?>

<?php if($show_buttons == 1) { ?>

<div class="mt-5">
  <!-- <a class="btn btn-outline-primary m-2" href="?show_section=">All Video</a> -->
  <a class="btn btn-outline-primary m-2" href="?show_section=playlist">Playlist</a>
  <a class="btn btn-outline-primary m-2" href="?show_section=subscribe"> Subscribe </a>
  <!-- <a class="btn btn-outline-primary m-2" href="?show_section=create_broadcast">Create Broadcast</a> -->
  <a class="btn btn-outline-primary m-2" href="?show_section=search_in_channel">Search In This channel</a>
  <a class="btn btn-outline-primary m-2" href="?show_section=search">Search</a>
  <a class="btn btn-outline-primary m-2" href="?show_section=all_video">All Video</a>
  <a class="btn btn-outline-primary m-2" href="?show_section=livestream">Live Stream</a>
</div>


<?php } else { echo $htmlBody;  }  ?>
<?=$htmlBody?>
    </div>
  </div>
