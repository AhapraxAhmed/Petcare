<?php
require_once '../../middleware/auth.php';

// Authenticate user
requireRole('shelter');

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Shelter User';

// Initialize database
$db = new Database();
$conn = $db->getConnection();

// Handle add/edit listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $pet_id = $_POST['pet_id'] ?? null;
        $name = $_POST['name'] ?? '';
        $species = $_POST['species'] ?? '';
        $breed = $_POST['breed'] ?? '';
        $age = $_POST['age'] ?? '';
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'available';

        if ($_POST['action'] === 'add') {
            $query = "INSERT INTO pets (name, species, breed, age, shelter_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssii", $name, $species, $breed, $age, $user_id);
            $stmt->execute();
            $pet_id = $conn->insert_id;
            $stmt->close();

            $query = "INSERT INTO adoption_listings (pet_id, shelter_id, description, status) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiss", $pet_id, $user_id, $description, $status);
            $stmt->execute();
            $stmt->close();
        } else {
            $query = "UPDATE pets SET name = ?, species = ?, breed = ?, age = ? WHERE pet_id = ? AND shelter_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssiii", $name, $species, $breed, $age, $pet_id, $user_id);
            $stmt->execute();
            $stmt->close();

            $query = "UPDATE adoption_listings SET description = ?, status = ? WHERE pet_id = ? AND shelter_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssii", $description, $status, $pet_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Get all pet listings
$query = "SELECT p.*, al.description, al.status 
          FROM pets p 
          JOIN adoption_listings al ON p.pet_id = al.pet_id 
          WHERE p.shelter_id = ? 
          ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listings = $stmt->get_result();
$stmt->close();

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Listings - FurShield</title>
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
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Pet Listings</h1>
                        <p class="text-gray-600">Manage your shelter's pet listings</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Add New Pet Listing</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">Name</label>
                                    <input type="text" name="name" class="w-full p-2 border rounded-lg" required>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">Species</label>
                                    <input type="text" name="species" class="w-full p-2 border rounded-lg" required>
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">Breed</label>
                                    <input type="text" name="breed" class="w-full p-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">Age</label>
                                    <input type="number" name="age" class="w-full p-2 border rounded-lg">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 font-medium mb-1">Description</label>
                                    <textarea name="description" class="w-full p-2 border rounded-lg" rows="4"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="bg-shelter-primary text-white px-4 py-2 rounded-lg hover:bg-shelter-secondary">
                                Add Listing
                            </button>
                        </form>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Current Listings</h3>
                        <div class="space-y-3">
                            <?php if ($listings->num_rows > 0): ?>
                                <?php while ($listing = $listings->fetch_assoc()): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($listing['name']); ?> (<?php echo htmlspecialchars($listing['species']); ?>)</p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($listing['description']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-2 py-1 text-xs rounded-full <?php 
                                                echo $listing['status'] === 'available' ? 'bg-green-100 text-green-800' : 
                                                    ($listing['status'] === 'adopted' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'); 
                                            ?>">
                                                <?php echo ucfirst($listing['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">No pet listings available</p>
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