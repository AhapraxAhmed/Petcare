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
$vet_query = "SELECT v.vet_id, v.specialization, v.clinic_name, v.clinic_address, v.experience_years, v.consultation_fee, v.available_hours, u.name, u.email, u.phone, u.avatar 
              FROM veterinarians v 
              JOIN users u ON v.user_id = u.id 
              WHERE v.user_id = ?";
$vet_stmt = $conn->prepare($vet_query);
$vet_stmt->bind_param("i", $user_id);
$vet_stmt->execute();
$vet = $vet_stmt->get_result()->fetch_assoc();
$vet_stmt->close();

// Parse available_hours JSON (check if column exists)
$available_hours = isset($vet['available_hours']) && $vet['available_hours'] ? json_decode($vet['available_hours'], true) : [];

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
            $upload_path = __DIR__ . '/../../uploads/users/' . $file_name;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $update_image_query = "UPDATE users SET avatar = ? WHERE id = ?";
                $update_image_stmt = $conn->prepare($update_image_query);
                $image_path = $file_name;
                $update_image_stmt->bind_param("si", $image_path, $user_id);

                if ($update_image_stmt->execute()) {
                    $update_image_stmt->close();
                    $user['avatar'] = $image_path; // Update local user
                    $_SESSION['user_data']['avatar'] = $image_path; // Sync session
                    $_SESSION['success_message'] = "Profile image updated successfully!";
                    header("Location: profile.php");
                    exit;
                }
                else {
                    $error = "Failed to update profile image in database.";
                }
                $update_image_stmt->close();
            }
            else {
                $error = "Failed to upload image.";
            }
        }
        else {
            $error = "Invalid file type or size. Please upload a JPEG, PNG, or GIF image under 5MB.";
        }
    }
    else {
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
    // Process availability
    $processed_hours = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if (isset($_POST['is_open'][$day])) {
            $start = $_POST['start_time'][$day] ?? '09:00';
            $end = $_POST['end_time'][$day] ?? '17:00';
            $processed_hours[$day] = $start . '-' . $end;
        }
        else {
            $processed_hours[$day] = 'Closed';
        }
    }
    $available_hours_json = json_encode($processed_hours);

    if (empty($name) || strlen($name) > 255) {
        $error = "Name is required and must be less than 255 characters.";
    }
    elseif (!empty($phone) && !preg_match("/^(\+92|0)3\d{9}$/", $phone)) {
        $error = "Invalid phone number format.";
    }
    elseif (empty($specialization) || empty($clinic_name) || empty($clinic_address)) {
        $error = "Specialization, clinic name, and clinic address are required.";
    }
    else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $user_id);
        $stmt2 = $conn->prepare("UPDATE veterinarians SET specialization = ?, clinic_name = ?, clinic_address = ?, available_hours = ? WHERE user_id = ?");
        $stmt2->bind_param("ssssi", $specialization, $clinic_name, $clinic_address, $available_hours_json, $user_id);

        if ($stmt->execute() && $stmt2->execute()) {
            $stmt->close();
            $stmt2->close();
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit;
        }
        else {
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

    $pass_query = "SELECT password FROM users WHERE id = ?";
    $pass_stmt = $conn->prepare($pass_query);
    $pass_stmt->bind_param("i", $user_id);
    $pass_stmt->execute();
    $pass_result = $pass_stmt->get_result();
    $user_data = $pass_result->fetch_assoc();
    $pass_stmt->close();

    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                $update_pass_query = "UPDATE users SET password = ? WHERE id = ?";
                $update_pass_stmt = $conn->prepare($update_pass_query);
                $update_pass_stmt->bind_param("si", $new_hash, $user_id);

                if ($update_pass_stmt->execute()) {
                    $update_pass_stmt->close();
                    $_SESSION['success_message'] = "Password changed successfully!";
                    header("Location: profile.php");
                    exit;
                }
                else {
                    $password_error = "Failed to change password. Please try again.";
                }
                $update_pass_stmt->close();
            }
            else {
                $password_error = "Password must be at least 8 characters long.";
            }
        }
        else {
            $password_error = "New passwords do not match.";
        }
    }
    else {
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
    <title>Vet Profile - FurShield</title>
    <link rel="icon" href="../../assets/images/favicon.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        .vet-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .vet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .animate-fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }
        .animate-fade-in.active {
            opacity: 1;
            transform: translateY(0);
        }
        .availability-form {
            max-height: 0;
            overflow: hidden;
            transition: all 0.5s ease;
        }
        .availability-form.active {
            max-height: 1000px;
            margin-top: 1rem;
        }
        .profile-image-container {
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }
        .profile-image-container input[type="file"] {
            display: none;
        }
        input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
        }
    </style>
    <link rel="icon" href="/furshield/assets/images/favicon.png" type="image/x-icon">
