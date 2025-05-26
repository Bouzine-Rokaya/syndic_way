<?php
require_once __DIR__ . '/../models/Subscription.php';
require_once __DIR__ . '/../utils/Mailer.php';

class SubscriptionController {
    private $db;
    private $subscription;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->subscription = new Subscription($this->db);
    }

    public function purchase() {
        $plan_id = $_GET['plan'] ?? null;
        
        if (!$plan_id) {
            header('Location: ?page=subscriptions');
            exit();
        }

        $selected_plan = $this->subscription->getById($plan_id);
        
        if (!$selected_plan) {
            $_SESSION['error'] = 'Invalid subscription plan selected.';
            header('Location: ?page=subscriptions');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->processPurchase($selected_plan);
        } else {
            $this->showPurchaseForm($selected_plan);
        }
    }

    private function processPurchase($plan) {
        // Validate form data
        $syndic_name = sanitizeInput($_POST['syndic_name'] ?? '');
        $syndic_email = sanitizeInput($_POST['syndic_email'] ?? '');
        $syndic_phone = sanitizeInput($_POST['syndic_phone'] ?? '');
        $company_name = sanitizeInput($_POST['company_name'] ?? '');
        $company_address = sanitizeInput($_POST['company_address'] ?? '');

        $errors = [];

        if (empty($syndic_name)) {
            $errors[] = 'Full name is required';
        }

        if (empty($syndic_email) || !isValidEmail($syndic_email)) {
            $errors[] = 'Valid email address is required';
        }

        if (empty($syndic_phone)) {
            $errors[] = 'Phone number is required';
        }

        if (empty($company_name)) {
            $errors[] = 'Company name is required';
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->showPurchaseForm($plan);
            return;
        }

        // Check if email already exists in purchases
        if ($this->emailExistsInPurchases($syndic_email)) {
            $_SESSION['error'] = 'An account with this email already exists or is pending approval.';
            $this->showPurchaseForm($plan);
            return;
        }

        // Save purchase to database
        $purchase_data = [
            'subscription_id' => $plan['id_subscription'],
            'syndic_email' => $syndic_email,
            'syndic_name' => $syndic_name,
            'syndic_phone' => $syndic_phone,
            'company_name' => $company_name,
            'company_address' => $company_address,
            'amount_paid' => $plan['price'],
            'payment_status' => 'completed',
            'expiry_date' => date('Y-m-d', strtotime('+' . $plan['duration_months'] . ' months'))
        ];

        $purchase_id = $this->savePurchase($purchase_data);

        if ($purchase_id) {
            // Send confirmation email
            $this->sendPurchaseConfirmation($syndic_email, $syndic_name, $plan);
            
            // Notify admin
            $this->notifyAdminOfPurchase($purchase_data, $plan);

            $_SESSION['success'] = 'Purchase completed successfully! You will receive account details within 24 hours.';
            header('Location: ?page=purchase-success&id=' . $purchase_id);
            exit();
        } else {
            $_SESSION['error'] = 'Purchase failed. Please try again.';
            $this->showPurchaseForm($plan);
        }
    }

    private function showPurchaseForm($plan) {
        include __DIR__ . '/../views/landing/purchase.php';
    }

    private function emailExistsInPurchases($email) {
        $query = "SELECT COUNT(*) as count FROM subscription_purchases WHERE syndic_email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    private function savePurchase($data) {
        $query = "INSERT INTO subscription_purchases 
                  (subscription_id, syndic_email, syndic_name, syndic_phone, company_name, 
                   company_address, payment_status, amount_paid, expiry_date, transaction_id)
                  VALUES (:subscription_id, :syndic_email, :syndic_name, :syndic_phone, 
                          :company_name, :company_address, :payment_status, :amount_paid, 
                          :expiry_date, :transaction_id)";

        $stmt = $this->db->prepare($query);
        
        $transaction_id = 'TXN_' . uniqid();
        
        $stmt->bindParam(':subscription_id', $data['subscription_id']);
        $stmt->bindParam(':syndic_email', $data['syndic_email']);
        $stmt->bindParam(':syndic_name', $data['syndic_name']);
        $stmt->bindParam(':syndic_phone', $data['syndic_phone']);
        $stmt->bindParam(':company_name', $data['company_name']);
        $stmt->bindParam(':company_address', $data['company_address']);
        $stmt->bindParam(':payment_status', $data['payment_status']);
        $stmt->bindParam(':amount_paid', $data['amount_paid']);
        $stmt->bindParam(':expiry_date', $data['expiry_date']);
        $stmt->bindParam(':transaction_id', $transaction_id);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    private function sendPurchaseConfirmation($email, $name, $plan) {
        $mailer = new Mailer();
        $subject = 'Purchase Confirmation - ' . APP_NAME;
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Thank you for your purchase!</h2>
            <p>Dear {$name},</p>
            <p>We have received your subscription purchase for the <strong>{$plan['name']}</strong>.</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Purchase Details:</h3>
                <ul>
                    <li><strong>Plan:</strong> {$plan['name']}</li>
                    <li><strong>Price:</strong> €{$plan['price']}/month</li>
                    <li><strong>Max Residents:</strong> {$plan['max_residents']}</li>
                    <li><strong>Max Apartments:</strong> {$plan['max_apartments']}</li>
                </ul>
            </div>
            
            <p><strong>What's Next?</strong></p>
            <p>Our admin team will create your syndicate account within 24 hours. You will receive:</p>
            <ul>
                <li>Login credentials via email</li>
                <li>Setup instructions</li>
                <li>Access to your management dashboard</li>
            </ul>
            
            <p>If you have any questions, please don't hesitate to contact our support team.</p>
            
            <p>Best regards,<br>
            The " . APP_NAME . " Team</p>
        </body>
        </html>";

        return $mailer->send($email, $subject, $body, true);
    }

    private function notifyAdminOfPurchase($purchase_data, $plan) {
        $mailer = new Mailer();
        $subject = 'New Subscription Purchase - Action Required';
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>New Subscription Purchase</h2>
            <p>A new subscription has been purchased and requires account creation.</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Customer Details:</h3>
                <ul>
                    <li><strong>Name:</strong> {$purchase_data['syndic_name']}</li>
                    <li><strong>Email:</strong> {$purchase_data['syndic_email']}</li>
                    <li><strong>Phone:</strong> {$purchase_data['syndic_phone']}</li>
                    <li><strong>Company:</strong> {$purchase_data['company_name']}</li>
                    <li><strong>Address:</strong> {$purchase_data['company_address']}</li>
                </ul>
                
                <h3>Subscription Details:</h3>
                <ul>
                    <li><strong>Plan:</strong> {$plan['name']}</li>
                    <li><strong>Price:</strong> €{$plan['price']}/month</li>
                    <li><strong>Duration:</strong> {$plan['duration_months']} months</li>
                    <li><strong>Expiry Date:</strong> {$purchase_data['expiry_date']}</li>
                </ul>
            </div>
            
            <p><strong>Action Required:</strong></p>
            <p>Please log in to the admin panel to create the syndic account for this customer.</p>
            
            <p><a href='" . BASE_URL . "admin/create-syndic-account.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Create Account</a></p>
        </body>
        </html>";

        return $mailer->send('admin@syndicate.com', $subject, $body, true);
    }

    public function purchaseSuccess() {
        $purchase_id = $_GET['id'] ?? null;
        
        if (!$purchase_id) {
            header('Location: index.php');
            exit();
        }

        $query = "SELECT sp.*, s.name as plan_name FROM subscription_purchases sp 
                  JOIN subscriptions s ON sp.subscription_id = s.id_subscription 
                  WHERE sp.id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $purchase_id);
        $stmt->execute();
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) {
            header('Location: index.php');
            exit();
        }

        include __DIR__ . '/../views/landing/purchase-success.php';
    }
}
?>