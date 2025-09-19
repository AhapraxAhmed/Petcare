<?php
require_once '../../middleware/auth.php';
requireRole('veterinarian');

$user = a();
$user_id = $user['user_id'];

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch veterinarian profile
$vet_profile = $conn->query("
    SELECT v.*, u.name, u.email, u.phone, u.address 
    FROM veterinarians v 
    JOIN users u ON v.user_id = u.user_id 
    WHERE v.user_id = $user_id
")->fetch_assoc();

$required_fields = ['experience_years', 'consultation_fee', 'clinic_name', 'clinic_address'];
$missing_fields = false;

foreach ($required_fields as $field) {
    if (empty($vet_profile[$field])) {
        $missing_fields = true;
        break;
    }
}

// Redirect to check.php ONLY if missing fields
if ($missing_fields) {
    header("Location: check.php");
    exit;
}

// Now continue loading dashboard (all your code below)
$vet_id = $vet_profile['vet_id'];

// Get statistics
$stats = [];

// Today's appointments
$today_appointments = $conn->query("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE vet_id = $vet_id 
    AND DATE(appointment_date) = CURDATE()
    AND status IN ('confirmed', 'pending')
")->fetch_assoc()['count'];

// This week's appointments
$week_appointments = $conn->query("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE vet_id = $vet_id 
    AND WEEK(appointment_date) = WEEK(NOW())
    AND YEAR(appointment_date) = YEAR(NOW())
")->fetch_assoc()['count'];

// Total patients
$total_patients = $conn->query("
    SELECT COUNT(DISTINCT p.pet_id) as count 
    FROM appointments a 
    JOIN pets p ON a.pet_id = p.pet_id 
    WHERE a.vet_id = $vet_id
")->fetch_assoc()['count'];

// Pending appointments
$pending_appointments = $conn->query("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE vet_id = $vet_id 
    AND status = 'pending'
")->fetch_assoc()['count'];

// Today's schedule
$todays_schedule = $conn->query("
    SELECT a.appointment_id, a.appointment_date, a.reason, a.status,
           p.name as pet_name, p.species, p.breed,
           u.name as owner_name, u.phone as owner_phone
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users u ON a.owner_id = u.user_id
    WHERE a.vet_id = $vet_id 
    AND DATE(a.appointment_date) = CURDATE()
    ORDER BY a.appointment_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Recent patients
$recent_patients = $conn->query("
    SELECT DISTINCT p.pet_id, p.name as pet_name, p.species, p.breed, p.age,
           u.name as owner_name, u.phone as owner_phone,
           MAX(a.appointment_date) as last_visit
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users u ON a.owner_id = u.user_id
    WHERE a.vet_id = $vet_id
    AND a.status = 'completed'
    GROUP BY p.pet_id
    ORDER BY last_visit DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Upcoming appointments
$upcoming_appointments = $conn->query("
    SELECT a.appointment_id, a.appointment_date, a.reason, a.status,
           p.name as pet_name, p.species,
           u.name as owner_name
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users u ON a.owner_id = u.user_id
    WHERE a.vet_id = $vet_id 
    AND a.appointment_date > NOW()
    AND a.status IN ('pending', 'confirmed')
    ORDER BY a.appointment_date ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinarian Dashboard - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .status-badge {
            transition: all 0.2s ease;
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
                        <h2 class="text-2xl font-bold text-gray-800">Good morning, Dr. <?php echo htmlspecialchars($user['name']); ?>!</h2>
                        <p class="text-gray-600">You have <?php echo $today_appointments; ?> appointments today</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                            <?php echo date('l, F j, Y'); ?>
                        </div>
                        <div class="relative">
                            <button class="text-gray-500 hover:text-gray-700 relative">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($pending_appointments > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                    <?php echo $pending_appointments; ?>
                                </span>
                                <?php endif; ?>
                            </button>
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
                                <p class="text-sm font-medium text-gray-600">Today's Appointments</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $today_appointments; ?></p>
                                <p class="text-sm text-green-600 mt-1">
                                    <a href="appointments.php?date=today" class="hover:underline">View schedule →</a>
                                </p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-calendar-day text-2xl text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">This Week</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $week_appointments; ?></p>
                                <p class="text-sm text-blue-600 mt-1">Total appointments</p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-calendar-week text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Patients</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $total_patients; ?></p>
                                <p class="text-sm text-purple-600 mt-1">
                                    <a href="patients.php" class="hover:underline">View patients →</a>
                                </p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-paw text-2xl text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Pending Requests</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $pending_appointments; ?></p>
                                <p class="text-sm text-orange-600 mt-1">Need approval</p>
                            </div>
                            <div class="bg-orange-100 p-3 rounded-full">
                                <i class="fas fa-clock text-2xl text-orange-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Today's Schedule -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">Today's Schedule</h3>
                            <a href="appointments.php" class="text-green-600 text-sm hover:underline">View all</a>
                        </div>
                        
                        <?php if (empty($todays_schedule)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-calendar-check text-6xl text-gray-300 mb-4"></i>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">No appointments today</h4>
                            <p class="text-gray-500">Enjoy your free day!</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($todays_schedule as $appointment): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-paw text-green-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($appointment['pet_name']); ?></h4>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['species']); ?> • <?php echo htmlspecialchars($appointment['owner_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-900"><?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?></p>
                                        <span class="px-2 py-1 text-xs rounded-full status-badge <?php 
                                            echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 
                                                 ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); 
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($appointment['reason']): ?>
                                <p class="text-sm text-gray-600 ml-13"><?php echo htmlspecialchars($appointment['reason']); ?></p>
                                <?php endif; ?>
                                <div class="flex items-center justify-between mt-3">
                                    <span class="text-xs text-gray-500">
                                        <i class="fas fa-phone mr-1"></i>
                                        <?php echo htmlspecialchars($appointment['owner_phone'] ?: 'No phone'); ?>
                                    </span>
                                    <div class="flex space-x-2">
                                        <?php if ($appointment['status'] === 'pending'): ?>
                                        <button class="text-green-600 text-sm hover:underline">Confirm</button>
                                        <button class="text-red-600 text-sm hover:underline">Decline</button>
                                        <?php else: ?>
                                        <a href="treatments.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="text-blue-600 text-sm hover:underline">Add Treatment</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Upcoming Appointments -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.5s;">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">Upcoming Appointments</h3>
                            <a href="appointments.php" class="text-green-600 text-sm hover:underline">View all</a>
                        </div>
                        
                        <?php if (empty($upcoming_appointments)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-plus text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 text-sm">No upcoming appointments</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                            <div class="border-l-4 border-green-500 pl-4 py-2">
                                <div class="flex items-center justify-between mb-1">
                                    <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($appointment['pet_name']); ?></h4>
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; 
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['owner_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo format_date($appointment['appointment_date'], 'M j, g:i A'); ?></p>
                                <?php if ($appointment['reason']): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($appointment['reason']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Patients and Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Patients -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.6s;">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Patients</h3>
                            <a href="patients.php" class="text-green-600 text-sm hover:underline">View all patients</a>
                        </div>
                        
                        <?php if (empty($recent_patients)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-paw text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 text-sm">No patients yet</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_patients as $patient): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-paw text-gray-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($patient['pet_name']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($patient['species']); ?> • <?php echo htmlspecialchars($patient['owner_name']); ?></p>
                                        <p class="text-xs text-gray-500">Last visit: <?php echo format_date($patient['last_visit'], 'M j'); ?></p>
                                    </div>
                                </div>
                                <a href="patients.php?id=<?php echo $patient['pet_id']; ?>" class="text-green-600 text-sm hover:underline">View</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.7s;">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">Quick Actions</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <a href="appointments.php?filter=pending" class="flex items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                                <div class="bg-yellow-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Review Pending</p>
                                    <p class="text-sm text-gray-600"><?php echo $pending_appointments; ?> requests waiting</p>
                                </div>
                            </a>
                            
                            <a href="treatments.php?action=add" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                <div class="bg-green-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-notes-medical text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Add Treatment</p>
                                    <p class="text-sm text-gray-600">Log new treatment record</p>
                                </div>
                            </a>
                            
                            <a href="schedule.php" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <div class="bg-blue-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-calendar-alt text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Manage Schedule</p>
                                    <p class="text-sm text-gray-600">Set availability hours</p>
                                </div>
                            </a>
                            
                            <a href="profile.php" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                                <div class="bg-purple-500 p-2 rounded-lg mr-4">
                                    <i class="fas fa-user-md text-white"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Update Profile</p>
                                    <p class="text-sm text-gray-600">Clinic info & specialization</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Auto-refresh appointments every 60 seconds
        setInterval(function() {
            // In a real application, you would fetch updated appointment data via AJAX
            console.log('Checking for new appointments...');
        }, 60000);

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

        // Status badge hover effects
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>