</head>
<body class="bg-gradient-to-br from-slate-50 via-indigo-50/30 to-slate-100">
    <div class="md:p-9">
        <div class="max-w-full mx-auto h-[100vh] md:h-[calc(95vh-3rem)]">

            <!-- Outer Shell with Rounded Glass -->
            <div class="flex h-full bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/50 overflow-hidden animate-scale-in">
               <?php include "sidebar.php"; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-8 py-5">
                    <div>
                        <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">Professional Profile</h2>
                        <p class="text-slate-500 text-sm font-medium">Manage your clinic details and schedule</p>
                    </div>
                    <div class="flex items-center space-x-5">
                        <div class="relative group">
                            <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-lg px-1.5 py-0.5 border-2 border-white">
                                        <?php echo $unread_notifications; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="hidden sm:block text-right">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Today</p>
                            <p class="text-sm font-bold text-slate-700"><?php echo date('l, F j'); ?></p>
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
                            <?php 
                            $avatar_url = APP_URL . '/assets/images/you.jpg';
                            if (!empty($vet['avatar'])) {
                                if (strpos($vet['avatar'], 'http') === 0) {
                                    $avatar_url = htmlspecialchars($vet['avatar']);
                                } else {
                                    $avatar_url = APP_URL . '/uploads/users/' . htmlspecialchars($vet['avatar']);
                                }
                            }
                            ?>
                            <?php if (empty($user['google_id']) && empty($user['githubid'])): ?>
                                <form method="POST" action="" enctype="multipart/form-data" id="imageUploadForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="upload_image" value="1">
                                    <label class="profile-image-container group">
                                        <img src="<?php echo $avatar_url; ?>" alt="Veterinarian" class="w-32 h-32 rounded-full mx-auto mb-3 object-cover border-4 border-white shadow-lg transition-transform hover:scale-105">
                                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="bg-black/50 text-white text-xs px-2 py-1 rounded-full">Change Photo</span>
                                        </div>
                                        <input type="file" name="profile_image" accept="image/*" onchange="document.getElementById('imageUploadForm').submit();">
                                    </label>
                                </form>
                            <?php else: ?>
                                <img src="<?php echo $avatar_url; ?>" alt="Veterinarian" class="w-32 h-32 rounded-full mx-auto mb-3 object-cover border-4 border-white shadow-lg">
                            <?php endif; ?>
                            <h5 class="font-medium"><?php echo htmlspecialchars($vet['name']); ?></h5>
                            <p class="text-gray-500"><?php echo htmlspecialchars($vet['email']); ?></p>
                            <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Veterinarian</span>
                            <?php if ($vet['experience_years']): ?>
                                <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 mt-2"><?php echo $vet['experience_years']; ?> Years Experience</span>
                            <?php
endif; ?>
                        </div>
                        <div class="col-span-2">
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div id="successMessage" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 transition-opacity duration-500 opacity-100" role="alert">
                                    <?php
    echo htmlspecialchars($_SESSION['success_message']);
    unset($_SESSION['success_message']); // remove after displaying
?>
                                </div>
                            <?php
endif; ?>

                            <?php if ($error): ?>
                                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php
