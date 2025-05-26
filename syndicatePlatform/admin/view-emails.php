<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';
require_once '../utils/Mailer.php'; 

checkAuthentication();
checkRole(ROLE_ADMIN);

$page_title = "Email Debug - View Sent Emails";

// ✅ ADD: Debug session info
$debug_info = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'emails_in_session' => isset($_SESSION['sent_emails']) ? count($_SESSION['sent_emails']) : 0,
    'session_data_exists' => isset($_SESSION['sent_emails']),
];

// Get emails from session
$sent_emails = $_SESSION['sent_emails'] ?? [];

// Option to clear emails
if (isset($_GET['clear'])) {
    $_SESSION['sent_emails'] = [];
    header('Location: view-emails.php');
    exit();
}

// ✅ ADD: Debug action
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "DEBUG INFO:\n";
    print_r($debug_info);
    echo "\nSESSION DATA:\n";
    print_r($_SESSION);
    echo "</pre>";
    exit();
}

// Read email log file
$log_file = __DIR__ . '/../storage/logs/emails.log';
$log_content = '';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
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
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-envelope"></i> Email Debug</h2>
        </div>
        <div class="nav-user">
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            <a href="syndic-accounts.php" class="btn btn-primary">Syndic Accounts</a>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-content" style="margin-left: 0; padding: 2rem;">
        <div class="content-header">
            <h1><i class="fas fa-envelope"></i> Email Debug - Sent Emails</h1>
            <p>View emails that would be sent (development mode)</p>
        </div>

        <!-- ✅ ADD: Debug info panel -->
        <div class="content-section" style="background: #e3f2fd; border-left: 4px solid #2196f3;">
            <h3>🐛 Debug Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0;">
                <div><strong>Session ID:</strong> <?php echo substr($debug_info['session_id'], 0, 10); ?>...</div>
                <div><strong>Session Status:</strong> <?php echo $debug_info['session_status']; ?></div>
                <div><strong>Emails in Session:</strong> <?php echo $debug_info['emails_in_session']; ?></div>
                <div><strong>Session Data Exists:</strong> <?php echo $debug_info['session_data_exists'] ? 'Yes' : 'No'; ?></div>
            </div>
            <p style="margin: 1rem 0;">
                <a href="view-emails.php?debug=1" class="btn btn-sm btn-info">View Full Debug</a>
                <button onclick="location.reload()" class="btn btn-sm btn-secondary">Refresh Page</button>
            </p>
        </div>

        <div class="content-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Recent Emails (<?php echo count($sent_emails); ?>)</h2>
                <div>
                    <?php if (!empty($sent_emails)): ?>
                    <a href="view-emails.php?clear=1" class="btn btn-warning" onclick="return confirm('Clear all emails?')">
                        <i class="fas fa-trash"></i> Clear All
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($sent_emails)): ?>
                <?php foreach(array_reverse($sent_emails) as $index => $email): ?>
                <div class="email-preview" style="border: 1px solid #ddd; margin-bottom: 2rem; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div class="email-header" style="background: #f8f9fa; padding: 1rem; border-bottom: 1px solid #ddd;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>📧 To:</strong> <?php echo htmlspecialchars($email['to']); ?><br>
                                <strong>📝 Subject:</strong> <?php echo htmlspecialchars($email['subject']); ?>
                            </div>
                            <div style="text-align: right; color: #666; font-size: 0.9rem;">
                                <i class="fas fa-clock"></i> <?php echo $email['timestamp']; ?><br>
                                <span class="badge" style="background: <?php echo $email['is_html'] ? '#28a745' : '#6c757d'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem;">
                                    <?php echo $email['is_html'] ? 'HTML' : 'TEXT'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="email-body" style="padding: 1rem;">
                        <?php if ($email['is_html']): ?>
                            <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 4px;">
                                <?php echo $email['body']; ?>
                            </div>
                        <?php else: ?>
                            <pre style="white-space: pre-wrap; background: #f8f9fa; padding: 1rem; border-radius: 4px; max-height: 300px; overflow-y: auto;"><?php echo htmlspecialchars($email['body']); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open"></i>
                    <h3>No emails sent yet</h3>
                    <p>Create a syndic account to see welcome emails here.</p>
                    <div style="margin-top: 1rem;">
                        <a href="syndic-accounts.php" class="btn btn-primary">Go to Syndic Accounts</a>
                        <button onclick="location.reload()" class="btn btn-secondary">Refresh Page</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($log_content)): ?>
        <div class="content-section">
            <h2>📋 Email Log File</h2>
            <div style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 4px; border: 1px solid #dee2e6;">
                <pre style="margin: 0; font-size: 0.8rem;"><?php echo htmlspecialchars($log_content); ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <style>
        .email-preview {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s ease;
        }
        
        .email-preview:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .badge {
            display: inline-block;
        }
    </style>

    <script>
        // Auto-refresh every 10 seconds if page is active
        let refreshInterval;
        
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    location.reload();
                }
            }, 10000);
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }
        
        // Start auto-refresh
        startAutoRefresh();
        
        // Stop auto-refresh when page is not visible
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
    </script>
</body>
</html>