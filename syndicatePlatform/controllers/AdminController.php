<?php
require_once 'models/User.php';
require_once 'models/Subscription.php';
require_once 'utils/Mailer.php';

class AdminController {
    private $db;
    private $user;
    private $subscription;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
        $this->subscription = new Subscription($this->db);
    }

    public function createSyndicAccount() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $subscription_id = $_POST['subscription_id'];
            $syndic_name = $_POST['syndic_name'];
            $syndic_email = $_POST['syndic_email'];
            $syndic_phone = $_POST['syndic_phone'];
            $company_name = $_POST['company_name'];

            // Generate random password
            $temp_password = $this->generateRandomPassword();

            // Create user account
            $this->user->nom_complet = $syndic_name;
            $this->user->email = $syndic_email;
            $this->user->mot_de_passe = $temp_password;
            $this->user->telephone = $syndic_phone;
            $this->user->role = ROLE_SYNDIC;
            $this->user->must_change_password = true;
            $this->user->created_by = $_SESSION['user_id'];

            $user_id = $this->user->create();

            if($user_id) {
                // Create syndic record
                $this->createSyndicRecord($user_id, $company_name, $subscription_id);
                
                // Send welcome email
                $this->sendWelcomeEmail($syndic_email, $syndic_name, $temp_password);
                
                $_SESSION['success'] = 'Syndic account created successfully';
            } else {
                $_SESSION['error'] = 'Failed to create syndic account';
            }
        }

        // Get pending subscription purchases
        $pending_purchases = $this->getPendingPurchases();
        include 'views/admin/create-syndic-account.php';
    }

    private function generateRandomPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle($chars), 0, $length);
    }

    private function createSyndicRecord($user_id, $company_name, $subscription_id) {
        $query = "INSERT INTO syndic (nom_syndic, code_syndic, id_admin_syndic, subscription_id)
                  VALUES (:nom_syndic, :code_syndic, :id_admin_syndic, :subscription_id)";
        
        $stmt = $this->db->prepare($query);
        $code_syndic = 'SYN' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
        
        $stmt->bindParam(':nom_syndic', $company_name);
        $stmt->bindParam(':code_syndic', $code_syndic);
        $stmt->bindParam(':id_admin_syndic', $user_id);
        $stmt->bindParam(':subscription_id', $subscription_id);
        
        return $stmt->execute();
    }

    private function sendWelcomeEmail($email, $name, $password) {
        $mailer = new Mailer();
        $subject = 'Welcome to ' . APP_NAME;
        $body = "Dear $name,\n\nYour account has been created.\n\nEmail: $email\nTemporary Password: $password\n\nPlease login and change your password.";
        
        return $mailer->send($email, $subject, $body);
    }
}
?>