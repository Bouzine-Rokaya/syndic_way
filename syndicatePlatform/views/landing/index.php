<?php
$page_title = "Welcome to " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="landing-nav">
        <div class="container">
            <div class="nav-brand">
                <h2><i class="fas fa-building"></i> <?php echo APP_NAME; ?></h2>
            </div>
            <div class="nav-links">
                <a href="?page=home">Home</a>
                <a href="?page=subscriptions">Pricing</a>
                <a href="login.php" class="btn btn-primary">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Modern Syndicate Management Made Simple</h1>
                <p>Streamline your building management with our comprehensive platform. Handle residents, maintenance, billing, and more in one place.</p>
                <div class="hero-buttons">
                    <a href="?page=subscriptions" class="btn btn-primary btn-large">Get Started</a>
                    <a href="#features" class="btn btn-secondary btn-large">Learn More</a>
                </div>
            </div>
            <div class="hero-image">
                <i class="fas fa-city"></i>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h2>Everything You Need to Manage Your Building</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Resident Management</h3>
                    <p>Easy resident registration, apartment assignments, and communication tools.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3>Maintenance Requests</h3>
                    <p>Streamlined maintenance workflow from request to resolution with tracking.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3>Billing & Invoicing</h3>
                    <p>Automated billing, invoice generation, and payment tracking.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3>Announcements</h3>
                    <p>Keep residents informed with building announcements and notifications.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Reports & Analytics</h3>
                    <p>Detailed reports on building operations, finances, and maintenance.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobile Friendly</h3>
                    <p>Access your syndicate management tools from any device, anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Transform Your Building Management?</h2>
            <p>Join hundreds of syndics who trust our platform to manage their buildings efficiently.</p>
            <a href="?page=subscriptions" class="btn btn-primary btn-large">Choose Your Plan</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo APP_NAME; ?></h3>
                    <p>Modern syndicate management solution for the digital age.</p>
                </div>
                <div class="footer-section">
                    <h4>Features</h4>
                    <ul>
                        <li><a href="#">Resident Management</a></li>
                        <li><a href="#">Maintenance Tracking</a></li>
                        <li><a href="#">Billing System</a></li>
                        <li><a href="#">Reports</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Documentation</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/landing.js"></script>
</body>
</html>