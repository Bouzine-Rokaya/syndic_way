<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';
require_once '../utils/Mailer.php';

checkAuthentication();
checkRole(ROLE_ADMIN);

$page_title = "Syndic Accounts";

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'process_purchase':
                $purchase_id = intval($_POST['purchase_id']);
                $result = processPurchase($db, $purchase_id);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;

            case 'create_manual':
                $result = createManualSyndic($db, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;

            case 'update_syndic':
                $result = updateSyndic($db, $_POST);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;

            case 'toggle_status':
                $user_id = intval($_POST['user_id']);
                $is_active = intval($_POST['is_active']);
                $stmt = $db->prepare("UPDATE utilisateur SET is_active = ? WHERE id_utilisateur = ?");
                if ($stmt->execute([$is_active, $user_id])) {
                    $_SESSION['success'] = 'User status updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update user status.';
                }
                break;
        }
        header('Location: syndic-accounts.php');
        exit();
    }
}

// Get pending purchases
$stmt = $db->query("SELECT sp.*, s.name as plan_name, s.price, s.max_residents, s.max_apartments 
                    FROM subscription_purchases sp 
                    JOIN subscriptions s ON sp.subscription_id = s.id_subscription 
                    WHERE sp.is_processed = 0 AND sp.payment_status = 'completed'
                    ORDER BY sp.purchase_date DESC");
$pending_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing syndics
$stmt = $db->query("SELECT u.*, s.nom_syndic, s.code_syndic, s.ville, sub.name as subscription_name, ss.end_date
                    FROM utilisateur u
                    LEFT JOIN syndic s ON u.id_utilisateur = s.id_admin_syndic
                    LEFT JOIN syndic_subscriptions ss ON s.subscription_id = ss.id
                    LEFT JOIN subscriptions sub ON ss.subscription_id = sub.id_subscription
                    WHERE u.role = 'syndic'
                    ORDER BY u.date_creation DESC");
$existing_syndics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get syndic for editing
$edit_syndic = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT u.*, s.* FROM utilisateur u 
                          LEFT JOIN syndic s ON u.id_utilisateur = s.id_admin_syndic 
                          WHERE u.id_utilisateur = ? AND u.role = 'syndic'");
    $stmt->execute([$edit_id]);
    $edit_syndic = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper functions
function processPurchase($db, $purchase_id)
{
    try {
        $db->beginTransaction();

        // Get purchase details
        $stmt = $db->prepare("SELECT sp.*, s.name as plan_name, s.duration_months 
                              FROM subscription_purchases sp 
                              JOIN subscriptions s ON sp.subscription_id = s.id_subscription 
                              WHERE sp.id = ?");
        $stmt->execute([$purchase_id]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) {
            throw new Exception('Purchase not found');
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM utilisateur WHERE email = ?");
        $stmt->execute([$purchase['syndic_email']]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('User with this email already exists');
        }

        // Generate temporary password
        $temp_password = generateRandomPassword();

        // Create user account
        $stmt = $db->prepare("INSERT INTO utilisateur (nom_complet, email, mot_de_passe, telephone, role, must_change_password, created_by) 
                              VALUES (?, ?, ?, ?, 'syndic', 1, ?)");
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        $stmt->execute([$purchase['syndic_name'], $purchase['syndic_email'], $hashed_password, $purchase['syndic_phone'], $_SESSION['user_id']]);
        $user_id = $db->lastInsertId();

        // Generate syndic code
        $syndic_code = 'SYN' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);

        // Create syndic record
        $stmt = $db->prepare("INSERT INTO syndic (nom_syndic, code_syndic, adresse_syndic, telephone, email, id_admin_syndic) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$purchase['company_name'], $syndic_code, $purchase['company_address'], $purchase['syndic_phone'], $purchase['syndic_email'], $user_id]);
        $syndic_id = $db->lastInsertId();

        // Create subscription record
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+' . $purchase['duration_months'] . ' months'));
        $stmt = $db->prepare("INSERT INTO syndic_subscriptions (syndic_id, subscription_id, start_date, end_date, payment_status, transaction_id) 
                              VALUES (?, ?, ?, ?, 'active', ?)");
        $stmt->execute([$syndic_id, $purchase['subscription_id'], $start_date, $end_date, $purchase['transaction_id']]);
        $subscription_id = $db->lastInsertId();

        // Update syndic with subscription reference
        $stmt = $db->prepare("UPDATE syndic SET subscription_id = ? WHERE id_syndic = ?");
        $stmt->execute([$subscription_id, $syndic_id]);

        // Mark purchase as processed
        $stmt = $db->prepare("UPDATE subscription_purchases SET is_processed = 1, processed_by = ?, processed_date = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $purchase_id]);

        $db->commit();

        // ✅ IMPORTANT: Send welcome email - This should trigger the email logging
        try {
            $mailer = new Mailer();
            $email_sent = $mailer->sendWelcomeEmail($purchase['syndic_email'], $purchase['syndic_name'], $temp_password);

            if ($email_sent) {
                return [
                    'success' => true,
                    'message' => 'Syndic account created successfully! Welcome email sent with login credentials. <a href="view-emails.php" target="_blank">View Email Debug</a>'
                ];
            } else {
                return [
                    'success' => true,
                    'message' => 'Syndic account created successfully, but email sending failed. Password: ' . $temp_password
                ];
            }
        } catch (Exception $email_error) {
            return [
                'success' => true,
                'message' => 'Syndic account created successfully, but email error: ' . $email_error->getMessage() . ' Password: ' . $temp_password
            ];
        }

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function createManualSyndic($db, $data)
{
    try {
        $db->beginTransaction();

        // Validate data
        $name = sanitizeInput($data['name']);
        $email = sanitizeInput($data['email']);
        $phone = sanitizeInput($data['phone']);
        $company_name = sanitizeInput($data['company_name']);
        $company_address = sanitizeInput($data['company_address']);
        $subscription_id = intval($data['subscription_id']);

        if (empty($name) || empty($email) || empty($company_name)) {
            throw new Exception('Required fields are missing');
        }

        if (!isValidEmail($email)) {
            throw new Exception('Invalid email address');
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('User with this email already exists');
        }

        // Generate temporary password
        $temp_password = generateRandomPassword();

        // Create user account
        $stmt = $db->prepare("INSERT INTO utilisateur (nom_complet, email, mot_de_passe, telephone, role, must_change_password, created_by) 
                              VALUES (?, ?, ?, ?, 'syndic', 1, ?)");
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        $stmt->execute([$name, $email, $hashed_password, $phone, $_SESSION['user_id']]);
        $user_id = $db->lastInsertId();

        // Generate syndic code
        $syndic_code = 'SYN' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);

        // Create syndic record
        $stmt = $db->prepare("INSERT INTO syndic (nom_syndic, code_syndic, adresse_syndic, telephone, email, id_admin_syndic) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$company_name, $syndic_code, $company_address, $phone, $email, $user_id]);
        $syndic_id = $db->lastInsertId();

        // Create subscription if selected
        if ($subscription_id > 0) {
            $stmt = $db->prepare("SELECT duration_months FROM subscriptions WHERE id_subscription = ?");
            $stmt->execute([$subscription_id]);
            $subscription = $stmt->fetch();

            if ($subscription) {
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime('+' . $subscription['duration_months'] . ' months'));
                $stmt = $db->prepare("INSERT INTO syndic_subscriptions (syndic_id, subscription_id, start_date, end_date, payment_status) 
                                      VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$syndic_id, $subscription_id, $start_date, $end_date]);
                $sub_id = $db->lastInsertId();

                // Update syndic with subscription reference
                $stmt = $db->prepare("UPDATE syndic SET subscription_id = ? WHERE id_syndic = ?");
                $stmt->execute([$sub_id, $syndic_id]);
            }
        }

        $db->commit();

        // ✅ Send welcome email
        try {
            $mailer = new Mailer();
            $email_sent = $mailer->sendWelcomeEmail($email, $name, $temp_password);

            if ($email_sent) {
                return [
                    'success' => true,
                    'message' => 'Syndic account created successfully! Welcome email sent. <a href="view-emails.php" target="_blank">View Email Debug</a>'
                ];
            } else {
                return [
                    'success' => true,
                    'message' => 'Syndic account created successfully, but email sending failed. Password: ' . $temp_password
                ];
            }
        } catch (Exception $email_error) {
            return [
                'success' => true,
                'message' => 'Syndic account created successfully, but email error: ' . $email_error->getMessage() . ' Password: ' . $temp_password
            ];
        }

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateSyndic($db, $data)
{
    try {
        $user_id = intval($data['user_id']);
        $name = sanitizeInput($data['name']);
        $email = sanitizeInput($data['email']);
        $phone = sanitizeInput($data['phone']);
        $company_name = sanitizeInput($data['company_name']);
        $company_address = sanitizeInput($data['company_address']);

        // Update user
        $stmt = $db->prepare("UPDATE utilisateur SET nom_complet = ?, email = ?, telephone = ? WHERE id_utilisateur = ?");
        $stmt->execute([$name, $email, $phone, $user_id]);

        // Update syndic
        $stmt = $db->prepare("UPDATE syndic SET nom_syndic = ?, adresse_syndic = ?, telephone = ?, email = ? WHERE id_admin_syndic = ?");
        $stmt->execute([$company_name, $company_address, $phone, $email, $user_id]);

        return ['success' => true, 'message' => 'Syndic account updated successfully!'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function generateRandomPassword($length = 12)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?></span>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Navigation</h3>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="subscriptions.php"><i class="fas fa-tags"></i> Subscriptions</a></li>
                    <li class="active"><a href="syndic-accounts.php"><i class="fas fa-building"></i> Syndic Accounts</a>
                    </li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-building"></i> Syndic Accounts</h1>
                <p>Process purchases and manage syndicate accounts</p>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Pending Purchases -->
            <?php if (!empty($pending_purchases)): ?>
                <div class="content-section">
                    <h2><i class="fas fa-clock"></i> Pending Purchase Processing (<?php echo count($pending_purchases); ?>)
                    </h2>
                    <p class="section-description">These customers have completed their payment and are waiting for account
                        creation.</p>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Purchase Date</th>
                                    <th>Customer Details</th>
                                    <th>Subscription Plan</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_purchases as $purchase): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y H:i', strtotime($purchase['purchase_date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($purchase['syndic_name']); ?></strong><br>
                                            <small><i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($purchase['syndic_email']); ?></small><br>
                                            <small><i class="fas fa-phone"></i>
                                                <?php echo htmlspecialchars($purchase['syndic_phone']); ?></small><br>
                                            <small><i class="fas fa-building"></i>
                                                <?php echo htmlspecialchars($purchase['company_name']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($purchase['plan_name']); ?></strong><br>
                                            <small>Max: <?php echo $purchase['max_residents']; ?> residents,
                                                <?php echo $purchase['max_apartments']; ?> apartments</small>
                                        </td>
                                        <td>
                                            <strong>€<?php echo number_format($purchase['amount_paid'], 2); ?></strong><br>
                                            <span class="status-badge status-completed">Paid</span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="process_purchase">
                                                <input type="hidden" name="purchase_id" value="<?php echo $purchase['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-primary"
                                                    onclick="return confirm('Create syndic account for <?php echo htmlspecialchars($purchase['syndic_name']); ?>?')">
                                                    <i class="fas fa-user-plus"></i> Create Account
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Manual Syndic Creation -->
            <div class="content-section">
                <h2><?php echo $edit_syndic ? 'Edit Syndic Account' : 'Create Syndic Account Manually'; ?></h2>
                <p class="section-description">Create a syndic account without a purchase (for testing or special
                    cases).</p>

                <form method="POST" class="syndic-form">
                    <input type="hidden" name="action"
                        value="<?php echo $edit_syndic ? 'update_syndic' : 'create_manual'; ?>">
                    <?php if ($edit_syndic): ?>
                        <input type="hidden" name="user_id" value="<?php echo $edit_syndic['id_utilisateur']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Contact Name *</label>
                            <input type="text" id="name" name="name"
                                value="<?php echo $edit_syndic ? htmlspecialchars($edit_syndic['nom_complet']) : ''; ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email"
                                value="<?php echo $edit_syndic ? htmlspecialchars($edit_syndic['email']) : ''; ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone"
                                value="<?php echo $edit_syndic ? htmlspecialchars($edit_syndic['telephone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="company_name">Company/Building Name *</label>
                            <input type="text" id="company_name" name="company_name"
                                value="<?php echo $edit_syndic ? htmlspecialchars($edit_syndic['nom_syndic']) : ''; ?>"
                                required>
                        </div>

                        <?php if (!$edit_syndic): ?>
                            <div class="form-group">
                                <label for="subscription_id">Subscription Plan</label>
                                <select id="subscription_id" name="subscription_id">
                                    <option value="0">No Subscription</option>
                                    <?php
                                    $stmt = $db->query("SELECT * FROM subscriptions WHERE is_active = 1 ORDER BY price ASC");
                                    $subscriptions = $stmt->fetchAll();
                                    foreach ($subscriptions as $sub): ?>
                                        <option value="<?php echo $sub['id_subscription']; ?>">
                                            <?php echo htmlspecialchars($sub['name']); ?> - €<?php echo $sub['price']; ?>/month
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="company_address">Address</label>
                        <textarea id="company_address" name="company_address"
                            rows="3"><?php echo $edit_syndic ? htmlspecialchars($edit_syndic['adresse_syndic']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <?php if ($edit_syndic): ?>
                            <a href="syndic-accounts.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $edit_syndic ? 'Update Account' : 'Create Account'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Syndics -->
            <div class="content-section">
                <h2>Existing Syndic Accounts (<?php echo count($existing_syndics); ?>)</h2>

                <?php if (!empty($existing_syndics)): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Syndic Code</th>
                                    <th>Contact Details</th>
                                    <th>Company</th>
                                    <th>Subscription</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_syndics as $syndic): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($syndic['code_syndic'] ?? 'N/A'); ?></strong><br>
                                            <small>Created:
                                                <?php echo date('M j, Y', strtotime($syndic['date_creation'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($syndic['nom_complet']); ?></strong><br>
                                            <small><i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($syndic['email']); ?></small><br>
                                            <?php if ($syndic['telephone']): ?>
                                                <small><i class="fas fa-phone"></i>
                                                    <?php echo htmlspecialchars($syndic['telephone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($syndic['nom_syndic'] ?? 'N/A'); ?></strong><br>
                                            <?php if ($syndic['ville']): ?>
                                                <small><i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($syndic['ville']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($syndic['subscription_name']): ?>
                                                <strong><?php echo htmlspecialchars($syndic['subscription_name']); ?></strong><br>
                                                <small>Expires:
                                                    <?php echo $syndic['end_date'] ? date('M j, Y', strtotime($syndic['end_date'])) : 'N/A'; ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">No Subscription</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span
                                                class="status-badge status-<?php echo $syndic['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $syndic['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <?php if ($syndic['must_change_password']): ?>
                                                <br><small class="text-warning">Must change password</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="syndic-accounts.php?edit=<?php echo $syndic['id_utilisateur']; ?>"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>

                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id"
                                                        value="<?php echo $syndic['id_utilisateur']; ?>">
                                                    <input type="hidden" name="is_active"
                                                        value="<?php echo $syndic['is_active'] ? 0 : 1; ?>">
                                                    <button type="submit"
                                                        class="btn btn-sm <?php echo $syndic['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                                        onclick="return confirm('<?php echo $syndic['is_active'] ? 'Deactivate' : 'Activate'; ?> this account?')">
                                                        <i
                                                            class="fas fa-<?php echo $syndic['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        <?php echo $syndic['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <h3>No syndic accounts</h3>
                        <p>Process purchases or create accounts manually to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .section-description {
            color: #6c757d;
            margin-bottom: 1.5rem;
            font-style: italic;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .syndic-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .text-warning {
            color: #856404;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>

    <script src="<?php echo BASE_URL; ?>public/assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Check if success message contains email debug link
            const successAlert = document.querySelector('.alert-success');
            if (successAlert && successAlert.innerHTML.includes('View Email Debug')) {
                // Add a button to easily view emails
                const emailButton = document.createElement('button');
                emailButton.className = 'btn btn-sm btn-info';
                emailButton.innerHTML = '<i class="fas fa-envelope"></i> View Sent Email';
                emailButton.style.marginLeft = '10px';
                emailButton.onclick = function () {
                    window.open('view-emails.php', '_blank');
                };
                successAlert.appendChild(emailButton);
            }
        });
    </script>
</body>

</html>