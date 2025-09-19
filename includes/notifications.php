<?php
// Notification system for FurShield
require_once 'mail.php';

class NotificationService {
    private $db;
    private $mailService;
    
    public function __construct() {
        $this->db = new Database();
        $this->mailService = new MailService();
    }
    
    // Create in-app notification
    public function createNotification($user_id, $title, $message, $type = 'info', $related_id = null) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $user_id, $title, $message, $type, $related_id);
        
        $result = $stmt->execute();
        $this->db->closeConnection();
        
        return $result;
    }
    
    // Get user notifications
    public function getUserNotifications($user_id, $limit = 10) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        
        $this->db->closeConnection();
        return $notifications;
    }
    
    // Mark notification as read
    public function markAsRead($notification_id, $user_id) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        
        $result = $stmt->execute();
        $this->db->closeConnection();
        
        return $result;
    }
    
    // Send appointment reminder
    public function sendAppointmentReminder($appointment_id) {
        $conn = $this->db->getConnection();
        
        $query = "SELECT a.*, p.name as pet_name, u.name as owner_name, u.email as owner_email, 
                         v.name as vet_name
                  FROM appointments a
                  JOIN pets p ON a.pet_id = p.pet_id
                  JOIN users u ON a.owner_id = u.user_id
                  JOIN users v ON a.vet_id = v.user_id
                  WHERE a.appointment_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        
        $appointment = $stmt->get_result()->fetch_assoc();
        
        if ($appointment) {
            // Send email reminder
            $subject = "Appointment Reminder - Tomorrow";
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #f59e0b; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Appointment Reminder</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$appointment['owner_name']}!</h2>
                        <p>This is a reminder that you have an appointment tomorrow:</p>
                        <ul>
                            <li><strong>Pet:</strong> {$appointment['pet_name']}</li>
                            <li><strong>Veterinarian:</strong> {$appointment['vet_name']}</li>
                            <li><strong>Date & Time:</strong> {$appointment['appointment_date']}</li>
                        </ul>
                        <p>Please don't forget to bring any relevant medical records.</p>
                    </div>
                </div>
            </body>
            </html>";
            
            $this->mailService->sendEmail($appointment['owner_email'], $appointment['owner_name'], $subject, $message);
            
            // Create in-app notification
            $this->createNotification(
                $appointment['owner_id'], 
                "Appointment Reminder", 
                "You have an appointment tomorrow with {$appointment['vet_name']} for {$appointment['pet_name']}", 
                "reminder", 
                $appointment_id
            );
        }
        
        $this->db->closeConnection();
    }
    
    // Send vaccination reminder
    public function sendVaccinationReminder($pet_id) {
        $conn = $this->db->getConnection();
        
        $query = "SELECT p.*, u.name as owner_name, u.email as owner_email
                  FROM pets p
                  JOIN users u ON p.owner_id = u.user_id
                  WHERE p.pet_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pet_id);
        $stmt->execute();
        
        $pet = $stmt->get_result()->fetch_assoc();
        
        if ($pet) {
            $subject = "Vaccination Reminder for {$pet['name']}";
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #ef4444; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Vaccination Reminder</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$pet['owner_name']}!</h2>
                        <p>It's time for {$pet['name']}'s vaccination update!</p>
                        <p>Regular vaccinations are essential for your pet's health and protection against diseases.</p>
                        <p>Please schedule an appointment with your veterinarian as soon as possible.</p>
                    </div>
                </div>
            </body>
            </html>";
            
            $this->mailService->sendEmail($pet['owner_email'], $pet['owner_name'], $subject, $message);
            
            // Create in-app notification
            $this->createNotification(
                $pet['owner_id'], 
                "Vaccination Due", 
                "It's time for {$pet['name']}'s vaccination update", 
                "health", 
                $pet_id
            );
        }
        
        $this->db->closeConnection();
    }
    
    // Send adoption application notification
    public function sendAdoptionApplicationNotification($application_id) {
        $conn = $this->db->getConnection();
        
        $query = "SELECT aa.*, al.pet_name, u.name as applicant_name, u.email as applicant_email,
                         s.name as shelter_name, s.email as shelter_email
                  FROM adoption_applications aa
                  JOIN adoption_listings al ON aa.listing_id = al.listing_id
                  JOIN users u ON aa.applicant_id = u.user_id
                  JOIN users s ON al.shelter_id = s.user_id
                  WHERE aa.application_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        
        $application = $stmt->get_result()->fetch_assoc();
        
        if ($application) {
            // Notify shelter
            $subject = "New Adoption Application";
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #8b5cf6; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>New Adoption Application</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$application['shelter_name']}!</h2>
                        <p>You have received a new adoption application:</p>
                        <ul>
                            <li><strong>Pet:</strong> {$application['pet_name']}</li>
                            <li><strong>Applicant:</strong> {$application['applicant_name']}</li>
                            <li><strong>Email:</strong> {$application['applicant_email']}</li>
                        </ul>
                        <p>Please review the application in your dashboard.</p>
                    </div>
                </div>
            </body>
            </html>";
            
            $this->mailService->sendEmail($application['shelter_email'], $application['shelter_name'], $subject, $message);
            
            // Create in-app notification for shelter
            $this->createNotification(
                $application['shelter_id'], 
                "New Adoption Application", 
                "{$application['applicant_name']} applied to adopt {$application['pet_name']}", 
                "application", 
                $application_id
            );
        }
        
        $this->db->closeConnection();
    }
    
    // Send newsletter
    public function sendNewsletter($subject, $content, $user_role = null) {
        $conn = $this->db->getConnection();
        
        $query = "SELECT user_id, name, email FROM users WHERE newsletter_subscription = 1";
        if ($user_role) {
            $query .= " AND role = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $user_role);
        } else {
            $stmt = $conn->prepare($query);
        }
        
        $stmt->execute();
        $users = $stmt->get_result();
        
        $sent_count = 0;
        while ($user = $users->fetch_assoc()) {
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>FurShield Newsletter</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$user['name']}!</h2>
                        {$content}
                    </div>
                </div>
            </body>
            </html>";
            
            if ($this->mailService->sendEmail($user['email'], $user['name'], $subject, $message)) {
                $sent_count++;
            }
        }
        
        $this->db->closeConnection();
        return $sent_count;
    }
}
?>
