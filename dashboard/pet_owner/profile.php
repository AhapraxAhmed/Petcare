<?php
require_once(__DIR__ . "/../../config/config.php");
require "../../includes/functions.php";

// Check if user is logged in and is a pet owner
$user = a(); // Assuming 'a()' is a function that returns user data
$user_id = $user['user_id'];

if (!isset($user['user_id']) || $user['role'] !== 'pet_owner') {
    header("Location: /../../login.php");
    exit();
}

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Generate or validate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle profile image upload
if ($_POST && isset($_POST['upload_image']) && $_POST['csrf_token'] === $csrf_token) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_type = $_FILES['profile_image']['type'];
        $file_size = $_FILES['profile_image']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $file_name = $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = __DIR__ . '/../../uploads/images/' . $file_name;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Update database with new image path
                $update_image_query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                $update_image_stmt = $conn->prepare($update_image_query);
                $image_path = $file_name;
                $update_image_stmt->bind_param("si", $image_path, $user_id);
                
                if ($update_image_stmt->execute()) {
                    $image_success = "Profile image updated successfully!";
                    $user_info['profile_image'] = $image_path; // Update local user data
                } else {
                    $image_error = "Failed to update profile image in database.";
                }
                $update_image_stmt->close();
            } else {
                $image_error = "Failed to upload image.";
            }
        } else {
            $image_error = "Invalid file type or size. Please upload a JPEG, PNG, or GIF image under 5MB.";
        }
    } else {
        $image_error = "No file uploaded or upload error occurred.";
    }
}

// Handle profile update
if ($_POST && isset($_POST['update_profile']) && $_POST['csrf_token'] === $csrf_token) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;

    // Basic validation
    if (empty($name) || strlen($name) > 100) {
        $error_message = "Name is required and must be less than 100 characters.";
    } elseif (!empty($phone) && !preg_match("/^\+?[1-9]\d{1,14}$/", $phone)) {
        $error_message = "Invalid phone number format.";
    } else {
        $update_query = "UPDATE users SET name = ?, phone = ?, address = ?, newsletter_subscription = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssii", $name, $phone, $address, $newsletter, $user_id);

        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            $_SESSION['user_name'] = $name;
            $user['name'] = $name; // Update local user data
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $update_stmt->close();
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password']) && $_POST['csrf_token'] === $csrf_token) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Get current password hash
    $pass_query = "SELECT password_hash FROM users WHERE user_id = ?";
    $pass_stmt = $conn->prepare($pass_query);
    $pass_stmt->bind_param("i", $user_id);
    $pass_stmt->execute();
    $pass_result = $pass_stmt->get_result();
    $user_data = $pass_result->fetch_assoc();
    $pass_stmt->close();

    if (password_verify($current_password, $user_data['password_hash'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) { // Assuming 8 as PASSWORD_MIN_LENGTH
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                $update_pass_query = "UPDATE users SET password_hash = ? WHERE user_id = ?";
                $update_pass_stmt = $conn->prepare($update_pass_query);
                $update_pass_stmt->bind_param("si", $new_hash, $user_id);

                if ($update_pass_stmt->execute()) {
                    $password_success = "Password changed successfully!";
                } else {
                    $password_error = "Failed to change password. Please try again.";
                }
                $update_pass_stmt->close();
            } else {
                $password_error = "Password must be at least 8 characters long.";
            }
        } else {
            $password_error = "New passwords do not match.";
        }
    } else {
        $password_error = "Current password is incorrect.";
    }
}

// Get user information
$user_query = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();
$user_stmt->close();

// Get user's pets count
$pets_count_query = "SELECT COUNT(*) as pet_count FROM pets WHERE owner_id = ?";
$pets_count_stmt = $conn->prepare($pets_count_query);
$pets_count_stmt->bind_param("i", $user_id);
$pets_count_stmt->execute();
$pets_count = $pets_count_stmt->get_result()->fetch_assoc()['pet_count'];
$pets_count_stmt->close();

// Get appointments count
$appointments_count_query = "SELECT COUNT(*) as appointment_count FROM appointments WHERE owner_id = ?";
$appointments_count_stmt = $conn->prepare($appointments_count_query);
$appointments_count_stmt->bind_param("i", $user_id);
$appointments_count_stmt->execute();
$appointments_count = $appointments_count_stmt->get_result()->fetch_assoc()['appointment_count'];
$appointments_count_stmt->close();

