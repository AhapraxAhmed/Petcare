<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php'; // include MailService

// Redirect if already logged in
if (is_logged_in()) {
    $user = a();
    redirect('../dashboard/' . $user['role'] . '/index.php');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!validate_email($email)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT user_id, email, password_hash, role, name, is_verified 
                                FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (!$user['is_verified']) {
                $error_message = 'Please verify your email address before logging in.';
            } elseif (verify_password($password, $user['password_hash'])) {
                // set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                // update last login
                $update_stmt = $conn->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();

                $db->closeConnection();

                // --- SEND WELCOME EMAIL ---
               $mail = new MailService();
$mail->sendWelcomeEmail($user['email'], $user['name'], $user['role'], false);
                // --------------------------

                redirect('../dashboard/' . $user['role'] . '/index.php');
            } else {
                $error_message = 'Invalid email or password.';
            }
        } else {
            $error_message = 'Invalid email or password.';
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
    <title>Login - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
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

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }
    </style>
</head>

<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <!-- Background icons -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-10 left-10 floating-animation">
            <i class="fas fa-paw text-4xl text-white opacity-20"></i>
        </div>
        <div class="absolute top-20 right-20 floating-animation" style="animation-delay:1s;">
            <i class="fas fa-heart text-3xl text-white opacity-20"></i>
        </div>
        <div class="absolute bottom-20 left-20 floating-animation" style="animation-delay:2s;">
            <i class="fas fa-bone text-3xl text-white opacity-20"></i>
        </div>
        <div class="absolute bottom-10 right-10 floating-animation" style="animation-delay:.5s;">
            <i class="fas fa-shield-alt text-4xl text-white opacity-20"></i>
        </div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo and heading -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4">
                <i class="fas fa-shield-alt text-2xl text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Welcome Back</h1>
            <p class="text-white opacity-80">Sign in to your FurShield account</p>
        </div>

        <!-- Card -->
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <!-- error message -->
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- success message -->
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Email/Password Form -->
            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email Address
                    </label>
                    <input type="email" id="email" name="email" required
                        class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent"
                        placeholder="Enter your email">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-lock mr-2"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent pr-12"
                            placeholder="Enter your password">
                        <button type="button" onclick="togglePassword()"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white opacity-70 hover:opacity-100">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center text-white text-sm">
                        <input type="checkbox" class="mr-2 rounded"> Remember me
                    </label>
                    <a href="forgot-password.php" class="text-white text-sm hover:underline">Forgot password?</a>
                </div>

                <button type="submit"
                    class="w-full bg-white text-blue-600 py-3 px-4 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
            </form>
             <div class="mt-6 text-center">
                <p class="text-white text-sm">
                    Don't have an account? 
                    <a href="register.php" class="font-semibold hover:underline">Sign up here</a>
                </p>
            </div>           <!-- Divider -->
            <div class="my-4 flex items-center">
                <div class="flex-1 border-t border-white border-opacity-30"></div>
                <span class="px-4 text-white text-sm opacity-70">OR</span>
                <div class="flex-1 border-t border-white border-opacity-30"></div>
            </div>

            <!-- Social Login -->
            <div class="space-y-3">
                <!-- Google custom button -->
                <a href="google-login.php"
                    class="w-full bg-white text-gray-700 py-3 px-4 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 flex items-center justify-center border">
                    <i class="fa-brands fa-google mr-3 text-xl text-red-500"></i>
                    Continue with Google
                </a>


                <!-- GitHub button -->
                <a href="github-login.php"
                    class="w-full bg-gray-900 text-white py-3 px-4 rounded-lg font-semibold hover:bg-gray-800 transition-all transform hover:scale-105 flex items-center justify-center">
                    <i class="fab fa-github mr-3 text-xl"></i>
                    Continue with GitHub
                </a>
            </div>

            <!-- back link -->
            <div class="text-center mt-4">
                <a href="../index.php" class="text-white text-sm hover:underline flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>

    <!-- hidden Google button container -->
    <div id="gsiButtonContainer" style="display:none"></div>

    <script>
        // toggle password eye
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // this is called by Google after user picks account
        function handleCredentialResponse(response) {
            fetch("google-login.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        token: response.credential
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = "../dashboard/" + data.role + "/index.php";
                    } else {
                        alert("Google login failed: " + (data.message || ""));
                    }
                })
                .catch(err => console.error("Google login error:", err));
        }

        // initialize GSI when page loads
        window.addEventListener('load', function() {
            if (!window.google || !google.accounts || !google.accounts.id) {
                console.error('GSI client not loaded');
                return;
            }
            // setup
            google.accounts.id.initialize({
                client_id: "<?php echo GOOGLE_CLIENT_ID; ?>",
                callback: handleCredentialResponse
            });
            // render real Google button in hidden container
            google.accounts.id.renderButton(
                document.getElementById("gsiButtonContainer"), {
                    theme: "outline",
                    size: "large"
                }
            );
            // connect custom button to trigger Google button
            document.getElementById("googleLoginBtn").addEventListener("click", function(e) {
                e.preventDefault();
                const realBtn = document.querySelector("#gsiButtonContainer div[role=button], #gsiButtonContainer button");
                if (realBtn) {
                    realBtn.click(); // simulate click on real Google button
                } else {
                    google.accounts.id.prompt(); // fallback
                }
            });
        });
    </script>
</body>

</html>