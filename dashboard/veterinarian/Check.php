<?php
// Ensure no output is sent before headers
ob_start();

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

// Check if profile query returned valid data
if (!$vet_profile) {
    // Log error and display a user-friendly message
    error_log("Failed to fetch veterinarian profile for user_id: $user_id");
    die("Error: Could not fetch profile. Please contact support.");
}

$vet_id = $vet_profile['vet_id'];

// Required fields to check
$required_fields = [
    'experience_years' => $vet_profile['experience_years'],
    'consultation_fee' => $vet_profile['consultation_fee'],
    'clinic_name' => $vet_profile['clinic_name'],
    'clinic_address' => $vet_profile['clinic_address']
];

// Check if any required field is empty or null
$missing_fields = false;
foreach ($required_fields as $field => $value) {
    if (empty($value)) {
        $missing_fields = true;
        break;
    }
}

// Debug: Log the state of missing fields and current script
error_log("Missing fields: " . ($missing_fields ? 'true' : 'false') . ", Current script: " . basename($_SERVER['PHP_SELF']));

// Check if the current page is not check.php and there are missing fields
$current_script = basename($_SERVER['PHP_SELF']);
if ($missing_fields && $current_script !== 'check.php') {
    header("Location: check.php");
    ob_end_flush();
    exit;
}

// Handle form submission (only if on check.php)
if ($current_script === 'check.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $experience_years = intval($_POST['experience_years'] ?? 0);
    $consultation_fee = floatval($_POST['consultation_fee'] ?? 0.00);
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $clinic_address = trim($_POST['clinic_address'] ?? '');

    // Basic validation
    if ($experience_years < 0) {
        $error = "Years of experience cannot be negative.";
    } elseif ($consultation_fee < 0) {
        $error = "Consultation fee cannot be negative.";
    } elseif (empty($clinic_name)) {
        $error = "Clinic name is required.";
    } elseif (empty($clinic_address)) {
        $error = "Clinic address is required.";
    } elseif (preg_match('/^\d+$/', $clinic_address)) {
        // Check if clinic_address is purely numeric (optional, adjust based on requirements)
        $error = "Clinic address cannot be purely numeric. Please provide a valid address.";
    } else {
        // Update query using prepared statement
        $stmt = $conn->prepare("
            UPDATE veterinarians 
            SET experience_years = ?, consultation_fee = ?, clinic_name = ?, clinic_address = ?
            WHERE vet_id = ?
        ");
        // Fix: Use 's' for clinic_address instead of 'd'
        $stmt->bind_param("idssi", $experience_years, $consultation_fee, $clinic_name, $clinic_address, $vet_id);
        
        if ($stmt->execute()) {
            // Redirect to index.php after successful update
            header("Location: index.php");
            ob_end_flush();
            exit;
        } else {
            $error = "Failed to update profile: " . $stmt->error;
            error_log("Database update error: " . $stmt->error);
        }
        
        $stmt->close();
    }
}

$db->closeConnection();

// Clear output buffer before rendering HTML
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-md w-full">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-800">Complete Your Veterinarian Profile</h2>
            <p class="text-gray-600 mt-2">Please fill in the required information to access your dashboard.</p>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Years of Experience</label>
                <input type="number" name="experience_years" min="0" value="<?php echo htmlspecialchars($vet_profile['experience_years'] ?? ''); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Consultation Fee</label>
                <input type="number" name="consultation_fee" step="0.01" min="0" value="<?php echo htmlspecialchars($vet_profile['consultation_fee'] ?? ''); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Clinic Name</label>
                <input type="text" name="clinic_name" value="<?php echo htmlspecialchars($vet_profile['clinic_name'] ?? ''); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Clinic Address</label>
                <textarea name="clinic_address" rows="3" 
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" required><?php echo htmlspecialchars($vet_profile['clinic_address'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg font-medium hover:bg-green-700 transition-colors">
                Save and Continue to Dashboard
            </button>
        </form>
    </div>
</body>
</html>