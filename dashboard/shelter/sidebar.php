<div class="w-16 bg-gradient-to-b from-shelter-primary to-purple-700 text-white shadow-2xl">
            <div class="p-6">
                <div class="flex items-center space-x-3">
                        <i class="fas fa-home text-shelter-primary text-lg text-white w-10 h-10"></i>
                </div>
            </div>
            
            <nav class="mt-8">
                <a href="index.php" class="flex items-center px-6 py-3 bg-white/20 border-r-4 border-white">
                    <i class="fas fa-chart-line mr-3"></i>
                    
                </a>
                <a href="listings.php" class="flex items-center px-6 py-3 hover:bg-white/10 transition-colors">
                    <i class="fas fa-list mr-3"></i>
                </a>
                <a href="applications.php" class="flex items-center px-6 py-3 hover:bg-white/10 transition-colors">
                    <i class="fas fa-file-alt mr-3"></i>
                    
                </a>
                <a href="care-logs.php" class="flex items-center px-6 py-3 hover:bg-white/10 transition-colors">
                    <i class="fas fa-heart mr-3"></i>
                </a>
            </nav>
             
    <div class="absolute bottom-0 w-16 mb-2">
    <!-- Profile Button -->
    <button id="userMenuBtn" class="flex items-center p-2">
        <img src="<?php 
            if (empty($user['profile_image'])) {
                echo 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR4g_2Qj3LsNR-iqUAFm6ut2EQVcaou4u2YXw&s';
            } else {
                echo (isset($user['google_id']) || isset($user['github_id'])) 
                    ? htmlspecialchars($user['profile_image']) 
                    : '../../Uploads/images/' . htmlspecialchars($user['profile_image']);
            }
        ?>" 
        alt="User" class="w-10 h-10 rounded-full">
    </button>

    <!-- Collapsible Menu -->
    <div id="userMenu" class="hidden absolute bottom-16 left-0 w-16 p-2  shadow-lg rounded-lg z-50 flex flex-col items-center space-y-4">
        <!-- Logout -->
        <a href="../../auth/logout.php" 
           class="w-10 h-10 flex items-center justify-center bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>

<script>
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');

    userMenuBtn.addEventListener('click', () => {
        userMenu.classList.toggle('hidden');
    });
</script>
        </div>