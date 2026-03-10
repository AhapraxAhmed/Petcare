<?php
// chat_handler.php
header('Content-Type: application/json');
require_once(__DIR__ . "/../../config/config.php");
require "../../includes/functions.php";

// Check if user is logged in and is a pet owner
$user = a(); // Assuming 'a()' returns user data
if (!isset($user['user_id']) || $user['role'] !== 'pet_owner') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get user input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['message']) || empty(trim($input['message']))) {
    echo json_encode(['error' => 'No message provided']);
    exit();
}
$message = trim($input['message']);

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch pet details for personalization
$pets_query = "SELECT name, species, breed FROM pets WHERE owner_id = ?";
$pets_stmt = $conn->prepare($pets_query);
$pets_stmt->bind_param("i", $user['user_id']);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = $pets_result->fetch_all(MYSQLI_ASSOC);
$pets_stmt->close();
$conn->close();

// Construct prompt for DeepSeek API
$prompt = "You are a pet care assistant. Provide a concise, helpful response to the following user query about pet care: '$message'. ";
if (!empty($pets)) {
    $pet_details = array_map(function($pet) {
        return "{$pet['name']} (a {$pet['species']}, {$pet['breed']})";
    }, $pets);
    $prompt .= "The user has the following pets: " . implode(", ", $pet_details) . ". Tailor your response to their pets' species and breeds if relevant.";
} else {
    $prompt .= "Provide a general response suitable for common pets like dogs and cats.";
}

// DeepSeek API call
$apiKey = OPENROUTER_API_KEY;
$siteUrl = APP_URL;
$siteName = APP_NAME;

$url = "https://openrouter.ai/api/v1/chat/completions";
$data = [
    "model" => "deepseek/deepseek-chat-v3.1:free",
    "messages" => [
        [
            "role" => "user",
            "content" => $prompt
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "HTTP-Referer: $siteUrl",
    "X-Title: $siteName",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
if ($response === false) {
    echo json_encode(['error' => 'Failed to fetch response from API: ' . curl_error($ch)]);
    curl_close($ch);
    exit();
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['error' => "API error: HTTP $httpCode"]);
    exit();
}

$result = json_decode($response, true);
if (isset($result['choices'][0]['message']['content'])) {
    echo json_encode(['response' => trim($result['choices'][0]['message']['content'])]);
} else {
    echo json_encode(['error' => 'Unexpected API response format']);
}
?>