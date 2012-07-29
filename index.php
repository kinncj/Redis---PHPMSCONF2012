<?php
session_start();
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 'On');
ini_set('display_startup_errors', 'On');
require 'Predis/Autoloader.php';
Predis\Autoloader::register();

if (!isset($_SESSION['username']) && !isset($_POST['login'])) {
    $form = <<<END
    <form action="" method="post" name="login">
    Username: <input type="text" name="login[username]" />
    <input type="submit" />
    </form>
END;
echo $form;
exit;
}

if (isset($_POST['login'])) {
	$_SESSION['username'] = $_POST['login']['username'];
}

if (isset($_POST['message'])) {
	$redis = new Predis\Client();
	$username = $_SESSION['username'];
	$message = $_POST['message']['text'];
	var_dump($redis->publish('room_phpms', "{$username} says: {$message}"));
}
?>
<form action="" method="post" name="message">
Message: <textarea name="message[text]"></textarea>
<input type="submit"/>
</form>
<a href="message.php" target="_blank">Messages</a>
