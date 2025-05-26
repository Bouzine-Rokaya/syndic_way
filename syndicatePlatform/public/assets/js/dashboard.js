// Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        }, 5000);
    });

    // Add loading states to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.classList.contains('btn-loading')) {
                e.preventDefault();
                return;
            }
            
            // Skip for certain button types
            if (this.type === 'button' || this.getAttribute('data-no-loading')) {
                return;
            }
            
            this.classList.add('btn-loading');
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            
            setTimeout(() => {
                this.classList.remove('btn-loading');
                this.innerHTML = originalText;
            }, 2000);
        });
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Initialize stats update only on dashboard page
    if (window.location.pathname.includes('dashboard.php')) {
        updateStats();
    }
});

// Update statistics (only for dashboard)
function updateStats() {
    // Check if we're on the dashboard page
    if (!window.location.pathname.includes('dashboard.php')) {
        return;
    }

    // Get the correct API path
    const apiPath = getApiPath() + 'api/dashboard-stats.php';
    
    fetch(apiPath)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateStatNumbers(data);
                updateLastUpdated();
            } else {
                console.warn('Stats update failed:', data.error);
            }
        })
        .catch(error => {
            console.error('Error updating stats:', error);
            // Silently fail - don't spam the console in production
        });
}

function updateStatNumbers(data) {
    const statElements = [
        { element: document.querySelector('[data-stat="syndics"] .stat-number'), value: data.total_syndics },
        { element: document.querySelector('[data-stat="users"] .stat-number'), value: data.total_users },
        { element: document.querySelector('[data-stat="pending"] .stat-number'), value: data.pending_purchases },
        { element: document.querySelector('[data-stat="subscriptions"] .stat-number'), value: data.total_subscriptions }
    ];

    statElements.forEach(({ element, value }) => {
        if (element && value !== undefined) {
            animateNumber(element, parseInt(element.textContent) || 0, value);
        }
    });
}

function animateNumber(element, start, end) {
    if (start === end) return;
    
    const duration = 500;
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const current = Math.round(start + (end - start) * progress);
        element.textContent = current.toLocaleString();
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    requestAnimationFrame(update);
}

function updateLastUpdated() {
    const lastUpdatedElement = document.querySelector('.last-updated');
    if (lastUpdatedElement) {
        lastUpdatedElement.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
    }
}

function getApiPath() {
    // Get the current path and construct API path
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    return basePath;
}

// Auto-refresh stats every 30 seconds (only on dashboard)
setInterval(() => {
    if (window.location.pathname.includes('dashboard.php')) {
        updateStats();
    }
}, 30000);

// Add smooth transitions for stat cards
function addStatCardEffects() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
}

// Initialize effects after DOM load
setTimeout(addStatCardEffects, 100);

// Table row hover effects
document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
});

// Form validation helpers
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,20}$/;
    return phoneRegex.test(phone);
}

// Add form validation to subscription forms
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const emailInputs = form.querySelectorAll('input[type="email"]');
            const phoneInputs = form.querySelectorAll('input[type="tel"]');
            let isValid = true;
            
            // Validate emails
            emailInputs.forEach(input => {
                if (input.value && !validateEmail(input.value)) {
                    showFieldError(input, 'Please enter a valid email address');
                    isValid = false;
                }
            });
            
            // Validate phones
            phoneInputs.forEach(input => {
                if (input.value && !validatePhone(input.value)) {
                    showFieldError(input, 'Please enter a valid phone number');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
});

function showFieldError(field, message) {
    // Remove existing error
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Add error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
    field.style.borderColor = '#dc3545';
    
    // Remove error on input
    field.addEventListener('input', function() {
        if (errorDiv.parentNode) {
            errorDiv.remove();
        }
        field.style.borderColor = '';
    }, { once: true });
}