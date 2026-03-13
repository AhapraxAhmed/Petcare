<?php
$username = 'testowner@example.com';
$password = 'password123';
$baseUrl = 'http://localhost/furshield';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$baseUrl/auth/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $username, 'password' => $password]));
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($http_code == 302) {
    echo "Login successful (redirected)\n";
} else {
    echo "Login returned HTTP $http_code\n";
    if (strpos($response, 'bg-red-100') !== false) {
        $doc = new DOMDocument();
        @$doc->loadHTML($response);
        $xpath = new DOMXPath($doc);
        $errors = $xpath->query("//div[contains(@class, 'bg-red-100')]");
        if ($errors->length > 0) {
            echo "Login Error Message: " . trim(strip_tags($errors->item(0)->nodeValue)) . "\n";
        }
    }
}

// Ensure the session is set by doing a quick test to a protected page
curl_setopt($ch, CURLOPT_URL, "$baseUrl/dashboard/pet_owner/index.php");
curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$test_resp = curl_exec($ch);
$test_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$test_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
echo "Visited index.php, ended up at: $test_url (HTTP $test_code)\n";

curl_close($ch);
