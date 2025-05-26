<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    public function login() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if($this->user->authenticate($email, $password)) {
                $_SESSION['user_id'] = $this->user->id_utilisateur;
                $_SESSION['user_name'] = $this->user->nom_complet;
                $_SESSION['user_role'] = $this->user->role;
                $_SESSION['syndic_id'] = $this->user->syndic_id;
                $_SESSION['must_change_password'] = $this->user->must_change_password;

                // Check if user must change password
                if($this->user->must_change_password) {
                    header('Location: change-password.php');
                    exit();
                } else {
                    $this->redirectToDashboard($this->user->role);
                    exit();
                }
            } else {
                $_SESSION['error'] = 'Invalid email or password';
            }
        }
        
        include __DIR__ . '/../views/auth/login.php';
    }

    public function changePassword() {
        // Check if user is logged in
        if(!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit();
        }

        // Check if user actually needs to change password
        if(!$_SESSION['must_change_password']) {
            $this->redirectToDashboard($_SESSION['user_role']);
            exit();
        }

        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validate passwords match
            if($new_password !== $confirm_password) {
                $_SESSION['error'] = 'New passwords do not match';
            } 
            // Validate password strength
            elseif(strlen($new_password) < 8) {
                $_SESSION['error'] = 'Password must be at least 8 characters long';
            } 
            // Try to change password
            else {
                $this->user->id_utilisateur = $_SESSION['user_id'];
                if($this->user->changePassword($old_password, $new_password)) {
                    $_SESSION['must_change_password'] = false;
                    $_SESSION['success'] = 'Password changed successfully! Redirecting to your dashboard...';
                    
                    // Small delay to show success message
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "' . $this->getDashboardUrl($_SESSION['user_role']) . '";
                        }, 2000);
                    </script>';
                    
                    // Also redirect immediately as fallback
                    header('refresh:2;url=' . $this->getDashboardUrl($_SESSION['user_role']));
                } else {
                    $_SESSION['error'] = 'Current password is incorrect';
                }
            }
        }

        include __DIR__ . '/../views/auth/change-password.php';
    }

    public function logout() {
        session_destroy();
        header('Location: login.php');
        exit();
    }

    private function redirectToDashboard($role) {
        $url = $this->getDashboardUrl($role);
        header('Location: ' . $url);
        exit();
    }

    private function getDashboardUrl($role) {
        switch($role) {
            case ROLE_ADMIN:
                return BASE_URL . 'admin/dashboard.php';
            case ROLE_SYNDIC:
                return BASE_URL . 'syndic/dashboard.php';
            case ROLE_RESIDENT:
                return BASE_URL . 'resident/dashboard.php';
            default:
                return 'login.php';
        }
    }
}
?>