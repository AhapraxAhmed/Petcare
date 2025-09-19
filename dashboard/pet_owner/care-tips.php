<?php
require_once(__DIR__ . "/../../config/config.php");
require "../../includes/functions.php";

// Check if user is logged in and is a pet owner
$user = a(); // Assuming 'a()' returns user data
$user_id = $user['user_id'] ?? null;

if (!$user_id || $user['role'] !== 'pet_owner') {
    header("Location: /../../login.php");
    exit();
}

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Get unread notifications count
$notifications_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$unread_notifications = $notifications_stmt->get_result()->fetch_assoc()['count'];
$notifications_stmt->close();

// Fetch pet details
$pets_query = "SELECT name, species, breed FROM pets WHERE owner_id = ?";
$pets_stmt = $conn->prepare($pets_query);
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$pets = $pets_result->fetch_all(MYSQLI_ASSOC);
$pets_stmt->close();

// Only fetch pet care tips if the user has pets
$care_tips = [];
$error = null;
if (!empty($pets)) {
    // Construct prompt for DeepSeek API, personalized with pet details
    $prompt = "Provide small pet care tips for a pet owner. (Don't answer by using # or * or any other markdown syntax, just plain text). ";
    $pet_details = array_map(function($pet) {
        return "{$pet['name']} (a {$pet['species']}, {$pet['breed']})";
    }, $pets);
    $prompt .= "The owner has the following pets: " . implode(", ", $pet_details) . ". Tailor the tips to their pets' species and breeds.";

    // DeepSeek API call
    $apiKey = OPENROUTER_API_KEY; // Defined in config.php
    $siteUrl = APP_URL; // Defined in config.php
    $siteName = APP_NAME; // Defined in config.php

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
        $error = "Failed to fetch tips from API: " . curl_error($ch);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            $error = "API error: HTTP $httpCode";
        } else {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                // Split the API response into individual tips
                $raw_tips = $result['choices'][0]['message']['content'];
                $care_tips = preg_split("/\n|\d+\.\s*/", trim($raw_tips), -1, PREG_SPLIT_NO_EMPTY);
                // Ensure we have at least 6 tips, pad with defaults if needed
                $default_tips = [
                    'Feed your pet at regular times each day to establish a routine.',
                    'Always provide fresh, clean water for your pet.',
                    'Schedule regular veterinary checkups at least once a year.',
                    'Brush your pet regularly to prevent matting and reduce shedding.',
                    'Use positive reinforcement with treats and praise for training.',
                    'Ensure your pet has proper identification (collar tags, microchip).'
                ];
                while (count($care_tips) < 6) {
                    $care_tips[] = $default_tips[count($care_tips) % count($default_tips)];
                }
                $care_tips = array_slice($care_tips, 0, 6); // Limit to 6 tips
            } else {
                $error = "Unexpected API response format";
            }
        }
    }
    curl_close($ch);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care Tips - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .pet-card {
            transition: all 0.3s ease;
        }
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .animate-fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .chat-container {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .chat-container .bg-blue-100 {
            background-color: #dbeafe;
        }
        .chat-container .border-gray-200 {
            border-color: #e5e7eb;
        }
        #chat-messages > div {
            word-wrap: break-word;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-indigo-50/30 to-slate-100">
    <div class="md:p-9">
        <div class="max-w-full mx-auto h-[100vh] md:h-[calc(95vh-3rem)]">

            <!-- Outer Shell with Rounded Glass -->
            <div class="flex h-full bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/50 overflow-hidden animate-scale-in">
               <?php include "sidebar.php";?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Pet Care Dashboard</h2>
                        <p class="text-gray-600">Manage your pets and get personalized care advice</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="text-gray-500 hover:text-gray-700 relative">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                        <?php echo $unread_notifications; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?php echo date('l, F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Chat Container -->
                <div class="chat-container">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-comment mr-2"></i>Pet Care Chat</h3>
                    <div id="chat-messages" class="h-64 overflow-y-auto bg-gray-100 p-4 rounded mb-4">
                        <div class="text-gray-600 text-center">Start a conversation with our pet care assistant!</div>
                    </div>
                    <div class="flex">
                        <input type="text" id="chat-input" class="flex-1 border rounded-l-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type your message..." autocomplete="off">
                        <button id="chat-send" class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>

                <!-- Error Message (if API call fails and pets exist) -->
                <?php if (isset($error) && !empty($pets)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?php echo htmlspecialchars($error); ?></p>
                        <p>Showing default tips instead.</p>
                    </div>
                <?php endif; ?>

                <!-- Pet Information Section (only if pets exist) -->
                <?php if (!empty($pets)): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in" style="animation-delay: 0.2s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-paw mr-2 text-blue-600"></i>Your Pets</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <ul class="list-disc list-inside text-gray-700">
                            <?php foreach ($pets as $pet): ?>
                            <li><?php echo htmlspecialchars($pet['name']) . " (a " . htmlspecialchars($pet['species']) . ", " . htmlspecialchars($pet['breed']) . ")"; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pet Care Tips Section (only if pets exist and no error) -->
                <?php if (!empty($pets) && !isset($error) && !empty($care_tips)): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in" style="animation-delay: 0.4s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-lightbulb mr-2 text-yellow-600"></i>Personalized Pet Care Tips</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <?php foreach ($care_tips as $tip): ?>
                        <?php echo htmlspecialchars($tip); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- No Pets Message (if no pets) -->
                <?php if (empty($pets)): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in" style="animation-delay: 0.2s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-paw mr-2 text-blue-600"></i>No Pets Registered</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-gray-700">You haven't registered any pets yet. Add a pet to receive personalized care tips and manage their health records!</p>
                        <a href="/add-pet.php" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Add a Pet</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Emergency Information -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in" style="animation-delay: 0.6s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>Emergency Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h6 class="font-medium text-gray-700 mb-3">Emergency Veterinary Clinics</h6>
                            <ul class="space-y-2 text-gray-600">
                                <li><i class="fas fa-phone mr-2"></i>24/7 Pet Emergency: (555) 123-4567</li>
                                <li><i class="fas fa-phone mr-2"></i>Animal Hospital Emergency: (555) 987-6543</li>
                                <li><i class="fas fa-phone mr-2"></i>Pet Poison Control: (855) 764-7661</li>
                            </ul>
                        </div>
                        <div>
                            <h6 class="font-medium text-gray-700 mb-3">Signs of Emergency</h6>
                            <ul class="space-y-2 text-gray-600">
                                <li>Difficulty breathing or choking</li>
                                <li>Severe bleeding or trauma</li>
                                <li>Loss of consciousness</li>
                                <li>Seizures or convulsions</li>
                                <li>Suspected poisoning</li>
                                <li>Extreme lethargy or collapse</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatInput = document.getElementById('chat-input');
            const chatSend = document.getElementById('chat-send');
            const chatMessages = document.getElementById('chat-messages');

            // Function to append a message to the chat
            function appendMessage(content, isUser = false) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `p-2 my-2 rounded-lg max-w-[80%] ${isUser ? 'bg-blue-100 ml-auto text-right' : 'bg-white border border-gray-200'}`;
                messageDiv.innerHTML = content.replace(/\n/g, '<br>'); // Preserve line breaks
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight; // Auto-scroll to bottom
            }

            // Function to show loading indicator
            function showLoading() {
                const loadingDiv = document.createElement('div');
                loadingDiv.id = 'loading';
                loadingDiv.className = 'text-center my-2';
                loadingDiv.innerHTML = '<div class="loader"></div>';
                chatMessages.appendChild(loadingDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Function to remove loading indicator
            function removeLoading() {
                const loadingDiv = document.getElementById('loading');
                if (loadingDiv) loadingDiv.remove();
            }

            // Handle send button click
            chatSend.addEventListener('click', sendMessage);

            // Handle Enter key press
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') sendMessage();
            });

            // Send message to backend
            function sendMessage() {
                const message = chatInput.value.trim();
                if (!message) return;

                // Append user message
                appendMessage(message, true);
                chatInput.value = ''; // Clear input

                // Show loading indicator
                showLoading();

                // Send message to backend via AJAX
                fetch('chat_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ message: message }),
                })
                .then(response => response.json())
                .then(data => {
                    removeLoading();
                    if (data.error) {
                        appendMessage('Error: ' + data.error);
                    } else {
                        appendMessage(data.response);
                    }
                })
                .catch(error => {
                    removeLoading();
                    appendMessage('Error: Failed to connect to the server.');
                    console.error('Error:', error);
                });
            }

            // Animate cards
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>