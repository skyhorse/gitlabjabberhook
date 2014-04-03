<?php

//  Core Configuration

$jabberServer = "yourjabber.server.com";
$jabberPort = 5222;
$jabberUser = "gitlab";
$jabberPassword = "password";
$jabberDomain = "server.com";

// The jabber notification types. groupchat for messages in a Jabber MUC,
// broadcast for Openfires broadcast plugin: http://www.igniterealtime.org/projects/openfire/plugins/broadcast/readme.html

$notificationType = "groupchat"; // groupchat / broadcast

// The following lines depend on which notification type you have chosen. For
// a MUC, ensure $groupChatRoom is correct, for the broadcast plugin, chek $broadcastService

$groupChatRoom = "room@conference.server.com/GitLab";
$broadcastService = "all@broadcast.server.com";

// End configuration

/**
 * Checks whether a string is valid json.
 *
 * @param string $string
 * @return boolean
 */
function valid_json($string)
{
    try {

        // try to decode string
        json_decode($string);
    }
    catch (ErrorException $e) {

        // exception has been caught which means argument wasn't a string and thus is definitely no json.
        return FALSE;
    }

    // check if error occured
    return (json_last_error() == JSON_ERROR_NONE);
}

// Gitlab Hook Handler
// Get the $_REQUEST and check if it is valid JSON.

$request = file_get_contents('php://input');
$json = ( valid_json($request) ? json_decode($request) : die() );

// Parse the JSON into a message payload that will be sent to the Jabber Server

$message = "\n";
$branch = $json->repository->name . ':' . $json->ref . " - " . $json->repository->url;
$message .= $json->user_name . " pushed " . count($json->commits) . " commits to " . $branch . "\n";
foreach ($json->commits as $commit) {

    // prepare commits message
    $message .= $commit->author->name . ": " . $commit->message . " - " . $commit->url . "\n"; 
}

$message .= "\n";

// Jabber Message Handling
include 'XMPPHP/XMPP.php';

// Use XMPPHP_Log::LEVEL_VERBOSE to get more logging for error reports. Set $printlog=true and run from
// the cli to test.
// If this doesn't work, are you running 64-bit PHP with < 5.2.6?
$conn = new XMPPHP_XMPP($jabberServer, $jabberPort, $jabberUser, $jabberPassword, 'gitlabhook', $jabberDomain, $printlog=false, $loglevel=XMPPHP_Log::LEVEL_VERBOSE);

try {

    $conn->connect();
    $conn->processUntil('session_start');

    switch ($notificationType) {
        case 'groupchat':
                $conn->presence(NULL, "gitlab", $groupChatRoom, "available");
                $conn->message($groupChatRoom, $message, $type='groupchat');
                $conn->presence(NULL, "gitlab", $groupChatRoom, "unavailable");
            break;

        case 'broadcast':
                $conn->presence(NULL, "gitlab", null, "available");
                sleep(5); // Openfire fix. Message wont be sent if we connect, send and disconnect too fast.
                $conn->message($broadcastService, $message);
                $conn->presence(NULL, "gitlab", null, "unavailable");
            break;
        
    }

    $conn->disconnect();
} catch(XMPPHP_Exception $e) {

    die($e->getMessage());
}

// Close the Jabber Connection
$conn = null;

?>
