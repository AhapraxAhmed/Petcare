🐾 FurShield – Complete Documentation

A smart pet care & adoption system with role-based dashboards for Pet Owners, Veterinarians, Shelters, and Admins.
Built with PHP 8, MySQL, Composer, PHPMailer, and OAuth.

## 📖 Introduction  
👉 [Read the Full Introduction Guide](https://online.fliphtml5.com/roarax/ndqd/#p=4)

Full Introduction Guide

👉 Read the Full Introduction Guide

FurShield is a web-based pet management platform designed for:

🐶 Pet Owners – Manage pets, health records, appointments & orders.

🏥 Veterinarians – Handle appointments, treatments & patient history.

🏠 Shelters – Manage adoption listings, care logs & applications.

👨‍💻 Admins – Manage users, roles & system settings.

✨ Key Features:

Role-based dashboards

Pet & health record management

Appointment booking & scheduling

Adoption listings + application workflow

E-commerce module (products, cart, checkout)

Notifications & reminders

AI chatbot for FAQs & pet care suggestions

## ⚙️ Developer Guide  
👉 [Read the Full Developer Guide](https://online.fliphtml5.com/roarax/vqox/)

Full Developer Guide

🔧 System Requirements

Hardware:

Minimum 4 GB RAM (8 GB recommended)

10 GB free storage

Intel i3 or higher

Software:

PHP 8.0+

MySQL 5.7+

Composer

XAMPP / WAMP / Laragon (Apache + MySQL)

Browser: Chrome/Firefox

🚀 Installation & Setup
1. Clone the Repository
<code> git clone https://github.com/your-username/furshield.git cd furshield </code>
2. Backend Setup
<code> # Move the project to your server’s web root htdocs/ (XAMPP) or www/ (WAMP)
Start Apache + MySQL from XAMPP/WAMP
</code>

Import database:
<code>
phpMyAdmin → Import → db/fur.sql
</code>

3. Install Dependencies
<code> composer install </code>
4. Configure Environment (.env)
<code> DB_HOST=localhost DB_USER=root DB_PASS= DB_NAME=fur

MAIL_HOST=smtp.gmail.com
MAIL_USER=your_email@gmail.com

MAIL_PASS=your_password
</code>

5. Run the Project
<code> http://localhost/furshield/ </code>
📂 Project Structure
furshield/
│── backend/
│   ├── config/         # DB connection, environment
│   ├── controllers/    # Request logic
│   ├── models/         # DB models
│   ├── routes/         # API endpoints
│   ├── middlewares/    # Auth & validation
│   └── index.php       # Entry point
│
│── dashboard/          # Role-based dashboards
│   ├── pet_owner/
│   ├── shelter/
│   ├── veterinarian/
│   └── admin/
│
│── frontend/           # Views, CSS, JS
│── db/                 # Database (fur.sql)
│── uploads/            # Pet & product images
│── vendor/             # Composer packages
│── docs/               # Documentation
│── README.md

🖥️ User Guide
👤 Registration
<code> 1. Visit http://localhost/furshield/ 2. Click "Register" 3. Fill in details + choose role (Pet Owner / Vet / Shelter) 4. Confirm email → Login </code>
🔑 Login
<code> - Email + Password - OR Google/GitHub OAuth </code>
🐶 Pet Owner Dashboard

Manage pets (add, edit, remove)

Track health records (visits, treatments, certificates)

Book appointments with vets

Order pet products (food, grooming, toys)

AI chatbot for care tips

🏥 Veterinarian Dashboard

Manage patient profiles

Approve/reschedule/cancel appointments

Log treatments, prescriptions & notes

View medical history of pets

🏠 Shelter Dashboard

Add pet listings for adoption

Manage applications

Log care activities (feeding, medical)

Coordinate with adopters

👨‍💻 Admin Dashboard

Manage users & roles

Monitor adoption, product & appointment data

Configure system-wide settings

🛠️ Testing

Tools:

Unit Testing → Functions (includes/functions.php)

Integration → Auth, dashboards, chatbot

API Testing → Postman

DB Testing → MySQL Workbench

✅ Sample Test Case:

Test ID	Description	Input	Expected Output	Status
TC-01	User Login	Email+Pass	Redirect to dashboard	Pass
TC-02	Appointment Booking	Pet Owner → Date/Time	Saved + Notified	Pass
TC-05	Product Checkout	Cart Items	Order Placed	Pass
TC-06	AI Chatbot Query	"Show my appointments"	DB result shown	Pass
🌍 Deployment
<code> # Localhost 1. Install XAMPP 2. Copy project → htdocs/ 3. Import fur.sql 4. Configure database.php 5. Access at http://localhost/furshield/ </code>

Production Deployment:

Upload to cPanel / VPS / Cloud

Import fur.sql to MySQL

Configure domain + SSL

Set permissions for uploads/

🔮 Future Enhancements

AI-powered pet diagnostics

Push notifications

Multi-language support

Mobile app
