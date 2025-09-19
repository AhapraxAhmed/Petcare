<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FurShield - Every Paw/Wing Deserves a Shield of Love</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .parallax-bg {
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .floating-animation {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .fade-in-up {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease-out;
        }
        
        .fade-in-up.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-gray-50">
<?php include "../includes/nav.php";?>

    <!-- Hero Section -->
    <section id="home" class="relative min-h-screen flex items-center justify-center parallax-bg" style="background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('/placeholder.svg?height=800&width=1200');">
        <div class="text-center text-white px-4 max-w-4xl mx-auto">
            <h1 class="text-5xl md:text-7xl font-bold mb-6 fade-in-up">
                Every Paw/Wing Deserves a 
                <span class="text-yellow-400">Shield of Love</span>
            </h1>
            <p class="text-xl md:text-2xl mb-8 fade-in-up">
                Comprehensive pet care management for owners, veterinarians, and shelters
            </p>
            <div class="space-x-4 fade-in-up">
                <a href="../auth/register.php" class="bg-blue-600 text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-blue-700 transition-all transform hover:scale-105 inline-block">
                    Get Started
                </a>
                <a href="#features" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-gray-900 transition-all inline-block">
                    Learn More
                </a>
            </div>
        </div>
        
        <!-- Floating elements -->
        <div class="absolute top-20 left-10 floating-animation">
            <i class="fas fa-paw text-4xl text-yellow-400 opacity-70"></i>
        </div>
        <div class="absolute top-40 right-20 floating-animation" style="animation-delay: 1s;">
            <i class="fas fa-heart text-3xl text-red-400 opacity-70"></i>
        </div>
        <div class="absolute bottom-40 left-20 floating-animation" style="animation-delay: 2s;">
            <i class="fas fa-bone text-3xl text-white opacity-70"></i>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 fade-in-up">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Comprehensive Pet Care Platform</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    FurShield brings together pet owners, veterinarians, and shelters in one unified platform
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Pet Owner Panel -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-2 fade-in-up">
                    <div class="text-center">
                        <div class="bg-blue-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Pet Owners</h3>
                        <p class="text-gray-600 mb-4">Manage pet profiles, health records, appointments, and shop for pet products</p>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Pet profile management</li>
                            <li>• Health tracking</li>
                            <li>• Appointment booking</li>
                            <li>• Product shopping</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Veterinarian Panel -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-2 fade-in-up">
                    <div class="text-center">
                        <div class="bg-green-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-stethoscope text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Veterinarians</h3>
                        <p class="text-gray-600 mb-4">Access patient records, manage appointments, and log treatments</p>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Patient management</li>
                            <li>• Medical records</li>
                            <li>• Treatment logging</li>
                            <li>• Schedule management</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Shelter Panel -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-2 fade-in-up">
                    <div class="text-center">
                        <div class="bg-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-home text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Animal Shelters</h3>
                        <p class="text-gray-600 mb-4">List adoptable pets, manage care records, and coordinate adoptions</p>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Adoption listings</li>
                            <li>• Care tracking</li>
                            <li>• Adopter coordination</li>
                            <li>• Health monitoring</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="fade-in-up">
                    <h2 class="text-4xl font-bold text-gray-900 mb-6">About FurShield</h2>
                    <p class="text-lg text-gray-600 mb-6">
                        FurShield is a comprehensive pet care management platform designed to bring together pet owners, 
                        veterinarians, and animal shelters in one unified ecosystem. Our mission is to improve the quality 
                        of pet care through better organization, communication, and access to resources.
                    </p>
                    <p class="text-lg text-gray-600 mb-8">
                        With features ranging from health record management to appointment scheduling and product shopping, 
                        FurShield ensures that every pet receives the love and care they deserve.
                    </p>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-600 mb-2">1000+</div>
                            <div class="text-gray-600">Happy Pet Owners</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-green-600 mb-2">50+</div>
                            <div class="text-gray-600">Partner Veterinarians</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-purple-600 mb-2">25+</div>
                            <div class="text-gray-600">Animal Shelters</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-red-600 mb-2">500+</div>
                            <div class="text-gray-600">Pets Helped</div>
                        </div>
                    </div>
                </div>
                <!-- Modified About Section div with modern corner-overlapping images -->
<div class="fade-in-up relative" style="height: 500px; width: 600px;">
    <img src="../home2.webp" alt="Veterinarian with pet and owner" class="rounded-lg shadow-xl absolute modern-image" style="width: 200px; height: 200px; top: 50px; left: 50px; transform: rotate(5deg);">
    <img src="../home5.jfif" alt="Veterinarian with pet and owner" class="rounded-lg shadow-xl absolute modern-image" style="width: 200px; height: 200px; top: 50px; left: 300px; transform: rotate(5deg);">
    <img src="../home3.jfif" alt="Veterinarian with pet and owner" class="rounded-lg shadow-xl absolute modern-image" style="width: 200px; height: 200px; top: 250px; left: 100px; transform: rotate(3deg);">
    <img src="../home4.jpg" alt="Veterinarian with pet and owner" class="rounded-lg shadow-xl absolute modern-image" style="width: 200px; height: 200px; top: 200px; left: 350px; transform: rotate(-3deg);">
    <img src="../home.jfif" alt="Veterinarian with pet and owner" class="rounded-lg shadow-xl absolute modern-image" style="width: 200px; height: 200px; top: 150px; left: 200px; transform: rotate(0deg);">
</div>

<script>
// Add subtle random rotation to images for a dynamic modern look
document.addEventListener('DOMContentLoaded', () => {
    const images = document.querySelectorAll('#about .modern-image');
    images.forEach(img => {
        // Generate a subtle random rotation between -7 and 7 degrees
        const randomAngle = (Math.random() * 14 - 7).toFixed(2);
        img.style.transform = `rotate(${randomAngle}deg)`;
    });
});
</script>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 fade-in-up">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Get in Touch</h2>
                <p class="text-xl text-gray-600">Have questions? We'd love to hear from you.</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <div class="fade-in-up">
                    <div class="bg-gray-50 p-8 rounded-lg">
                        <h3 class="text-2xl font-bold text-gray-900 mb-6">Contact Information</h3>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <i class="fas fa-map-marker-alt text-blue-600 w-6"></i>
                                <span class="ml-3 text-gray-600">123 Pet Care Avenue, City, State 12345</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-phone text-blue-600 w-6"></i>
                                <span class="ml-3 text-gray-600">+1 (555) 123-4567</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-envelope text-blue-600 w-6"></i>
                                <span class="ml-3 text-gray-600">contact@furshield.com</span>
                            </div>
                        </div>
                        
                        <div class="mt-8">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Follow Us</h4>
                            <div class="flex space-x-4">
                                <a href="#" class="text-blue-600 hover:text-blue-800 text-2xl">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <a href="#" class="text-blue-400 hover:text-blue-600 text-2xl">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="text-pink-600 hover:text-pink-800 text-2xl">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="text-blue-700 hover:text-blue-900 text-2xl">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="fade-in-up">
                    <form class="bg-gray-50 p-8 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                            <textarea rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 transition-colors font-semibold">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include "../includes/footer.php";?>

    <script>
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Fade in animation on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.fade-in-up').forEach(el => {
        observer.observe(el);
    });

    // Parallax effect and opacity adjustment for hero text and buttons
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const parallax = document.querySelector('.parallax-bg');
        const heroText = document.querySelectorAll('#home h1, #home p, #home .space-x-4 a'); // Target h1, p, and buttons in hero section

        if (parallax) {
            const speed = scrolled * 0.5;
            parallax.style.transform = `translateY(${speed}px)`;
        }

        // Adjust opacity based on scroll position
        const heroSection = document.querySelector('#home');
        const heroHeight = heroSection.offsetHeight;
        const maxScroll = heroHeight * 0.5; // Adjust this multiplier to control when opacity starts to decrease
        const opacity = Math.max(0, 1 - Math.abs(scrolled) / maxScroll);

        heroText.forEach(text => {
            text.style.opacity = opacity;
        });
    });
    </script>
</body>
</html>