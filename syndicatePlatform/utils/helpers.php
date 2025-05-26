<?php
// General helper functions

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function redirectTo($url) {
    header("Location: " . $url);
    exit();
}

function checkAuthentication() {
    if (!isset($_SESSION['user_id'])) {
        redirectTo(BASE_URL . 'login.php');
    }
}

function checkRole($required_role) {
    checkAuthentication();
    if ($_SESSION['user_role'] !== $required_role) {
        http_response_code(403);
        die('Access denied');
    }
}
?>