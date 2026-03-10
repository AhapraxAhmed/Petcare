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

// Get patients with filters
$search = $_GET['search'] ?? '';
$where_conditions = ["a.vet_id = $vet_id"];

if ($search) {
    $search = $conn->real_escape_string($search);
    $where_conditions[] = "(p.name LIKE '%$search%' OR u.name LIKE '%$search%' OR p.species LIKE '%$search%' OR p.breed LIKE '%$search%')";
}

$where_clause = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$patients = $conn->query("
    SELECT p.pet_id, p.name as pet_name, p.species, p.breed, p.age, p.gender,
           u.name as owner_name, u.phone as owner_phone, u.email as owner_email
    FROM pets p
    JOIN users u ON p.owner_id = u.user_id
    JOIN appointments a ON p.pet_id = a.pet_id
    $where_clause
    GROUP BY p.pet_id
    ORDER BY p.name ASC
")->fetch_all(MYSQLI_ASSOC);

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - FurShield Veterinarian</title>
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
                        <h2 class="text-2xl font-bold text-gray-800">Patients</h2>
                        <p class="text-gray-600">Manage your patient records</p>
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

                <!-- Search -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <form method="GET" class="flex items-center space-x-4">
                        <div class="relative flex-1">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by pet name, owner, species, or breed" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            Search
                        </button>
                    </form>
                </div>

                <!-- Patients List -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <?php if (empty($patients)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-paw text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-600 mb-2">No patients found</h3>
                        <p class="text-gray-500">No patients match your current search.</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($patients as $patient): ?>
                        <div class="p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-paw text-green-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($patient['pet_name']); ?></h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($patient['species']); ?>
                                            <?php if ($patient['breed']): ?>
                                            • <?php echo htmlspecialchars($patient['breed']); ?>
                                            <?php endif; ?>
                                            <?php if ($patient['age']): ?>
                                            • <?php echo $patient['age']; ?> years old
                                            <?php endif; ?>
                                            <?php if ($patient['gender']): ?>
                                            • <?php echo ucfirst($patient['gender']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-sm text-gray-600">Owner: <?php echo htmlspecialchars($patient['owner_name']); ?></p>
                                        <?php if ($patient['owner_phone']): ?>
                                        <p class="text-sm text-gray-500">
                                            <i class="fas fa-phone mr-1"></i>
                                            <?php echo htmlspecialchars($patient['owner_phone']); ?>
                                        </p>
                                        <?php endif; ?>
                                        <?php if ($patient['owner_email']): ?>
                                        <p class="text-sm text-gray-500">
                                            <i class="fas fa-envelope mr-1"></i>
                                            <?php echo htmlspecialchars($patient['owner_email']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <a href="patients.php?id=<?php echo $patient['pet_id']; ?>" class="text-green-600 hover:text-green-800 text-sm">
                                        View Full Record
                                    </a>
                                </div>
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