<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <?php
    session_start();
    require_once '../config/database.php';
    require_once '../includes/notifications.php';
    require_once '../middleware/auth.php';
    
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../auth/login.php');
        exit();
    }
    
    $notificationService = new NotificationService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = $_POST['subject'];
        $content = $_POST['content'];
        $target_role = $_POST['target_role'] === 'all' ? null : $_POST['target_role'];
        
        $sent_count = $notificationService->sendNewsletter($subject, $content, $target_role);
        $success_message = "Newsletter sent to {$sent_count} users successfully!";
    }
    ?>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-blue-600 to-blue-800 text-white shadow-2xl">
            <div class="p-6">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-shield-alt text-blue-600 text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold">FurShield</h2>
                        <p class="text-blue-200 text-sm">Admin Panel</p>
                    </div>
                </div>
            </div>
            
            <nav class="mt-8">
                <a href="../dashboard/admin/index.php" class="flex items-center px-6 py-3 hover:bg-white/10 transition-colors">
                    <i class="fas fa-chart-line mr-3"></i>
                    Dashboard
                </a>
                <a href="../dashboard/admin/users.php" class="flex items-center px-6 py-3 hover:bg-white/10 transition-colors">
                    <i class="fas fa-users mr-3"></i>
                    Users
                </a>
                <a href="newsletter.php" class="flex items-center px-6 py-3 bg-white/20 border-r-4 border-white">
                    <i class="fas fa-envelope mr-3"></i>
                    Newsletter
                </a>
                <a href="../auth/logout.php" class="flex items-center px-6 py-3 hover:bg-white/10 transition-colors mt-8">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Newsletter Management</h1>
                <p class="text-gray-600">Send newsletters to your users</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Compose Newsletter</h3>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                        <input type="text" name="subject" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Newsletter subject...">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Target Audience</label>
                        <select name="target_role" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="all">All Users</option>
                            <option value="pet_owner">Pet Owners</option>
                            <option value="veterinarian">Veterinarians</option>
                            <option value="shelter">Shelters</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                        <textarea name="content" rows="12" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Write your newsletter content here... (HTML supported)"></textarea>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Send Newsletter
                        </button>
                        <button type="button" class="bg-gray-300 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-400 transition-colors">
                            Save Draft
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
