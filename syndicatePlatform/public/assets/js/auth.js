// Authentication JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('form');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            if (!validateLoginForm()) {
                e.preventDefault();
            }
        });
    }

    // Password change form validation
    const changePasswordForm = document.getElementById('change-password-form');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            if (!validatePasswordChange()) {
                e.preventDefault();
            }
        });
    }
});

function validateLoginForm() {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    if (!email) {
        showError('Email is required');
        return false;
    }

    if (!isValidEmail(email)) {
        showError('Please enter a valid email address');
        return false;
    }

    if (!password) {
        showError('Password is required');
        return false;
    }

    return true;
}

function validatePasswordChange() {
    const oldPassword = document.getElementById('old_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (!oldPassword) {
        showError('Current password is required');
        return false;
    }

    if (!newPassword) {
        showError('New password is required');
        return false;
    }

    if (newPassword.length < 8) {
        showError('New password must be at least 8 characters long');
        return false;
    }

    if (newPassword !== confirmPassword) {
        showError('New passwords do not match');
        return false;
    }

    if (!isStrongPassword(newPassword)) {
        showError('Password must contain at least one uppercase letter, one lowercase letter, and one number');
        return false;
    }

    return true;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isStrongPassword(password) {
   const hasUpperCase = /[A-Z]/.test(password);
   const hasLowerCase = /[a-z]/.test(password);
   const hasNumbers = /\d/.test(password);
   const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
   
   return hasUpperCase && hasLowerCase && hasNumbers && (hasSpecialChar || password.length >= 12);
}

function showError(message) {
   // Remove existing error messages
   const existingError = document.querySelector('.alert-error');
   if (existingError) {
       existingError.remove();
   }

   // Create new error message
   const errorDiv = document.createElement('div');
   errorDiv.className = 'alert alert-error';
   errorDiv.textContent = message;

   // Insert at the top of the form
   const form = document.querySelector('form');
   form.insertBefore(errorDiv, form.firstChild);

   // Auto-remove after 5 seconds
   setTimeout(() => {
       if (errorDiv.parentNode) {
           errorDiv.remove();
       }
   }, 5000);
}

// Show/hide password functionality
function togglePassword(inputId) {
   const input = document.getElementById(inputId);
   const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
   input.setAttribute('type', type);
}