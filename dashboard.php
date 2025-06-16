<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/qr-generator.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

// Get user statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM categories WHERE user_id = ?) as total_categories,
    (SELECT COUNT(*) FROM products WHERE user_id = ?) as total_products,
    (SELECT COUNT(*) FROM qr_scans qs JOIN products p ON qs.product_id = p.id WHERE p.user_id = ?) as total_scans";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id, $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent products
$products_query = "SELECT p.*, c.name as category_name FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 5";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute([$user_id]);
$recent_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent scans
$scans_query = "SELECT p.name as product_name, qs.scanned_at, qs.ip_address 
                FROM qr_scans qs 
                JOIN products p ON qs.product_id = p.id 
                WHERE p.user_id = ? 
                ORDER BY qs.scanned_at DESC LIMIT 5";
$scans_stmt = $db->prepare($scans_query);
$scans_stmt->execute([$user_id]);
$recent_scans = $scans_stmt->fetchAll(PDO::FETCH_ASSOC);

$flash_message = getFlashMessage();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php require_once 'includes/navigation.php'; ?>
    <?php renderNavigation(); ?>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($flash_message): ?>
            <div class="mb-6 bg-<?php echo $flash_message['type'] === 'success' ? 'green' : 'red'; ?>-50 border border-<?php echo $flash_message['type'] === 'success' ? 'green' : 'red'; ?>-200 text-<?php echo $flash_message['type'] === 'success' ? 'green' : 'red'; ?>-700 px-4 py-3 rounded-lg">
                <i class="fas fa-<?php echo $flash_message['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($flash_message['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl shadow-xl p-8 mb-8 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars(getUserName()); ?>!</h1>
                    <p class="text-indigo-100">Manage your products and track QR code performance</p>
                </div>
                <div class="hidden md:block">
                    <i class="fas fa-chart-line text-6xl opacity-20"></i>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow-xl rounded-2xl hover:shadow-2xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-folder text-xl text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Categories</dt>
                                <dd class="text-2xl font-bold text-gray-900"><?php echo $stats['total_categories']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-xl rounded-2xl hover:shadow-2xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-teal-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-box text-xl text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Products</dt>
                                <dd class="text-2xl font-bold text-gray-900"><?php echo $stats['total_products']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-xl rounded-2xl hover:shadow-2xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-eye text-xl text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Scans</dt>
                                <dd class="text-2xl font-bold text-gray-900"><?php echo $stats['total_scans']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white shadow-xl rounded-2xl mb-8">
            <div class="px-6 py-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="categories.php" class="group bg-gradient-to-r from-blue-50 to-cyan-50 hover:from-blue-100 hover:to-cyan-100 p-6 rounded-xl text-center transition-all transform hover:scale-105 border border-blue-100">
                        <i class="fas fa-folder-plus text-3xl text-blue-600 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-blue-900">Manage Categories</p>
                    </a>
                    <a href="products.php" class="group bg-gradient-to-r from-green-50 to-teal-50 hover:from-green-100 hover:to-teal-100 p-6 rounded-xl text-center transition-all transform hover:scale-105 border border-green-100">
                        <i class="fas fa-plus-circle text-3xl text-green-600 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-green-900">Add Products</p>
                    </a>
                    <a href="qr-codes.php" class="group bg-gradient-to-r from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 p-6 rounded-xl text-center transition-all transform hover:scale-105 border border-purple-100">
                        <i class="fas fa-qrcode text-3xl text-purple-600 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-purple-900">View QR Codes</p>
                    </a>
                    <a href="analytics.php" class="group bg-gradient-to-r from-yellow-50 to-orange-50 hover:from-yellow-100 hover:to-orange-100 p-6 rounded-xl text-center transition-all transform hover:scale-105 border border-yellow-100">
                        <i class="fas fa-chart-bar text-3xl text-yellow-600 mb-3 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-yellow-900">Analytics</p>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Products -->
            <div class="bg-white shadow-xl rounded-2xl">
                <div class="px-6 py-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900">Recent Products</h3>
                        <a href="products.php" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">View all →</a>
                    </div>
                    <?php if (empty($recent_products)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 mb-4">No products yet</p>
                            <a href="products.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Create your first product
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_products as $product): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-box text-white"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($product['category_name']); ?> • <?php echo formatPrice($product['price']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <a href="view-product.php?id=<?php echo $product['id']; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="text-green-600 hover:text-green-800 text-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Scans -->
            <div class="bg-white shadow-xl rounded-2xl">
                <div class="px-6 py-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900">Recent Scans</h3>
                        <a href="analytics.php" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">View all →</a>
                    </div>
                    <?php if (empty($recent_scans)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No scans yet</p>
                            <p class="text-xs text-gray-400 mt-2">Share your QR codes to start tracking scans</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_scans as $scan): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-teal-500 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-qrcode text-white"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($scan['product_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo timeAgo($scan['scanned_at']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        <?php echo htmlspecialchars($scan['ip_address']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
