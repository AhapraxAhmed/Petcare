<?php
require_once '../../middleware/auth.php';

// Authenticate user
requireRole('shelter');

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Shelter User';

// Initialize database
$db = new Database();
$conn = $db->getConnection();

// Handle add care log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $pet_id = $_POST['pet_id'] ?? null;
    $visit_type = $_POST['visit_type'] ?? 'checkup';
    $notes = $_POST['notes'] ?? '';
    $visit_date = $_POST['visit_date'] ?? date('Y-m-d'); // Default to today if not provided
    
    if ($pet_id && $visit_type) {
        $query = "INSERT INTO health_records (pet_id, shelter_id, visit_date, visit_type, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            die('<div class="bg-red-100 text-red-700 p-4 rounded">Database error. Please try again later.</div>');
        }
        $stmt->bind_param("iisss", $pet_id, $user_id, $visit_date, $visit_type, $notes);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            die('<div class="bg-red-100 text-red-700 p-4 rounded">Database query failed. Please try again later.</div>');
        }
        $stmt->close();
    }
}

// Get all pets for dropdown
$pet_query = "SELECT pet_id, name FROM pets WHERE shelter_id = ?";
$stmt = $conn->prepare($pet_query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database error. Please try again later.</div>');
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database query failed. Please try again later.</div>');
}
$pets = $stmt->get_result();
$stmt->close();

// Get care logs (health records)
$logs_query = "SELECT hr.*, p.name AS pet_name 
               FROM health_records hr 
               JOIN pets p ON hr.pet_id = p.pet_id 
               WHERE hr.shelter_id = ? 
               ORDER BY hr.created_at DESC";
$stmt = $conn->prepare($logs_query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database error. Please try again later.</div>');
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database query failed. Please try again later.</div>');
}
$care_logs = $stmt->get_result();
$stmt->close();

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Care Logs - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'shelter-primary': '#8B5CF6',
                        'shelter-secondary': '#A78BFA',
                        'shelter-accent': '#C4B5FD'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-slate-50 via-indigo-50/30 to-slate-100">
    <div class="md:p-8">
        <div class="max-w-full mx-auto h-[100vh] md:h-[calc(95vh-3rem)]">
            <div class="flex h-full bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/50 overflow-hidden animate-scale-in">
                <?php include "sidebar.php"; ?>
                
                <div class="flex-1 p-8 overflow-y-auto">
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Care Logs</h1>
                        <p class="text-gray-600">Track pet care activities</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Log New Care Activity</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">Pet</label>
                                    <select name="pet_id" class="w-full p-2 border rounded-lg" required>
                                        <option value="">Select a pet</option>
                                        <?php while ($pet = $pets->fetch_assoc()): ?>
                                            <option value="<?php echo $pet['pet_id']; ?>"><?php echo htmlspecialchars($pet['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">Activity Type</label>
                                    <select name="visit_type" class="w-full p-2 border rounded-lg" required>
                                        <option value="checkup">Checkup</option>
                                        <option value="vaccination">Vaccination</option>
                                        <option value="treatment">Treatment</option>
                                        <option value="emergency">Emergency</option>
                                        <option value="surgery">Surgery</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">Visit Date</label>
                                    <input type="date" name="visit_date" class="w-full p-2 border rounded-lg" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 font-medium mb-1">Notes</label>
                                    <textarea name="notes" class="w-full p-2 border rounded-lg" rows="4"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="bg-shelter-primary text-white px-4 py-2 rounded-lg hover:bg-shelter-secondary">
                                Log Activity
                            </button>
                        </form>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Care Log History</h3>
                        <div class="space-y-3">
                            <?php if ($care_logs->num_rows > 0): ?>
                                <?php while ($log = $care_logs->fetch_assoc()): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($log['pet_name']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo ucfirst(htmlspecialchars($log['visit_type'])); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($log['notes'] ?? 'No notes'); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-gray-600"><?php echo date('M d, Y', strtotime($log['visit_date'])); ?></p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">No care logs available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.transform');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>