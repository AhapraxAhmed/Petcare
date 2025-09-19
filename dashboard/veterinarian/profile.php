<?php
require_once '../../middleware/auth.php';
requireRole('veterinarian');

$user = a();
$user_id = $user['user_id'];

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Generate or validate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get veterinarian details
$vet_query = "SELECT v.vet_id, v.specialization, v.clinic_name, v.clinic_address, v.experience_years, v.consultation_fee, v.available_hours, u.name, u.email, u.phone, u.profile_image, u.google_id 
              FROM veterinarians v 
              JOIN users u ON v.user_id = u.user_id 
              WHERE v.user_id = ?";
$vet_stmt = $conn->prepare($vet_query);
$vet_stmt->bind_param("i", $user_id);
$vet_stmt->execute();
$vet = $vet_stmt->get_result()->fetch_assoc();
$vet_stmt->close();

// Parse available_hours JSON
$available_hours = $vet['available_hours'] ? json_decode($vet['available_hours'], true) : [];

// Get counts for overview
$appointments_count_query = "SELECT COUNT(*) as appointment_count FROM appointments WHERE vet_id = ?";
$appointments_count_stmt = $conn->prepare($appointments_count_query);
$appointments_count_stmt->bind_param("i", $vet['vet_id']);
$appointments_count_stmt->execute();
$appointments_count = $appointments_count_stmt->get_result()->fetch_assoc()['appointment_count'];
$appointments_count_stmt->close();

$pets_treated_query = "SELECT COUNT(DISTINCT pet_id) as pet_count FROM health_records WHERE vet_id = ?";
$pets_treated_stmt = $conn->prepare($pets_treated_query);
$pets_treated_stmt->bind_param("i", $vet['vet_id']);
$pets_treated_stmt->execute();
$pets_treated = $pets_treated_stmt->get_result()->fetch_assoc()['pet_count'];
$pets_treated_stmt->close();

$notifications_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$unread_notifications = $notifications_stmt->get_result()->fetch_assoc()['count'];
$notifications_stmt->close();

$message = '';
$error = '';

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
            $upload_path = __DIR__ . '/../../Uploads/images/' . $file_name;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $update_image_query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                $update_image_stmt = $conn->prepare($update_image_query);
                $image_path = $file_name;
                $update_image_stmt->bind_param("si", $image_path, $user_id);
                
                if ($update_image_stmt->execute()) {
                    $message = "Profile image updated successfully!";
                    $vet['profile_image'] = $image_path;
                } else {
                    $error = "Failed to update profile image in database.";
                }
                $update_image_stmt->close();
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type or size. Please upload a JPEG, PNG, or GIF image under 5MB.";
        }
    } else {
        $error = "No file uploaded or upload error occurred.";
    }
}

