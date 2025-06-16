<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

$error = '';
$success = '';

// Get current user data
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $name = sanitizeInput($_POST['name']);
                $email = sanitizeInput($_POST['email']);
                $phone = sanitizeInput($_POST['phone']);
                $address = sanitizeInput($_POST['address']);
                
                if (empty($name) || empty($email)) {
                    $error = 'Name and email are required.';
                } elseif (!validateEmail($email)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    // Check if email is already taken by another user
                    $email_check = "SELECT id FROM users WHERE email = ? AND id != ?";
                    $email_stmt = $db->prepare($email_check);
                    $email_stmt->execute([$email, $user_id]);
                    
                    if ($email_stmt->fetch()) {
                        $error = 'Email address is already taken by another user.';
                    } else {
                        // Handle profile image upload
                        $image_update = '';
                        $image_params = [];
                        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                            $image_path = uploadImage($_FILES['profile_image'], 'assets/profiles/');
                            if ($image_path) {
                                // Delete old profile image
                                if ($user['profile_image'] && file_exists($user['profile_image'])) {
                                    unlink($user['profile_image']);
                                }
                                $image_update = ', profile_image = ?';
                                $image_params[] = $image_path;
                            }
                        }
                        
                        $query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?" . $image_update . " WHERE id = ?";
                        $params = array_merge([$name, $email, $phone, $address], $image_params, [$user_id]);
                        $stmt = $db->prepare($query);
                        
                        if ($stmt->execute($params)) {
                            // Update session data
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_email'] = $email;
                            
                            // Refresh user data in session including profile image
                            refreshUserSession($user_id);
                            
                            // Refresh user data
                            $user_stmt->execute([$user_id]);
                            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $success = 'Profile updated successfully!';
                        } else {
                            $error = 'Failed to update profile.';
                        }
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = 'All password fields are required.';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$hashed_password, $user_id])) {
                        $success = 'Password changed successfully!';
                    } else {
                        $error = 'Failed to change password.';
                    }
                }
                break;
        }
    }
}

// Get user statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM categories WHERE user_id = ?) as total_categories,
    (SELECT COUNT(*) FROM products WHERE user_id = ?) as total_products,
    (SELECT COUNT(*) FROM qr_scans qs JOIN products p ON qs.product_id = p.id WHERE p.user_id = ?) as total_scans";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id, $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php require_once 'includes/navigation.php'; ?>
    <?php renderNavigation(); ?>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-xl rounded-2xl p-8 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="mb-4 md:mb-0">
                    <h1 class="text-3xl font-bold text-gray-900">Profile Settings</h1>
                    <p class="text-gray-600 mt-2">Manage your account information and preferences</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-center">
                        <?php if ($user['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="Profile" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover">
                        <?php else: ?>
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center mx-auto mb-2">
                                <span class="text-xl font-bold text-white"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                            </div>
                        <?php endif; ?>
                        <p class="text-sm text-gray-600">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Statistics -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-xl rounded-2xl p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Account Statistics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-folder text-white text-sm"></i>
                                </div>
                                <span class="text-gray-700">Categories</span>
                            </div>
                            <span class="font-bold text-gray-900"><?php echo $stats['total_categories']; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-teal-500 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-box text-white text-sm"></i>
                                </div>
                                <span class="text-gray-700">Products</span>
                            </div>
                            <span class="font-bold text-gray-900"><?php echo $stats['total_products']; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-eye text-white text-sm"></i>
                                </div>
                                <span class="text-gray-700">Total Scans</span>
                            </div>
                            <span class="font-bold text-gray-900"><?php echo $stats['total_scans']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Account Status -->
                <div class="bg-white shadow-xl rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Account Status</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Status</span>
                            <span class="px-3 py-1 rounded-full text-sm font-medium 
                                <?php echo $user['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                           ($user['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Role</span>
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">Last Updated</span>
                            <span class="text-sm text-gray-500"><?php echo timeAgo($user['updated_at']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Forms -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Profile Information -->
                <div class="bg-white shadow-xl rounded-2xl p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Profile Information</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                                <div class="flex items-center space-x-4 profile-preview">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                             alt="Profile" class="w-16 h-16 rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center">
                                            <span class="text-xl font-bold text-white"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="profile_image" id="profile_image" accept="image/*" 
                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Max file size: 5MB. Supported formats: JPG, PNG, GIF</p>
                            </div>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($user['name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea id="address" name="address" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                                <i class="fas fa-save mr-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="bg-white shadow-xl rounded-2xl p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Change Password</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                                <input type="password" id="new_password" name="new_password" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long</p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                                <i class="fas fa-key mr-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/profile-update.js"></script>
    <script>
// Handle successful profile update
<?php if ($success && strpos($success, 'Profile updated') !== false): ?>
    // Check if a new image was uploaded
    <?php if (isset($image_path)): ?>
        notifyProfileUpdate('<?php echo $image_path; ?>');
    <?php else: ?>
        notifyProfileUpdate('<?php echo htmlspecialchars($user['profile_image'] ?? ''); ?>');
    <?php endif; ?>
<?php endif; ?>

// Preview image before upload
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.profile-preview img, .profile-preview div');
            if (preview) {
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    // Replace div with img
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Profile Preview';
                    img.className = 'w-16 h-16 rounded-full object-cover';
                    preview.parentNode.replaceChild(img, preview);
                }
            }
        };
        reader.readAsDataURL(file);
    }
});
</script>
</body>
</html>
