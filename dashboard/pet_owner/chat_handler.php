<?php
// chat_handler.php
header('Content-Type: application/json');
require_once(__DIR__ . "/../../config/config.php");
require "../../includes/functions.php";

// Check if user is logged in and is a pet owner
$user = getCurrentUser(); 
if (!$user || !isset($user['id']) || $user['role'] !== 'owner') {
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

// Handle fetch_history request
if (isset($_GET['fetch_history'])) {
    $history_query = "SELECT role, content FROM chat_history WHERE user_id = ? ORDER BY created_at ASC";
    $history_stmt = $conn->prepare($history_query);
    $history_stmt->bind_param("i", $user['id']);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    $history = $history_result->fetch_all(MYSQLI_ASSOC);
    $history_stmt->close();
    
    echo json_encode(['history' => $history]);
    exit();
}

// Fetch pet details for personalization
$pets_query = "SELECT name, species, breed FROM pets WHERE owner_id = ?";
$pets_stmt = $conn->prepare($pets_query);
$pets_stmt->bind_param("i", $user['id']);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = $pets_result->fetch_all(MYSQLI_ASSOC);
$pets_stmt->close();

// Save user message to history
$save_user_msg_query = "INSERT INTO chat_history (user_id, role, content) VALUES (?, 'user', ?)";
$save_user_msg_stmt = $conn->prepare($save_user_msg_query);
$save_user_msg_stmt->bind_param("is", $user['id'], $message);
$save_user_msg_stmt->execute();
$save_user_msg_stmt->close();

// Fetch previous chat history for AI context (last 5 interactions to save tokens)
$context_query = "SELECT role, content FROM chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$context_stmt = $conn->prepare($context_query);
$context_stmt->bind_param("i", $user['id']);
$context_stmt->execute();
$context_result = $context_stmt->get_result();
$context_history = array_reverse($context_result->fetch_all(MYSQLI_ASSOC)); // Reverse to get chronological order
$context_stmt->close();

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

// Gemini API call
$apiKey = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$contents = [];
// Append previous context
foreach ($context_history as $msg) {
    if ($msg['content'] !== $message) { // Don't duplicate the current message being appended below
        $contents[] = [
            "role" => $msg['role'] === 'user' ? 'user' : 'model',
            "parts" => [["text" => $msg['content']]]
        ];
    }
}

// Append current message
$contents[] = [
    "role" => "user",
    "parts" => [["text" => $message]]
];

$data = [
    "systemInstruction" => [
        "parts" => [["text" => $prompt]]
    ],
    "contents" => $contents
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
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
    echo json_encode(['error' => "API error: HTTP $httpCode " . $response]);
    exit();
}

$result = json_decode($response, true);
if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $ai_response = trim($result['candidates'][0]['content']['parts'][0]['text']);
    
    // Save AI response to history
    $save_ai_msg_query = "INSERT INTO chat_history (user_id, role, content) VALUES (?, 'assistant', ?)";
    $save_ai_msg_stmt = $conn->prepare($save_ai_msg_query);
    $save_ai_msg_stmt->bind_param("is", $user['id'], $ai_response);
    $save_ai_msg_stmt->execute();
    $save_ai_msg_stmt->close();
    
    echo json_encode(['response' => $ai_response]);
} else {
    echo json_encode(['error' => 'Unexpected API response format']);
}

$conn->close();
?>