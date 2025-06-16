<?php
require_once __DIR__ . '/../config/database.php';

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

function generateRandomColor() {
    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EF4444', '#06B6D4', '#84CC16', '#F97316'];
    return $colors[array_rand($colors)];
}

// File upload functions
function uploadImage($file, $upload_dir = 'assets/uploads/') {
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        return false;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        resizeImage($filepath, 800, 600);
        return $filepath;
    }

    return false;
}

function resizeImage($filepath, $max_width = 800, $max_height = 600) {
    $image_info = getimagesize($filepath);
    if (!$image_info) return false;

    list($width, $height, $type) = $image_info;

    $ratio = min($max_width / $width, $max_height / $height);
    if ($ratio >= 1) return true;

    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);

    $new_image = imagecreatetruecolor($new_width, $new_height);

    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($filepath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($filepath);
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($filepath);
            break;
        default:
            return false;
    }

    imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $filepath, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $filepath);
            break;
        case IMAGETYPE_GIF:
            imagegif($new_image, $filepath);
            break;
    }

    imagedestroy($source);
    imagedestroy($new_image);

    return true;
}

// Database helper functions
function getUserStats($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $stats = [];

    $query = "SELECT COUNT(*) as count FROM products WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM categories WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $stats['categories'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM qr_scans qs 
              JOIN products p ON qs.product_id = p.id 
              WHERE p.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $stats['scans'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM products WHERE user_id = ? AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $stats['active_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    return $stats;
}

function getSystemStats() {
    $database = new Database();
    $db = $database->getConnection();
    
    $stats = [];

    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM users WHERE status = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM products";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM qr_scans";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_scans'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    return $stats;
}

// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function validatePhone($phone) {
    return preg_match('/^[\+]?[0-9\s\-]{10,}$/', $phone);
}

// Security functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Export functions
function exportUserData($user_id, $format = 'json', $download = false) {
    $database = new Database();
    $db = $database->getConnection();

    $data = [];

    $query = "SELECT name, email, phone, address, created_at FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $data['user'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $query = "SELECT * FROM categories WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $data['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT p.*, c.name as category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $data['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT qs.*, p.name as product_name FROM qr_scans qs 
              JOIN products p ON qs.product_id = p.id 
              WHERE p.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $data['scan_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'json') {
        $output = json_encode($data, JSON_PRETTY_PRINT);
        if ($download) {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="qr_data_export_' . date('Ymd_His') . '.json"');
            echo $output;
            exit;
        }
        return $output;
    } elseif ($format === 'csv') {
        $csv = "User Data Export\n\n";
        $csv .= "Name: " . $data['user']['name'] . "\n";
        $csv .= "Email: " . $data['user']['email'] . "\n";
        $csv .= "Phone: " . $data['user']['phone'] . "\n";
        $csv .= "Address: " . $data['user']['address'] . "\n";
        $csv .= "Member Since: " . $data['user']['created_at'] . "\n\n";

        $csv .= "Products:\n";
        $csv .= "Name,Category,Price,Status,Created\n";
        foreach ($data['products'] as $product) {
            $csv .= '"' . $product['name'] . '","' . $product['category_name'] . '","' . $product['price'] . '","' . ($product['is_active'] ? 'Active' : 'Inactive') . '","' . $product['created_at'] . '"' . "\n";
        }

        if ($download) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="qr_data_export_' . date('Ymd_His') . '.csv"');
            echo $csv;
            exit;
        }
        return $csv;
    }

    return $data;
}

?>
