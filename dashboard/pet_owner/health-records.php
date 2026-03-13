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

// Get user's pets
$pets_query = "SELECT pet_id, name, species, breed, image FROM pets WHERE owner_id = ?";
$pets_stmt = $conn->prepare($pets_query);
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets_result = $pets_stmt->get_result();
$user_pets = $pets_result->fetch_all(MYSQLI_ASSOC);
// error_log(print_r($user_pets, true)); // Debugging
$pets_stmt->close();

// Get selected pet's health records
$selected_pet_id = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : (count($user_pets) > 0 ? $user_pets[0]['pet_id'] : null);

if ($selected_pet_id) {
    $records_query = "SELECT * FROM health_records WHERE pet_id = ? ORDER BY date DESC";
    $records_stmt = $conn->prepare($records_query);
    $records_stmt->bind_param("i", $selected_pet_id);
    $records_stmt->execute();
    $records_result = $records_stmt->get_result();
    $health_records = $records_result->fetch_all(MYSQLI_ASSOC);
    $records_stmt->close();
    
    // Get selected pet info
    $pet_query = "SELECT * FROM pets WHERE pet_id = ? AND owner_id = ?";
    $pet_stmt = $conn->prepare($pet_query);
    $pet_stmt->bind_param("ii", $selected_pet_id, $user_id);
    $pet_stmt->execute();
    $selected_pet = $pet_stmt->get_result()->fetch_assoc();
    $pet_stmt->close();
}

// Get unread notifications count
$notifications_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_notifications = $notifications_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records - FurShield</title>
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
                <?php include "sidebar.php";?>


        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Health Records</h2>
                        <p class="text-gray-600">View your pet's medical history</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="text-gray-500 hover:text-gray-700 relative">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                        <?php echo $unread_notifications; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?php echo date('l, F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?php if (empty($user_pets)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in">
                        <div class="text-center py-8">
                            <i class="fas fa-info-circle text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 text-sm">You haven't added any pets yet. 
                                <a href="add-pet.php" class="text-blue-600 hover:underline">Add your first pet</a> to start tracking health records.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Pet Selection -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-paw mr-2"></i>Select Pet</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <?php foreach ($user_pets as $pet): ?>
                                <a href="?pet_id=<?php echo $pet['pet_id']; ?>" class="pet-card">
                                    <div class="border rounded-lg p-4 text-center <?php echo ($selected_pet_id == $pet['pet_id']) ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-2 overflow-hidden">
                                            <?php if ($pet['image']): ?>
                                                <img src="../../uploads/pets/<?php echo htmlspecialchars($pet['image']); ?>" alt="" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-paw text-xl text-blue-600"></i>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="font-medium"><?php echo htmlspecialchars($pet['name']); ?></h6>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($pet['species'] . ' - ' . $pet['breed']); ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($selected_pet): ?>
                        <!-- Pet Info -->
                        <div class="bg-white rounded-xl shadow-sm p-6 mb-6 pet-card animate-fade-in" style="animation-delay: 0.1s;">
                            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                                <?php if ($selected_pet['image']): ?>
                                    <div class="w-32 h-32 rounded-xl overflow-hidden shadow-md flex-shrink-0">
                                        <img src="../../uploads/pets/<?php echo htmlspecialchars($selected_pet['image']); ?>" alt="<?php echo htmlspecialchars($selected_pet['name']); ?>" class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="w-32 h-32 rounded-xl bg-indigo-100 flex items-center justify-center shadow-inner flex-shrink-0">
                                        <i class="fas fa-paw text-4xl text-indigo-400"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($selected_pet['name']); ?>'s Profile</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p><strong class="text-gray-700">Species:</strong> <?php echo htmlspecialchars($selected_pet['species']); ?></p>
                                    <p><strong class="text-gray-700">Breed:</strong> <?php echo htmlspecialchars($selected_pet['breed']); ?></p>
                                    <p><strong class="text-gray-700">Age:</strong> <?php echo $selected_pet['age']; ?> years</p>
                                </div>
                                <div>
                                    <p><strong class="text-gray-700">Gender:</strong> <?php echo ucfirst($selected_pet['gender']); ?></p>
                                    <p><strong class="text-gray-700">Weight:</strong> <?php echo $selected_pet['weight']; ?> lbs</p>
                                    <p><strong class="text-gray-700">Color:</strong> <?php echo htmlspecialchars($selected_pet['color']); ?></p>
                                </div>
                            </div>
                            <?php if (isset($selected_pet['medical_history']) && $selected_pet['medical_history']): ?>
                                <div class="mt-4">
                                    <p><strong class="text-gray-700">Medical Notes:</strong></p>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($selected_pet['medical_history']); ?></p>
                                </div>
                            <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Health Records -->
                        <div class="bg-white rounded-xl shadow-sm p-6 pet-card animate-fade-in" style="animation-delay: 0.2s;">
                            <h3 class="text-lg font-semibold text-gray-800 mb-6"><i class="fas fa-file-medical mr-2"></i>Medical History</h3>
                            <?php if (empty($health_records)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-file-medical text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-500 text-sm">No health records found for <?php echo htmlspecialchars($selected_pet['name']); ?>.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($health_records as $record): ?>
                                        <div class="border rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <h6 class="font-medium">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    <?php echo date('F j, Y', strtotime($record['date'])); ?>
                                                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                        <?php echo ucfirst($record['type']); ?>
                                                    </span>
                                                </h6>
                                            </div>
                                            <?php if (!empty($record['vet_name'])): ?>
                                                <p class="text-sm text-gray-600 mb-2">
                                                    <i class="fas fa-user-md mr-2"></i>
                                                    Dr. <?php echo htmlspecialchars($record['vet_name']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($record['description'])): ?>
                                                <div class="mb-2">
                                                    <p><strong class="text-gray-700">Description:</strong></p>
                                                    <p class="text-gray-600"><?php echo htmlspecialchars($record['description']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($record['document_path'])): ?>
                                                <div class="mb-2">
                                                    <p><strong class="text-gray-700">Document:</strong></p>
                                                    <a href="../../uploads/health_records/<?php echo htmlspecialchars($record['document_path']); ?>" target="_blank" class="text-blue-600 hover:underline">View Document</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
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
<?php $conn->close(); ?>