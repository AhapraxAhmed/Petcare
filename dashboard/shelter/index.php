<?php
require_once '../../middleware/auth.php';

// Authenticate user
requireRole('shelter');

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Shelter User';

// Initialize database
$db = new Database();
$conn = $db->getConnection();

// Get shelter statistics
$stats_query = "SELECT 
    COUNT(*) as total_listings,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_pets,
    SUM(CASE WHEN status = 'adopted' THEN 1 ELSE 0 END) as adopted_pets,
    (SELECT COUNT(*) FROM adoption_applications aa 
     JOIN adoption_listings al ON aa.listing_id = al.listing_id 
     WHERE al.shelter_id = ?) as total_applications
    FROM adoption_listings WHERE shelter_id = ?";

$stmt = $conn->prepare($stats_query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database error. Please try again later.</div>');
}
$stmt->bind_param("ii", $user_id, $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database query failed. Please try again later.</div>');
}
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent adoption applications
$applications_query = "SELECT 
    aa.*, 
    p.name AS pet_name, 
    p.species, 
    u.name AS applicant_name, 
    u.email AS applicant_email
FROM adoption_applications aa
JOIN adoption_listings al ON aa.listing_id = al.listing_id
JOIN pets p ON al.pet_id = p.pet_id
JOIN users u ON aa.applicant_id = u.user_id
WHERE al.shelter_id = ?
ORDER BY aa.submitted_at DESC
LIMIT 5;
";

$stmt = $conn->prepare($applications_query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database error. Please try again later.</div>');
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die('<div class="bg-red-100 text-red-700 p-4 rounded">Database query failed. Please try again later.</div>');
}
$recent_applications = $stmt->get_result();
$stmt->close();

// Close database connection
$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shelter Dashboard - FurShield</title>
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

            <!-- Outer Shell with Rounded Glass -->
            <div class="flex h-full bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/50 overflow-hidden animate-scale-in">
               <?php include "sidebar.php";?>

        <!-- Main Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Shelter Dashboard</h1>
                <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($name); ?>!</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-shelter-primary transform hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total Listings</p>
                            <p class="text-3xl font-bold text-shelter-primary"><?php echo (int)$stats['total_listings']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-shelter-primary/10 rounded-full flex items-center justify-center">
                            <i class="fas fa-list text-shelter-primary text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 transform hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Available Pets</p>
                            <p class="text-3xl font-bold text-green-600"><?php echo (int)$stats['available_pets']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-paw text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 transform hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Adopted Pets</p>
                            <p class="text-3xl font-bold text-blue-600"><?php echo (int)$stats['adopted_pets']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-heart text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500 transform hover:scale-105 transition-transform">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Applications</p>
                            <p class="text-3xl font-bold text-orange-600"><?php echo (int)$stats['total_applications']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-alt text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Quick Actions Card -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="listings.php?action=add" class="flex items-center p-4 bg-shelter-primary/10 rounded-lg hover:bg-shelter-primary/20 transition-colors">
                            <i class="fas fa-plus text-shelter-primary mr-3"></i>
                            <span class="font-medium text-gray-700">Add New Pet Listing</span>
                        </a>
                        <a href="care-logs.php?action=add" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <i class="fas fa-heart text-green-600 mr-3"></i>
                            <span class="font-medium text-gray-700">Log Pet Care Activity</span>
                        </a>
                        <a href="applications.php" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                            <i class="fas fa-file-alt text-blue-600 mr-3"></i>
                            <span class="font-medium text-gray-700">Review Applications</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Applications -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Applications</h3>
                    <div class="space-y-3">
                        <?php if ($recent_applications->num_rows > 0): ?>
                            <?php while ($app = $recent_applications->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($app['applicant_name']); ?></p>
                                        <p class="text-sm text-gray-600">Interested in <?php echo htmlspecialchars($app['pet_name']); ?></p>
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
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No recent applications</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Adoption Success Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Adoption Overview</h3>
                <div class="flex items-center justify-center h-64">
                    <canvas id="adoptionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Adoption Chart
        const ctx = document.getElementById('adoptionChart');
        if (!ctx) {
            console.error('Canvas element not found');
        } else {
            const adoptionChart = new Chart(ctx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Adopted', 'Pending'],
                    datasets: [{
                        data: [
                            <?php echo (int)$stats['available_pets']; ?>,
                            <?php echo (int)$stats['adopted_pets']; ?>,
                            <?php echo (int)$stats['total_applications']; ?>
                        ],
                        backgroundColor: ['#8B5CF6', '#10B981', '#F59E0B'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing animations');
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