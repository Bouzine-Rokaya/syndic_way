<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="public/public/assets/css/style.css">
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>404 - Page Not Found</h1>
            <p>The page you are looking for doesn't exist or has been moved.</p>
            <div class="error-actions">
                <a href="index.php" class="btn btn-primary">Go Home</a>
                <a href="login.php" class="btn btn-secondary">Login</a>
            </div>
        </div>
    </div>

    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        
        .error-content {
            text-align: center;
            padding: 2rem;
            max-width: 500px;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        
        .error-content h1 {
            color: #343a40;
            margin-bottom: 1rem;
        }
        
        .error-content p {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</body>
</html>