// Get unread notifications count
$notifications_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_notifications = $notifications_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FurShield</title>
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
        .profile-image-container {
            position: relative;
            cursor: pointer;
        }
        .profile-image-container input[type="file"] {
            display: none;
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
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">My Profile</h2>
                        <p class="text-gray-600">Manage your account details</p>
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
                <!-- Profile Overview -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <?php if (!isset($user_info['google_id']) && !isset($user_info['github_id'])): ?>
                                <form method="POST" action="" enctype="multipart/form-data" id="imageUploadForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="upload_image" value="1">
                                    <label class="profile-image-container">
                                        <img src="<?php echo (!empty($user_info['profile_image']) && file_exists(__DIR__ . '/../../uploads/images/' . $user_info['profile_image'])) ? '../../uploads/images/' . htmlspecialchars($user_info['profile_image']) : 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR4g_2Qj3LsNR-iqUAFm6ut2EQVcaou4u2YXw&s'; ?>" alt="User" class="w-10 h-10 rounded-full mx-auto mb-3">
                                        <input type="file" name="profile_image" accept="image/*" onchange="document.getElementById('imageUploadForm').submit();">
                                    </label>
                                </form>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($user_info['profile_image']); ?>" alt="User" class="w-10 h-10 rounded-full mx-auto mb-3">
                            <?php endif; ?>
                            <h5 class="font-medium"><?php echo htmlspecialchars($user_info['name']); ?></h5>
                            <p class="text-gray-500"><?php echo htmlspecialchars($user_info['email']); ?></p>
                            <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Pet Owner</span>
                        </div>
                        <div class="col-span-2">
                            <?php if (isset($image_success)): ?>
                                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 animate-fade-in" role="alert">
                                    <?php echo htmlspecialchars($image_success); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($image_error)): ?>
                                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in" role="alert">
                                    <?php echo htmlspecialchars($image_error); ?>
                                </div>
                            <?php endif; ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div class="bg-blue-50 rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-blue-600"><?php echo $pets_count; ?></h4>
                                        <p class="text-gray-600">My Pets</p>
                                    </div>
                                    <i class="fas fa-paw text-2xl text-blue-600"></i>
                                </div>
                                <div class="bg-green-50 rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-green-600"><?php echo $appointments_count; ?></h4>
                                        <p class="text-gray-600">Total Appointments</p>
                                    </div>
                                    <i class="fas fa-calendar-check text-2xl text-green-600"></i>
                                </div>
                            </div>
                            <div class="bg-white border rounded-lg p-4">
                                <h6 class="font-medium text-gray-700 mb-3">Account Information</h6>
                                <p><strong class="text-gray-700">Member since:</strong> <?php echo date('F j, Y', strtotime($user_info['created_at'])); ?></p>
                                <p><strong class="text-gray-700">Email verified:</strong>
                                    <span class="inline-block px-2 py-1 text-xs rounded-full <?php echo $user_info['is_verified'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $user_info['is_verified'] ? 'Verified' : 'Not Verified'; ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Update Form -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in" style="animation-delay: 0.1s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-edit mr-2"></i>Update Profile Information</h3>
                    <?php if (isset($success_message)): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 animate-fade-in" role="alert">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in" role="alert">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                <input type="text" name="name" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                       value="<?php echo htmlspecialchars($user_info['name']); ?>" required maxlength="100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" class="block w-full rounded-lg border-gray-300 bg-gray-100 shadow-sm" 
                                       value="<?php echo htmlspecialchars($user_info['email']); ?>" disabled>
                                <p class="text-sm text-gray-500 mt-1">Email cannot be changed</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                       value="<?php echo htmlspecialchars($user_info['phone']); ?>" pattern="^\+?[1-9]\d{1,14}$">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea name="address" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" rows="3"><?php echo htmlspecialchars($user_info['address']); ?></textarea>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="newsletter" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" 
                                       <?php echo $user_info['newsletter_subscription'] ? 'checked' : ''; ?>>
                                <span class="ml-2 text-sm text-gray-700">Subscribe to newsletter and pet care tips</span>
                            </label>
                        </div>
                        <div class="mt-6">
                            <button type="submit" name="update_profile" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                                <i class="fas fa-save mr-2"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in" style="animation-delay: 0.2s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-lock mr-2"></i>Change Password</h3>
                    <?php if (isset($password_success)): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 animate-fade-in" role="alert">
                            <?php echo htmlspecialchars($password_success); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($password_error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in" role="alert">
                            <?php echo htmlspecialchars($password_error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required minlength="8">
                                <p class="text-sm text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" name="change_password" class="bg-yellow-600 text-white px-6 py-3 rounded-lg hover:bg-yellow-700 transition-colors flex items-center">
                                <i class="fas fa-key mr-2"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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