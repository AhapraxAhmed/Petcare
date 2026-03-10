<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Create a database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch cart items for order details
$total = 0;
$cart_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT c.quantity, p.price, p.name 
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

// Assuming billing address is submitted and stored in session (from payment form)
$order = [
    'address' => $_SESSION['billing_address'] ?? '1234 Example St, City, State, ZIP, Country', // Fallback address
    'cardholder_name' => $_SESSION['cardholder_name'] ?? 'John Doe',
    'order_date' => date('Y-m-d H:i:s'),
];

// For demo purposes, we'll assume the order is placed successfully
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed - FurShield</title>
    
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

        .success-checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #10b981;
            stroke-miterlimit: 10;
            margin: 10% auto;
            box-shadow: inset 0px 0px 0px #10b981;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }

        .success-checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #10b981;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .success-checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        @keyframes scale {
            0%, 100% {
                transform: none;
            }
            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }

        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 30px #10b981;
            }
        }

        .order-item {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .pulse-ring {
            animation: pulseRing 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }

        @keyframes pulseRing {
            0% { transform: scale(0.33); }
            80%, 100% { opacity: 0; }
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #f39c12;
            animation: confetti-fall 3s linear infinite;
        }

        .confetti:nth-child(1) { left: 10%; animation-delay: 0s; background: #e74c3c; }
        .confetti:nth-child(2) { left: 20%; animation-delay: 0.5s; background: #f39c12; }
        .confetti:nth-child(3) { left: 30%; animation-delay: 1s; background: #2ecc71; }
        .confetti:nth-child(4) { left: 40%; animation-delay: 1.5s; background: #3498db; }
        .confetti:nth-child(5) { left: 50%; animation-delay: 2s; background: #9b59b6; }
        .confetti:nth-child(6) { left: 60%; animation-delay: 2.5s; background: #e67e22; }
        .confetti:nth-child(7) { left: 70%; animation-delay: 3s; background: #1abc9c; }
        .confetti:nth-child(8) { left: 80%; animation-delay: 3.5s; background: #34495e; }
        .confetti:nth-child(9) { left: 90%; animation-delay: 4s; background: #e91e63; }

        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 overflow-x-hidden">
    <!-- Navigation -->
    <?php include "../includes/nav.php"; ?>

    <!-- Enhanced background with animated elements -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute inset-0 morphing-bg opacity-10"></div>
        <div class="absolute top-20 left-10 w-32 h-32 bg-green-300/20 rounded-full blur-xl floating-element"></div>
        <div class="absolute top-40 right-20 w-24 h-24 bg-blue-300/20 rounded-full blur-lg floating-element"></div>
        <div class="absolute bottom-32 left-1/4 w-40 h-40 bg-purple-300/10 rounded-full blur-2xl floating-element"></div>
        
        <!-- Confetti Animation -->
        <div class="confetti-container">
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
        </div>
    </div>

    <!-- Order Confirmation Section -->
    <section class="pt-24 pb-20 relative z-10 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Enhanced success header with animated checkmark -->
            <div class="text-center mb-12 success-header">
                <div class="success-checkmark">
                    <svg class="success-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="success-checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="success-checkmark__check" fill="none" d="m14.1 27.2l7.1 7.2 16.7-16.8"/>
                    </svg>
                </div>
                
                <h2 class="text-4xl md:text-5xl font-bold gradient-text mb-4">Order Confirmed!</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-6">
                    Thank you for your purchase. Your order has been successfully placed and is being processed.
                </p>
                <div class="w-24 h-1 bg-gradient-to-r from-green-400 to-blue-500 mx-auto rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Enhanced order details with better styling -->
                <div class="lg:col-span-2 order-details">
                    <div class="order-item p-8 rounded-2xl shadow-xl card-3d">
                        <div class="flex items-center mb-6">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-receipt text-white text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold gradient-text">Order Details</h3>
                        </div>
                        
                        <div class="space-y-8">
                            <!-- Order Items -->
                            <div class="order-items">
                                <h4 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                                    <i class="fas fa-shopping-bag mr-2 text-blue-500"></i>
                                    Items Ordered
                                </h4>
                                <div class="space-y-3">
                                    <?php foreach ($cart_items as $index => $item): ?>
                                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-xl order-item-row" data-index="<?= $index ?>">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-3 h-3 bg-gradient-to-r from-green-400 to-blue-500 rounded-full"></div>
                                                <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($item['name']); ?></span>
                                                <span class="text-sm text-gray-500">x<?php echo $item['quantity']; ?></span>
                                            </div>
                                            <span class="text-lg font-bold text-gray-900">$<?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="border-t border-gray-200 pt-6 mt-6">
                                    <div class="flex justify-between items-center p-4 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl">
                                        <span class="text-xl font-bold text-gray-900">Total Amount</span>
                                        <span class="text-2xl font-bold gradient-text">$<?php echo htmlspecialchars(number_format($total, 2)); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Billing Information -->
                            <div class="billing-info">
                                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-credit-card mr-2 text-green-500"></i>
                                    Billing Information
                                </h4>
                                <div class="bg-gray-50 p-6 rounded-xl space-y-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-user mr-3 text-blue-500"></i>
                                        <span class="text-gray-700">Cardholder: <strong><?php echo htmlspecialchars($order['cardholder_name']); ?></strong></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt mr-3 text-red-500"></i>
                                        <span class="text-gray-700">Address: <strong><?php echo htmlspecialchars($order['address']); ?></strong></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar mr-3 text-purple-500"></i>
                                        <span class="text-gray-700">Order Date: <strong><?php echo htmlspecialchars($order['order_date']); ?></strong></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Shipping Address with Map -->
                            <div class="shipping-info">
                                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-shipping-fast mr-2 text-orange-500"></i>
                                    Shipping Address
                                </h4>
                                <div class="rounded-xl overflow-hidden shadow-lg">
                                    <iframe src="https://www.google.com/maps/embed/v1/place?key=AIzaSyAOVYRIgupAurZup5y1PRh8Ismb1A3lLao&q=<?php echo urlencode($order['address'] ?? ''); ?>" 
                                            width="100%" height="300" style="border:0;" allowfullscreen loading="lazy"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced actions sidebar with better styling -->
                <div class="lg:col-span-1 actions-sidebar">
                    <div class="order-item p-8 rounded-2xl shadow-xl card-3d">
                        <div class="flex items-center mb-6">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-teal-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-check-circle text-white text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold gradient-text">What's Next?</h3>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="bg-green-50 p-6 rounded-xl border-l-4 border-green-400">
                                <div class="flex items-start">
                                    <i class="fas fa-envelope text-green-500 mt-1 mr-3"></i>
                                    <div>
                                        <h4 class="font-semibold text-green-800 mb-2">Email Confirmation</h4>
                                        <p class="text-sm text-green-700">You'll receive an email confirmation with your order details and tracking information.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 p-6 rounded-xl border-l-4 border-blue-400">
                                <div class="flex items-start">
                                    <i class="fas fa-truck text-blue-500 mt-1 mr-3"></i>
                                    <div>
                                        <h4 class="font-semibold text-blue-800 mb-2">Shipping Updates</h4>
                                        <p class="text-sm text-blue-700">Track your order status and delivery progress through your account dashboard.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <a href="products.php" class="block text-center bg-gradient-to-r from-blue-500 to-purple-500 text-white px-6 py-4 rounded-full font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-300 pulse-ring">
                                    <i class="fas fa-shopping-bag mr-2"></i>
                                    Continue Shopping
                                </a>
                                
                                <a href="../dashboard/index.php" class="block text-center border-2 border-blue-500 text-blue-600 px-6 py-4 rounded-full font-semibold hover:bg-blue-500 hover:text-white transition-all duration-300">
                                    <i class="fas fa-tachometer-alt mr-2"></i>
                                    View Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Initialize animations on page load
        window.addEventListener('load', function() {
            // Success header animation
            gsap.fromTo('.success-header', 
                { opacity: 0, y: 50, scale: 0.9 },
                { opacity: 1, y: 0, scale: 1, duration: 1, ease: "back.out(1.7)" }
            );

            // Order details animation
            gsap.fromTo('.order-details', 
                { opacity: 0, x: -50 },
                { opacity: 1, x: 0, duration: 0.8, ease: "power2.out", delay: 0.3 }
            );

            // Actions sidebar animation
            gsap.fromTo('.actions-sidebar', 
                { opacity: 0, x: 50 },
                { opacity: 1, x: 0, duration: 0.8, ease: "power2.out", delay: 0.5 }
            );

            // Order items stagger animation
            gsap.utils.toArray('.order-item-row').forEach((item, index) => {
                gsap.fromTo(item, 
                    { opacity: 0, y: 20 },
                    { 
                        opacity: 1, 
                        y: 0, 
                        duration: 0.5, 
                        ease: "power2.out",
                        delay: 0.8 + (index * 0.1)
                    }
                );
            });

            // Billing and shipping info animations
            gsap.fromTo('.billing-info', 
                { opacity: 0, y: 30 },
                { opacity: 1, y: 0, duration: 0.6, ease: "power2.out", delay: 1.2 }
            );

            gsap.fromTo('.shipping-info', 
                { opacity: 0, y: 30 },
                { opacity: 1, y: 0, duration: 0.6, ease: "power2.out", delay: 1.4 }
            );

            // Confetti animation trigger
            setTimeout(() => {
                createConfetti();
            }, 1000);
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

        // Create additional confetti particles
        function createConfetti() {
            const colors = ['#e74c3c', '#f39c12', '#2ecc71', '#3498db', '#9b59b6', '#e67e22', '#1abc9c', '#34495e', '#e91e63'];
            
            for (let i = 0; i < 20; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                
                document.querySelector('.confetti-container').appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => {
                    confetti.remove();
                }, 5000);
            }
        }

        // Button hover animations
        document.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('mouseenter', function() {
                gsap.to(this, {
                    scale: 1.05,
                    duration: 0.2,
                    ease: "power2.out"
                });
            });
            
            link.addEventListener('mouseleave', function() {
                gsap.to(this, {
                    scale: 1,
                    duration: 0.2,
                    ease: "power2.out"
                });
            });
        });
    </script>

<?php
$conn->close();
?>
</body>
</html>
