<?php
session_start();
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 'On');
ini_set('display_startup_errors', 'On');
require 'Predis/Autoloader.php';
Predis\Autoloader::register();
$client = new Predis\Client();

// Initialize a new pubsub context
$pubsub = $client->pubSub();

// Subscribe to your channels
$pubsub->subscribe('room_phpms');

// Start processing the pubsup messages. Open a terminal and use redis-cli
// to push messages to the channels. Examples:
//   ./redis-cli PUBLISH notifications "this is a test"
//   ./redis-cli PUBLISH control_channel quit_loop
foreach($pubsub as $response){
	switch($response->kind){
		case 'subscribe':
			echo "Welcome to {$response->channel} channel.<br/>";
		break;
		case 'message':
			echo "{$response->payload}<br/>";
		break;
	}
	@ob_flush();
    flush();
}
unset($pubsub);
