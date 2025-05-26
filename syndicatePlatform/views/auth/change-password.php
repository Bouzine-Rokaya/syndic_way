<?php 
$page_title = "Change Password - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <div class="auth-header">
                <h2><i class="fas fa-key"></i> Change Password</h2>
                <p>For security reasons, you must change your password before continuing</p>
            </div>
            
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="change-password-form">
                <div class="form-group">
                    <label for="old_password">Current Password:</label>
                    <div class="password-input">
                        <input type="password" id="old_password" name="old_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('old_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <div class="password-input">
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="password-help">Password must be at least 8 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <div class="password-input">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Strength Indicator -->
                <div class="password-strength" style="display: none;">
                    <div class="strength-meter">
                        <div class="strength-bar" id="strength-bar"></div>
                    </div>
                    <small class="strength-text" id="strength-text"></small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>

            <div class="auth-footer">
                <div class="security-tips">
                    <h4><i class="fas fa-shield-alt"></i> Password Security Tips:</h4>
                    <ul>
                        <li>Use at least 8 characters</li>
                        <li>Include uppercase and lowercase letters</li>
                        <li>Add numbers and special characters</li>
                        <li>Avoid using personal information</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
        }
        
        .auth-form {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-header h2 {
            color: #343a40;
            margin-bottom: 0.5rem;
        }
        
        .auth-header p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .password-input {
            position: relative;
        }
        
        .password-input input {
            padding-right: 45px;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0.25rem;
        }
        
        .password-toggle:hover {
            color: #495057;
        }
        
        .password-help {
            color: #6c757d;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        .password-strength {
            margin: 1rem 0;
        }
        
        .strength-meter {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .strength-text {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .btn-full {
            width: 100%;
            padding: 0.75rem;
            margin-top: 1rem;
        }
        
        .security-tips {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
        }
        
        .security-tips h4 {
            color: #495057;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .security-tips ul {
            color: #6c757d;
            font-size: 0.8rem;
            margin: 0;
            padding-left: 1.2rem;
        }
        
        .security-tips li {
            margin-bottom: 0.25rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert i {
            font-size: 1.1rem;
        }
    </style>

    <script>
        // Password toggle functionality
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.parentNode.querySelector('.password-toggle i');
            
            if (input.type === 'password') {
                input.type = 'text';
                button.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                button.className = 'fas fa-eye';
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = [];

            if (password.length >= 8) {
                strength += 1;
            } else {
                feedback.push('Use at least 8 characters');
            }

            if (/[a-z]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add lowercase letters');
            }

            if (/[A-Z]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add uppercase letters');
            }

            if (/[0-9]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add numbers');
            }

            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add special characters');
            }

            return { strength, feedback };
        }

        // Update password strength indicator
        function updatePasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthContainer = document.querySelector('.password-strength');
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');

            if (password.length === 0) {
                strengthContainer.style.display = 'none';
                return;
            }

            strengthContainer.style.display = 'block';

            const { strength, feedback } = checkPasswordStrength(password);
            const percentage = (strength / 5) * 100;

            strengthBar.style.width = percentage + '%';

            if (strength <= 2) {
                strengthBar.style.backgroundColor = '#dc3545';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 3) {
                strengthBar.style.backgroundColor = '#ffc107';
                strengthText.textContent = 'Fair';
                strengthText.style.color = '#ffc107';
            } else if (strength <= 4) {
                strengthBar.style.backgroundColor = '#28a745';
                strengthText.textContent = 'Good';
                strengthText.style.color = '#28a745';
            } else {
                strengthBar.style.backgroundColor = '#17a2b8';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#17a2b8';
            }

            if (feedback.length > 0) {
                strengthText.textContent += ' - ' + feedback.join(', ');
            }
        }

        // Form validation
        document.getElementById('change-password-form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match');
                return false;
            }

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                return false;
            }

            return true;
        });

        // Add event listeners
        document.getElementById('new_password').addEventListener('input', updatePasswordStrength);
        
        // Real-time password confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '';
            }
        });
    </script>
</body>
</html>