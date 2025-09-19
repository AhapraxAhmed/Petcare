<?php
// Including configuration and helper functions
require_once '../config/config.php';
require_once '../includes/functions.php';

// Redirect if user is already logged in
if (is_logged_in()) {
    $user = get_current_user();
    redirect('../dashboard/' . $user['role'] . '/index.php');
}

// Initialize variables for messages
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $role = sanitize_input($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $shelter_name = sanitize_input($_POST['shelter_name'] ?? '');

    // Server-side validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (!in_array($role, ['pet_owner', 'veterinarian', 'shelter'])) {
        $error_message = 'Please select a valid role.';
    } elseif ($role === 'shelter' && empty($shelter_name)) {
        $error_message = 'Shelter name is required for shelter role.';
    } else {
        // Connect to database
        $db = new Database();
        $conn = $db->getConnection();

        // Check if email already exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error_message = 'This email is already registered.';
        } else {
            // Save user to database
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, role, password_hash, is_verified) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $name, $email, $role, $password_hash);

            if ($stmt->execute()) {
                $user_id = $conn->insert_id;

                // Handle veterinarian role
                if ($role === 'veterinarian') {
                    $vet_stmt = $conn->prepare("INSERT INTO veterinarians (user_id) VALUES (?)");
                    $vet_stmt->bind_param("i", $user_id);
                    $vet_stmt->execute();
                }

                // Handle shelter role
                if ($role === 'shelter') {
                    $shelter_stmt = $conn->prepare("INSERT INTO shelters (user_id, shelter_name) VALUES (?, ?)");
                    $shelter_stmt->bind_param("is", $user_id, $shelter_name);
                    $shelter_stmt->execute();
                }

                // Show success message
                $success_message = 'Account created successfully! You can now log in.';
                send_notification($user_id, 'Welcome to FurShield!', 'Thanks for joining. Complete your profile to get started.');
            } else {
                $error_message = 'Error creating account. Please try again.';
            }
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
    <title>Register - FurShield</title>
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
        .error-text {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <!-- Background decorative elements -->
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
    </div>

    <div class="w-full max-w-2xl relative z-10">
        <!-- Logo and title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4">
                <i class="fas fa-shield-alt text-2xl text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Join FurShield</h1>
            <p class="text-white opacity-80">Create your account and start caring for pets</p>
        </div>

        <!-- Registration form container -->
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <!-- Server-side error or success messages -->
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <div class="mt-2">
                        <a href="login.php" class="font-semibold underline">Click here to login</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration form -->
            <form id="registerForm" method="POST" class="space-y-6" novalidate>
                <!-- Role selection -->
                <div>
                    <label class="block text-sm font-medium text-white mb-3">
                        <i class="fas fa-user-tag mr-2"></i>I am a:
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="pet_owner" class="sr-only peer" required>
                            <div class="bg-white bg-opacity-20 border-2 border-white border-opacity-30 rounded-lg p-4 text-center peer-checked:border-white peer-checked:bg-opacity-30 transition-all">
                                <i class="fas fa-user text-2xl text-white mb-2"></i>
                                <div class="text-white font-medium">Pet Owner</div>
                                <div class="text-white text-xs opacity-70">Manage my pets</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="veterinarian" class="sr-only peer" required>
                            <div class="bg-white bg-opacity-20 border-2 border-white border-opacity-30 rounded-lg p-4 text-center peer-checked:border-white peer-checked:bg-opacity-30 transition-all">
                                <i class="fas fa-stethoscope text-2xl text-white mb-2"></i>
                                <div class="text-white font-medium">Veterinarian</div>
                                <div class="text-white text-xs opacity-70">Provide care</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="shelter" class="sr-only peer" required>
                            <div class="bg-white bg-opacity-20 border-2 border-white border-opacity-30 rounded-lg p-4 text-center peer-checked:border-white peer-checked:bg-opacity-30 transition-all">
                                <i class="fas fa-home text-2xl text-white mb-2"></i>
                                <div class="text-white font-medium">Shelter</div>
                                <div class="text-white text-xs opacity-70">Manage adoptions</div>
                            </div>
                        </label>
                    </div>
                    <div id="role-error" class="error-text hidden"></div>
                </div>

                <!-- Name and Email fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-user mr-2"></i>Full Name *
                        </label>
                        <input type="text" id="name" name="name" required
                               class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all"
                               placeholder="Enter your full name">
                        <div id="name-error" class="error-text hidden"></div>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-envelope mr-2"></i>Email Address *
                        </label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all"
                               placeholder="Enter your email">
                        <div id="email-error" class="error-text hidden"></div>
                    </div>
                </div>

                <!-- Shelter name field (hidden by default) -->
                <div id="shelter-name-field" class="hidden">
                    <label for="shelter_name" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-building mr-2"></i>Shelter Name *
                    </label>
                    <input type="text" id="shelter_name" name="shelter_name"
                           class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all"
                           placeholder="Enter shelter name">
                    <div id="shelter-name-error" class="error-text hidden"></div>
                </div>

                <!-- Password fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>Password *
                        </label>
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all"
                               placeholder="Create a password">
                        <div id="password-error" class="error-text hidden"></div>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>Confirm Password *
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent transition-all"
                               placeholder="Confirm your password">
                        <div id="confirm-password-error" class="error-text hidden"></div>
                    </div>
                </div>

                <!-- Terms checkbox -->
                <div class="flex items-center">
                    <input type="checkbox" id="terms" required class="mr-3 rounded">
                    <label for="terms" class="text-white text-sm">
                        I agree to the <a href="#" class="underline hover:no-underline">Terms of Service</a> 
                        and <a href="#" class="underline hover:no-underline">Privacy Policy</a>
                    </label>
                    <div id="terms-error" class="error-text hidden"></div>
                </div>

                <!-- Submit button -->
                <button type="submit" 
                        class="w-full bg-white text-blue-600 py-3 px-4 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600">
                    <i class="fas fa-user-plus mr-2"></i>Create Account
                </button>
            </form>

            <!-- Social login divider -->
            <div class="my-4 flex items-center">
                <div class="flex-1 border-t border-white border-opacity-30"></div>
                <span class="px-4 text-white text-sm opacity-70">OR</span>
                <div class="flex-1 border-t border-white border-opacity-30"></div>
            </div>

            <!-- Social login buttons -->
            <div class="mt-6">
                <p class="text-white text-center mb-3">Or sign up with</p>
                <div id="socialMessage" class="error-text mb-3 text-center"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button id="googleBtn" type="button" 
                            class="w-full bg-white text-gray-700 py-3 px-4 rounded-lg font-semibold hover:bg-gray-100 transition flex items-center justify-center">
                        <i class="fab fa-google mr-2 text-red-500"></i> Continue with Google
                    </button>
                    <button id="githubBtn" type="button" 
                            class="w-full bg-gray-900 text-white py-3 px-4 rounded-lg font-semibold hover:bg-gray-800 transition flex items-center justify-center">
                        <i class="fab fa-github mr-2"></i> Continue with GitHub
                    </button>
                </div>
            </div>

            <!-- Sign in link -->
            <div class="mt-6 text-center">
                <p class="text-white text-sm">
                    Already have an account? 
                    <a href="login.php" class="font-semibold hover:underline">Sign in here</a>
                </p>
            </div>
        </div>

        <!-- Back to home link -->
        <div class="text-center mt-6">
            <a href="../index.php" class="text-white text-sm hover:underline flex items-center justify-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Home
            </a>
        </div>
    </div>

    <script>
        // Client-side form validation
        const form = document.getElementById('registerForm');
        form.addEventListener('submit', function(event) {
            // Clear previous error messages
            document.querySelectorAll('.error-text').forEach(error => error.classList.add('hidden'));

            let hasError = false;

            // Validate name
            const name = document.getElementById('name').value.trim();
            if (!name) {
                document.getElementById('name-error').textContent = 'Please enter your full name.';
                document.getElementById('name-error').classList.remove('hidden');
                hasError = true;
            }

            // Validate email
            const email = document.getElementById('email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email) {
                document.getElementById('email-error').textContent = 'Please enter your email.';
                document.getElementById('email-error').classList.remove('hidden');
                hasError = true;
            } else if (!emailRegex.test(email)) {
                document.getElementById('email-error').textContent = 'Please enter a valid email address.';
                document.getElementById('email-error').classList.remove('hidden');
                hasError = true;
            }

            // Validate role
            const role = document.querySelector('input[name="role"]:checked');
            if (!role) {
                document.getElementById('role-error').textContent = 'Please select a role.';
                document.getElementById('role-error').classList.remove('hidden');
                hasError = true;
            }

            // Validate shelter name if role is shelter
            if (role && role.value === 'shelter') {
                const shelterName = document.getElementById('shelter_name').value.trim();
                if (!shelterName) {
                    document.getElementById('shelter-name-error').textContent = 'Please enter your shelter name.';
                    document.getElementById('shelter-name-error').classList.remove('hidden');
                    hasError = true;
                }
            }

            // Validate password
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            if (!password) {
                document.getElementById('password-error').textContent = 'Please enter a password.';
                document.getElementById('password-error').classList.remove('hidden');
                hasError = true;
            } else if (password.length < 8) {
                document.getElementById('password-error').textContent = 'Password must be at least 8 characters.';
                document.getElementById('password-error').classList.remove('hidden');
                hasError = true;
            }

            // Validate confirm password
            if (!confirmPassword) {
                document.getElementById('confirm-password-error').textContent = 'Please confirm your password.';
                document.getElementById('confirm-password-error').classList.remove('hidden');
                hasError = true;
            } else if (password !== confirmPassword) {
                document.getElementById('confirm-password-error').textContent = 'Passwords do not match.';
                document.getElementById('confirm-password-error').classList.remove('hidden');
                hasError = true;
            }

            // Validate terms checkbox
            const terms = document.getElementById('terms').checked;
            if (!terms) {
                document.getElementById('terms-error').textContent = 'You must agree to the terms.';
                document.getElementById('terms-error').classList.remove('hidden');
                hasError = true;
            }

            // Prevent form submission if there are errors
            if (hasError) {
                event.preventDefault();
            }
        });

        // Show/hide shelter name field based on role
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const shelterField = document.getElementById('shelter-name-field');
                const shelterInput = document.getElementById('shelter_name');
                if (this.value === 'shelter') {
                    shelterField.classList.remove('hidden');
                    shelterInput.required = true;
                } else {
                    shelterField.classList.add('hidden');
                    shelterInput.required = false;
                }
            });
        });

        // Social login handlers
        document.getElementById('googleBtn').addEventListener('click', function() {
            handleSocialLogin('google');
        });
        document.getElementById('githubBtn').addEventListener('click', function() {
            handleSocialLogin('github');
        });

        function handleSocialLogin(provider) {
            const roleInput = document.querySelector('input[name="role"]:checked');
            const messageBox = document.getElementById('socialMessage');
            messageBox.textContent = '';

            if (!roleInput) {
                messageBox.textContent = '⚠️ Please select a role before continuing.';
                return;
            }

            const role = roleInput.value;
            let shelterName = '';
            if (role === 'shelter') {
                shelterName = document.getElementById('shelter_name').value.trim();
                if (!shelterName) {
                    messageBox.textContent = '⚠️ Please enter your shelter name before continuing.';
                    return;
                }
            }

            // Save role to session before redirect
            fetch('save-role-session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'role=' + encodeURIComponent(role) + '&shelter_name=' + encodeURIComponent(shelterName)
            }).then(response => {
                if (response.ok) {
                    if (provider === 'google') {
                        window.location.href = 'google-login.php';
                    } else {
                        window.location.href = 'github-login.php';
                    }
                } else {
                    messageBox.textContent = '⚠️ Error saving role. Please try again.';
                }
            });
        }
    </script>
</body>
</html>