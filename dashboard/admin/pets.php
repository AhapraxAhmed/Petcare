<?php
require_once '../../middleware/auth.php';
requireRole('admin');

$user = a();

$db = new Database();
$conn = $db->getConnection();

// Fetch pets
$pets = $conn->query("
    SELECT p.*, u.name as owner_name, s.shelter_name
    FROM pets p
    LEFT JOIN users u ON p.owner_id = u.user_id
    LEFT JOIN shelters s ON p.shelter_id = s.shelter_id
    ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Records - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .animate-fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-scrollbar {
            width: 8px; /* Thinner scrollbar width */
            height: 8px; /* For horizontal scrollbar */
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1; /* Light track background */
            border-radius: 10px; /* Rounded track */
        }

        ::-webkit-scrollbar-thumb {
            background: #888; /* Scrollbar thumb color */
            border-radius: 10px; /* Rounded thumb */
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555; /* Darker on hover */
        }

        /* Firefox scrollbar support */
        * {
            scrollbar-width: thin; /* Thinner scrollbar for Firefox */
            scrollbar-color: #888 #f1f1f1; /* Thumb and track colors */
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
                    <div class="flex items-center">
                        <button id="sidebar-toggle" class="text-gray-500 hover:text-gray-700 lg:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h2 class="text-2xl font-bold text-gray-800 ml-4">Pet Records</h2>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600"><?php echo date('l, F j, Y'); ?></div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">Registered Pets</h3>
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-600">
                                <th class="pb-2">Name</th>
                                <th>Species</th>
                                <th>Breed</th>
                                <th>Owner/Shelter</th>
                                <th>Age</th>
                                <th>Gender</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pets as $pet): ?>
                                <tr class="border-t text-sm">
                                    <td class="py-3"><?php echo htmlspecialchars($pet['name']); ?></td>
                                    <td><?php echo htmlspecialchars($pet['species']); ?></td>
                                    <td><?php echo htmlspecialchars($pet['breed'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($pet['owner_name'] ?? $pet['shelter_name'] ?? 'None'); ?></td>
                                    <td><?php echo $pet['age'] ?? 'Unknown'; ?></td>
                                    <td><?php echo ucfirst($pet['gender'] ?? 'Unknown'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });
    </script>
</body>
</html>