<?php
//GitLab Jabber hook config
$jabberServer = "yourjabber.server.com";
$jabberPort = 5222;
$jabberUser = "gitlab";
$jabberPassword = "password";
$jabberDomain = "server.com";

$groupChatRoom = "room@service/GitLab";

//gitlab hook handler
$request = file_get_contents('php://input');
$json = json_decode($request);
$branch = $json->repository->name . ':' . $json->ref . " - " . $json->repository->url;
$message = $json->user_name . " pushed " . count($json->commits) . " commits to " . $branch . "\n";
foreach ($json->commits as $commit) {
    // prepare commits message
    $message .= $commit->author->name . ": " . $commit->message . " - " . $commit->url . "\n"; 
}
// activate full error reporting
//error_reporting(E_ALL & E_STRICT);
include 'XMPPHP/XMPP.php';

#Use XMPPHP_Log::LEVEL_VERBOSE to get more logging for error reports
#If this doesn't work, are you running 64-bit PHP with < 5.2.6?
$conn = new XMPPHP_XMPP($jabberServer, $jabberPort, $jabberUser, $jabberPassword, 'gitlabhook', $jabberDomain, $printlog=false, $loglevel=XMPPHP_Log::LEVEL_VERBOSE);

try {
   $conn->connect();
    $conn->processUntil('session_start');
    //$conn->presence();
    $conn->presence(NULL, "gitlab", $groupChatRoom, "available");
    $conn->message($groupChatRoom, $message, $type='groupchat');
    $conn->presence(NULL, "gitlab", $groupChatRoom, "unavailable");
    $conn->disconnect();
} catch(XMPPHP_Exception $e) {
    die($e->getMessage());
}
$conn = null;
?>
