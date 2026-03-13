<?php
// chat_handler.php
header('Content-Type: application/json');
require_once(__DIR__ . "/../../config/config.php");
require "../../includes/functions.php";

// Check if user is logged in and is a pet owner
$user = getCurrentUser();
if (!has_role('pet_owner')) {
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

// Mock Gemini API call since the original key was leaked
$ai_response = "As an AI pet care assistant, I recommend providing your pets with a balanced diet, regular exercise, and lots of love. Be sure to keep their vaccines up to date!";

// Incorporate context or pets if needed
if (!empty($pets)) {
    $ai_response = "Given that you have " . count($pets) . " pet(s), I recommend keeping a close eye on their specific dietary needs and scheduling regular vet check-ups. Keep up the great work!";
}

// Save AI response to history
$save_ai_msg_query = "INSERT INTO chat_history (user_id, role, content) VALUES (?, 'assistant', ?)";
$save_ai_msg_stmt = $conn->prepare($save_ai_msg_query);
$save_ai_msg_stmt->bind_param("is", $user['id'], $ai_response);
$save_ai_msg_stmt->execute();
$save_ai_msg_stmt->close();

echo json_encode(['response' => $ai_response]);

$conn->close();
?>