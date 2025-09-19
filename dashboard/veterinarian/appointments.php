<?php
require_once '../../middleware/auth.php';
requireRole('veterinarian');

$user = a();
$user_id = $user['user_id'];

// Get veterinarian ID
$db = new Database();
$conn = $db->getConnection();

$vet_result = $conn->query("SELECT vet_id FROM veterinarians WHERE user_id = $user_id");
$vet_id = $vet_result->fetch_assoc()['vet_id'];

$message = '';
$error = '';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    switch ($action) {
        case 'confirm':
            $stmt = $conn->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ? AND vet_id = ?");
            $stmt->bind_param("ii", $appointment_id, $vet_id);
            if ($stmt->execute()) {
                $message = "Appointment confirmed successfully!";
                
                // Get appointment details for notification
                $apt_details = $conn->query("
                    SELECT a.appointment_date, p.name as pet_name, u.user_id, u.name as owner_name, u.email
                    FROM appointments a
                    JOIN pets p ON a.pet_id = p.pet_id
                    JOIN users u ON a.owner_id = u.user_id
                    WHERE a.appointment_id = $appointment_id
                ")->fetch_assoc();
                
                if ($apt_details) {
                    send_notification($apt_details['user_id'], 'Appointment Confirmed', 
                        "Your appointment for {$apt_details['pet_name']} on " . format_date($apt_details['appointment_date'], 'M j, g:i A') . " has been confirmed.");
                }
            }
            break;
            
        case 'cancel':
            $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND vet_id = ?");
            $stmt->bind_param("ii", $appointment_id, $vet_id);
            if ($stmt->execute()) {
                $message = "Appointment cancelled successfully!";
            }
            break;
            
        case 'complete':
            $stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ? AND vet_id = ?");
            $stmt->bind_param("ii", $appointment_id, $vet_id);
            if ($stmt->execute()) {
                $message = "Appointment marked as completed!";
            }
            break;
    }
}

// Get appointments with filters
$filter = $_GET['filter'] ?? 'all';
$date_filter = $_GET['date'] ?? '';

$where_conditions = ["a.vet_id = $vet_id"];

if ($filter === 'pending') {
    $where_conditions[] = "a.status = 'pending'";
} elseif ($filter === 'confirmed') {
    $where_conditions[] = "a.status = 'confirmed'";
} elseif ($filter === 'today') {
    $where_conditions[] = "DATE(a.appointment_date) = CURDATE()";
} elseif ($filter === 'upcoming') {
    $where_conditions[] = "a.appointment_date >= NOW() AND a.status IN ('pending', 'confirmed')";
}

if ($date_filter === 'today') {
    $where_conditions[] = "DATE(a.appointment_date) = CURDATE()";
} elseif ($date_filter === 'week') {
    $where_conditions[] = "WEEK(a.appointment_date) = WEEK(NOW()) AND YEAR(a.appointment_date) = YEAR(NOW())";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$appointments = $conn->query("
    SELECT a.appointment_id, a.appointment_date, a.reason, a.status, a.notes,
           p.pet_id, p.name as pet_name, p.species, p.breed, p.age,
           u.name as owner_name, u.phone as owner_phone, u.email as owner_email
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users u ON a.owner_id = u.user_id
    $where_clause
    ORDER BY a.appointment_date ASC
")->fetch_all(MYSQLI_ASSOC);

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - FurShield Veterinarian</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
                        <h2 class="text-2xl font-bold text-gray-800">Appointments</h2>
                        <p class="text-gray-600">Manage your appointment schedule</p>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-medium text-gray-700">Filter:</span>
                            <a href="appointments.php" class="px-3 py-1 rounded-full text-sm <?php echo $filter === 'all' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                All
                            </a>
                            <a href="appointments.php?filter=pending" class="px-3 py-1 rounded-full text-sm <?php echo $filter === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                Pending
                            </a>
                            <a href="appointments.php?filter=confirmed" class="px-3 py-1 rounded-full text-sm <?php echo $filter === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                Confirmed
                            </a>
                            <a href="appointments.php?filter=today" class="px-3 py-1 rounded-full text-sm <?php echo $filter === 'today' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                Today
                            </a>
                            <a href="appointments.php?filter=upcoming" class="px-3 py-1 rounded-full text-sm <?php echo $filter === 'upcoming' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                Upcoming
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Appointments List -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <?php if (empty($appointments)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-600 mb-2">No appointments found</h3>
                        <p class="text-gray-500">No appointments match your current filter.</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($appointments as $appointment): ?>
                        <div class="p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-paw text-green-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($appointment['pet_name']); ?></h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($appointment['species']); ?>
                                            <?php if ($appointment['breed']): ?>
                                            • <?php echo htmlspecialchars($appointment['breed']); ?>
                                            <?php endif; ?>
                                            <?php if ($appointment['age']): ?>
                                            • <?php echo $appointment['age']; ?> years old
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-sm text-gray-600">Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?></p>
                                        <?php if ($appointment['owner_phone']): ?>
                                        <p class="text-sm text-gray-500">
                                            <i class="fas fa-phone mr-1"></i>
                                            <?php echo htmlspecialchars($appointment['owner_phone']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <p class="text-lg font-medium text-gray-900"><?php echo format_date($appointment['appointment_date'], 'M j, Y'); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo format_date($appointment['appointment_date'], 'g:i A'); ?></p>
                                    <span class="inline-block mt-2 px-3 py-1 text-sm rounded-full <?php 
                                        echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 
                                             ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                              ($appointment['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')); 
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($appointment['reason']): ?>
                            <div class="mt-4 ml-16">
                                <p class="text-sm text-gray-700">
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($appointment['notes']): ?>
                            <div class="mt-2 ml-16">
                                <p class="text-sm text-gray-600">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-4 ml-16 flex items-center space-x-3">
                                <?php if ($appointment['status'] === 'pending'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="confirm">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm">
                                        <i class="fas fa-check mr-1"></i>Confirm
                                    </button>
                                </form>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm">
                                        <i class="fas fa-times mr-1"></i>Cancel
                                    </button>
                                </form>
                                
                                <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                        <i class="fas fa-check-circle mr-1"></i>Mark Complete
                                    </button>
                                </form>
                                
                                <a href="treatments.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors text-sm">
                                    <i class="fas fa-notes-medical mr-1"></i>Add Treatment
                                </a>
                                <?php endif; ?>
                                
                                <a href="patients.php?id=<?php echo $appointment['pet_id']; ?>" class="text-green-600 hover:text-green-800 text-sm">
                                    View Patient Record
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
