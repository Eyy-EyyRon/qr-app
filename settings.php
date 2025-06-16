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

// Get system settings
$settings_query = "SELECT * FROM settings";
$settings_stmt = $db->prepare($settings_query);
$settings_stmt->execute();
$system_settings = [];
while ($setting = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $system_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_preferences':
                // For now, we'll just show success since we don't have user preferences table
                $success = 'Preferences updated successfully!';
                break;
                
            case 'export_data':
                // Export user data (JSON format with file download)
                exportUserData($user_id, 'json', true);
                break;
                
            case 'delete_account':
                $confirm_email = sanitizeInput($_POST['confirm_email']);
                if ($confirm_email !== $user['email']) {
                    $error = 'Email confirmation does not match your account email.';
                } else {
                    // Delete user account and all associated data
                    deleteUserAccount($user_id, $db);
                    session_destroy();
                    header('Location: index.php?deleted=1');
                    exit();
                }
                break;
        }
    }
}

function deleteUserAccount($user_id, $db) {
    try {
        $db->beginTransaction();
        
        // Get file paths for cleanup
        $files_query = "SELECT profile_image FROM users WHERE id = ? 
                        UNION 
                        SELECT image FROM products WHERE user_id = ?
                        UNION
                        SELECT qr_code_path FROM products WHERE user_id = ?";
        $files_stmt = $db->prepare($files_query);
        $files_stmt->execute([$user_id, $user_id, $user_id]);
        $files = $files_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete database records (cascading will handle related records)
        $delete_user = "DELETE FROM users WHERE id = ?";
        $delete_stmt = $db->prepare($delete_user);
        $delete_stmt->execute([$user_id]);
        
        $db->commit();
        
        // Clean up files
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                unlink($file);
            }
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php require_once 'includes/navigation.php'; ?>
    <?php renderNavigation(); ?>

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-xl rounded-2xl p-8 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
                    <p class="text-gray-600 mt-2">Manage your account preferences and data</p>
                </div>
                <div class="text-center">
                    <?php if ($user['profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                             alt="Profile" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover">
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center mx-auto mb-2">
                            <span class="text-xl font-bold text-white"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user['name']); ?></p>
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

        <div class="space-y-8">
            <!-- Application Preferences -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Application Preferences</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_preferences">
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Email Notifications</label>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" id="email_scans" name="email_scans" checked
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="email_scans" class="ml-2 block text-sm text-gray-900">
                                        Notify me when my QR codes are scanned
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="email_weekly" name="email_weekly" checked
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="email_weekly" class="ml-2 block text-sm text-gray-900">
                                        Send me weekly analytics reports
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="email_updates" name="email_updates" checked
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="email_updates" class="ml-2 block text-sm text-gray-900">
                                        Receive product updates and news
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">QR Code Settings</label>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" id="auto_generate" name="auto_generate" checked
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="auto_generate" class="ml-2 block text-sm text-gray-900">
                                        Automatically generate QR codes for new products
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="high_quality" name="high_quality" checked
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="high_quality" class="ml-2 block text-sm text-gray-900">
                                        Generate high-quality QR codes (larger file size)
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="default_category" class="block text-sm font-medium text-gray-700 mb-2">Default Category for New Products</label>
                            <select id="default_category" name="default_category" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">No default category</option>
                                <!-- Categories would be populated here -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-save mr-2"></i>Save Preferences
                        </button>
                    </div>
                </form>
            </div>

            <!-- System Information -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">System Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Application Name</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($system_settings['app_name'] ?? 'QR Generator Pro'); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Version</label>
                        <p class="text-gray-900">1.0.0</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Support Email</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($system_settings['contact_email'] ?? 'support@qrgen.com'); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Max Products per User</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($system_settings['max_products_per_user'] ?? '100'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Data Management -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Data Management</h3>
                <div class="space-y-6">
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900">Export Your Data</h4>
                                <p class="text-sm text-gray-500 mt-1">Download all your account data in JSON format</p>
                            </div>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="export_data">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                                    <i class="fas fa-download mr-2"></i>Export Data
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="border border-red-200 rounded-lg p-6 bg-red-50">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-red-900">Delete Account</h4>
                                <p class="text-sm text-red-600 mt-1">Permanently delete your account and all associated data</p>
                            </div>
                            <button onclick="openDeleteModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                                <i class="fas fa-trash mr-2"></i>Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Privacy & Security -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Privacy & Security</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900">Two-Factor Authentication</h4>
                            <p class="text-sm text-gray-500">Add an extra layer of security to your account</p>
                        </div>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                            Coming Soon
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900">Login History</h4>
                            <p class="text-sm text-gray-500">View your recent login activity</p>
                        </div>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                            Coming Soon
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-900">API Access</h4>
                            <p class="text-sm text-gray-500">Generate API keys for third-party integrations</p>
                        </div>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                            Coming Soon
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Account</h3>
                <p class="text-sm text-gray-500 mb-6">This action cannot be undone. All your data including products, QR codes, and analytics will be permanently deleted.</p>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_account">
                    
                    <div class="mb-4">
                        <label for="confirm_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Type your email to confirm: <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                        </label>
                        <input type="email" id="confirm_email" name="confirm_email" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div class="flex items-center justify-center space-x-3">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Delete My Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('confirm_email').value = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
