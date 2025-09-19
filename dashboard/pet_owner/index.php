<?php
require_once '../../middleware/auth.php';
requireRole('pet_owner');

$user = a();
$user_id = $user['user_id'];

// Get pet owner's data
$db = new Database();
$conn = $db->getConnection();

// Get pets count
$pets_result = $conn->query("SELECT COUNT(*) as count FROM pets WHERE owner_id = $user_id");
$total_pets = $pets_result->fetch_assoc()['count'];

// Get upcoming appointments
$appointments_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM appointments a 
    WHERE a.owner_id = $user_id 
    AND a.appointment_date >= NOW() 
    AND a.status IN ('pending', 'confirmed')
");
$upcoming_appointments = $appointments_result->fetch_assoc()['count'];

// Get recent health records
$health_records_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM health_records hr 
    JOIN pets p ON hr.pet_id = p.pet_id 
    WHERE p.owner_id = $user_id 
    AND hr.visit_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$recent_health_records = $health_records_result->fetch_assoc()['count'];

// Get notifications count
$notifications_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = $user_id AND is_read = 0
");
$unread_notifications = $notifications_result->fetch_assoc()['count'];

// Get recent pets
$recent_pets = $conn->query("
    SELECT pet_id, name, species, breed, age, profile_image, created_at
    FROM pets 
    WHERE owner_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 4
")->fetch_all(MYSQLI_ASSOC);

// Get upcoming appointments details
$upcoming_appointments_details = $conn->query("
    SELECT a.appointment_id, a.appointment_date, a.reason, a.status,
           p.name as pet_name, p.species,
           u.name as vet_name
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN veterinarians v ON a.vet_id = v.vet_id
    JOIN users u ON v.user_id = u.user_id
    WHERE a.owner_id = $user_id 
    AND a.appointment_date >= NOW()
    AND a.status IN ('pending', 'confirmed')
    ORDER BY a.appointment_date ASC
    LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

// Get recent notifications
$recent_notifications = $conn->query("
    SELECT notification_id, title, message, type, created_at, is_read
    FROM notifications 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Owner Dashboard - FurShield</title>
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
        
        .floating-animation {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-in;
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
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                        <p class="text-gray-600">Manage your pets and their care</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                    <div class="relative group">
                        <button class="text-gray-500 hover:text-gray-700 relative">
                            <i class="fas fa-bell text-xl"></i>
                            <?php if ($unread_notifications > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $unread_notifications; ?>
                            </span>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown -->
                        <div class="absolute right-0 mt-2 w-80 bg-white shadow-lg rounded-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="p-4">
                                <h4 class="font-medium text-gray-800 mb-3">Notifications</h4>
                                <?php if (empty($recent_notifications)): ?>
                                    <p class="text-gray-500 text-sm text-center">No new notifications</p>
                                <?php else: ?>
                                    <div class="space-y-3 max-h-64 overflow-y-auto">
                                        <?php foreach ($recent_notifications as $notification): ?>
                                        <div class="flex items-start <?php echo $notification['is_read'] ? 'opacity-60' : ''; ?>">
                                            <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas <?php 
                                                    echo $notification['type'] === 'appointment' ? 'fa-calendar' : 
                                                        ($notification['type'] === 'vaccination' ? 'fa-syringe' : 'fa-bell'); 
                                                ?> text-blue-600 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <span class="text-xs text-gray-400"><?php echo format_date($notification['created_at'], 'M j, g:i A'); ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?php echo date('l, F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-1 p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">My Pets</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $total_pets; ?></p>
                                <p class="text-sm text-blue-600 mt-1">
                                    <a href="pets.php" class="hover:underline">Manage pets →</a>
                                </p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-paw text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Upcoming Appointments</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $upcoming_appointments; ?></p>
                                <p class="text-sm text-green-600 mt-1">
                                    <a href="appointments.php" class="hover:underline">View schedule →</a>
                                </p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-calendar-check text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Recent Health Records</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $recent_health_records; ?></p>
                                <p class="text-sm text-purple-600 mt-1">Last 30 days</p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-heartbeat text-2xl text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Notifications</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $unread_notifications; ?></p>
                                <p class="text-sm text-orange-600 mt-1">Unread messages</p>
                            </div>
                            <div class="bg-orange-100 p-3 rounded-full">
                                <i class="fas fa-bell text-2xl text-orange-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- My Pets -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">My Pets</h3>
                            <a href="pets.php?action=add" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                <i class="fas fa-plus mr-2"></i>Add Pet
                            </a>
                        </div>
                        
                        <?php if (empty($recent_pets)): ?>
                        <div class="text-center py-12">
                            <div class="floating-animation mb-4">
                                <i class="fas fa-paw text-6xl text-gray-300"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">No pets yet</h4>
                            <p class="text-gray-500 mb-4">Add your first pet to get started with FurShield</p>
                            <a href="pets.php?action=add" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Add Your First Pet
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($recent_pets as $pet): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                                        <?php if ($pet['profile_image']): ?>
                                        <img src="../../uploads/<?php echo $pet['profile_image']; ?>" alt="<?php echo htmlspecialchars($pet['name']); ?>" class="w-12 h-12 rounded-full object-cover">
                                        <?php else: ?>
                                        <i class="fas fa-paw text-gray-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($pet['name']); ?></h4>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($pet['species']); ?> • <?php echo $pet['age']; ?> years old</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500">Added <?php echo format_date($pet['created_at'], 'M j'); ?></span>
                                    <a href="pets.php?id=<?php echo $pet['pet_id']; ?>" class="text-blue-600 text-sm hover:underline">View Details</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($recent_pets) >= 4): ?>
                        <div class="mt-4 text-center">
                            <a href="pets.php" class="text-blue-600 hover:underline">View all pets →</a>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Upcoming Appointments -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.5s;">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">Upcoming Appointments</h3>
                            <a href="appointments.php?action=book" class="text-blue-600 text-sm hover:underline">Book New</a>
                        </div>
                        
                        <?php if (empty($upcoming_appointments_details)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-plus text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 text-sm">No upcoming appointments</p>
                            <a href="appointments.php?action=book" class="text-blue-600 text-sm hover:underline mt-2 inline-block">Book an appointment</a>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_appointments_details as $appointment): ?>
                            <div class="border-l-4 border-blue-500 pl-4 py-2">
                                <div class="flex items-center justify-between mb-1">
                                    <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($appointment['pet_name']); ?></h4>
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; 
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">Dr. <?php echo htmlspecialchars($appointment['vet_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo format_date($appointment['appointment_date'], 'M j, g:i A'); ?></p>
                                <?php if ($appointment['reason']): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($appointment['reason']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="appointments.php" class="text-blue-600 text-sm hover:underline">View all appointments →</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications and Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Notifications -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.6s;">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">Recent Notifications</h3>
                        
                        <?php if (empty($recent_notifications)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-bell-slash text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 text-sm">No notifications yet</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_notifications as $notification): ?>
                            <div class="flex items-start <?php echo $notification['is_read'] ? 'opacity-60' : ''; ?>">
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas <?php 
                                        echo $notification['type'] === 'appointment' ? 'fa-calendar' : 
                                             ($notification['type'] === 'vaccination' ? 'fa-syringe' : 'fa-bell'); 
                                    ?> text-blue-600 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <span class="text-xs text-gray-500"><?php echo format_date($notification['created_at'], 'M j, g:i A'); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.7s;">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">Quick Actions</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <a href="pets.php?action=add" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <div class="bg-blue-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-plus text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Add New Pet</p>
                                    <p class="text-sm text-gray-600">Register a new pet</p>
                                </div>
                            </a>
                            
                            <a href="appointments.php?action=book" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                <div class="bg-green-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-calendar-plus text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Book Appointment</p>
                                    <p class="text-sm text-gray-600">Schedule vet visit</p>
                                </div>
                            </a>
                            
                            <a href="shop.php" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                                <div class="bg-purple-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-shopping-cart text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Shop Products</p>
                                    <p class="text-sm text-gray-600">Browse pet supplies</p>
                                </div>
                            </a>
                            
                            <a href="care-tips.php" class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                                <div class="bg-orange-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-lightbulb text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Care Tips</p>
                                    <p class="text-sm text-gray-600">Expert advice</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // In a real application, you would fetch updated notifications via AJAX
            console.log('Checking for new notifications...');
        }, 30000);

        // Add smooth animations on load
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
