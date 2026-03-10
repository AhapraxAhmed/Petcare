<?php
require_once '../../middleware/auth.php';
requireRole('admin');

$user = a();

$db = new Database();
$conn = $db->getConnection();

// Fetch analytics data
$stats = [];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['total_pets'] = $conn->query("SELECT COUNT(*) as count FROM pets")->fetch_assoc()['count'];
$stats['total_appointments'] = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$stats['total_products'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1")->fetch_assoc()['count'];
$stats['adoption_listings'] = $conn->query("SELECT COUNT(*) as count FROM adoption_listings")->fetch_assoc()['count'];

// Monthly data for graphs (last 6 months)
$stats['monthly_registrations'] = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

$stats['monthly_pets'] = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM pets 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

$stats['monthly_appointments'] = $conn->query("
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as count 
    FROM appointments 
    WHERE appointment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

$stats['monthly_adoptions'] = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM adoption_listings 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

$stats['monthly_products'] = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM products 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND is_active = 1
    GROUP BY month
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .animate-fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .sidebar-transition { transition: all 0.3s ease-in-out; }
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
                        <h2 class="text-2xl font-bold text-gray-800 ml-4">Analytics</h2>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600"><?php echo date('l, F j, Y'); ?></div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in">
                        <p class="text-sm font-medium text-gray-600">Total Users</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_users']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in">
                        <p class="text-sm font-medium text-gray-600">Total Pets</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_pets']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in">
                        <p class="text-sm font-medium text-gray-600">Appointments</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_appointments']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in">
                        <p class="text-sm font-medium text-gray-600">Adoption Listings</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['adoption_listings']; ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover animate-fade-in">
                        <p class="text-sm font-medium text-gray-600">Active Products</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_products']; ?></p>
                    </div>
                </div>

                <!-- Graphs -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Monthly User Registrations -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly User Registrations</h3>
                        <canvas id="registrationChart" height="100"></canvas>
                    </div>

                    <!-- Monthly Pet Registrations -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Pet Registrations</h3>
                        <canvas id="petChart" height="100"></canvas>
                    </div>

                    <!-- Monthly Appointments -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Appointments</h3>
                        <canvas id="appointmentChart" height="100"></canvas>
                    </div>

                    <!-- Monthly Adoption Listings -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Adoption Listings</h3>
                        <canvas id="adoptionChart" height="100"></canvas>
                    </div>

                    <!-- Monthly Product Additions -->
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Product Additions</h3>
                        <canvas id="productChart" height="100"></canvas>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });

        // Generate months for consistent x-axis (last 6 months)
        const months = [];
        for (let i = 5; i >= 0; i--) {
            const date = new Date();
            date.setMonth(date.getMonth() - i);
            months.push(date.toISOString().slice(0, 7));
        }

        // Helper function to map data to months
        function mapDataToMonths(data, key) {
            const result = months.map(month => {
                const entry = data.find(item => item.month === month);
                return entry ? entry.count : 0;
            });
            return result;
        }

        // User Registration Chart
        const registrationCtx = document.getElementById('registrationChart').getContext('2d');
        new Chart(registrationCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'New Users',
                    data: mapDataToMonths(<?php echo json_encode($stats['monthly_registrations']); ?>, 'count'),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Pet Registration Chart
        const petCtx = document.getElementById('petChart').getContext('2d');
        new Chart(petCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'New Pets',
                    data: mapDataToMonths(<?php echo json_encode($stats['monthly_pets']); ?>, 'count'),
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Appointment Chart
        const appointmentCtx = document.getElementById('appointmentChart').getContext('2d');
        new Chart(appointmentCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Appointments',
                    data: mapDataToMonths(<?php echo json_encode($stats['monthly_appointments']); ?>, 'count'),
                    borderColor: '#9f7aea',
                    backgroundColor: 'rgba(159, 122, 234, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Adoption Listing Chart
        const adoptionCtx = document.getElementById('adoptionChart').getContext('2d');
        new Chart(adoptionCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Adoption Listings',
                    data: mapDataToMonths(<?php echo json_encode($stats['monthly_adoptions']); ?>, 'count'),
                    borderColor: '#ed8936',
                    backgroundColor: 'rgba(237, 137, 54, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Product Addition Chart
        const productCtx = document.getElementById('productChart').getContext('2d');
        new Chart(productCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'New Products',
                    data: mapDataToMonths(<?php echo json_encode($stats['monthly_products']); ?>, 'count'),
                    borderColor: '#f56565',
                    backgroundColor: 'rgba(245, 101, 101, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>