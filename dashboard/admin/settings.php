<?php
require_once '../../middleware/auth.php';
requireRole('admin');

$user = a(); // Assumes this retrieves the authenticated user's data
$user_id = $user['user_id']; // Get the admin's user_id

$db = new Database();
$conn = $db->getConnection();

// Fetch current user data
$stmt = $conn->prepare("SELECT name, phone, address, email, profile_image FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $site_name = filter_input(INPUT_POST, 'site_name', FILTER_SANITIZE_STRING);
    $admin_email = filter_input(INPUT_POST, 'admin_email', FILTER_SANITIZE_EMAIL);

    // Validate required fields
    if (empty($name) || empty($admin_email)) {
        $message = "Name and Admin Email are required!";
        $message_type = "error";
    } else {
        // Create upload directory if it doesn't exist
        $upload_dir = '../../Uploads/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Handle profile image upload (optional)
        $profile_image = $current_user['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($_FILES['profile_image']['tmp_name']);
            if (in_array($file_type, $allowed_types)) {
                $file_name = uniqid() . '-' . basename($_FILES['profile_image']['name']);
                $upload_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Delete old profile image if it exists
                    if ($profile_image && file_exists($upload_dir . $profile_image)) {
                        unlink($upload_dir . $profile_image);
                    }
                    $profile_image = $file_name;
                } else {
                    $message = "Failed to upload profile image!";
                    $message_type = "error";
                }
            } else {
                $message = "Invalid file type! Only JPEG, PNG, and GIF are allowed.";
                $message_type = "error";
            }
        }

        // Update user data in the database if no errors
        if (empty($message)) {
            $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ?, email = ?, profile_image = ? WHERE user_id = ?");
            $stmt->bind_param("sssssi", $name, $phone, $address, $admin_email, $profile_image, $user_id);
            if ($stmt->execute()) {
                $message = "Profile and settings updated successfully!";
                $message_type = "success";
                // Update current_user data for form display
                $current_user['name'] = $name;
                $current_user['phone'] = $phone;
                $current_user['address'] = $address;
                $current_user['email'] = $admin_email;
                $current_user['profile_image'] = $profile_image;
            } else {
                $message = "Failed to update profile!";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .animate-fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
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
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <button id="sidebar-toggle" class="text-gray-500 hover:text-gray-700 lg:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h2 class="text-2xl font-bold text-gray-800 ml-4">Settings</h2>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600"><?php echo date('l, F j, Y'); ?></div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">System and Profile Settings</h3>
                    <?php if ($message): ?>
                        <div class="<?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> p-4 rounded-lg mb-6"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- System Settings -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Site Name</label>
                            <input type="text" name="site_name" value="<?php echo htmlspecialchars($site_name ?? 'FurShield'); ?>" class="mt-1 p-2 w-full border rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Admin Email</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($current_user['email'] ?? 'admin@furshield.com'); ?>" class="mt-1 p-2 w-full border rounded-md">
                        </div>
                        <!-- Profile Settings -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($current_user['name'] ?? ''); ?>" class="mt-1 p-2 w-full border rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" class="mt-1 p-2 w-full border rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($current_user['address'] ?? ''); ?>" class="mt-1 p-2 w-full border rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Profile Image</label>
                            <input type="file" name="profile_image" accept="image/jpeg,image/png,image/gif" class="mt-1 p-2 w-full border rounded-md">
                            <?php if ($current_user['profile_image']): ?>
                                <p class="text-sm text-gray-500 mt-1">Current: <a href="../../Uploads/images/<?php echo htmlspecialchars($current_user['profile_image']); ?>" target="_blank">View Image</a></p>
                            <?php endif; ?>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Save Settings</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });
    </script>
</body>
</html>