<?php
require_once(__DIR__ . "/../../config/config.php");
require "../../includes/functions.php";

// Check if user is logged in
if (!is_logged_in()) {
    redirect(APP_URL . "/auth/login.php");
}

$user = getCurrentUser();
$user_id = $_SESSION['user_id'];

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Get user's pets for appointment booking
$pets_query = "SELECT pet_id, name FROM pets WHERE owner_id = ?";
$pets_stmt = $conn->prepare($pets_query);
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$user_pets = $pets_result->fetch_all(MYSQLI_ASSOC);
$pets_stmt->close();

// Get available veterinarians
$vets_query = "SELECT v.vet_id, u.name, v.specialization, v.clinic_name, v.consultation_fee, v.available_hours 
               FROM veterinarians v 
               JOIN users u ON v.user_id = u.id 
               WHERE u.is_verified = 1";
$vets_stmt = $conn->prepare($vets_query);
$vets_stmt->execute();
$vets_result = $vets_stmt->get_result();
$veterinarians = $vets_result->fetch_all(MYSQLI_ASSOC);
$vets_stmt->close();

// Handle appointment booking
if ($_POST && isset($_POST['book_appointment'])) {
    $pet_id = $_POST['pet_id'];
    $vet_id = $_POST['vet_id'];
    $appointment_date = $_POST['appointment_date']; // Format: YYYY-MM-DD
    $appointment_time = $_POST['appointment_time']; // Format: HH:MM
    $full_datetime = $appointment_date . ' ' . $appointment_time . ':00';
    $reason = $_POST['reason'];

    $insert_query = "INSERT INTO appointments (pet_id, owner_id, vet_id, appointment_date, notes, status) 
                     VALUES (?, ?, ?, ?, ?, 'pending')";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iiiss", $pet_id, $user_id, $vet_id, $full_datetime, $reason);

    if ($insert_stmt->execute()) {
        $insert_stmt->close();

        // Get vet's user_id for notification
        $v_stmt = $conn->prepare("SELECT user_id FROM veterinarians WHERE vet_id = ?");
        $v_stmt->bind_param("i", $vet_id);
        $v_stmt->execute();
        $v_user = $v_stmt->get_result()->fetch_assoc();
        if ($v_user) {
            send_notification($v_user['user_id'], 'New Appointment', 'A new appointment has been booked for ' . $full_datetime);
        }
        $v_stmt->close();

        $_SESSION['success_message'] = "Appointment booked successfully!";
        header("Location: appointments.php");
        exit;
    }
    else {
        $insert_stmt->close();
        $_SESSION['error_message'] = "Failed to book appointment. Please try again.";
        header("Location: appointments.php");
        exit;
    }
}

// Handle appointment cancellation
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $cancel_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Appointment cancelled successfully.";
    }
    else {
        $_SESSION['error_message'] = "Failed to cancel appointment.";
    }
    $stmt->close();
    header("Location: appointments.php");
    exit;
}

// Handle appointment deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND owner_id = ? AND status IN ('pending', 'cancelled')");
    $stmt->bind_param("ii", $delete_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Appointment deleted successfully.";
    }
    else {
        $_SESSION['error_message'] = "Failed to delete appointment.";
    }
    $stmt->close();
    header("Location: appointments.php");
    exit;
}

