<?php
class Mailer
{
    private $from_email;
    private $from_name;
    private $log_file;

    public function __construct()
    {
        $this->from_email = defined('MAIL_USERNAME') ? MAIL_USERNAME : 'noreply@syndicate.com';
        $this->from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : APP_NAME;
        $this->log_file = __DIR__ . '/../storage/logs/emails.log';

        // Create logs directory if it doesn't exist
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }

    public function send($to_email, $subject, $body, $is_html = false)
    {
        // Always log email for development/debugging
        $this->logEmail($to_email, $subject, $body);

        // Always store in session for web display
        $this->displayEmailInBrowser($to_email, $subject, $body, $is_html);

        // If MAIL_LOG_ONLY is true, only log emails (for development)
        if (defined('MAIL_LOG_ONLY') && MAIL_LOG_ONLY) {
            return true; // Return success for development
        }

        // Try to send real email
        return $this->sendRealEmail($to_email, $subject, $body, $is_html);
    }

    private function sendRealEmail($to_email, $subject, $body, $is_html = false)
    {
        $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        if ($is_html) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        return mail($to_email, $subject, $body, $headers);
    }

    private function logEmail($to_email, $subject, $body)
    {
        $log_entry = "===============================\n";
        $log_entry .= "DATE: " . date('Y-m-d H:i:s') . "\n";
        $log_entry .= "TO: $to_email\n";
        $log_entry .= "SUBJECT: $subject\n";
        $log_entry .= "BODY:\n$body\n";
        $log_entry .= "===============================\n\n";

        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function displayEmailInBrowser($to_email, $subject, $body, $is_html)
    {
        // ✅ FIX: Ensure session is started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // ✅ FIX: Initialize sent_emails array if not exists
        if (!isset($_SESSION['sent_emails'])) {
            $_SESSION['sent_emails'] = [];
        }

        // ✅ FIX: Add email to session
        $_SESSION['sent_emails'][] = [
            'to' => $to_email,
            'subject' => $subject,
            'body' => $body,
            'is_html' => $is_html,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // ✅ DEBUG: Log to verify session storage
        error_log("Email added to session. Total emails: " . count($_SESSION['sent_emails']));
        error_log("Latest email: " . json_encode(end($_SESSION['sent_emails'])));
    }

    public function sendWelcomeEmail($email, $name, $password)
    {
        $subject = "Welcome to " . APP_NAME . " - Your Account Details";

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; }
                .credentials { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #667eea; }
                .footer { text-align: center; color: #666; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
                .button { background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .password-box { background: #f1f1f1; padding: 10px; font-size: 18px; font-weight: bold; border-radius: 4px; color: #333; letter-spacing: 1px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🏢 Welcome to " . APP_NAME . "</h1>
                </div>
                
                <div class='content'>
                    <h2>Dear {$name},</h2>
                    
                    <p>Congratulations! Your syndicate management account has been created successfully. You can now access your comprehensive dashboard to manage all your building operations efficiently.</p>
                    
                    <div class='credentials'>
                        <h3>🔐 Your Login Credentials:</h3>
                        <p><strong>📧 Email:</strong> {$email}</p>
                        <p><strong>🔑 Temporary Password:</strong></p>
                        <div class='password-box'>{$password}</div>
                    </div>
                    
                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                        <p><strong>⚠️ Important Security Notice:</strong><br>
                        For your security, you will be required to change this temporary password on your first login.</p>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='" . BASE_URL . "public/login.php' class='button'>🚀 Login to Your Account</a>
                    </div>
                    
                    <h3>🏢 What you can do with your account:</h3>
                    <ul style='background: white; padding: 20px; border-radius: 5px;'>
                        <li>👥 <strong>Manage residents and apartments</strong></li>
                        <li>🔧 <strong>Handle maintenance requests efficiently</strong></li>
                        <li>💰 <strong>Generate and track invoices</strong></li>
                        <li>📢 <strong>Send announcements to residents</strong></li>
                        <li>📊 <strong>View detailed reports and analytics</strong></li>
                        <li>⚙️ <strong>Configure building settings</strong></li>
                    </ul>
                    
                    <p>Need help getting started? Our support team is ready to assist you with any questions or guidance you may need.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>Best regards,</strong><br>
                    <strong>The " . APP_NAME . " Team</strong></p>
                    
                    <p><small>📧 This is an automated message. Please do not reply to this email.<br>
                    🔗 Login URL: <a href='" . BASE_URL . "public/login.php'>" . BASE_URL . "public/login.php</a></small></p>
                </div>
            </div>
        </body>
        </html>";

        return $this->send($email, $subject, $body, true);
    }

    public function sendMaintenanceNotification($email, $request_data)
    {
        $subject = "Maintenance Request Update - " . APP_NAME;

        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #667eea; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h2>🔧 Maintenance Request Update</h2>
                </div>
                
                <div style='background: #f8f9fa; padding: 30px;'>
                    <p>Your maintenance request has been updated:</p>
                    <div style='background: white; padding: 20px; border-radius: 5px; border-left: 4px solid #28a745;'>
                        <ul style='margin: 0; padding-left: 20px;'>
                            <li><strong>Request ID:</strong> #{$request_data['id_demande']}</li>
                            <li><strong>Description:</strong> {$request_data['description']}</li>
                            <li><strong>Status:</strong> <span style='background: #28a745; color: white; padding: 3px 8px; border-radius: 3px;'>{$request_data['statut']}</span></li>
                            <li><strong>Priority:</strong> {$request_data['priorite']}</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . BASE_URL . "login.php' style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                            View Full Details
                        </a>
                    </div>
                    
                    <p><strong>Best regards,</strong><br>" . APP_NAME . " Team</p>
                </div>
            </div>
        </body>
        </html>";

        return $this->send($email, $subject, $body, true);
    }

    // ✅ ADD: Method to get email count for debugging
    public function getEmailCount()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['sent_emails']) ? count($_SESSION['sent_emails']) : 0;
    }

    // ✅ ADD: Method to clear emails
    public function clearEmails()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['sent_emails'] = [];
        return true;
    }

    public function sendResidentWelcomeEmail($email, $name, $password)
    {
        $subject = "Welcome to Your Building Portal - " . APP_NAME;

        $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; }
            .credentials { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745; }
            .footer { text-align: center; color: #666; margin-top: 30px; }
            .button { background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏠 Welcome to Your Building Portal</h1>
            </div>
            
            <div class='content'>
                <h2>Dear {$name},</h2>
                
                <p>Welcome! Your resident account has been created for your building's management system. You can now access the resident portal to submit maintenance requests, view announcements, and manage your account.</p>
                
                <div class='credentials'>
                    <h3>🔐 Your Login Credentials:</h3>
                    <p><strong>📧 Email:</strong> {$email}</p>
                    <p><strong>🔑 Temporary Password:</strong></p>
                    <div style='background: #f1f1f1; padding: 10px; font-size: 16px; font-weight: bold; border-radius: 4px; color: #333;'>{$password}</div>
                </div>
                
                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                    <p><strong>⚠️ Important:</strong> You must change this temporary password on your first login.</p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . BASE_URL . "public/login.php' class='button'>🚀 Access Resident Portal</a>
                </div>
                
                <h3>🏠 What you can do:</h3>
                <ul style='background: white; padding: 20px; border-radius: 5px;'>
                    <li>📝 <strong>Submit maintenance requests</strong></li>
                    <li>📋 <strong>Track your request status</strong></li>
                    <li>📢 <strong>View building announcements</strong></li>
                    <li>💰 <strong>View your invoices and charges</strong></li>
                    <li>👤 <strong>Update your profile information</strong></li>
                </ul>
                
                <p>If you have any questions, please contact your building management.</p>
            </div>
            
            <div class='footer'>
                <p><strong>Best regards,</strong><br>
                <strong>Your Building Management Team</strong></p>
                
                <p><small>This is an automated message from " . APP_NAME . "</small></p>
            </div>
        </div>
    </body>
    </html>";

        return $this->send($email, $subject, $body, true);
    }
}
?>