<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Create a database connection
$db = new Database();
$conn = $db->getConnection();

// Handle Add to Cart AJAX request
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart' && isset($_SESSION['user_id'])) {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    if ($product_id > 0 && $quantity > 0) {
        // Check if product exists and has stock
        $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ? AND is_active = 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            if ($product['stock_quantity'] >= $quantity) {
                // Check if item already in cart
                $stmt = $conn->prepare("SELECT cart_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    // Update quantity
                    $cart_item = $result->fetch_assoc();
                    $new_quantity = $cart_item['quantity'] + $quantity;
                    $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ?");
                    $stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
                } else {
                    // Insert new cart item
                    $stmt = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $_SESSION['user_id'], $product_id, $quantity);
                }
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Item added to cart']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to add item to cart']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Insufficient stock']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product or quantity']);
    }
    exit;
}

// Check if AJAX request for product grid
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// Get filter parameters with validation
$category = isset($_GET['category']) && in_array($_GET['category'], ['food', 'grooming', 'toys', 'health', 'accessories', 'training']) ? $_GET['category'] : '';
$brand = isset($_GET['brand']) && is_string($_GET['brand']) ? $_GET['brand'] : '';
$price_min = isset($_GET['price_min']) && is_numeric($_GET['price_min']) && floatval($_GET['price_min']) >= 0 ? floatval($_GET['price_min']) : 0;
$price_max = isset($_GET['price_max']) && is_numeric($_GET['price_max']) && floatval($_GET['price_max']) > 0 ? floatval($_GET['price_max']) : PHP_FLOAT_MAX;
$search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['name_asc', 'name_desc', 'price_asc', 'price_desc']) ? $_GET['sort'] : 'name_asc';

// Build query
$sql = "SELECT * FROM products WHERE is_active = 1";
$params = [];
$types = "";

if ($category) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}
if ($brand) {
    $sql .= " AND brand = ?";
    $params[] = $brand;
    $types .= "s";
}
if ($search) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}
if ($price_min > 0 || $price_max < PHP_FLOAT_MAX) {
    $sql .= " AND price BETWEEN ? AND ?";
    $params[] = $price_min;
    $params[] = $price_max;
    $types .= "dd";
}

// Sort
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY name DESC";
        break;
    default:
        $sql .= " ORDER BY name ASC";
}

