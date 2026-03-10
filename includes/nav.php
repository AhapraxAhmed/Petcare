<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check login
$isLoggedIn = is_logged_in();
$user       = $isLoggedIn ? a() : null;

$username   = $isLoggedIn ? $user['name'] : "SomeOne";
$role       = $isLoggedIn ? $user['role'] : "For Something";

$dashboardLink = "../dashboard/" . ($isLoggedIn ? $role : "") . "/index.php";
$logoutLink    = "../auth/logout.php";
?>

<style>
[x-cloak] { display: none !important; }
</style>

<nav class="fixed w-full max-w-[calc(100%-1.5rem)] mx-auto mt-4 ms-4 z-50 top-0 bg-white/10 backdrop-blur-lg rounded-full shadow-xl transition-all duration-300" id="navbar">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">

            <!-- Left side -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="../index.php#home" class="text-gray-700 text-lg font-medium hover:text-gray-600 relative group">
                    Home
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gray-600 transition-all group-hover:w-full"></span>
                </a>
                <a href="products.php" class="text-gray-700 text-lg font-medium hover:text-gray-600 relative group">
                    Products
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gray-600 transition-all group-hover:w-full"></span>
                </a>
            </div>

            <!-- Center Logo -->
            <div class="flex items-center justify-center flex-1">
                <div class="flex-shrink-0 flex items-center space-x-2">
                    <img src="../logo.png" alt="" class="w-50 h-60 mt-8">
                </div>
            </div>

            <!-- Right side -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="cart.php" class="text-gray-700 hover:text-blue-300 transform hover:scale-110">
                    <i class="fas fa-shopping-cart text-xl"></i>
                </a>

               <?php if ($isLoggedIn): ?>
    <div x-data="{ open: false }" class="relative">
        <!-- Toggle Button -->
        <button @click="open = !open"
                class="flex items-center space-x-2 px-4 py-2 rounded-full 
                       bg-white/20 backdrop-blur-md border border-gray/80 
                       text-gray-900 font-medium shadow-md
                       hover:bg-white/20 hover:text-blue-500  transition">
            <i class="fas fa-user-circle text-xl"></i>
            <span><?= htmlspecialchars($username) ?> {<?= htmlspecialchars($role) ?>}</span>
            <!-- Arrow toggle -->
            <span x-text="open ? '▲' : '▼'" class="text-sm"></span>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open" x-cloak @click.away="open = false"
             class="absolute right-0 mt-2 w-52 bg-white/90 backdrop-blur-md 
                    rounded-xl shadow-lg border border-gray-200 py-2 z-50 transition">
            <a href="<?= $dashboardLink ?>" 
               class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-100 hover:text-blue-600 rounded-md">
               <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
            </a>
            <a href="<?= $logoutLink ?>" 
               class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-100 hover:text-red-600 rounded-md">
               <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </div>
<?php else: ?>

                    <!-- Login / Register -->
                    <a href="../auth/login.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2.5 rounded-full font-semibold text-sm shadow-lg hover:scale-105">
                        Login
                    </a>
                    <a href="../auth/register.php" 
                       class="border-2 border-blue-500 text-blue-600 px-6 py-2.5 rounded-full font-semibold text-sm hover:bg-blue-500/20 hover:border-blue-400 hover:text-blue-400 shadow-lg">
                       Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Alpine.js -->
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
