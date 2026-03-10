<?php
require_once '../../middleware/auth.php';
requireRole('admin');

$user = a();

// Get dashboard statistics
$db = new Database();
$conn = $db->getConnection();

// Count statistics
$stats = [];

// Total users by role
$result = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $result->fetch_assoc()) {
    $stats['users'][$row['role']] = $row['count'];
}

// Total pets
$result = $conn->query("SELECT COUNT(*) as count FROM pets");
$stats['total_pets'] = $result->fetch_assoc()['count'];

// Total appointments
$result = $conn->query("SELECT COUNT(*) as count FROM appointments");
$stats['total_appointments'] = $result->fetch_assoc()['count'];

// Recent appointments
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= CURDATE()");
$stats['upcoming_appointments'] = $result->fetch_assoc()['count'];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
$stats['total_products'] = $result->fetch_assoc()['count'];

// Recent registrations (last 30 days)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['recent_registrations'] = $result->fetch_assoc()['count'];

// Get recent activities
$recent_users = $conn->query("SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$recent_appointments = $conn->query("
    SELECT a.appointment_date, a.status, u.name as owner_name, p.name as pet_name, v.name as vet_name
    FROM appointments a
    JOIN users u ON a.owner_id = u.user_id
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN veterinarians vet ON a.vet_id = vet.vet_id
    JOIN users v ON vet.user_id = v.user_id
    ORDER BY a.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        ::-webkit-scrollbar {
            width: 8px; /* Thinner scrollbar width */
            height: 8px; /* For horizontal scrollbar */
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1; /* Light track background */
            border-radius: 10px; /* Rounded track */
        }

        ::-webkit-scrollbar-thumb {
            background: #888; /* Scrollbar thumb color */
            border-radius: 10px; /* Rounded thumb */
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555; /* Darker on hover */
        }

        /* Firefox scrollbar support */
        * {
            scrollbar-width: thin; /* Thinner scrollbar for Firefox */
            scrollbar-color: #888 #f1f1f1; /* Thumb and track colors */
        }
        .sidebar-transition {
            transition: all 0.3s ease-in-out;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
                    <div class="flex items-center">
                        <button id="sidebar-toggle" class="text-gray-500 hover:text-gray-700 lg:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h2 class="text-2xl font-bold text-gray-800 ml-4">Dashboard Overview</h2>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="text-gray-500 hover:text-gray-700 relative">
                                <i class="fas fa-bell text-xl"></i>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
                            </button>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?php echo date('l, F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Users</p>
                                <p class="text-3xl font-bold text-gray-900">
                                    <?php echo array_sum($stats['users'] ?? []); ?>
                                </p>
                                <p class="text-sm text-green-600 mt-1">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    +<?php echo $stats['recent_registrations']; ?> this month
                                </p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Pets</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_pets']; ?></p>
                                <p class="text-sm text-gray-500 mt-1">Registered pets</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-paw text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Appointments</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['upcoming_appointments']; ?></p>
                                <p class="text-sm text-blue-600 mt-1">Upcoming</p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-calendar-alt text-2xl text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Products</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_products']; ?></p>
                                <p class="text-sm text-gray-500 mt-1">Active products</p>
                            </div>
                            <div class="bg-orange-100 p-3 rounded-full">
                                <i class="fas fa-box text-2xl text-orange-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Role Distribution -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.4s;">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">User Distribution</h3>
                        <div class="space-y-4">
                            <?php 
                            $role_colors = [
                                'pet_owner' => 'bg-blue-500',
                                'veterinarian' => 'bg-green-500',
                                'shelter' => 'bg-purple-500',
                                'admin' => 'bg-red-500'
                            ];
                            $role_names = [
                                'pet_owner' => 'Pet Owners',
                                'veterinarian' => 'Veterinarians',
                                'shelter' => 'Shelters',
                                'admin' => 'Administrators'
                            ];
                            $total_users = array_sum($stats['users'] ?? []);
                            
                            foreach ($stats['users'] ?? [] as $role => $count):
                                $percentage = $total_users > 0 ? ($count / $total_users) * 100 : 0;
                            ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full <?php echo $role_colors[$role]; ?> mr-3"></div>
                                    <span class="text-sm text-gray-600"><?php echo $role_names[$role]; ?></span>
                                </div>
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900 mr-2"><?php echo $count; ?></span>
                                    <span class="text-xs text-gray-500">(<?php echo number_format($percentage, 1); ?>%)</span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?php echo $role_colors[$role]; ?> h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Users -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.5s;">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Registrations</h3>
                        <div class="space-y-4">
                            <?php foreach ($recent_users as $recent_user): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($recent_user['name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo ucfirst(str_replace('_', ' ', $recent_user['role'])); ?></p>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo format_date($recent_user['created_at'], 'M j'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="users.php" class="mt-4 text-blue-600 text-sm hover:underline">View all users →</a>
                    </div>

                    <!-- Recent Appointments -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.6s;">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Appointments</h3>
                        <div class="space-y-4">
                            <?php foreach ($recent_appointments as $appointment): ?>
                            <div class="border-l-4 border-blue-500 pl-4">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($appointment['pet_name']); ?></p>
                                <p class="text-xs text-gray-600">Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?></p>
                                <p class="text-xs text-gray-600">Vet: <?php echo htmlspecialchars($appointment['vet_name']); ?></p>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-gray-500"><?php echo format_date($appointment['appointment_date'], 'M j, g:i A'); ?></span>
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 
                                             ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); 
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="appointments.php" class="mt-4 text-blue-600 text-sm hover:underline">View all appointments →</a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.7s;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="users.php?action=add" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                            <div class="bg-blue-500 p-2 rounded-lg mr-4">
                                <i class="fas fa-user-plus text-white"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Add User</p>
                                <p class="text-sm text-gray-600">Create new account</p>
                            </div>
                        </a>
                        
                        <a href="products.php?action=add" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <div class="bg-green-500 p-2 rounded-lg mr-4">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Add Product</p>
                                <p class="text-sm text-gray-600">New pet product</p>
                            </div>
                        </a>
                        
                        <a href="analytics.php" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                            <div class="bg-purple-500 p-2 rounded-lg mr-4">
                                <i class="fas fa-chart-line text-white"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">View Reports</p>
                                <p class="text-sm text-gray-600">System analytics</p>
                            </div>
                        </a>
                        
                        <a href="settings.php" class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                            <div class="bg-orange-500 p-2 rounded-lg mr-4">
                                <i class="fas fa-cog text-white"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">System Settings</p>
                                <p class="text-sm text-gray-600">Configure system</p>
                            </div>
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        });

        // Auto-refresh dashboard data every 30 seconds
        setInterval(function() {
            // In a real application, you would fetch updated data via AJAX
            console.log('Refreshing dashboard data...');
        }, 30000);

        // Add smooth scrolling and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
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
