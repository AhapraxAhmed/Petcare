    <?php
    require_once '../config/config.php';
    require_once '../includes/functions.php';

    // Redirect if already logged in
    if (is_logged_in()) {
        $user = a();
        redirect('../dashboard/' . $user['role'] . '/index.php');
    }

    $error_message = '';
    $success_message = '';
    $token = '';
    $valid_token = false;
    $user_data = null;

    // Check if token is provided
    if (isset($_GET['token'])) {
        $token = sanitize_input($_GET['token']);
        
        $db = new Database();
        $conn = $db->getConnection();
        
        // Validate token
        $stmt = $conn->prepare("
            SELECT prt.token_id, prt.user_id, u.name, u.email 
            FROM password_reset_tokens prt 
            JOIN users u ON prt.user_id = u.user_id 
            WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = FALSE
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user_data = $result->fetch_assoc()) {
            $valid_token = true;
        } else {
            $error_message = 'Invalid or expired reset token. Please request a new password reset.';
        }
        
        $db->closeConnection();
    } else {
        $error_message = 'No reset token provided.';
    }

    // Handle password reset form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($password) || empty($confirm_password)) {
            $error_message = 'Please fill in all fields.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error_message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } else {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Update user password
            $password_hash = hash_password($password);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update_stmt->bind_param("si", $password_hash, $user_data['user_id']);
            
            if ($update_stmt->execute()) {
                // Mark token as used
                $token_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token = ?");
                $token_stmt->bind_param("s", $token);
                $token_stmt->execute();
                
                $success_message = 'Your password has been successfully reset. You can now log in with your new password.';
                $valid_token = false; // Hide the form
            } else {
                $error_message = 'Failed to update password. Please try again.';
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
        <title>Reset Password - FurShield</title>
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
            
            .password-strength {
                height: 4px;
                border-radius: 2px;
                transition: all 0.3s ease;
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
                    <i class="fas fa-lock text-2xl text-blue-600"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Reset Password</h1>
                <?php if ($valid_token && $user_data): ?>
                    <p class="text-white opacity-80">Create a new password for <?php echo htmlspecialchars($user_data['name']); ?></p>
                <?php else: ?>
                    <p class="text-white opacity-80">Set your new password</p>
                <?php endif; ?>
            </div>

            <!-- Reset Password Form -->
            <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $error_message; ?>
                        <?php if (!$valid_token): ?>
                            <div class="mt-2">
                                <a href="forgot-password.php" class="font-semibold underline">Request new reset link</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $success_message; ?>
                        <div class="mt-2">
                            <a href="login.php" class="font-semibold underline">Login with new password</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($valid_token && !$success_message): ?>
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>New Password
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all pr-12"
                                placeholder="Enter new password"
                                minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <button type="button" onclick="togglePassword('password', 'toggleIcon1')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white opacity-70 hover:opacity-100">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <!-- Password Strength Indicator -->
                        <div class="mt-2">
                            <div class="password-strength bg-gray-300" id="passwordStrength"></div>
                            <p class="text-xs text-white opacity-70 mt-1" id="strengthText">
                                Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>Confirm New Password
                        </label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" required
                                class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all pr-12"
                                placeholder="Confirm new password">
                            <button type="button" onclick="togglePassword('confirm_password', 'toggleIcon2')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white opacity-70 hover:opacity-100">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-white text-blue-600 py-3 px-4 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600">
                        <i class="fas fa-save mr-2"></i>Update Password
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

        <script>
            function togglePassword(inputId, iconId) {
                const passwordInput = document.getElementById(inputId);
                const toggleIcon = document.getElementById(iconId);
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                }
            }

            // Password strength indicator
            document.getElementById('password').addEventListener('input', function() {
                const password = this.value;
                const strength = getPasswordStrength(password);
                const strengthBar = document.getElementById('passwordStrength');
                const strengthText = document.getElementById('strengthText');
                
                let color, text;
                switch(strength) {
                    case 0:
                    case 1:
                        color = '#ef4444';
                        text = 'Very weak password';
                        break;
                    case 2:
                        color = '#f97316';
                        text = 'Weak password';
                        break;
                    case 3:
                        color = '#eab308';
                        text = 'Fair password';
                        break;
                    case 4:
                        color = '#22c55e';
                        text = 'Good password';
                        break;
                    case 5:
                        color = '#16a34a';
                        text = 'Strong password';
                        break;
                }
                
                strengthBar.style.backgroundColor = color;
                strengthBar.style.width = (strength * 20) + '%';
                strengthText.textContent = text;
            });

            function getPasswordStrength(password) {
                let strength = 0;
                if (password.length >= 8) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                return strength;
            }

            // Password match validation
            document.getElementById('confirm_password').addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirmPassword = this.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = 'rgba(255, 255, 255, 0.3)';
                }
            });
        </script>
    </body>
    </html>
