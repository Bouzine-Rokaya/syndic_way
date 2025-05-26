<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <h2><?php echo APP_NAME; ?></h2>
        </div>
        <?php if(isset($_SESSION['user_id'])): ?>
        <div class="nav-user">
            <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-logout">Logout</a>
        </div>
        <?php endif; ?>
    </nav>