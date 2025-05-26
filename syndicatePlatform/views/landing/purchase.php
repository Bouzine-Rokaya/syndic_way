<?php
$page_title = "Complete Your Purchase - " . APP_NAME;
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

    <!-- Purchase Form Section -->
    <section class="purchase-form">
        <div class="container">
            <div class="purchase-content">
                <div class="purchase-header">
                    <h1>Complete Your Purchase</h1>
                    <p>You're almost ready to start managing your building more efficiently!</p>
                </div>

                <div class="purchase-wrapper">
                    <!-- Selected Plan Summary -->
                    <div class="plan-summary">
                        <h3>Selected Plan</h3>
                        <div class="selected-plan-card">
                            <h4><?php echo htmlspecialchars($plan['name']); ?></h4>
                            <div class="plan-price">
                                <span class="currency">€</span>
                                <span class="amount"><?php echo number_format($plan['price'], 0); ?></span>
                                <span class="period">/month</span>
                            </div>
                            <ul class="plan-benefits">
                                <li><i class="fas fa-check"></i> Up to <?php echo $plan['max_residents']; ?> residents</li>
                                <li><i class="fas fa-check"></i> Up to <?php echo $plan['max_apartments']; ?> apartments</li>
                                <li><i class="fas fa-check"></i> Full maintenance management</li>
                                <li><i class="fas fa-check"></i> Billing & invoicing system</li>
                                <?php if($plan['name'] !== 'Basic Plan'): ?>
                                <li><i class="fas fa-check"></i> Advanced reporting</li>
                                <li><i class="fas fa-check"></i> Email notifications</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Purchase Form -->
                    <div class="form-section">
                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-error">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['errors'])): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach($_SESSION['errors'] as $error): ?>
                                <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php unset($_SESSION['errors']); ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="purchase-form">
                            <div class="form-section-header">
                                <h3><i class="fas fa-user"></i> Contact Information</h3>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="syndic_name">Full Name *</label>
                                    <input type="text" id="syndic_name" name="syndic_name" 
                                           value="<?php echo htmlspecialchars($_POST['syndic_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="syndic_email">Email Address *</label>
                                    <input type="email" id="syndic_email" name="syndic_email" 
                                           value="<?php echo htmlspecialchars($_POST['syndic_email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="syndic_phone">Phone Number *</label>
                                <input type="tel" id="syndic_phone" name="syndic_phone" 
                                       value="<?php echo htmlspecialchars($_POST['syndic_phone'] ?? ''); ?>" required>
                            </div>

                            <div class="form-section-header">
                                <h3><i class="fas fa-building"></i> Company Information</h3>
                            </div>

                            <div class="form-group">
                                <label for="company_name">Company/Building Name *</label>
                                <input type="text" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="company_address">Building Address</label>
                                <textarea id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($_POST['company_address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-section-header">
                                <h3><i class="fas fa-credit-card"></i> Payment Summary</h3>
                            </div>

                            <div class="payment-summary">
                                <div class="summary-row">
                                    <span>Plan: <?php echo htmlspecialchars($plan['name']); ?></span>
                                    <span>€<?php echo number_format($plan['price'], 2); ?>/month</span>
                                </div>
                                <div class="summary-row">
                                    <span>Setup Fee:</span>
                                    <span class="free">FREE</span>
                                </div>
                                <div class="summary-row total">
                                    <span><strong>Monthly Total:</strong></span>
                                    <span><strong>€<?php echo number_format($plan['price'], 2); ?></strong></span>
                                </div>
                            </div>

                            <div class="terms-section">
                                <label class="checkbox-label">
                                    <input type="checkbox" required>
                                    <span class="checkmark"></span>
                                    I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
                                </label>
                            </div>

                            <div class="form-actions">
                                <a href="?page=subscriptions" class="btn btn-secondary">← Back to Plans</a>
                                <button type="submit" class="btn btn-primary btn-large">
                                    <i class="fas fa-credit-card"></i> Complete Purchase
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security & Trust Section -->
    <section class="trust-section">
        <div class="container">
            <div class="trust-items">
                <div class="trust-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Payment</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-clock"></i>
                    <span>24/7 Support</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-undo"></i>
                    <span>30-Day Money Back</span>
                </div>
            </div>
        </div>
    </section>

    <style>
        .purchase-form {
            padding: 4rem 0;
            background: #f8f9fa;
        }
        
        .purchase-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .purchase-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .purchase-header h1 {
            font-size: 2.5rem;
            color: #343a40;
            margin-bottom: 1rem;
        }
        
        .purchase-wrapper {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 3rem;
        }
        
        .plan-summary {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .selected-plan-card h4 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .plan-price {
            margin-bottom: 1.5rem;
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .plan-price .amount {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .plan-benefits {
            list-style: none;
            padding: 0;
        }
        
        .plan-benefits li {
            padding: 0.5rem 0;
            color: #343a40;
        }
        
        .plan-benefits i {
            color: #28a745;
            margin-right: 0.75rem;
        }
        
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-section-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .form-section-header h3 {
            color: #343a40;
            margin: 0;
        }
        
        .form-section-header i {
            color: #667eea;
            margin-right: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .payment-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }
        
        .summary-row.total {
            border-top: 2px solid #dee2e6;
            margin-top: 1rem;
            padding-top: 1rem;
        }
        
        .free {
            color: #28a745;
            font-weight: bold;
        }
        
        .terms-section {
            margin-bottom: 2rem;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 0.75rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        
        .trust-section {
            padding: 2rem 0;
            background: white;
            border-top: 1px solid #e9ecef;
        }
        
        .trust-items {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
        }
        
        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
        }
        
        .trust-item i {
            color: #28a745;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .purchase-wrapper {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .trust-items {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
        }
    </style>

    <script src="assets/js/landing.js"></script>
</body>
</html>