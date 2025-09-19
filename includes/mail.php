<?php
// Email functionality for FurShield
require_once '../config/config.php';

class MailService {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
       $this->smtp_host = 'smtp.gmail.com';         // Gmail SMTP server
        $this->smtp_port = 587;                      // TLS port (use 465 for SSL)
        $this->smtp_username = 'syedzofishahali@gmail.com'; // Your Gmail address
        $this->smtp_password = 'vidl xvas dakg tfub';    // Your Gmail App Password
        $this->from_email = 'syedzofishahali@gmail.com';    // Sender email
        $this->from_name = 'Furshield';   
    }
    
    public function sendEmail($to_email, $to_name, $subject, $message, $is_html = true) {
        // For demonstration, we'll log the email instead of actually sending
        $this->logEmail($to_email, $subject, $message, 'sent');
        require_once '../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email, $to_name);
            
            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            $this->logEmail($to_email, $subject, $message, 'sent');
            return true;
        } catch (Exception $e) {
            $this->logEmail($to_email, $subject, $message, 'failed');
            return false;
        }
        
        return true; // For demonstration
    }
    public function sendPasswordResetEmail($to_email, $to_name, $reset_link) {
    // Subject for the password reset email
    $subject = 'Password Reset Instructions';

    // Message body (HTML)
    $message = "
        <p>Hi {$to_name},</p>
        <p>We received a request to reset your password.</p>
        <p>Click the link below to reset your password:</p>
        <p><a href='{$reset_link}'>Reset Password</a></p>
        <p>If you did not request a password reset, please ignore this email.</p>
        <p>Thanks,<br>Your Website Team</p>
    ";

    // Use the existing sendEmail method
    return $this->sendEmail($to_email, $to_name, $subject, $message, true);
}

   public function sendWelcomeEmail($user_email, $user_name, $user_role, $is_new_user = true) {
    $subject = $is_new_user ? "Welcome to FurShield!" : "Welcome back to FurShield!";

    // Start email HTML
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; color: #666; }
            .button { background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . ($is_new_user ? "Welcome to FurShield!" : "Welcome Back!") . "</h1>
                <p>Every Paw/Wing Deserves a Shield of Love</p>
            </div>
            <div class='content'>
                <h2>Hello {$user_name}!</h2>
    ";

    if ($is_new_user) {
        // Show different content for registration
        $message .= "<p>Thank you for joining FurShield as a <strong>" . ucfirst(str_replace('_', ' ', $user_role)) . "</strong>.</p>
                     <p>You can now:</p>
                     <ul>";

        // Add features based on user role
        switch ($user_role) {
            case 'pet_owner':
                $message .= "
                    <li>Manage your pet profiles and health records</li>
                    <li>Book appointments with veterinarians</li>
                    <li>Shop for pet products</li>
                    <li>Receive care reminders and tips</li>";
                break;
            case 'veterinarian':
                $message .= "
                    <li>Manage your practice profile</li>
                    <li>View patient medical histories</li>
                    <li>Log treatments and observations</li>
                    <li>Manage appointment schedules</li>";
                break;
            case 'shelter':
                $message .= "
                    <li>List pets available for adoption</li>
                    <li>Manage pet care records</li>
                    <li>Coordinate with potential adopters</li>
                    <li>Track adoption applications</li>";
                break;
        }

        $message .= "</ul>";

        // Show Login button **only for new users**
        $message .= "<p style='text-align: center;'>
                        <a href='" . APP_URL . "/auth/login.php' class='button'>Login to Dashboard</a>
                     </p>";

    } else {
        // Existing user: just greet without login button
        $message .= "<p>Glad to see you back! You’re now logged in as <strong>" . ucfirst(str_replace('_', ' ', $user_role)) . "</strong>.</p>";
    }

    // Footer
    $message .= "
            </div>
            <div class='footer'>
                <p>If you have any questions, feel free to contact us at support@furshield.com</p>
                <p>&copy; 2024 FurShield. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";

    return $this->sendEmail($user_email, $user_name, $subject, $message, true);
}
    
    public function sendAppointmentConfirmation($user_email, $user_name, $appointment_details) {
        $subject = "Appointment Confirmation - FurShield";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #10b981; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .appointment-details { background: white; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Appointment Confirmed</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$user_name}!</h2>
                    <p>Your appointment has been confirmed. Here are the details:</p>
                    <div class='appointment-details'>
                        <p><strong>Pet:</strong> {$appointment_details['pet_name']}</p>
                        <p><strong>Veterinarian:</strong> {$appointment_details['vet_name']}</p>
                        <p><strong>Date & Time:</strong> {$appointment_details['appointment_date']}</p>
                        <p><strong>Reason:</strong> {$appointment_details['reason']}</p>
                    </div>
                    <p>Please arrive 10 minutes early and bring any relevant medical records.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($user_email, $user_name, $subject, $message, true);
    }
    
    private function logEmail($recipient_email, $subject, $message, $status) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, subject, message, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $recipient_email, $subject, $message, $status);
        $stmt->execute();
        
        $db->closeConnection();
    }
}
?>