// Handle profile update
if ($_POST && isset($_POST['update_profile']) && $_POST['csrf_token'] === $csrf_token) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $specialization = trim($_POST['specialization']);
    $clinic_name = trim($_POST['clinic_name']);
    $clinic_address = trim($_POST['clinic_address']);
    $available_hours = $_POST['available_hours'] ?? [];

    if (empty($name) || strlen($name) > 255) {
        $error = "Name is required and must be less than 255 characters.";
    } elseif (!empty($phone) && !preg_match("/^\+?[1-9]\d{1,14}$/", $phone)) {
        $error = "Invalid phone number format.";
    } elseif (empty($specialization) || empty($clinic_name) || empty($clinic_address)) {
        $error = "Specialization, clinic name, and clinic address are required.";
    } else {
        $available_hours_json = json_encode($available_hours);
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $name, $phone, $user_id);
        $stmt2 = $conn->prepare("UPDATE veterinarians SET specialization = ?, clinic_name = ?, clinic_address = ?, available_hours = ? WHERE user_id = ?");
        $stmt2->bind_param("ssssi", $specialization, $clinic_name, $clinic_address, $available_hours_json, $user_id);

        if ($stmt->execute() && $stmt2->execute()) {
            $message = "Profile updated successfully!";
            $vet['name'] = $name;
            $vet['phone'] = $phone;
            $vet['specialization'] = $specialization;
            $vet['clinic_name'] = $clinic_name;
            $vet['clinic_address'] = $clinic_address;
            $vet['available_hours'] = $available_hours_json;
            $available_hours = json_decode($available_hours_json, true);
        } else {
            $error = "Failed to update profile. Please try again.";
        }
        $stmt->close();
        $stmt2->close();
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password']) && $_POST['csrf_token'] === $csrf_token) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $pass_query = "SELECT password_hash FROM users WHERE user_id = ?";
    $pass_stmt = $conn->prepare($pass_query);
    $pass_stmt->bind_param("i", $user_id);
    $pass_stmt->execute();
    $pass_result = $pass_stmt->get_result();
    $user_data = $pass_result->fetch_assoc();
    $pass_stmt->close();

    if (password_verify($current_password, $user_data['password_hash'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinarian Profile - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .vet-card {
            transition: all 0.3s ease;
        }
        .vet-card:hover {
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
        .availability-toggle {
            cursor: pointer;
        }
        .availability-form {
            display: none;
        }
        .availability-form.active {
            display: block;
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
                        <h2 class="text-2xl font-bold text-gray-800">Veterinarian Profile</h2>
                        <p class="text-gray-600">Manage your professional details</p>
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
                            <?php echo date('l, F j, Y, g:i A'); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Profile Overview -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 vet-card animate-fade-in">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <?php if (empty($user['google_id']) && empty($user['githubid'])): ?>
                                <form method="POST" action="" enctype="multipart/form-data" id="imageUploadForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="upload_image" value="1">
                                    <label class="profile-image-container">
                                        <img src="<?php echo (!empty($vet['profile_image']) && file_exists(__DIR__ . '/../../Uploads/images/' . $vet['profile_image'])) ? '../../Uploads/images/' . htmlspecialchars($vet['profile_image']) : 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR4g_2Qj3LsNR-iqUAFm6ut2EQVcaou4u2YXw&s'; ?>" alt="Veterinarian" class="w-24 h-24 rounded-full mx-auto mb-3">
                                        <input type="file" name="profile_image" accept="image/*" onchange="document.getElementById('imageUploadForm').submit();">
                                    </label>
                                </form>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($vet['profile_image']); ?>" alt="Veterinarian" class="w-24 h-24 rounded-full mx-auto mb-3">
                            <?php endif; ?>
                            <h5 class="font-medium"><?php echo htmlspecialchars($vet['name']); ?></h5>
                            <p class="text-gray-500"><?php echo htmlspecialchars($vet['email']); ?></p>
                            <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Veterinarian</span>
                            <?php if ($vet['experience_years']): ?>
                                <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 mt-2"><?php echo $vet['experience_years']; ?> Years Experience</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-2">
                            <?php if ($message): ?>
                                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 animate-fade-in" role="alert">
                                    <?php echo htmlspecialchars($message); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                                <div class="bg-green-50 rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-green-600"><?php echo $pets_treated; ?></h4>
                                        <p class="text-gray-600">Pets Treated</p>
                                    </div>
                                    <i class="fas fa-paw text-2xl text-green-600"></i>
                                </div>
                                <div class="bg-blue-50 rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-blue-600"><?php echo $appointments_count; ?></h4>
                                        <p class="text-gray-600">Total Appointments</p>
                                    </div>
                                    <i class="fas fa-calendar-check text-2xl text-blue-600"></i>
                                </div>
                                <div class="bg-yellow-50 rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-yellow-600">$<?php echo number_format($vet['consultation_fee'], 2); ?></h4>
                                        <p class="text-gray-600">Consultation Fee</p>
                                    </div>
                                    <i class="fas fa-dollar-sign text-2xl text-yellow-600"></i>
                                </div>
                            </div>
                            <div class="bg-white border rounded-lg p-4">
                                <h6 class="font-medium text-gray-700 mb-3">Professional Information</h6>
                                <p><strong class="text-gray-700">Clinic:</strong> <?php echo htmlspecialchars($vet['clinic_name']); ?></p>
                                <p><strong class="text-gray-700">Specialization:</strong> <?php echo htmlspecialchars($vet['specialization']); ?></p>
                                <p><strong class="text-gray-700">Experience:</strong> <?php echo $vet['experience_years'] ? $vet['experience_years'] . ' years' : 'Not specified'; ?></p>
                                <p><strong class="text-gray-700">Consultation Fee:</strong> $<?php echo number_format($vet['consultation_fee'], 2); ?></p>
                                <p class="availability-toggle text-green-600 hover:text-green-700 cursor-pointer"><i class="fas fa-clock mr-1"></i>View Availability</p>
                                <div class="availability-form mt-2">
                                    <?php if ($available_hours): ?>
                                        <?php foreach ($available_hours as $day => $hours): ?>
                                            <p><strong class="text-gray-700"><?php echo ucfirst($day); ?>:</strong> <?php echo htmlspecialchars($hours); ?></p>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-500">No availability set</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Update Form -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 vet-card animate-fade-in" style="animation-delay: 0.1s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-edit mr-2"></i>Update Profile Information</h3>
                    <form method="POST" action="" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                <input type="text" name="name" id="name" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" 
                                       value="<?php echo htmlspecialchars($vet['name']); ?>" required maxlength="255">
                                <p class="text-sm text-red-500 hidden" id="name-error">Name is required and must be less than 255 characters.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" class="block w-full rounded-lg border-gray-300 bg-gray-100 shadow-sm" 
                                       value="<?php echo htmlspecialchars($vet['email']); ?>" disabled>
                                <p class="text-sm text-gray-500 mt-1">Email cannot be changed</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" 
                                       value="<?php echo htmlspecialchars($vet['phone']); ?>" pattern="^\+?[1-9]\d{1,14}$">
                                <p class="text-sm text-red-500 hidden" id="phone-error">Invalid phone number format.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Specialization</label>
                                <input type="text" name="specialization" id="specialization" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" 
                                       value="<?php echo htmlspecialchars($vet['specialization']); ?>" required>
                                <p class="text-sm text-red-500 hidden" id="specialization-error">Specialization is required.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Clinic Name</label>
                                <input type="text" name="clinic_name" id="clinic_name" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" 
                                       value="<?php echo htmlspecialchars($vet['clinic_name']); ?>" required>
                                <p class="text-sm text-red-500 hidden" id="clinic_name-error">Clinic name is required.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Clinic Address</label>
                                <textarea name="clinic_address" id="clinic_address" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" rows="3"><?php echo htmlspecialchars($vet['clinic_address']); ?></textarea>
                                <p class="text-sm text-red-500 hidden" id="clinic_address-error">Clinic address is required.</p>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Availability</label>
                                <?php $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']; ?>
                                <?php foreach ($days as $day): ?>
                                    <div class="mb-2">
                                        <label class="block text-sm text-gray-600"><?php echo ucfirst($day); ?></label>
                                        <input type="text" name="available_hours[<?php echo $day; ?>]" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" 
                                               value="<?php echo isset($available_hours[$day]) ? htmlspecialchars($available_hours[$day]) : ''; ?>" placeholder="e.g., 09:00-17:00">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" name="update_profile" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors flex items-center">
                                <i class="fas fa-save mr-2"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="bg-white rounded-xl shadow-sm p-6 vet-card animate-fade-in" style="animation-delay: 0.2s;">
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
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password" id="current_password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required>
                                <p class="text-sm text-red-500 hidden" id="current_password-error">Current password is required.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required minlength="8">
                                <p class="text-sm text-gray-500 mt-1">Minimum 8 characters</p>
                                <p class="text-sm text-red-500 hidden" id="new_password-error">New password must be at least 8 characters.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required>
                                <p class="text-sm text-red-500 hidden" id="confirm_password-error">Passwords do not match.</p>
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
            // Animate cards
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Toggle availability form
            const toggle = document.querySelector('.availability-toggle');
            const form = document.querySelector('.availability-form');
            toggle.addEventListener('click', () => {
                form.classList.toggle('active');
            });

            // Client-side form validation for profile
            const profileForm = document.getElementById('profileForm');
            profileForm.addEventListener('submit', function(e) {
                let valid = true;
                const name = document.getElementById('name').value;
                const phone = document.getElementById('phone').value;
                const specialization = document.getElementById('specialization').value;
                const clinicName = document.getElementById('clinic_name').value;
                const clinicAddress = document.getElementById('clinic_address').value;

                document.getElementById('name-error').classList.add('hidden');
                document.getElementById('phone-error').classList.add('hidden');
                document.getElementById('specialization-error').classList.add('hidden');
                document.getElementById('clinic_name-error').classList.add('hidden');
                document.getElementById('clinic_address-error').classList.add('hidden');

                if (!name || name.length > 255) {
                    document.getElementById('name-error').classList.remove('hidden');
                    valid = false;
                }
                if (phone && !/^\+?[1-9]\d{1,14}$/.test(phone)) {
                    document.getElementById('phone-error').classList.remove('hidden');
                    valid = false;
                }
                if (!specialization) {
                    document.getElementById('specialization-error').classList.remove('hidden');
                    valid = false;
                }
                if (!clinicName) {
                    document.getElementById('clinic_name-error').classList.remove('hidden');
                    valid = false;
                }
                if (!clinicAddress) {
                    document.getElementById('clinic_address-error').classList.remove('hidden');
                    valid = false;
                }

                if (!valid) e.preventDefault();
            });

            // Client-side form validation for password
            const passwordForm = document.getElementById('passwordForm');
            passwordForm.addEventListener('submit', function(e) {
                let valid = true;
                const currentPassword = document.getElementById('current_password').value;
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                document.getElementById('current_password-error').classList.add('hidden');
                document.getElementById('new_password-error').classList.add('hidden');
                document.getElementById('confirm_password-error').classList.add('hidden');

                if (!currentPassword) {
                    document.getElementById('current_password-error').classList.remove('hidden');
                    valid = false;
                }
                if (!newPassword || newPassword.length < 8) {
                    document.getElementById('new_password-error').classList.remove('hidden');
                    valid = false;
                }
                if (newPassword !== confirmPassword) {
                    document.getElementById('confirm_password-error').classList.remove('hidden');
                    valid = false;
                }

                if (!valid) e.preventDefault();
            });
        });
    </script>
</body>
</html>