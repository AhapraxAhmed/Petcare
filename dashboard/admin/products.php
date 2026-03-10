<?php
require_once '../../middleware/auth.php';
require_once '../../config/database.php'; // Assuming Database class is here
requireRole('admin');

$user = a();

$db = new Database();
$conn = $db->getConnection();

// Handle product actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ensure upload directory exists
$upload_dir = __DIR__ . '/../../uploads/products/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $brand = $_POST['brand'];
    $image_url = '';

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;
        $relative_path = 'Uploads/products/' . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            $image_url = $relative_path; // Store relative path
        } else {
            // Handle upload error
            error_log("Failed to upload image: " . $_FILES['image']['error']);
        }
    }

    $stmt = $conn->prepare("INSERT INTO products (name, category, description, price, stock_quantity, brand, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdiss", $name, $category, $description, $price, $stock_quantity, $brand, $image_url);
    $stmt->execute();
    header("Location: products.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $product_id) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $brand = $_POST['brand'];
    $image_url = isset($_POST['existing_image']) ? $_POST['existing_image'] : '';

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;
        $relative_path = 'Uploads/products/' . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            $image_url = $relative_path; // Update with new image path
        } else {
            // Handle upload error
            error_log("Failed to upload image: " . $_FILES['image']['error']);
        }
    }

    $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock_quantity = ?, brand = ?, image_url = ? WHERE product_id = ?");
    $stmt->bind_param("sssdissi", $name, $category, $description, $price, $stock_quantity, $brand, $image_url, $product_id);
    $stmt->execute();
    header("Location: products.php");
    exit;
}

if ($action === 'delete' && $product_id) {
    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    header("Location: products.php");
    exit;
}

// Fetch product for update
$product = null;
if ($action === 'update' && $product_id) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ? AND is_active = 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
}

// Fetch products
$products = $conn->query("SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - FurShield</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .animate-fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
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
                        <h2 class="text-2xl font-bold text-gray-800 ml-4">Products</h2>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600"><?php echo date('l, F j, Y'); ?></div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <?php if ($action === 'add' || ($action === 'update' && $product)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-8 animate-fade-in">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6"><?php echo $action === 'add' ? 'Add New Product' : 'Update Product'; ?></h3>
                        <form method="POST" action="products.php?action=<?php echo $action; ?><?php echo $action === 'update' ? '&id=' . $product_id : ''; ?>" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" value="<?php echo $action === 'update' ? htmlspecialchars($product['name']) : ''; ?>" required class="mt-1 p-2 w-full border rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <select name="category" required class="mt-1 p-2 w-full border rounded-md">
                                    <option value="food" <?php echo $action === 'update' && $product['category'] === 'food' ? 'selected' : ''; ?>>Food</option>
                                    <option value="grooming" <?php echo $action === 'update' && $product['category'] === 'grooming' ? 'selected' : ''; ?>>Grooming</option>
                                    <option value="toys" <?php echo $action === 'update' && $product['category'] === 'toys' ? 'selected' : ''; ?>>Toys</option>
                                    <option value="health" <?php echo $action === 'update' && $product['category'] === 'health' ? 'selected' : ''; ?>>Health</option>
                                    <option value="accessories" <?php echo $action === 'update' && $product['category'] === 'accessories' ? 'selected' : ''; ?>>Accessories</option>
                                    <option value="training" <?php echo $action === 'update' && $product['category'] === 'training' ? 'selected' : ''; ?>>Training</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" class="mt-1 p-2 w-full border rounded-md"><?php echo $action === 'update' ? htmlspecialchars($product['description']) : ''; ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price</label>
                                <input type="number" step="0.01" name="price" value="<?php echo $action === 'update' ? $product['price'] : ''; ?>" required class="mt-1 p-2 w-full border rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                                <input type="number" name="stock_quantity" value="<?php echo $action === 'update' ? $product['stock_quantity'] : ''; ?>" required class="mt-1 p-2 w-full border rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Brand</label>
                                <input type="text" name="brand" value="<?php echo $action === 'update' ? htmlspecialchars($product['brand'] ?? '') : ''; ?>" class="mt-1 p-2 w-full border rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Image</label>
                                <input type="file" name="image" accept="image/*" class="mt-1 p-2 w-full border rounded-md">
                                <?php if ($action === 'update' && !empty($product['image_url'])): ?>
                                    <img src="/<?php echo htmlspecialchars($product['image_url']); ?>" alt="Product Image" class="mt-2 h-20 w-20 object-cover">
                                    <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($product['image_url']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600"><?php echo $action === 'add' ? 'Add Product' : 'Update Product'; ?></button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 animate-fade-in">
                        <div class="flex justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">Products</h3>
                            <a href="products.php?action=add" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Add Product</a>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-600">
                                    <th class="pb-2">Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Brand</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr class="border-t text-sm">
                                        <td class="py-3">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" alt="Product Image" class="h-12 w-12 object-cover">
                                            <?php else: ?>
                                                No Image
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo ucfirst($product['category']); ?></td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td><?php echo $product['stock_quantity']; ?></td>
                                        <td><?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="products.php?action=update&id=<?php echo $product['product_id']; ?>" class="text-blue-500 hover:underline mr-2">Update</a>
                                            <a href="products.php?action=delete&id=<?php echo $product['product_id']; ?>" class="text-red-500 hover:underline">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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