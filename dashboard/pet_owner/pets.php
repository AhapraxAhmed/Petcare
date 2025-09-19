<?php
require_once '../../middleware/auth.php';
require_once '../../includes/functions.php'; // Assuming utils.php contains shared functions like sanitize_input
requireRole('pet_owner');

$user = a();
$user_id = $user['user_id'];

$message = '';
$error = '';

// Function to handle file uploads
function av($file, $upload_dir) {
    // Ensure the directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $file_type = $file['type'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_name = basename($file['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $new_file_name = uniqid() . '.' . $file_ext;
    $destination = $upload_dir . $new_file_name;

    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'];
    }

    if ($file_size > $max_size) {
        return ['success' => false, 'error' => 'File size exceeds 5MB limit.'];
    }

    if (move_uploaded_file($file_tmp, $destination)) {
        return ['success' => true, 'filename' => $new_file_name];
    } else {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($action === 'add_pet') {
        $name = sanitize_input($_POST['name']);
        $species = sanitize_input($_POST['species']);
        $breed = sanitize_input($_POST['breed']);
        $age = (int)$_POST['age'];
        $gender = sanitize_input($_POST['gender']);
        $weight = (float)$_POST['weight'];
        $color = sanitize_input($_POST['color']);
        $microchip_id = sanitize_input($_POST['microchip_id']);
        $medical_notes = sanitize_input($_POST['medical_notes']);
        
        // Handle file upload
        $profile_image = '';
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = av($_FILES['profile_image'], '../../Uploads/pets/');
            if ($upload_result['success']) {
                $profile_image = $upload_result['filename'];
            } else {
                $error = "Failed to upload image: " . $upload_result['error'];
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO pets (owner_id, name, species, breed, age, gender, weight, color, microchip_id, profile_image, medical_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisdssss", $user_id, $name, $species, $breed, $age, $gender, $weight, $color, $microchip_id, $profile_image, $medical_notes);
        
        if ($stmt->execute()) {
            $message = "Pet added successfully!";
            send_notification($user_id, 'New Pet Added', "Welcome $name to your FurShield family!");
        } else {
            $error = "Failed to add pet. Please try again.";
        }
    } elseif ($action === 'send_to_shelter') {
    $pet_id = (int)$_POST['pet_id'];
    $shelter_id = (int)$_POST['shelter_id'];
    $application_text = sanitize_input($_POST['application_text']);
    
    // Check if pet belongs to user
    $stmt = $conn->prepare("SELECT pet_id FROM pets WHERE pet_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $pet_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Create adoption listing
        $stmt = $conn->prepare("INSERT INTO adoption_listings (shelter_id, pet_id, title, description, adoption_fee, status) VALUES (?, ?, ?, ?, ?, 'available')");
        $title = "Adoption Listing for " . $conn->query("SELECT name FROM pets WHERE pet_id = $pet_id")->fetch_assoc()['name'];
        $description = $application_text;
        $adoption_fee = 0.00; // Default fee, can be modified
        $stmt->bind_param("iissd", $shelter_id, $pet_id, $title, $description, $adoption_fee);
        
        if ($stmt->execute()) {
            $listing_id = $conn->insert_id;
            // Create adoption application with shelter_id
            $stmt = $conn->prepare("INSERT INTO adoption_applications (listing_id, applicant_id, shelter_id, application_text, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iiis", $listing_id, $user_id, $shelter_id, $application_text);
            
            if ($stmt->execute()) {
                $message = "Pet sent to shelter successfully!";
                send_notification($user_id, 'Shelter Application', "Your application to send your pet to a shelter has been submitted.");
            } else {
                $error = "Failed to create adoption application.";
            }
        } else {
            $error = "Failed to create adoption listing.";
        }
    } else {
        $error = "Invalid pet selection.";
    }
}
    
    $db->closeConnection();
}

// Get user's pets
$db = new Database();
$conn = $db->getConnection();

$pets = $conn->query("
    SELECT pet_id, name, species, breed, age, gender, weight, color, microchip_id, profile_image, medical_notes, created_at
    FROM pets 
    WHERE owner_id = $user_id 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get shelters for dropdown
$shelters = $conn->query("SELECT shelter_id, shelter_name FROM shelters")->fetch_all(MYSQLI_ASSOC);

$db->closeConnection();

$show_add_form = isset($_GET['action']) && $_GET['action'] === 'add';
$show_shelter_form = isset($_GET['action']) && $_GET['action'] === 'send_to_shelter';
$view_pet_id = $_GET['id'] ?? null;
$view_pet = null;

if ($view_pet_id) {
    foreach ($pets as $pet) {
        if ($pet['pet_id'] == $view_pet_id) {
            $view_pet = $pet;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pets - FurShield</title>
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
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-indigo-50/30 to-slate-100">
    <div class="md:p-9">
        <div class="max-w-full mx-auto h-[100vh] md:h-[calc(95vh-3rem)]">

            <!-- Outer Shell with Rounded Glass -->
            <div class="flex h-full bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/50 overflow-hidden animate-scale-in">
               <?php include "sidebar.php"; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">My Pets</h2>
                        <p class="text-gray-600">Manage your pet profiles and information</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <?php if (!$show_add_form && !$view_pet && !$show_shelter_form): ?>
                        <a href="pets.php?action=add" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add New Pet
                        </a>
                        <a href="pets.php?action=send_to_shelter" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-home mr-2"></i>Send to Shelter
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if ($show_add_form): ?>
                <!-- Add Pet Form -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-800">Add New Pet</h3>
                        <a href="pets.php" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times text-xl"></i>
                        </a>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="add_pet">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Pet Name *</label>
                                <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Species *</label>
                                <select name="species" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Species</option>
                                    <option value="Dog">Dog</option>
                                    <option value="Cat">Cat</option>
                                    <option value="Bird">Bird</option>
                                    <option value="Rabbit">Rabbit</option>
                                    <option value="Fish">Fish</option>
                                    <option value="Hamster">Hamster</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Breed</label>
                                <input type="text" name="breed" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Age (years)</label>
                                <input type="number" name="age" min="0" max="50" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                <select name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="unknown">Unknown</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Weight (lbs)</label>
                                <input type="number" name="weight" step="0.1" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                                <input type="text" name="color" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Microchip ID</label>
                            <input type="text" name="microchip_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Profile Image</label>
                            <input type="file" name="profile_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Medical Notes</label>
                            <textarea name="medical_notes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Any medical conditions, allergies, or special notes..."></textarea>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <a href="pets.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Add Pet
                            </button>
                        </div>
                    </form>
                </div>

                <?php elseif ($show_shelter_form): ?>
                <!-- Send to Shelter Form -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-800">Send Pet to Shelter</h3>
                        <a href="pets.php" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times text-xl"></i>
                        </a>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="send_to_shelter">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Pet *</label>
                            <select name="pet_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Pet</option>
                                <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['pet_id']; ?>"><?php echo htmlspecialchars($pet['name']); ?> (<?php echo htmlspecialchars($pet['species']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Shelter *</label>
                            <select name="shelter_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Shelter</option>
                                <?php foreach ($shelters as $shelter): ?>
                                <option value="<?php echo $shelter['shelter_id']; ?>"><?php echo htmlspecialchars($shelter['shelter_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Application Details</label>
                            <textarea name="application_text" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Provide details about why you are sending this pet to a shelter..."></textarea>
                        </div>
                        
                        <div class="flex items-center justify-end space-x-4">
                            <a href="pets.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                            <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                                <i class="fas fa-home mr-2"></i>Send to Shelter
                            </button>
                        </div>
                    </form>
                </div>

                <?php elseif ($view_pet): ?>
                <!-- Pet Details View -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($view_pet['name']); ?></h3>
                        <a href="pets.php" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Pets
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-1">
                            <div class="text-center">
                                <?php if ($view_pet['profile_image']): ?>
                                <img src="../../Uploads/pets/<?php echo htmlspecialchars($view_pet['profile_image']); ?>" alt="<?php echo htmlspecialchars($view_pet['name']); ?>" class="w-48 h-48 rounded-full object-cover mx-auto mb-4">
                                <?php else: ?>
                                <div class="w-48 h-48 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-paw text-6xl text-gray-400"></i>
                                </div>
                                <?php endif; ?>
                                <h4 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($view_pet['name']); ?></h4>
                                <p class="text-gray-600"><?php echo htmlspecialchars($view_pet['species']); ?></p>
                            </div>
                        </div>
                        
                        <div class="lg:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h5 class="font-semibold text-gray-800 mb-3">Basic Information</h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Breed:</span>
                                            <span class="font-medium"><?php echo htmlspecialchars($view_pet['breed'] ?: 'Not specified'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Age:</span>
                                            <span class="font-medium"><?php echo $view_pet['age'] ? $view_pet['age'] . ' years' : 'Not specified'; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Gender:</span>
                                            <span class="font-medium"><?php echo $view_pet['gender'] ? ucfirst($view_pet['gender']) : 'Not specified'; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Weight:</span>
                                            <span class="font-medium"><?php echo $view_pet['weight'] ? $view_pet['weight'] . ' lbs' : 'Not specified'; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Color:</span>
                                            <span class="font-medium"><?php echo htmlspecialchars($view_pet['color'] ?: 'Not specified'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h5 class="font-semibold text-gray-800 mb-3">Additional Details</h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Microchip ID:</span>
                                            <span class="font-medium"><?php echo htmlspecialchars($view_pet['microchip_id'] ?: 'Not specified'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Added:</span>
                                            <span class="font-medium"><?php echo format_date($view_pet['created_at']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($view_pet['medical_notes']): ?>
                            <div class="mt-6">
                                <h5 class="font-semibold text-gray-800 mb-3">Medical Notes</h5>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($view_pet['medical_notes'])); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-6 flex space-x-4">
                                <a href="health-records.php?pet_id=<?php echo $view_pet['pet_id']; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-heartbeat mr-2"></i>Health Records
                                </a>
                                <a href="appointments.php?action=book&pet_id=<?php echo $view_pet['pet_id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                                    <i class="fas fa-calendar-plus mr-2"></i>Book Appointment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- Pets Grid -->
                <?php if (empty($pets)): ?>
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <div class="mb-6">
                        <i class="fas fa-paw text-6xl text-gray-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No pets yet</h3>
                    <p class="text-gray-600 mb-6">Add your first pet to get started with FurShield</p>
                    <a href="pets.php?action=add" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Your First Pet
                    </a>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($pets as $pet): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 pet-card">
                        <div class="text-center mb-4">
                            <?php if ($pet['profile_image']): ?>
                            <img src="../../Uploads/pets/<?php echo htmlspecialchars($pet['profile_image']); ?>" alt="<?php echo htmlspecialchars($pet['name']); ?>" class="w-24 h-24 rounded-full object-cover mx-auto mb-3">
                            <?php else: ?>
                            <div class="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-paw text-2xl text-gray-400"></i>
                            </div>
                            <?php endif; ?>
                            <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($pet['name']); ?></h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($pet['species']); ?> • <?php echo $pet['age'] ? $pet['age'] . ' years old' : 'Age not specified'; ?></p>
                        </div>
                        
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Breed:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($pet['breed'] ?: 'Mixed'); ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Gender:</span>
                                <span class="font-medium"><?php echo $pet['gender'] ? ucfirst($pet['gender']) : 'Unknown'; ?></span>
                            </div>
                            <?php if ($pet['weight']): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Weight:</span>
                                <span class="font-medium"><?php echo $pet['weight']; ?> lbs</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex space-x-2">
                            <a href="pets.php?id=<?php echo $pet['pet_id']; ?>" class="flex-1 bg-blue-600 text-white py-2 px-3 rounded-lg hover:bg-blue-700 transition-colors text-center text-sm">
                                View Details
                            </a>
                            <a href="appointments.php?action=book&pet_id=<?php echo $pet['pet_id']; ?>" class="flex-1 bg-green-600 text-white py-2 px-3 rounded-lg hover:bg-green-700 transition-colors text-center text-sm">
                                Book Vet
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>