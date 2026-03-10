<?php
require_once '../../middleware/auth.php';

// Authenticate user
requireRole('shelter');

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Shelter User';

// Initialize database
$db = new Database();
$conn = $db->getConnection();

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $application_id = $_POST['application_id'] ?? null;
    $status = $_POST['status'] ?? '';
    
    if ($status === 'approved') {
        // Get application details
        $query = "SELECT listing_id, applicant_id, pet_id FROM adoption_applications aa 
                  JOIN adoption_listings al ON aa.listing_id = al.listing_id 
                  WHERE aa.application_id = ? AND al.shelter_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $application_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();
        $stmt->close();

        if ($application) {
            // Update adoption listing status to adopted
            $query = "UPDATE adoption_listings SET status = 'adopted' WHERE listing_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $application['listing_id']);
            $stmt->execute();
            $stmt->close();

            // Update pet to assign owner and remove from shelter
            $query = "UPDATE pets SET owner_id = ?, shelter_id = NULL, is_adopted = 1 WHERE pet_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $application['applicant_id'], $application['pet_id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Update application status
    $query = "UPDATE adoption_applications SET status = ? WHERE application_id = ? AND EXISTS (
        SELECT 1 FROM adoption_listings al WHERE al.listing_id = adoption_applications.listing_id AND al.shelter_id = ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $status, $application_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Get all applications for this shelter
$query = "SELECT aa.*, p.name AS pet_name, p.species, u.name AS applicant_name, u.email AS applicant_email
          FROM adoption_applications aa
          JOIN adoption_listings al ON aa.listing_id = al.listing_id
          JOIN pets p ON al.pet_id = p.pet_id
          JOIN users u ON aa.applicant_id = u.user_id
          WHERE al.shelter_id = ?
          ORDER BY aa.submitted_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applications = $stmt->get_result();
$stmt->close();

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adoption Applications - FurShield</title>
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
    <div class="md:p-9">
        <div class="max-w-full mx-auto h-[100vh] md:h-[calc(95vh-3rem)]">
            <div class="flex h-full bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/50 overflow-hidden animate-scale-in">
                <?php include "sidebar.php"; ?>
                
                <div class="flex-1 p-8 overflow-y-auto">
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Adoption Applications</h1>
                        <p class="text-gray-600">Review and manage adoption applications</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Applications</h3>
                        <div class="space-y-3">
                            <?php if ($applications->num_rows > 0): ?>
                                <?php while ($app = $applications->fetch_assoc()): ?>
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <div>
                                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($app['applicant_name']); ?></p>
                                                <p class="text-sm text-gray-600">Interested in <?php echo htmlspecialchars($app['pet_name']); ?> (<?php echo htmlspecialchars($app['species']); ?>)</p>
                                                <p class="text-sm text-gray-600">Email: <?php echo htmlspecialchars($app['applicant_email']); ?></p>
                                            </div>
                                            <div class="text-right">
                                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                                    echo $app['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                        ($app['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); 
                                                ?>">
                                                    <?php echo ucfirst($app['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <form method="POST" class="flex space-x-2">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                            <select name="status" class="p-2 border rounded-lg">
                                                <option value="pending" <?php echo $app['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo $app['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                            <button type="submit" class="bg-shelter-primary text-white px-4 py-2 rounded-lg hover:bg-shelter-secondary">
                                                Update
                                            </button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">No applications available</p>
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