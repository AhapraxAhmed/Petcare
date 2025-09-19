<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notificationService = new NotificationService();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get user notifications
        $notifications = $notificationService->getUserNotifications($user_id);
        echo json_encode(['notifications' => $notifications]);
        break;
        
    case 'POST':
        // Mark notification as read
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['notification_id'])) {
            $result = $notificationService->markAsRead($input['notification_id'], $user_id);
            echo json_encode(['success' => $result]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Missing notification_id']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
