<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Create a database connection
$db = new Database();
$conn = $db->getConnection();

// Handle AJAX requests for cart updates
if (isset($_POST['action']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    if ($_POST['action'] === 'update_quantity') {
        $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        if ($cart_id > 0 && $quantity >= 0) {
            if ($quantity == 0) {
                $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $cart_id, $user_id);
            } else {
                // Check stock
                $stmt = $conn->prepare("SELECT p.stock_quantity FROM cart_items c JOIN products p ON c.product_id = p.product_id WHERE c.cart_id = ? AND c.user_id = ?");
                $stmt->bind_param("ii", $cart_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0 && $result->fetch_assoc()['stock_quantity'] >= $quantity) {
                    $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND user_id = ?");
                    $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Insufficient stock']);
                    exit;
                }
            }
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Cart updated']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update cart']);
            }
            $stmt->close();
        }
        exit;
    } elseif ($_POST['action'] === 'remove_item') {
        $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
        if ($cart_id > 0) {
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_id, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Item removed']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
            }
            $stmt->close();
        }
        exit;
    }
}

// Fetch cart items
$cart_items = [];
$total = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT c.cart_id, c.product_id, c.quantity, p.name, p.price, p.image_url, p.stock_quantity 
                            FROM cart_items c 
                            JOIN products p ON c.product_id = p.product_id 
                            WHERE c.user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total += $row['price'] * $row['quantity'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - FurShield</title>
    
    <!-- Added GSAP and enhanced styling to match home page theme -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-3d {
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }

        .card-3d:hover {
            transform: rotateX(2deg) rotateY(2deg) scale(1.02);
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
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .cart-item {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .quantity-controls {
            background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
            border-radius: 12px;
            padding: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        @keyframes slideIn {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideOut {
            to { opacity: 0; transform: translateY(-20px); }
        }

        .pulse-ring {
            animation: pulseRing 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }

        @keyframes pulseRing {
            0% { transform: scale(0.33); }
            80%, 100% { opacity: 0; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 overflow-x-hidden">
    <!-- Navigation -->
    <?php include "../includes/nav.php";?>

    <!-- Enhanced hero section with animated background -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute inset-0 morphing-bg opacity-10"></div>
        <div class="absolute top-20 left-10 w-32 h-32 bg-blue-300/20 rounded-full blur-xl floating-element"></div>
        <div class="absolute top-40 right-20 w-24 h-24 bg-purple-300/20 rounded-full blur-lg floating-element"></div>
        <div class="absolute bottom-32 left-1/4 w-40 h-40 bg-pink-300/10 rounded-full blur-2xl floating-element"></div>
    </div>

    <!-- Cart Section -->
    <section class="pt-24 pb-20 relative z-10 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Enhanced header with gradient text and animations -->
            <div class="text-center mb-12 cart-header">
                <h2 class="text-4xl md:text-5xl font-bold gradient-text mb-4">Your Cart</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Review your selected pet products and proceed to checkout
                </p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto mt-6 rounded-full"></div>
            </div>

            <?php if (empty($cart_items)): ?>
                <!-- Enhanced empty cart state with animations -->
                <div class="text-center empty-cart">
                    <div class="w-32 h-32 mx-auto mb-8 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Your cart is empty</h3>
                    <p class="text-lg text-gray-600 mb-8">Discover amazing products for your beloved pets</p>
                    <a href="products.php" class="btn-primary text-white px-8 py-4 rounded-full font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-300 inline-flex items-center">
                        <i class="fas fa-shopping-bag mr-3"></i>
                        Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <!-- Enhanced cart items with 3D cards and animations -->
                <div class="grid grid-cols-1 gap-6 cart-items">
                    <?php foreach ($cart_items as $index => $item): ?>
                        <div class="cart-item p-6 rounded-2xl shadow-xl card-3d cart-item-card" data-index="<?= $index ?>">
                            <div class="flex flex-col lg:flex-row items-center justify-between space-y-4 lg:space-y-0">
                                <div class="flex items-center space-x-6 flex-1">
                                    <div class="relative">
                                        <img src="<?= htmlspecialchars($item['image_url'] ?? '/placeholder.svg?height=100&width=100') ?>" 
                                             alt="<?= htmlspecialchars($item['name'] ?? 'Product') ?>" 
                                             class="w-24 h-24 object-cover rounded-xl shadow-lg">
                                        <div class="absolute -top-2 -right-2 w-6 h-6 bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center">
                                            <i class="fas fa-check text-white text-xs"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($item['name'] ?? 'Unknown') ?></h3>
                                        <div class="flex items-center space-x-4 text-sm text-gray-600">
                                            <span class="flex items-center">
                                                <i class="fas fa-tag mr-1 text-blue-500"></i>
                                                $<?= htmlspecialchars(number_format($item['price'] ?? 0, 2)) ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-boxes mr-1 text-green-500"></i>
                                                Stock: <?= htmlspecialchars($item['stock_quantity'] ?? 0) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-6">
                                    <!-- Enhanced quantity controls with better styling -->
                                    <div class="quantity-controls flex items-center space-x-3">
                                        <button class="decrease-quantity w-10 h-10 bg-white rounded-full shadow-md hover:shadow-lg transition-all duration-300 flex items-center justify-center text-gray-600 hover:text-red-500" data-cart-id="<?= $item['cart_id'] ?>">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="quantity-input w-16 px-3 py-2 border-0 bg-white rounded-lg text-center font-semibold shadow-inner" 
                                               value="<?= $item['quantity'] ?>" 
                                               data-cart-id="<?= $item['cart_id'] ?>" 
                                               min="0" 
                                               max="<?= $item['stock_quantity'] ?>">
                                        <button class="increase-quantity w-10 h-10 bg-white rounded-full shadow-md hover:shadow-lg transition-all duration-300 flex items-center justify-center text-gray-600 hover:text-green-500" data-cart-id="<?= $item['cart_id'] ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="text-right">
                                        <p class="text-2xl font-bold gradient-text">$<?= htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)) ?></p>
                                        <p class="text-sm text-gray-500">Subtotal</p>
                                    </div>
                                    
                                    <button class="remove-item w-12 h-12 bg-red-50 hover:bg-red-100 rounded-full transition-all duration-300 flex items-center justify-center text-red-500 hover:text-red-700 hover:scale-110" data-cart-id="<?= $item['cart_id'] ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Enhanced checkout section with better styling -->
                <div class="mt-12 bg-white p-8 rounded-2xl shadow-xl card-3d checkout-summary">
                    <div class="flex flex-col lg:flex-row justify-between items-center space-y-6 lg:space-y-0">
                        <div class="text-center lg:text-left">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Order Summary</h3>
                            <p class="text-gray-600">Review your items before checkout</p>
                        </div>
                        
                        <div class="flex flex-col lg:flex-row items-center space-y-4 lg:space-y-0 lg:space-x-8">
                            <div class="text-center lg:text-right">
                                <p class="text-sm text-gray-500 uppercase tracking-wide">Total Amount</p>
                                <p class="text-4xl font-bold gradient-text">$<?= htmlspecialchars(number_format($total, 2)) ?></p>
                            </div>
                            
                            <a href="payment.php" class="btn-success text-white px-8 py-4 rounded-full font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-300 inline-flex">
                                <i class="fas fa-credit-card mr-3"></i>
                                Proceed to Checkout
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Initialize animations on page load
        window.addEventListener('load', function() {
            // Header animation
            gsap.fromTo('.cart-header', 
                { opacity: 0, y: 50 },
                { opacity: 1, y: 0, duration: 1, ease: "power2.out" }
            );

            // Empty cart animation
            if (document.querySelector('.empty-cart')) {
                gsap.fromTo('.empty-cart', 
                    { opacity: 0, scale: 0.9 },
                    { opacity: 1, scale: 1, duration: 0.8, ease: "back.out(1.7)", delay: 0.3 }
                );
            }

            // Cart items animation
            gsap.utils.toArray('.cart-item-card').forEach((card, index) => {
                gsap.fromTo(card, 
                    { opacity: 0, y: 50, rotationX: -15 },
                    { 
                        opacity: 1, 
                        y: 0, 
                        rotationX: 0,
                        duration: 0.8, 
                        ease: "power2.out",
                        delay: index * 0.1
                    }
                );
            });

            // Checkout summary animation
            if (document.querySelector('.checkout-summary')) {
                gsap.fromTo('.checkout-summary', 
                    { opacity: 0, y: 30 },
                    { opacity: 1, y: 0, duration: 0.8, ease: "power2.out", delay: 0.5 }
                );
            }
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

        // Handle quantity updates
        function updateQuantity(cartId, quantity) {
            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&cart_id=${cartId}&quantity=${quantity}`
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
                if (data.status === 'success') {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error updating quantity:', error);
                const messageDiv = document.createElement('div');
                messageDiv.className = 'success-message';
                messageDiv.style.background = '#ef4444';
                messageDiv.textContent = 'Error updating cart';
                document.body.appendChild(messageDiv);
                setTimeout(() => messageDiv.remove(), 2500);
            });
        }

        // Handle remove item
        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', () => {
                const cartId = button.getAttribute('data-cart-id');
                
                // Add removal animation
                const cartItem = button.closest('.cart-item-card');
                gsap.to(cartItem, {
                    opacity: 0,
                    x: 100,
                    scale: 0.8,
                    duration: 0.5,
                    ease: "power2.in",
                    onComplete: () => {
                        fetch('cart.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=remove_item&cart_id=${cartId}`
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
                            if (data.status === 'success') {
                                location.reload();
                            }
                        })
                        .catch(error => {
                            console.error('Error removing item:', error);
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'success-message';
                            messageDiv.style.background = '#ef4444';
                            messageDiv.textContent = 'Error removing item';
                            document.body.appendChild(messageDiv);
                            setTimeout(() => messageDiv.remove(), 2500);
                        });
                    }
                });
            });
        });

        // Handle quantity input
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', () => {
                const cartId = input.getAttribute('data-cart-id');
                const quantity = parseInt(input.value);
                if (quantity >= 0) {
                    updateQuantity(cartId, quantity);
                }
            });
        });

        // Handle increase/decrease buttons with animations
        document.querySelectorAll('.increase-quantity').forEach(button => {
            button.addEventListener('click', () => {
                gsap.to(button, { scale: 0.9, duration: 0.1, yoyo: true, repeat: 1 });
                
                const cartId = button.getAttribute('data-cart-id');
                const input = button.parentElement.querySelector('.quantity-input');
                const quantity = parseInt(input.value) + 1;
                if (quantity <= parseInt(input.getAttribute('max'))) {
                    input.value = quantity;
                    updateQuantity(cartId, quantity);
                }
            });
        });

        document.querySelectorAll('.decrease-quantity').forEach(button => {
            button.addEventListener('click', () => {
                gsap.to(button, { scale: 0.9, duration: 0.1, yoyo: true, repeat: 1 });
                
                const cartId = button.getAttribute('data-cart-id');
                const input = button.parentElement.querySelector('.quantity-input');
                const quantity = parseInt(input.value) - 1;
                if (quantity >= 0) {
                    input.value = quantity;
                    updateQuantity(cartId, quantity);
                }
            });
        });
    </script>

<?php
$conn->close();
?>
</body>
</html>
