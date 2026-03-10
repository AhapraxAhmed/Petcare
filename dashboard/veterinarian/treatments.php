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

// Get appointment ID from query parameter
$appointment_id = $_GET['appointment_id'] ?? null;

if (!$appointment_id) {
    die("Invalid appointment ID");
}

// Verify the appointment belongs to the veterinarian
$appointment = $conn->query("
    SELECT a.appointment_id, p.name as pet_name, u.name as owner_name
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users u ON a.owner_id = u.user_id
    WHERE a.appointment_id = $appointment_id AND a.vet_id = $vet_id
")->fetch_assoc();

if (!$appointment) {
    die("Appointment not found or you don't have access to it");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle treatment submission (e.g., save to database)
    $treatment_details = $_POST['treatment_details'] ?? '';
    if ($treatment_details) {
        $stmt = $conn->prepare("INSERT INTO treatments (appointment_id, vet_id, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $appointment_id, $vet_id, $treatment_details);
        if ($stmt->execute()) {
            $message = "Treatment added successfully!";
        } else {
            $message = "Error adding treatment.";
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
    <title>Add Treatment - FurShield Veterinarian</title>
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
        <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Add Treatment</h2>
                        <p class="text-gray-600">
                            For <?php echo htmlspecialchars($appointment['pet_name']); ?> 
                            (Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?>)
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                            <?php echo date('l, F j, Y, g:i A'); ?>
                        </div>
                    </div>
                </div>
            </header>


        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded-lg shadow-sm">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Treatment Details</label>
                <textarea name="treatment_details" class="mt-1 block w-full border-gray-300 rounded-lg" rows="4" required></textarea>
            </div>
            <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                <i class="fas fa-save mr-1"></i>Save Treatment
            </button>
        </form>
    </div>
</body>
</html>