<?php
require_once 'vendor/autoload.php';

// Load configuration
$config = require 'config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Database configuration
$host = $config['database']['host'];
$db = $config['database']['db'];
$user = $config['database']['user'];
$pass = $config['database']['pass'];

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die('Connection error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        echo 'Error: ' . htmlspecialchars($token['error_description']);
        exit();
    }

    if (!isset($token['access_token'])) {
        echo 'Error: Access token not found in the response';
        exit();
    }

    $client->setAccessToken($token['access_token']);

    // Get profile info
    $google_oauth = new Google_Service_Oauth2($client);

    $google_account_info = $google_oauth->userinfo->get();
    $google_id = $google_account_info->id;
    $email = $google_account_info->email;
    $name = $google_account_info->name;
	$profile_image = $google_account_info->picture; //store profile image URL

    // Check if user exists
    $result = $mysqli->query("SELECT * FROM users WHERE email = '$email'");
    if ($result->num_rows > 0) {
        // User exists, update information
        $stmt = $mysqli->prepare("UPDATE users SET google_id = ?, name = ? WHERE email = ?");
        $stmt->bind_param('sss', $google_id, $name, $email);
    } else {
        // New user, insert information
        $stmt = $mysqli->prepare("INSERT INTO users (google_id, name, email) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $google_id, $name, $email);
    }
    $stmt->execute();
    $stmt->close();

    // Start session and save user info
    session_start();
    $_SESSION['email'] = $email;
    $_SESSION['name'] = $name;
	$_SESSION['google_id'] = $google_id;
	$_SESSION['profile_image'] = $profile_image;
	$_SESSION['access_token'] = $token;  // Store the token in the session

    // Check if the user belongs to the admin domain
    if (strpos($email, '@samanagroup.co') !== false) {
        $_SESSION['is_admin'] = true;
        header('Location: https://jobs.samana.cloud/admin/');
    } else {
        $_SESSION['is_admin'] = false;
        header('Location: https://jobs.samana.cloud/');
    }

    exit();
} else {
    echo "Google login failed";
    exit();
}
?>