endif; ?>
                            <div class="grid grid-cols-2 gap-4 mt-8">
                                <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-5 flex items-center justify-between group-hover:bg-indigo-100 transition-all">
                                    <div>
                                        <h4 class="text-3xl font-extrabold text-indigo-700"><?php echo $pets_treated; ?></h4>
                                        <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Patients</p>
                                    </div>
                                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-sm">
                                        <i class="fas fa-paw text-indigo-500"></i>
                                    </div>
                                </div>
                                <div class="bg-purple-50 border border-purple-100 rounded-2xl p-5 flex items-center justify-between group-hover:bg-purple-100 transition-all">
                                    <div>
                                        <h4 class="text-3xl font-extrabold text-purple-700"><?php echo $appointments_count; ?></h4>
                                        <p class="text-[10px] font-bold text-purple-400 uppercase tracking-widest">Visits</p>
                                    </div>
                                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-sm">
                                        <i class="fas fa-calendar-check text-purple-500"></i>
                                    </div>
                                </div>
                                <div class="col-span-2 bg-slate-50 border border-slate-100 rounded-2xl p-5 flex items-center justify-between group-hover:bg-slate-100 transition-all">
                                    <div>
                                        <h4 class="text-2xl font-extrabold text-slate-700"><?php echo format_currency($vet['consultation_fee']); ?></h4>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Base Rate</p>
                                    </div>
                                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-sm">
                                        <i class="fas fa-tag text-slate-500"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-span-2">
                             <div class="bg-white border-2 border-slate-50 rounded-[2rem] p-8 h-full relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-slate-50 rounded-bl-[4rem] -mr-16 -mt-16 -z-10 group-hover:bg-blue-50 transition-all"></div>
                                <h6 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">Clinic Overview</h6>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-hospital text-blue-500 text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Practice</p>
                                            <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($vet['clinic_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-start space-x-4">
                                        <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-award text-orange-500 text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Expertise</p>
                                            <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($vet['specialization']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-start space-x-4">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-history text-emerald-500 text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Practice Since</p>
                                            <p class="text-sm font-bold text-slate-700"><?php echo $vet['experience_years'] ? $vet['experience_years'] . ' years' : 'Establishing'; ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-start space-x-4">
                                        <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-map-marker-alt text-rose-500 text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Location</p>
                                            <p class="text-xs font-bold text-slate-700 leading-relaxed"><?php echo htmlspecialchars($vet['clinic_address']); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-auto pt-6 border-t border-slate-50 flex items-center justify-between">
                                    <button class="availability-toggle flex items-center space-x-2 text-[10px] font-black text-blue-600 uppercase tracking-widest hover:text-blue-700 transition-colors">
                                        <i class="fas fa-clock"></i>
                                        <span>Show Public Schedule</span>
                                    </button>
                                </div>
                                <div class="availability-form mt-2">
                                    <?php if ($available_hours): ?>
                                        <?php foreach ($available_hours as $day => $hours): ?>
                                            <?php
        $display_hours = 'Closed';
        if ($hours && strtolower($hours) !== 'closed' && strpos($hours, '-') !== false) {
            list($start, $end) = explode('-', $hours);
            $start_time = strtotime($start);
            $end_time = strtotime($end);
            $display_hours = date('g A', $start_time) . ' - ' . date('g A', $end_time);
        }
?>
                                            <p><strong class="text-gray-700"><?php echo ucfirst($day); ?>:</strong> <?php echo $display_hours; ?></p>
                                        <?php
    endforeach; ?>
                                    <?php
else: ?>
                                        <p class="text-gray-500">No availability set</p>
                                    <?php
endif; ?>
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
                                <input type="text" name="name" id="name" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 p-1 focus:ring focus:ring-green-200 focus:ring-opacity-50" 
                                       value="<?php echo htmlspecialchars($vet['name']); ?>" required maxlength="255">
                                <p class="text-sm text-red-500 hidden" id="name-error">Name is required and must be less than 255 characters.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" class="block w-full rounded-lg border-gray-300 bg-gray-100 shadow-sm p-1" 
                                       value="<?php echo htmlspecialchars($vet['email']); ?>" disabled>
                                <p class="text-sm text-gray-500 mt-1">Email cannot be changed</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 p-1" 
                                       value="<?php echo htmlspecialchars($vet['phone']); ?>" 
                                       placeholder="03xxxxxxxxx or +923xxxxxxxxx"
                                       pattern="(\+92|0)3\d{9}" title="Please enter a valid Pakistani number (e.g., 03331234567 or +923331234567)">
                                <p class="text-xs text-gray-500 mt-1">Format: 03xxxxxxxxx or +923xxxxxxxxx</p>
                                <p class="text-sm text-red-500 hidden" id="phone-error">Please enter a valid Pakistani number.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Specialization</label>
                                <input type="text" name="specialization" id="specialization" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 p-1" 
                                       value="<?php echo htmlspecialchars($vet['specialization']); ?>" required>
                                <p class="text-sm text-red-500 hidden" id="specialization-error">Specialization is required.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Clinic Name</label>
                                <input type="text" name="clinic_name" id="clinic_name" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 p-1" 
                                       value="<?php echo htmlspecialchars($vet['clinic_name']); ?>" required>
                                <p class="text-sm text-red-500 hidden" id="clinic_name-error">Clinic name is required.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Clinic Address</label>
                                <textarea name="clinic_address" id="clinic_address" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 p-1" rows="3"><?php echo htmlspecialchars($vet['clinic_address']); ?></textarea>
                                <p class="text-sm text-red-500 hidden" id="clinic_address-error">Clinic address is required.</p>
                            </div>
                            <div class="col-span-2">
                                <div class="flex items-center justify-between mb-4 mt-8">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-clock text-blue-500"></i>
                                        <label class="block text-sm font-bold text-gray-700 uppercase tracking-wider">Consultation Hours</label>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button type="button" onclick="copyMondayToAll()" class="text-[10px] font-bold bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full hover:bg-blue-100 transition-all border border-blue-100">
                                            <i class="fas fa-copy mr-1"></i> Copy Monday
                                        </button>
                                        <button type="button" onclick="clearAllDays()" class="text-[10px] font-bold bg-red-50 text-red-600 px-3 py-1.5 rounded-full hover:bg-red-100 transition-all border border-red-100">
                                            <i class="fas fa-trash-alt mr-1"></i> Clear All
                                        </button>
                                    </div>
                                </div>
                                <?php $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']; ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($days as $day): ?>
                                        <?php
                                        $hour_str = isset($available_hours[$day]) ? $available_hours[$day] : '';
                                        $isOpen = ($hour_str && strtolower($hour_str) !== 'closed');
                                        $start = '';
                                        $end = '';
                                        if ($isOpen && strpos($hour_str, '-') !== false) {
                                            list($start, $end) = explode('-', $hour_str);
                                        }
                                        ?>
                                        <div class="flex flex-col bg-slate-50 p-4 rounded-2xl border border-slate-100 transition-all hover:border-blue-200" id="row_<?php echo $day; ?>">
                                            <div class="flex items-center justify-between mb-3">
                                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider"><?php echo ucfirst($day); ?></span>
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" name="is_open[<?php echo $day; ?>]" class="sr-only peer" <?php echo $isOpen ? 'checked' : ''; ?> onchange="toggleDay('<?php echo $day; ?>')">
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                    <span class="ms-2 text-[10px] font-bold text-slate-500 uppercase status-label"><?php echo $isOpen ? 'Open' : 'Closed'; ?></span>
                                                </label>
                                            </div>
                                            <div class="time-inputs flex items-center space-x-2 <?php echo $isOpen ? '' : 'hidden opacity-50 pointer-events-none'; ?>">
                                                <div class="flex-1 relative">
                                                    <input type="time" name="start_time[<?php echo $day; ?>]" value="<?php echo htmlspecialchars($start); ?>" 
                                                           class="w-full rounded-xl border-slate-200 text-xs py-2 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 start-time">
                                                </div>
                                                <span class="text-slate-300">to</span>
                                                <div class="flex-1 relative">
                                                    <input type="time" name="end_time[<?php echo $day; ?>]" value="<?php echo htmlspecialchars($end); ?>" 
                                                           class="w-full rounded-xl border-slate-200 text-xs py-2 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 end-time">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="available_hours_submit" id="available_hours_json">
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
                    <?php
endif; ?>
                    <?php if (isset($password_error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in" role="alert">
                            <?php echo htmlspecialchars($password_error); ?>
                        </div>
                    <?php
endif; ?>
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

            window.addEventListener('DOMContentLoaded', () => {
                const msg = document.getElementById('successMessage');
                if(msg){
                    // fade out after 3 seconds
                    setTimeout(() => {
                        msg.style.opacity = '0';
                        // remove from DOM after transition
                        setTimeout(() => msg.remove(), 500);
                    }, 3000);
                }
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
                if (phone && !/^(\+92|0)3\d{9}$/.test(phone)) {
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
        function toggleDay(day) {
            const row = document.getElementById('row_' + day);
            const checkbox = row.querySelector('input[type="checkbox"]');
            const label = row.querySelector('.status-label');
            const timeInputs = row.querySelector('.time-inputs');
            
            if (checkbox.checked) {
                label.textContent = 'Open';
                timeInputs.classList.remove('hidden', 'opacity-50', 'pointer-events-none');
            } else {
                label.textContent = 'Closed';
                timeInputs.classList.add('hidden', 'opacity-50', 'pointer-events-none');
            }
        }

        function copyMondayToAll() {
            const mondayOpen = document.querySelector('input[name="is_open[monday]"]').checked;
            const mondayStart = document.querySelector('input[name="start_time[monday]"]').value;
            const mondayEnd = document.querySelector('input[name="end_time[monday]"]').value;
            
            const days = ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                const checkbox = document.querySelector(`input[name="is_open[${day}]"]`);
                checkbox.checked = mondayOpen;
                
                document.querySelector(`input[name="start_time[${day}]"]`).value = mondayStart;
                document.querySelector(`input[name="end_time[${day}]"]`).value = mondayEnd;
                
                toggleDay(day);
            });
        }

        function clearAllDays() {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                const checkbox = document.querySelector(`input[name="is_open[${day}]"]`);
                checkbox.checked = false;
                document.querySelector(`input[name="start_time[${day}]"]`).value = "";
                document.querySelector(`input[name="end_time[${day}]"]`).value = "";
                toggleDay(day);
            });
        }
    </script>
</body>
</html>