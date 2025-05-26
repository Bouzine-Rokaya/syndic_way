<?php
$page_title = "Choose Your Plan - " . APP_NAME;

// Get subscription plans from database
require_once '../models/Subscription.php';
$database = new Database();
$db = $database->getConnection();
$subscription = new Subscription($db);
$plans = $subscription->getAllActive();
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

    <!-- Pricing Section -->
    <section class="pricing">
        <div class="container">
            <div class="pricing-header">
                <h1>Choose the Perfect Plan for Your Building</h1>
                <p>Start managing your syndicate more efficiently today</p>
            </div>

            <div class="pricing-grid">
                <?php foreach($plans as $plan): ?>
                <div class="pricing-card <?php echo $plan['name'] === 'Professional Plan' ? 'featured' : ''; ?>">
                    <?php if($plan['name'] === 'Professional Plan'): ?>
                    <div class="popular-badge">Most Popular</div>
                    <?php endif; ?>
                    
                    <div class="plan-header">
                        <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                        <div class="price">
                            <span class="currency">€</span>
                            <span class="amount"><?php echo number_format($plan['price'], 0); ?></span>
                            <span class="period">/month</span>
                        </div>
                        <p class="plan-description"><?php echo htmlspecialchars($plan['description']); ?></p>
                    </div>

                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Up to <?php echo $plan['max_residents']; ?> residents</li>
                            <li><i class="fas fa-check"></i> Up to <?php echo $plan['max_apartments']; ?> apartments</li>
                            <li><i class="fas fa-check"></i> Maintenance management</li>
                            <li><i class="fas fa-check"></i> Resident portal</li>
                            <?php if($plan['name'] !== 'Basic Plan'): ?>
                            <li><i class="fas fa-check"></i> Financial management</li>
                            <li><i class="fas fa-check"></i> Advanced reporting</li>
                            <?php endif; ?>
                            <?php if($plan['name'] === 'Enterprise Plan'): ?>
                            <li><i class="fas fa-check"></i> Priority support</li>
                            <li><i class="fas fa-check"></i> Custom features</li>
                            <li><i class="fas fa-check"></i> API access</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="plan-footer">
                        <a href="?page=purchase&plan=<?php echo $plan['id_subscription']; ?>" 
                           class="btn <?php echo $plan['name'] === 'Professional Plan' ? 'btn-primary' : 'btn-secondary'; ?> btn-full">
                            Get Started
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="pricing-footer">
                <p>All plans include free setup and 24/7 support. No hidden fees.</p>
                <p>Need a custom solution? <a href="mailto:sales@syndicate.com">Contact our sales team</a></p>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>Can I change my plan later?</h3>
                    <p>Yes, you can upgrade or downgrade your plan at any time. Changes will be reflected in your next billing cycle.</p>
                </div>
                <div class="faq-item">
                    <h3>Is there a setup fee?</h3>
                    <p>No, all our plans include free setup and onboarding support to get you started quickly.</p>
                </div>
                <div class="faq-item">
                    <h3>What payment methods do you accept?</h3>
                    <p>We accept all major credit cards, bank transfers, and online payment methods.</p>
                </div>
                <div class="faq-item">
                    <h3>Is my data secure?</h3>
                    <p>Absolutely. We use enterprise-grade security measures to protect your data with regular backups.</p>
                </div>
            </div>
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