// Handle appointment edit
if ($_POST && isset($_POST['edit_appointment'])) {
    $app_id = intval($_POST['appointment_id']);
    $pet_id = $_POST['pet_id'];
    $vet_id = $_POST['vet_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $full_datetime = $appointment_date . ' ' . $appointment_time . ':00';
    $reason = $_POST['reason'];

    $update_query = "UPDATE appointments SET pet_id = ?, vet_id = ?, appointment_date = ?, notes = ? 
                     WHERE id = ? AND owner_id = ? AND status = 'pending'";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("iissii", $pet_id, $vet_id, $full_datetime, $reason, $app_id, $user_id);

    if ($update_stmt->execute()) {
        $update_stmt->close();
        $_SESSION['success_message'] = "Appointment updated successfully!";
    }
    else {
        $update_stmt->close();
        $_SESSION['error_message'] = "Failed to update appointment.";
    }
    header("Location: appointments.php");
    exit;
}

// Get user's appointments
$appointments_query = "SELECT a.*, p.name as pet_name, u.name as vet_name, v.clinic_name 
                      FROM appointments a 
                      JOIN pets p ON a.pet_id = p.pet_id 
                      JOIN veterinarians v ON a.vet_id = v.vet_id 
                      JOIN users u ON v.user_id = u.id 
                      WHERE a.owner_id = ? 
                      ORDER BY a.appointment_date DESC";
$appointments_stmt = $conn->prepare($appointments_query);
$appointments_stmt->bind_param("i", $user_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$appointments = $appointments_result->fetch_all(MYSQLI_ASSOC);
$appointments_stmt->close();

// Get unread notifications count for header
$notifications_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = $user_id AND is_read = 0
");
$unread_notifications = $notifications_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - FurShield</title>
    <link rel="icon" href="../../assets/images/favicon.png" type="image/x-icon">
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
    </style>
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
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">My Appointments</h2>
                        <p class="text-gray-600">Manage your pet appointments</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="text-gray-500 hover:text-gray-700 relative">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($unread_notifications > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                    <?php echo $unread_notifications; ?>
                                </span>
                                <?php
endif; ?>
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
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800 px-2">
                            <i class="fas fa-stethoscope mr-2 text-blue-500"></i>Find & Book a Specialist
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($veterinarians as $v): ?>
                            <?php $hours = json_decode($v['available_hours'], true); ?>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all group">
                                <div class="p-5 flex flex-col h-full">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-lg">
                                                <?= strtoupper(substr($v['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 leading-tight">Dr. <?= htmlspecialchars($v['name']) ?></h4>
                                                <p class="text-xs text-blue-600 font-medium"><?= htmlspecialchars($v['specialization']) ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-lg font-bold text-gray-900">$<?= number_format($v['consultation_fee'], 0) ?></span>
                                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Fee</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex-1 space-y-1.5 mb-5">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Weekly Schedule</p>
                                        <?php 
                                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                        foreach ($days as $day): 
                                            $h = $hours[$day] ?? 'Closed';
                                            $isOpen = ($h && strtolower($h) !== 'closed');
                                        ?>
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500 capitalize"><?= substr($day, 0, 3) ?></span>
                                                <span class="<?= $isOpen ? 'text-gray-700 font-medium' : 'text-gray-300' ?>">
                                                    <?= $isOpen ? str_replace('-', ' - ', $h) : 'Closed' ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <button onclick="openBookingModal(<?= htmlspecialchars(json_encode($v)) ?>)" 
                                            class="w-full py-2.5 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">
                                        Book Consultation
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="bookingForm"></div>
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div id="successMessage" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 transition-opacity duration-500 opacity-100" role="alert">
                        <p><?php echo htmlspecialchars($_SESSION['success_message']);
    unset($_SESSION['success_message']); ?></p>
                    </div>
                <?php
endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 animate-fade-in" role="alert">
                        <p><?php echo htmlspecialchars($_SESSION['error_message']);
    unset($_SESSION['error_message']); ?></p>
                    </div>
                <?php
endif; ?>
                
                <div id="bookingModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                    <div class="bg-white rounded-[2rem] max-w-lg w-full p-8 shadow-2xl overflow-hidden animate-scale-in border border-white/20">
                        <div class="flex items-center justify-between mb-8">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-calendar-plus text-blue-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800">New Appointment</h3>
                                    <p class="text-xs text-gray-500" id="booking_vet_name_display">Booking for Dr. Smith</p>
                                </div>
                            </div>
                            <button onclick="closeBookingModal()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-all">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form method="POST" action="appointments.php" class="space-y-5">
                            <input type="hidden" name="vet_id" id="book_vet_id">
                            
                            <div class="space-y-4 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">My Pet</label>
                                    <select name="pet_id" class="block w-full rounded-xl border-gray-200 text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all" required>
                                        <option value="">Which pet needs care?</option>
                                        <?php foreach ($user_pets as $pet): ?>
                                            <option value="<?php echo $pet['pet_id']; ?>">
                                                🐾 <?php echo htmlspecialchars($pet['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Appointment Date</label>
                                        <select name="appointment_date" id="book_appointment_date" class="block w-full rounded-xl border-gray-200 text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all" required onchange="updateTimeSlots('book')">
                                            <option value="">Loading...</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Available Time</label>
                                        <select name="appointment_time" id="book_appointment_time" class="block w-full rounded-xl border-gray-200 text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all" required disabled>
                                            <option value="">Select date...</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="book_availability_info" class="text-[10px] font-medium text-center"></div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2 px-2">Reason for Visit</label>
                                <textarea name="reason" class="block w-full rounded-2xl border-gray-200 text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all" rows="3" placeholder="Describe the symptoms or reason..."></textarea>
                            </div>

                            <button type="submit" name="book_appointment" class="w-full py-4 bg-blue-600 text-white rounded-2xl font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-200 flex items-center justify-center space-x-2">
                                <i class="fas fa-check-circle"></i>
                                <span>Confirm Booking</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Appointments List -->
                <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-list mr-2"></i>My Appointments</h3>
                    </div>
                    <?php if (empty($appointments)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 text-sm">No appointments found. Book your first appointment above!</p>
                        </div>
                    <?php
else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-600">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Pet</th>
                                        <th scope="col" class="px-6 py-3">Veterinarian</th>
                                        <th scope="col" class="px-6 py-3">Date & Time</th>
                                        <th scope="col" class="px-6 py-3">Status</th>
                                        <th scope="col" class="px-6 py-3">Reason</th>
                                        <th scope="col" class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($appointment['pet_name']); ?></td>
                                            <td class="px-6 py-4">
                                                Dr. <?php echo htmlspecialchars($appointment['vet_name']); ?><br>
                                                <span class="text-xs text-gray-500"><?php echo htmlspecialchars($appointment['clinic_name']); ?></span>
                                            </td>
                                            <td class="px-6 py-4"><?php echo date('M j, Y g:i A', strtotime($appointment['appointment_date'])); ?></td>
                                            <td class="px-6 py-4">
                                                <?php
        $status_class = '';
        switch ($appointment['status']) {
            case 'pending':
                $status_class = 'bg-yellow-100 text-yellow-800';
                break;
            case 'confirmed':
                $status_class = 'bg-green-100 text-green-800';
                break;
            case 'completed':
                $status_class = 'bg-blue-100 text-blue-800';
                break;
            case 'cancelled':
                $status_class = 'bg-red-100 text-red-800';
                break;
        }
?>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($appointment['notes']); ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center space-x-3">
                                                    <?php if ($appointment['status'] === 'pending'): ?>
                                                        <button onclick='openEditModal(<?php echo json_encode($appointment); ?>)' class="text-blue-600 hover:text-blue-900" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="?cancel_id=<?php echo $appointment['id']; ?>" onclick="return confirm('Discard this appointment?')" class="text-orange-600 hover:text-orange-900" title="Discard">
                                                            <i class="fas fa-times-circle"></i>
                                                        </a>
                                                    <?php
        endif; ?>
                                                    <a href="?delete_id=<?php echo $appointment['id']; ?>" onclick="return confirm('Permanently delete this record?')" class="text-red-600 hover:text-red-900" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php
endif; ?>
                </div>
            </main>
        </div>
    </div>
    </div>
    </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-8 shadow-2xl overflow-hidden animate-scale-in">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-800"><i class="fas fa-edit mr-2 text-blue-600"></i>Edit Appointment</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" action="appointments.php">
                <input type="hidden" name="appointment_id" id="edit_appointment_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Pet</label>
                        <select name="pet_id" id="edit_pet_id" class="block w-full rounded-lg p-2 border border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required>
                            <?php foreach ($user_pets as $pet): ?>
                                <option value="<?php echo $pet['pet_id']; ?>"><?php echo htmlspecialchars($pet['name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Veterinarian</label>
                        <select name="vet_id" id="edit_vet_id" class="block w-full rounded-lg p-2 border border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required onchange="updateVetAvailability('edit')">
                            <?php foreach ($veterinarians as $vet): ?>
                                <option value="<?php echo $vet['vet_id']; ?>">Dr. <?php echo htmlspecialchars($vet['name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Available Dates</label>
                        <select name="appointment_date" id="edit_appointment_date" class="block w-full rounded-lg p-2 border border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required onchange="updateTimeSlots('edit')">
                            <option value="">Select veterinarian first...</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Available Time Slots</label>
                        <select name="appointment_time" id="edit_appointment_time" class="block w-full rounded-lg p-2 border border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required disabled>
                            <option value="">Select date first...</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Visit</label>
                        <textarea name="reason" id="edit_reason" class="block w-full rounded-lg p-2 border border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" rows="3" required></textarea>
                    </div>
                </div>
                <div class="mt-8 flex justify-end space-x-4">
                    <button type="button" onclick="closeEditModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" name="edit_appointment" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-lg">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openBookingModal(vet) {
            document.getElementById('book_vet_id').value = vet.vet_id;
            document.getElementById('booking_vet_name_display').textContent = 'Booking for Dr. ' + vet.name;
            document.getElementById('bookingModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            updateVetAvailability('book');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        const globalAvailability = {};

        async function openEditModal(appointment) {
            const modal = document.getElementById('editModal');
            document.getElementById('edit_appointment_id').value = appointment.id;
            document.getElementById('edit_pet_id').value = appointment.pet_id;
            document.getElementById('edit_vet_id').value = appointment.vet_id;
            document.getElementById('edit_reason').value = appointment.notes;
            
            const dateStr = appointment.appointment_date.split(' ')[0];
            const timeStr = appointment.appointment_date.split(' ')[1].substring(0, 5);
            
            modal.classList.remove('hidden');
            
            // Populate dates and then select the right one
            await updateVetAvailability('edit');
            document.getElementById('edit_appointment_date').value = dateStr;
            updateTimeSlots('edit', timeStr);
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        async function updateVetAvailability(type) {
            const vetId = document.getElementById(type + '_vet_id').value;
            const dateSelect = document.getElementById(type + '_appointment_date');
            const timeSelect = document.getElementById(type + '_appointment_time');

            if (!vetId) {
                dateSelect.innerHTML = '<option value="">Select veterinarian first...</option>';
                dateSelect.disabled = true;
                return;
            }

            try {
                const response = await fetch('get_vet_availability.php?vet_id=' + vetId);
                const data = await response.json();

                if (data.success) {
                    globalAvailability[type] = data.available_hours;
                    
                    // Generate next 14 days
                    dateSelect.innerHTML = '<option value="">Choose a date...</option>';
                    const today = new Date();
                    let count = 0;
                    for(let i=0; i<30 && count < 14; i++) { // Check up to 30 days to find 14 working days
                        const d = new Date(today);
                        d.setDate(today.getDate() + i);
                        const dayNames = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
                        const dayName = dayNames[d.getDay()];
                        const range = data.available_hours[dayName];
                        
                        if (range && range.toLowerCase() !== 'closed' && range.trim() !== '' && range.includes('-')) {
                            const opt = document.createElement('option');
                            const val = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                            opt.value = val;
                            opt.textContent = d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
                            dateSelect.appendChild(opt);
                            count++;
                        }
                    }
                    dateSelect.disabled = false;
                    timeSelect.innerHTML = '<option value="">Select date first...</option>';
                    timeSelect.disabled = true;
                }
            } catch (err) {
                console.error('Failed to fetch availability:', err);
            }
        }

        function updateTimeSlots(type, selectTime = null) {
            const dateVal = document.getElementById(type + '_appointment_date').value;
            const timeSelect = document.getElementById(type + '_appointment_time');
            const infoText = document.getElementById(type + '_availability_info');

            if (!dateVal || !globalAvailability[type]) return;

            // Reliable day calculation from YYYY-MM-DD
            const [year, month, day] = dateVal.split('-').map(Number);
            const d = new Date(year, month - 1, day);
            const dayNames = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
            const dayName = dayNames[d.getDay()];
            const range = globalAvailability[type][dayName];

            if (!range || range.toLowerCase() === 'closed' || !range.includes('-')) {
                timeSelect.innerHTML = '<option value="">Doctor unavailable</option>';
                timeSelect.disabled = true;
                if (infoText) infoText.textContent = '❌ Closed';
                return;
            }

            if (infoText) infoText.textContent = '✅ Available: ' + range;

            // Generate slots
            const [startStr, endStr] = range.split('-');
            const slots = generateTimeSlots(startStr.trim(), endStr.trim());
            
            timeSelect.innerHTML = '';
            slots.forEach(slot => {
                const opt = document.createElement('option');
                opt.value = slot;
                opt.textContent = slot;
                if (selectTime && slot === selectTime) opt.selected = true;
                timeSelect.appendChild(opt);
            });
            timeSelect.disabled = false;
        }

        function generateTimeSlots(start, end) {
            const slots = [];
            let current = new Date("2000-01-01 " + start);
            const endTime = new Date("2000-01-01 " + end);

            while (current < endTime) {
                const timeStr = current.toTimeString().substring(0, 5);
                slots.push(timeStr);
                current.setMinutes(current.getMinutes() + 30); // 30 min slots
            }
            return slots;
        }

        // Add smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Success message fade out
            const msg = document.getElementById('successMessage');
            if(msg){
                setTimeout(() => {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>