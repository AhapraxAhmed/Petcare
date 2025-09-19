<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Create a database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch cart items for total
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_number = $_POST['card_number'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $cardholder_name = $_POST['cardholder_name'] ?? '';
    $street_address = $_POST['street_address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    $country = $_POST['country'] ?? '';

    // Validate all fields are filled
    if (!empty($card_number) && !empty($expiry_date) && !empty($cvv) && 
        !empty($cardholder_name) && !empty($street_address) && 
        !empty($city) && !empty($state) && !empty($zip_code) && !empty($country)) {
        // Store billing information in session
        $_SESSION['billing_address'] = "$street_address, $city, $state, $zip_code, $country";
        $_SESSION['cardholder_name'] = $cardholder_name;

        // Clear the cart for the user
        if (isset($_SESSION['user_id'])) {
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }

        // Redirect to order_placed.php
        header("Location: order_placed.php");
        exit();
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - FurShield</title>
    
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

        .payment-form {
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .secure-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .payment-step {
            position: relative;
        }

        .payment-step::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 40px;
            height: calc(100% - 40px);
            width: 2px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            opacity: 0.3;
        }

        .payment-step:last-child::before {
            display: none;
        }

        .step-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }

        .success-message, .error-message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(-20px);
            animation: slideIn 0.3s ease-in-out forwards, slideOut 0.3s ease-in-out 2s forwards;
            z-index: 1000;
        }

        .success-message {
            background: #10b981;
            color: white;
        }

        .error-message {
            background: #ef4444;
            color: white;
        }

        @keyframes slideIn {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideOut {
            to { opacity: 0; transform: translateY(-20px); }
        }

        .credit-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .credit-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(180deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 overflow-x-hidden">
    <!-- Navigation -->
    <?php include "../includes/nav.php";?>

    <!-- Enhanced background with animated elements -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute inset-0 morphing-bg opacity-10"></div>
        <div class="absolute top-20 left-10 w-32 h-32 bg-blue-300/20 rounded-full blur-xl floating-element"></div>
        <div class="absolute top-40 right-20 w-24 h-24 bg-purple-300/20 rounded-full blur-lg floating-element"></div>
        <div class="absolute bottom-32 left-1/4 w-40 h-40 bg-green-300/10 rounded-full blur-2xl floating-element"></div>
    </div>

    <!-- Payment Section -->
    <section class="pt-24 pb-20 relative z-10 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Enhanced header with gradient text and security badges -->
            <div class="text-center mb-12 payment-header">
                <div class="flex justify-center items-center mb-6">
                    <div class="secure-badge px-4 py-2 rounded-full text-white text-sm font-semibold mr-4">
                        <i class="fas fa-shield-alt mr-2"></i>
                        SSL Secured
                    </div>
                    <div class="secure-badge px-4 py-2 rounded-full text-white text-sm font-semibold">
                        <i class="fas fa-lock mr-2"></i>
                        256-bit Encryption
                    </div>
                </div>
                
                <h2 class="text-4xl md:text-5xl font-bold gradient-text mb-4">Secure Checkout</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-6">
                    Complete your purchase with confidence using our secure payment system
                </p>
                <div class="w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Enhanced payment form with better styling and animations -->
                <div class="lg:col-span-2 payment-form-container">
                    <div class="payment-form p-8 rounded-2xl shadow-xl card-3d">
                        <div class="flex items-center mb-8">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-credit-card text-white text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold gradient-text">Payment Details</h3>
                        </div>
                        
                        <!-- Payment Steps -->
                        <div class="flex items-center mb-8 payment-steps">
                            <div class="payment-step flex items-center mr-8">
                                <div class="step-icon">1</div>
                                <span class="ml-3 text-sm font-medium text-gray-700">Card Info</span>
                            </div>
                            <div class="payment-step flex items-center mr-8">
                                <div class="step-icon">2</div>
                                <span class="ml-3 text-sm font-medium text-gray-700">Billing</span>
                            </div>
                            <div class="payment-step flex items-center">
                                <div class="step-icon">3</div>
                                <span class="ml-3 text-sm font-medium text-gray-700">Confirm</span>
                            </div>
                        </div>
                        
                        <form id="payment-form" method="POST" action="">
                            <div class="space-y-8">
                                <!-- Credit Card Preview -->
                                <div class="credit-card mb-8">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="text-sm opacity-80">FurShield Card</div>
                                        <i class="fas fa-wifi text-xl opacity-60"></i>
                                    </div>
                                    <div class="text-lg font-mono mb-4" id="card-display">**** **** **** ****</div>
                                    <div class="flex justify-between items-end">
                                        <div>
                                            <div class="text-xs opacity-80 mb-1">CARDHOLDER</div>
                                            <div class="text-sm font-semibold" id="name-display">YOUR NAME</div>
                                        </div>
                                        <div>
                                            <div class="text-xs opacity-80 mb-1">EXPIRES</div>
                                            <div class="text-sm font-semibold" id="expiry-display">MM/YY</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Information -->
                                <div class="card-info-section">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-credit-card mr-2 text-blue-500"></i>
                                        Card Information
                                    </h4>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                                            <input type="text" name="card_number" id="card_number" placeholder="1234 5678 9012 3456" 
                                                   class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Expiration Date</label>
                                                <input type="text" name="expiry_date" id="expiry_date" placeholder="MM/YY" 
                                                       class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                                                <input type="text" name="cvv" placeholder="123" 
                                                       class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Cardholder Name</label>
                                            <input type="text" name="cardholder_name" id="cardholder_name" placeholder="John Doe" 
                                                   class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Billing Address -->
                                <div class="billing-section">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-map-marker-alt mr-2 text-green-500"></i>
                                        Billing Address
                                    </h4>
                                    <div class="space-y-4">
                                        <input type="text" name="street_address" placeholder="Street Address" 
                                               class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                        <div class="grid grid-cols-2 gap-4">
                                            <input type="text" name="city" placeholder="City" 
                                                   class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                            <input type="text" name="state" placeholder="State" 
                                                   class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <input type="text" name="zip_code" placeholder="ZIP Code" 
                                                   class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                            <input type="text" name="country" placeholder="Country" 
                                                   class="form-input w-full px-4 py-3 rounded-xl focus:outline-none">
                                        </div>
                                    </div>
                                </div>
                                
                                <button id="submit-payment" type="submit" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center card-3d">
                                    <i class="fas fa-shield-alt mr-3"></i>
                                    Complete Secure Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Enhanced order summary with better styling -->
                <div class="lg:col-span-1 order-summary-container">
                    <div class="payment-form p-8 rounded-2xl shadow-xl card-3d">
                        <div class="flex items-center mb-6">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-receipt text-white text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold gradient-text">Order Summary</h3>
                        </div>
                        
                        <div class="space-y-4 mb-6">
                            <?php foreach ($cart_items as $index => $item): ?>
                                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-xl order-item" data-index="<?= $index ?>">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($item['name'] ?? 'Item'); ?></span>
                                        <span class="text-xs text-gray-500 block">Qty: <?php echo $item['quantity']; ?></span>
                                    </div>
                                    <span class="text-lg font-bold text-gray-900">$<?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-6 mb-6">
                            <div class="flex justify-between items-center p-4 bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl">
                                <span class="text-xl font-bold text-gray-900">Total</span>
                                <span class="text-2xl font-bold gradient-text">$<?php echo htmlspecialchars(number_format($total, 2)); ?></span>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <a href="cart.php" class="block text-center border-2 border-blue-500 text-blue-600 px-6 py-3 rounded-full font-semibold hover:bg-blue-500 hover:text-white transition-all duration-300">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Cart
                            </a>
                            
                            <div class="text-center text-xs text-gray-500 space-y-2">
                                <div class="flex items-center justify-center">
                                    <i class="fas fa-lock mr-2"></i>
                                    Your payment information is secure and encrypted
                                </div>
                                <div class="flex items-center justify-center">
                                    <i class="fas fa-shield-alt mr-2"></i>
                                    Protected by FurShield Security
                                </div>
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
            // Header animation
            gsap.fromTo('.payment-header', 
                { opacity: 0, y: 50 },
                { opacity: 1, y: 0, duration: 1, ease: "power2.out" }
            );

            // Payment form animation
            gsap.fromTo('.payment-form-container', 
                { opacity: 0, x: -50 },
                { opacity: 1, x: 0, duration: 0.8, ease: "power2.out", delay: 0.3 }
            );

            // Order summary animation
            gsap.fromTo('.order-summary-container', 
                { opacity: 0, x: 50 },
                { opacity: 1, x: 0, duration: 0.8, ease: "power2.out", delay: 0.5 }
            );

            // Payment steps animation
            gsap.utils.toArray('.payment-step').forEach((step, index) => {
                gsap.fromTo(step, 
                    { opacity: 0, scale: 0.8 },
                    { 
                        opacity: 1, 
                        scale: 1, 
                        duration: 0.5, 
                        ease: "back.out(1.7)",
                        delay: 0.8 + (index * 0.1)
                    }
                );
            });

            // Order items stagger animation
            gsap.utils.toArray('.order-item').forEach((item, index) => {
                gsap.fromTo(item, 
                    { opacity: 0, y: 20 },
                    { 
                        opacity: 1, 
                        y: 0, 
                        duration: 0.5, 
                        ease: "power2.out",
                        delay: 1 + (index * 0.1)
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

        // Credit card preview updates
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || '';
            e.target.value = formattedValue;
            
            let displayValue = formattedValue || '**** **** **** ****';
            if (formattedValue.length < 19) {
                displayValue = formattedValue + '**** **** **** ****'.substring(formattedValue.length);
            }
            document.getElementById('card-display').textContent = displayValue;
        });

        document.getElementById('cardholder_name').addEventListener('input', function(e) {
            document.getElementById('name-display').textContent = e.target.value.toUpperCase() || 'YOUR NAME';
        });

        document.getElementById('expiry_date').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
            document.getElementById('expiry-display').textContent = value || 'MM/YY';
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

        // Client-side form validation and error message
        document.getElementById('payment-form').addEventListener('submit', (event) => {
            const submitBtn = document.getElementById('submit-payment');
            
            // Animate submit button
            gsap.to(submitBtn, {
                scale: 0.95,
                duration: 0.1,
                yoyo: true,
                repeat: 1,
                ease: "power2.inOut"
            });

            const inputs = document.querySelectorAll('#payment-form input');
            let allFilled = true;
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    allFilled = false;
                    // Shake animation for empty inputs
                    gsap.to(input, {
                        x: [-10, 10, -10, 10, 0],
                        duration: 0.5,
                        ease: "power2.inOut"
                    });
                }
            });

            if (!allFilled) {
                event.preventDefault();
                const messageDiv = document.createElement('div');
                messageDiv.className = 'error-message';
                messageDiv.textContent = 'Please fill in all required fields.';
                document.body.appendChild(messageDiv);
                setTimeout(() => messageDiv.remove(), 2500);
            } else {
                // Success animation
                gsap.to(submitBtn, {
                    backgroundColor: '#10b981',
                    duration: 0.3,
                    ease: "power2.out"
                });
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i>Processing...';
            }
        });

        <?php if (isset($error)): ?>
            // Show error message if server-side validation fails
            document.addEventListener('DOMContentLoaded', () => {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'error-message';
                messageDiv.textContent = '<?php echo htmlspecialchars($error); ?>';
                document.body.appendChild(messageDiv);
                setTimeout(() => messageDiv.remove(), 2500);
            });
        <?php endif; ?>
    </script>

<?php
$conn->close();
?>
</body>
</html>