// Prepare and execute
$stmt = $conn->prepare($sql);
if ($params && $types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($is_ajax) {
    // Output the products grid wrapped in the parent container for AJAX
    ?>
    <div class="lg:col-span-3 products-grid-container">
        <div id="products-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <!-- Enhanced product card with unique design and animations -->
                    <div class="product-card group relative bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-3 overflow-hidden">
                        <!-- Product Image with Overlay -->
                        <div class="relative overflow-hidden rounded-t-2xl">
                            <img src="../<?= htmlspecialchars($row['image_url'] ?? '/placeholder.svg?height=250&width=300') ?>" 
                                 alt="<?= htmlspecialchars($row['name'] ?? 'Product') ?>" 
                                 class="w-full h-56 object-cover transition-transform duration-700 group-hover:scale-110">
                            
                            <!-- Gradient Overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            
                            <!-- Category Badge -->
                            <div class="absolute top-4 left-4 px-3 py-1 bg-gray-500 text-white text-xs font-semibold rounded-full shadow-lg">
                                <?= htmlspecialchars(ucfirst($row['category'] ?? 'Product')) ?>
                            </div>
                            
                            <!-- Stock Badge -->
                            <?php if ($row['stock_quantity'] > 0): ?>
                                <div class="absolute top-4 right-4 px-3 py-1 bg-green-500 text-white text-xs font-semibold rounded-full shadow-lg">
                                    In Stock
                                </div>
                            <?php else: ?>
                                <div class="absolute top-4 right-4 px-3 py-1 bg-red-500 text-white text-xs font-semibold rounded-full shadow-lg">
                                    Out of Stock
                                </div>
                            <?php endif; ?>
                            
                            <!-- Quick View Button -->
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <button class="quick-view-btn bg-white/90 backdrop-blur-sm text-gray-800 px-6 py-2 rounded-full font-semibold shadow-lg hover:bg-white transition-colors duration-200">
                                    <i class="fas fa-eye mr-2"></i>Quick View
                                </button>
                            </div>
                        </div>
                        
                        <!-- Product Info -->
                        <div class="p-6 space-y-4">
                            <!-- Brand -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600 font-medium bg-gray-50 px-2 py-1 rounded-md">
                                    <?= htmlspecialchars($row['brand'] ?? 'Brand') ?>
                                </span>
                                <div class="flex items-center space-x-1">
                                    <?php for($i = 0; $i < 5; $i++): ?>
                                        <i class="fas fa-star text-yellow-400 text-xs"></i>
                                    <?php endfor; ?>
                                    <span class="text-xs text-gray-500 ml-1">(4.8)</span>
                                </div>
                            </div>
                            
                            <!-- Product Name -->
                            <h3 class="text-lg font-bold text-gray-900 line-clamp-2 group-hover:text-gray-700 transition-colors duration-200">
                                <?= htmlspecialchars($row['name'] ?? 'Unknown Product') ?>
                            </h3>
                            
                            <!-- Description -->
                            <p class="text-sm text-gray-600 line-clamp-2">
                                <?= htmlspecialchars($row['description'] ?? 'High-quality pet product designed for your beloved companion.') ?>
                            </p>
                            
                            <!-- Price and Stock -->
                            <div class="flex items-center justify-between">
                                <div class="space-y-1">
                                    <div class="text-2xl font-bold text-gray-900">
                                        $<?= htmlspecialchars(number_format($row['price'] ?? 0, 2)) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars($row['stock_quantity'] ?? 0) ?> available
                                    </div>
                                </div>
                                
                                <!-- Add to Cart Button -->
                                <div class="flex flex-col space-y-2">
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <?php if ($row['stock_quantity'] > 0): ?>
                                            <button class="add-to-cart bg-gray-500 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center" 
                                                    data-product-id="<?= $row['product_id'] ?>" 
                                                    data-quantity="1">
                                                <i class="fas fa-cart-plus mr-2"></i>
                                                Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button class="bg-gray-400 text-white px-6 py-3 rounded-xl font-semibold cursor-not-allowed flex items-center" disabled>
                                                <i class="fas fa-times mr-2"></i>
                                                Out of Stock
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="../auth/login.php" class="bg-gray-500 text-white px-3 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center">
                                            <i class="fas fa-sign-in-alt mr-2"></i>
                                            Login
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Floating Action Buttons -->
                        <div class="absolute top-1/2 right-4 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-all duration-300 space-y-2">
                            <button class="w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full shadow-lg hover:bg-red-500 hover:text-white transition-colors duration-200 flex items-center justify-center">
                                <i class="fas fa-heart text-sm"></i>
                            </button>
                            <button class="w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full shadow-lg hover:bg-gray-500 hover:text-white transition-colors duration-200 flex items-center justify-center">
                                <i class="fas fa-share-alt text-sm"></i>
                            </button>
                        </div>
                        
                        <!-- Animated Border -->
                        <div class="absolute inset-0 rounded-2xl border-2 border-transparent group-hover:border-gray-500 transition-all duration-300 pointer-events-none"></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-3 text-center py-16">
                    <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-search text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No products found</h3>
                    <p class="text-gray-600">Try adjusting your search criteria or browse all categories.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $stmt->close();
    if ($result) $result->free();
    $conn->close();
    exit;
}

// Rest of the non-AJAX code remains unchanged
$categories = ['food', 'grooming', 'toys', 'health', 'accessories', 'training'];
$brand_sql = "SELECT DISTINCT brand FROM products WHERE is_active = 1 ORDER BY brand";
$brand_result = $conn->query($brand_sql);
$brands = [];
if ($brand_result) {
    while ($row = $brand_result->fetch_assoc()) {
        $brands[] = $row['brand'];
    }
    $brand_result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - FurShield</title>
    
    <!-- Added GSAP and enhanced styling to match home page theme -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        .gradient-text {
            background: linear-gradient(135deg, #4B5563 0%, #6B7280 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .floating-element {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-10px) rotate(1deg); }
            66% { transform: translateY(-5px) rotate(-0.5deg); }
        }

        .morphing-bg {
            background: linear-gradient(-45deg, #e5e7eb, #d1d5db, #9ca3af, #6b7280);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .product-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .product-card:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .sticky-sidebar {
            position: sticky;
            top: 1rem;
            align-self: start;
            z-index: 10;
        }

        .filter-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .form-input {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 2px solid transparent;
            background-clip: padding-box;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: #6b7280;
            box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.1);
            transform: translateY(-2px);
        }

        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(-20px);
            animation: slideIn 0.3s ease-in-out forwards, slideOut 0.3s ease-in-out 2s forwards;
            z-index: 1000;
        }

        @keyframes slideIn {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideOut {
            to { opacity: 0; transform: translateY(-20px); }
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-3d {
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }

        .card-3d:hover {
            transform: rotateX(2deg) rotateY(2deg) scale(1.02);
        }
    </style>
</head>
<body class="bg-gray-50 overflow-x-hidden">
    <!-- Navigation -->
    <?php include "../includes/nav.php";?>

    <!-- Enhanced background with animated elements -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute inset-0 morphing-bg opacity-10"></div>
        <div class="absolute top-20 left-10 w-32 h-32 bg-gray-300/20 rounded-full blur-xl floating-element"></div>
        <div class="absolute top-40 right-20 w-24 h-24 bg-gray-300/20 rounded-full blur-lg floating-element"></div>
        <div class="absolute bottom-32 left-1/4 w-40 h-40 bg-gray-300/10 rounded-full blur-2xl floating-element"></div>
    </div>

    <!-- Products Section -->
    <section class="pt-24 pb-20 relative z-10 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Enhanced header with gradient text and animations -->
            <div class="text-center mb-16 products-header">
                <h2 class="text-4xl md:text-5xl font-bold gradient-text mb-6">Premium Pet Products</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-8">
                    Discover our curated collection of high-quality products designed to keep your pets happy and healthy
                </p>
                <div class="w-24 h-1 bg-gray-500 mx-auto rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Enhanced filters sidebar with better styling -->
                <aside class="lg:col-span-1 filters-sidebar">
                    <div class="filter-card p-8 rounded-2xl shadow-xl card-3d sticky-sidebar">
                        <div class="flex items-center mb-8">
                            <div class="w-12 h-12 bg-gray-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-filter text-white text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold gradient-text">Filters</h3>
                        </div>
                        
                        <form id="filter-form" class="space-y-6">
                            <!-- Search -->
                            <div class="filter-group">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Search Products</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                           class="form-input w-full px-4 py-3 rounded-xl focus:outline-none pl-12"
                                           placeholder="Search for products...">
                                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                </div>
                            </div>

                            <!-- Categories -->
                            <div class="filter-group">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Category</label>
                                <select name="category" class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                            <?= ucfirst($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Brands -->
                            <div class="filter-group">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Brand</label>
                                <select name="brand" class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                    <option value="">All Brands</option>
                                    <?php foreach ($brands as $br): ?>
                                        <option value="<?= htmlspecialchars($br) ?>" <?= $brand === $br ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($br) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Price Range -->
                            <div class="filter-group">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Price Range</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <input type="number" name="price_min" value="<?= $price_min > 0 ? htmlspecialchars($price_min) : '' ?>" placeholder="Min" 
                                           class="form-input px-4 py-3 rounded-xl focus:outline-none" min="0" step="0.01">
                                    <input type="number" name="price_max" value="<?= $price_max < PHP_FLOAT_MAX ? htmlspecialchars($price_max) : '' ?>" placeholder="Max" 
                                           class="form-input px-4 py-3 rounded-xl focus:outline-none" min="0" step="0.01">
                                </div>
                            </div>

                            <!-- Sort -->
                            <div class="filter-group">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Sort By</label>
                                <select name="sort" class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
                                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
                                </select>
                            </div>

                            <button type="button" id="apply-filters" class="w-full bg-gray-500 text-white py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center card-3d">
                                <i class="fas fa-search mr-3"></i>
                                Apply Filters
                            </button>
                        </form>
                    </div>
                </aside>

                <!-- Products Grid -->
                <div class="lg:col-span-3 products-grid-container">
                    <div id="products-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <!-- Enhanced product card with unique design and animations -->
                                <div class="product-card group relative bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-3 overflow-hidden">
                                    <!-- Product Image with Overlay -->
                                    <div class="relative overflow-hidden rounded-t-2xl">
                                        <img src="../<?= htmlspecialchars($row['image_url'] ?? '/placeholder.svg?height=250&width=300') ?>" 
                                             alt="<?= htmlspecialchars($row['name'] ?? 'Product') ?>" 
                                             class="w-full h-56 object-cover transition-transform duration-700 group-hover:scale-110">
                                        
                                        <!-- Gradient Overlay -->
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                        
                                        <!-- Category Badge -->
                                        <div class="absolute top-4 left-4 px-3 py-1 bg-gray-500 text-white text-xs font-semibold rounded-full shadow-lg">
                                            <?= htmlspecialchars(ucfirst($row['category'] ?? 'Product')) ?>
                                        </div>
                                        
                                        <!-- Stock Badge -->
                                        <?php if ($row['stock_quantity'] > 0): ?>
                                            <div class="absolute top-4 right-4 px-3 py-1 bg-green-500 text-white text-xs font-semibold rounded-full shadow-lg">
                                                In Stock
                                            </div>
                                        <?php else: ?>
                                            <div class="absolute top-4 right-4 px-3 py-1 bg-red-500 text-white text-xs font-semibold rounded-full shadow-lg">
                                                Out of Stock
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Quick View Button -->
                                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <button class="quick-view-btn bg-white/90 backdrop-blur-sm text-gray-800 px-6 py-2 rounded-full font-semibold shadow-lg hover:bg-white transition-colors duration-200">
                                                <i class="fas fa-eye mr-2"></i>Quick View
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="p-6 space-y-4">
                                        <!-- Brand -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-600 font-medium bg-gray-50 px-2 py-1 rounded-md">
                                                <?= htmlspecialchars($row['brand'] ?? 'Brand') ?>
                                            </span>
                                           
                                        </div>
                                        
                                        <!-- Product Name -->
                                        <h3 class="text-lg font-bold text-gray-900 line-clamp-2 group-hover:text-gray-700 transition-colors duration-200">
                                            <?= htmlspecialchars($row['name'] ?? 'Unknown Product') ?>
                                        </h3>
                                        
                                        <!-- Description -->
                                        <p class="text-sm text-gray-600 line-clamp-2">
                                            <?= htmlspecialchars($row['description'] ?? 'High-quality pet product designed for your beloved companion.') ?>
                                        </p>
                                        
                                        <!-- Price and Stock -->
                                        <div class="flex items-center justify-between">
                                            <div class="space-y-1">
                                                <div class="text-2xl font-bold text-gray-900">
                                                    $<?= htmlspecialchars(number_format($row['price'] ?? 0, 2)) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($row['stock_quantity'] ?? 0) ?> available
                                                </div>
                                            </div>
                                            
                                            <!-- Add to Cart Button -->
                                            <div class="flex flex-col space-y-2">
                                                <?php if (isset($_SESSION['user_id'])): ?>
                                                    <?php if ($row['stock_quantity'] > 0): ?>
                                                        <button class="add-to-cart bg-gray-500 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center" 
                                                                data-product-id="<?= $row['product_id'] ?>" 
                                                                data-quantity="1">
                                                            <i class="fas fa-cart-plus mr-2"></i>
                                                            Add to Cart
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="bg-gray-400 text-white px-6 py-3 rounded-xl font-semibold cursor-not-allowed flex items-center" disabled>
                                                            <i class="fas fa-times mr-2"></i>
                                                            Out of Stock
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="../auth/login.php" class="bg-gray-500 text-white px-3 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center">
                                                        <i class="fas fa-sign-in-alt mr-2"></i>
                                                        Login
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Floating Action Buttons -->
                                    <div class="absolute top-1/2 right-4 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-all duration-300 space-y-2">
                                        <button class="w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full shadow-lg hover:bg-red-500 hover:text-white transition-colors duration-200 flex items-center justify-center">
                                            <i class="fas fa-heart text-sm"></i>
                                        </button>
                                        <button class="w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full shadow-lg hover:bg-gray-500 hover:text-white transition-colors duration-200 flex items-center justify-center">
                                            <i class="fas fa-share-alt text-sm"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Animated Border -->
                                    <div class="absolute inset-0 rounded-2xl border-2 border-transparent group-hover:border-gray-500 transition-all duration-300 pointer-events-none"></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-span-3 text-center py-16">
                                <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-search text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">No products found</h3>
                                <p class="text-gray-600">Try adjusting your search criteria or browse all categories.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Initialize animations on page load
        window.addEventListener('load', function() {
            // Header animation
            gsap.fromTo('.products-header', 
                { opacity: 0, y: 50 },
                { opacity: 1, y: 0, duration: 1, ease: "power2.out" }
            );

            // Filters sidebar animation
            gsap.fromTo('.filters-sidebar', 
                { opacity: 0, x: -50 },
                { opacity: 1, x: 0, duration: 0.8, ease: "power2.out", delay: 0.3 }
            );

            // Product cards stagger animation
            gsap.utils.toArray('.product-card').forEach((card, index) => {
                gsap.fromTo(card, 
                    { opacity: 0, y: 50, scale: 0.9 },
                    { 
                        opacity: 1, 
                        y: 0, 
                        scale: 1,
                        duration: 0.6, 
                        ease: "back.out(1.7)",
                        delay: 0.5 + (index * 0.1)
                    }
                );
            });

            // Filter groups animation
            gsap.utils.toArray('.filter-group').forEach((group, index) => {
                gsap.fromTo(group, 
                    { opacity: 0, x: -30 },
                    { 
                        opacity: 1, 
                        x: 0, 
                        duration: 0.5, 
                        ease: "power2.out",
                        delay: 0.8 + (index * 0.1)
                    }
                );
            });
        });

        // Enhanced 3D hover effects
        document.querySelectorAll('.card-3d').forEach(card => {
            card.addEventListener('mouseenter', function() {
                gsap.to(this, {
                    rotationX: 2,
                    rotationY: 2,
                    scale: 1.02,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
            
            card.addEventListener('mouseleave', function() {
                gsap.to(this, {
                    rotationX: 0,
                    rotationY: 0,
                    scale: 1,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
        });

        // Form input animations
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                gsap.to(this, {
                    scale: 1.02,
                    duration: 0.2,
                    ease: "power2.out"
                });
            });
            
            input.addEventListener('blur', function() {
                gsap.to(this, {
                    scale: 1,
                    duration: 0.2,
                    ease: "power2.out"
                });
            });
        });

        // AJAX for filters
        let debounceTimer;
        const filterForm = document.getElementById('filter-form');
        const productsGridContainer = document.querySelector('.products-grid-container');
        const applyButton = document.getElementById('apply-filters');

        function applyFilters() {
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);
            params.append('ajax', '1');

            fetch('products.php?' + params.toString())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.text();
                })
                .then(html => {
                    productsGridContainer.innerHTML = html;
                    // Re-animate new product cards
                    gsap.utils.toArray('.product-card').forEach((card, index) => {
                        gsap.fromTo(card, 
                            { opacity: 0, y: 30, scale: 0.9 },
                            { 
                                opacity: 1, 
                                y: 0, 
                                scale: 1,
                                duration: 0.5, 
                                ease: "back.out(1.7)",
                                delay: index * 0.05
                            }
                        );
                    });
                    // Re-bind add to cart buttons
                    bindAddToCartButtons();
                })
                .catch(error => {
                    console.error('Error fetching products:', error);
                    productsGridContainer.innerHTML = '<p class="col-span-3 text-center text-red-600">Error loading products. Please try again.</p>';
                });
        }

        // Function to bind add to cart buttons
        function bindAddToCartButtons() {
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', () => {
                    const productId = button.getAttribute('data-product-id');
                    const quantity = button.getAttribute('data-quantity');

                    // Add to cart animation
                    gsap.to(button, {
                        scale: 0.9,
                        duration: 0.1,
                        yoyo: true,
                        repeat: 1,
                        ease: "power2.inOut"
                    });

                    fetch('products.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=add_to_cart&product_id=${productId}&quantity=${quantity}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'success-message';
                        messageDiv.textContent = data.message;
                        if (data.status === 'error') {
                            messageDiv.style.background = '#ef4444';
                        }
                        document.body.appendChild(messageDiv);
                        setTimeout(() => messageDiv.remove(), 2500);
                    })
                    .catch(error => {
                        console.error('Error adding to cart:', error);
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'success-message';
                        messageDiv.style.background = '#ef4444';
                        messageDiv.textContent = 'Error adding to cart';
                        document.body.appendChild(messageDiv);
                        setTimeout(() => messageDiv.remove(), 2500);
                    });
                });
            });
        }

        // Initial binding of add to cart buttons
        bindAddToCartButtons();

        // Listen to input changes
        filterForm.addEventListener('input', (e) => {
            if (e.target.name === 'search') {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(applyFilters, 500);
            } else {
                applyFilters();
            }
        });

        // Listen to select changes
        filterForm.addEventListener('change', applyFilters);

        // Apply on button click
        applyButton.addEventListener('click', () => {
            gsap.to(applyButton, {
                scale: 0.95,
                duration: 0.1,
                yoyo: true,
                repeat: 1,
                ease: "power2.inOut"
            });
            applyFilters();
        });
    </script>

<?php
if ($stmt) {
    $stmt->close();
}
if ($result) {
    $result->free();
}
$conn->close();
?>
</body>
</html>