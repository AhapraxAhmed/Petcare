<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user = a();
    redirect('../dashboard/' . $user['role'] . '/index.php');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    
    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } elseif (!validate_email($email)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id, name FROM users WHERE email = ? AND is_verified = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            $token = generate_token(32);
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
            
            // Store token in database
            $token_stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $token_stmt->bind_param("iss", $user['user_id'], $token, $expires_at);
            if ($token_stmt->execute()) {
                // Send reset email
                $mail_service = new MailService();
                $reset_link = APP_URL . "/auth/reset-password.php?token=" . $token;
                
                if ($mail_service->sendPasswordResetEmail($email, $user['name'], $reset_link)) {
                    $success_message = 'Password reset instructions have been sent to your email address.';
                } else {
                    $error_message = 'Failed to send reset email. Please try again.';
                }
            } else {
                $error_message = 'Failed to generate reset token. Please try again.';
            }
        } else {
            // Don't reveal if email exists or not for security
            $success_message = 'If an account with that email exists, password reset instructions have been sent.';
        }
        
        $db->closeConnection();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .floating-animation {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-10 left-10 floating-animation">
            <i class="fas fa-paw text-4xl text-white opacity-20"></i>
        </div>
        <div class="absolute top-20 right-20 floating-animation" style="animation-delay: 1s;">
            <i class="fas fa-heart text-3xl text-white opacity-20"></i>
        </div>
        <div class="absolute bottom-20 left-20 floating-animation" style="animation-delay: 2s;">
            <i class="fas fa-bone text-3xl text-white opacity-20"></i>
        </div>
        <div class="absolute bottom-10 right-10 floating-animation" style="animation-delay: 0.5s;">
            <i class="fas fa-shield-alt text-4xl text-white opacity-20"></i>
        </div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4">
                <i class="fas fa-key text-2xl text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Forgot Password?</h1>
            <p class="text-white opacity-80">Enter your email to reset your password</p>
        </div>

        <!-- Forgot Password Form -->
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                    <div class="mt-2">
                        <a href="login.php" class="font-semibold underline">Back to Login</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$success_message): ?>
            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all"
                           placeholder="Enter your email address">
                </div>

                <button type="submit" 
                        class="w-full bg-white text-blue-600 py-3 px-4 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600">
                    <i class="fas fa-paper-plane mr-2"></i>Send Reset Instructions
                </button>
            </form>
            <?php endif; ?>

            <!-- Back to Login -->
            <div class="mt-6 text-center">
                <p class="text-white text-sm">
                    Remember your password? 
                    <a href="login.php" class="font-semibold hover:underline">Sign in here</a>
                </p>
            </div>
        </div>

        <!-- Back to Home -->
        <div class="text-center mt-6">
            <a href="../index.php" class="text-white text-sm hover:underline flex items-center justify-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Home
            </a>
        </div>
    </div>
</body>
</html>