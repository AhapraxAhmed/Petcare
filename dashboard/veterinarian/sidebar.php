<div class="bg-white shadow-lg w-16">
    <div class="p-6 gradient-bg">
        <div class="flex items-center text-white">
            <i class="fas fa-stethoscope text-2xl mr-3"></i>
        </div>
    </div>
    
    <nav class="mt-6">
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        ?>
        <a href="index.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-700 transition-colors <?php echo $current_page == 'index.php' ? 'bg-green-50 border-r-4 border-green-500 text-gray-700' : ''; ?>">
            <i class="fas fa-tachometer-alt mr-3"></i>
            
        </a>
        <a href="appointments.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-700 transition-colors <?php echo $current_page == 'appointments.php' ? 'bg-green-50 border-r-4 border-green-500 text-gray-700' : ''; ?>">
            <i class="fas fa-calendar-alt mr-3"></i>
            
        </a>
        <a href="patients.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-700 transition-colors <?php echo $current_page == 'patients.php' ? 'bg-green-50 border-r-4 border-green-500 text-gray-700' : ''; ?>">
            <i class="fas fa-paw mr-3"></i>
            
        </a>
        <a href="profile.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-700 transition-colors <?php echo $current_page == 'profile.php' ? 'bg-green-50 border-r-4 border-green-500 text-gray-700' : ''; ?>">
            <i class="fas fa-user-md mr-3"></i>
            
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
    <div id="userMenu" class="hidden absolute bottom-16 left-0 w-16 p-2 bg-white shadow-lg rounded-lg z-50 flex flex-col items-center space-y-4">
        <!-- Profile -->
        <a href="profile.php" 
           class="w-10 h-10 flex items-center justify-center bg-gray-100 rounded-full hover:bg-gray-200 transition-colors">
            <i class="fas fa-user text-gray-700"></i>
        </a>
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