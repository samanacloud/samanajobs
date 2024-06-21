<?php
require_once 'vendor/autoload.php';

session_start();

// Load configuration
$config = require 'config.php';

// Google Client Configuration
$client = new Google_Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setRedirectUri($config['google']['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    // Get profile info from Google
    $oauth = new Google_Service_Oauth2($client);
    $profile = $oauth->userinfo->get();

    // Set session variables
    $_SESSION['google_id'] = $profile->id;
    $_SESSION['name'] = $profile->name;
    $_SESSION['email'] = $profile->email;

    // Redirect to register.php
    header('Location: ../register.php');
    exit();
}

// Redirect to Google login
$loginUrl = $client->createAuthUrl();
header('Location: ' . filter_var($loginUrl, FILTER_SANITIZE_URL));
exit();
