<?php
$username = 'testowner@example.com';
$password = 'password123';
$baseUrl = 'http://localhost/furshield';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$baseUrl/auth/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
// Ensure we're hitting the local server properly
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $username, 'password' => $password, 'action' => 'login']));
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies_debug.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies_debug.txt');
curl_setopt($ch, CURLOPT_HEADER, 1); // Get headers to see redirect
$response = curl_exec($ch);

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
echo "Headers:\n$headers\n";

curl_close($ch);
