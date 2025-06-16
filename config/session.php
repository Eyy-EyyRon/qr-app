<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['user_name'] ?? '';
}

function getUserEmail() {
    return $_SESSION['user_email'] ?? '';
}

function getUserRole() {
    return $_SESSION['role'] ?? 'user';
}

function getUserProfileImage() {
    return $_SESSION['profile_image'] ?? null;
}

function updateUserSession($user_data) {
    $_SESSION['user_name'] = $user_data['name'];
    $_SESSION['user_email'] = $user_data['email'];
    $_SESSION['profile_image'] = $user_data['profile_image'];
    $_SESSION['phone'] = $user_data['phone'] ?? '';
    $_SESSION['address'] = $user_data['address'] ?? '';
}

function refreshUserSession($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        updateUserSession($user);
        return true;
    }
    return false;
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function renderProfileImage($size = 'w-10 h-10', $classes = '') {
    $profile_image = getUserProfileImage();
    $user_name = getUserName();
    $initials = strtoupper(substr($user_name, 0, 1));
    
    if ($profile_image && file_exists($profile_image)) {
        return '<img src="' . htmlspecialchars($profile_image) . '" alt="Profile" class="' . $size . ' rounded-full object-cover ' . $classes . '">';
    } else {
        return '<div class="' . $size . ' rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center ' . $classes . '">
                    <span class="text-white font-bold">' . $initials . '</span>
                </div>';
    }
}
?>
