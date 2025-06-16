<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';
require_once 'includes/qr-generator.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

$error = '';
$success = '';
$product = null;

// Get product ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: products.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Get product details
$product_query = "SELECT p.*, c.name as category_name FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.id = ? AND p.user_id = ?";
$product_stmt = $db->prepare($product_query);
$product_stmt->execute([$product_id, $user_id]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $description = sanitizeInput($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($category_id) || $price <= 0) {
        $error = 'Please fill in all required fields with valid values.';
    } else {
        // Handle image upload
        $image_update = '';
        $image_params = [];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_path = uploadImage($_FILES['image'], 'assets/products/');
            if ($image_path) {
                // Delete old image
                if ($product['image'] && file_exists($product['image'])) {
                    unlink($product['image']);
                }
                $image_update = ', image = ?';
                $image_params[] = $image_path;
            }
        }
        
        $query = "UPDATE products SET name = ?, category_id = ?, price = ?, description = ?, is_active = ?" . $image_update . " WHERE id = ? AND user_id = ?";
        $params = array_merge([$name, $category_id, $price, $description, $is_active], $image_params, [$product_id, $user_id]);
        $stmt = $db->prepare($query);
        
        if ($stmt->execute($params)) {
            // Regenerate QR code with updated info
            $updated_product = ['id' => $product_id, 'name' => $name, 'price' => $price, 'description' => $description];
            $qr_path = QRGenerator::generateProductQR($updated_product, getUserName());
            
            if ($qr_path) {
                $update_query = "UPDATE products SET qr_code_path = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$qr_path, $product_id]);
            }
            
            // Refresh product data
            $product_stmt->execute([$product_id, $user_id]);
            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
            
            $success = 'Product updated successfully!';
        } else {
            $error = 'Failed to update product.';
        }
    }
}

// Get categories for dropdown
$categories_query = "SELECT * FROM categories WHERE user_id = ? ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute([$user_id]);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get scan statistics for this product
$scan_stats_query = "SELECT COUNT(*) as total_scans,
                            COUNT(CASE WHEN DATE(scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_scans,
                            COUNT(CASE WHEN DATE(scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_scans
                     FROM qr_scans WHERE product_id = ?";
$scan_stats_stmt = $db->prepare($scan_stats_query);
$scan_stats_stmt->execute([$product_id]);
$scan_stats = $scan_stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - QR Generator Pro</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Edit Product</h1>
                    <p class="text-gray-600 mt-2">Update product information and regenerate QR code</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="products.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Products
                    </a>
                    <?php if ($product['qr_code_path']): ?>
                        <a href="<?php echo htmlspecialchars($product['qr_code_path']); ?>" 
                           download="qr_<?php echo $product['id']; ?>.png"
                           class="bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">
                            <i class="fas fa-download mr-2"></i>Download QR
                        </a>
                    <?php endif; ?>
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
            <!-- Edit Form -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow-xl rounded-2xl p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Product Information</h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($product['name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                                <select id="category_id" name="category_id" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $category['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700 mb-2">Price (PHP) *</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required 
                                       value="<?php echo $product['price']; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="image" class="block text-sm font-medium text-gray-700 mb-2">Product Image</label>
                                <?php if ($product['image']): ?>
                                    <div class="mb-3">
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="Current product image" class="w-32 h-32 object-cover rounded-lg">
                                        <p class="text-sm text-gray-500 mt-1">Current image</p>
                                    </div>
                                <?php endif; ?>
                                <input type="file" id="image" name="image" accept="image/*" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Leave empty to keep current image. Max file size: 5MB.</p>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea id="description" name="description" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>
                            
                            <div class="md:col-span-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="is_active" name="is_active" 
                                           <?php echo $product['is_active'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                        Product is active (visible to scanners)
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-8 flex items-center justify-between">
                            <button type="button" onclick="confirmDelete()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                                <i class="fas fa-trash mr-2"></i>Delete Product
                            </button>
                            <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                                <i class="fas fa-save mr-2"></i>Update Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Product Info & QR Code -->
            <div class="lg:col-span-1 space-y-6">
                <!-- QR Code Display -->
                <div class="bg-white shadow-xl rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">QR Code</h3>
                    <?php if ($product['qr_code_path'] && file_exists($product['qr_code_path'])): ?>
                        <div class="text-center">
                            <img src="<?php echo htmlspecialchars($product['qr_code_path']); ?>" 
                                 alt="QR Code" class="w-48 h-48 mx-auto mb-4 border-2 border-gray-200 rounded-lg">
                            <div class="space-y-2">
                                <a href="<?php echo htmlspecialchars($product['qr_code_path']); ?>" 
                                   download="qr_<?php echo $product['id']; ?>.png"
                                   class="w-full bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white px-4 py-2 rounded-lg text-sm transition-all block text-center">
                                    <i class="fas fa-download mr-2"></i>Download
                                </a>
                                <button onclick="printQR()" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-4 py-2 rounded-lg text-sm transition-all">
                                    <i class="fas fa-print mr-2"></i>Print
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-qrcode text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">QR code will be generated after saving</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Scan Statistics -->
                <div class="bg-white shadow-xl rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Scan Statistics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Total Scans</span>
                            <span class="font-bold text-gray-900"><?php echo $scan_stats['total_scans']; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">This Week</span>
                            <span class="font-bold text-green-600"><?php echo $scan_stats['week_scans']; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">This Month</span>
                            <span class="font-bold text-blue-600"><?php echo $scan_stats['month_scans']; ?></span>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="analytics.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            View detailed analytics →
                        </a>
                    </div>
                </div>

                <!-- Product Status -->
                <div class="bg-white shadow-xl rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Product Status</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Status</span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Created</span>
                            <span class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($product['created_at'])); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Last Updated</span>
                            <span class="text-sm text-gray-500"><?php echo timeAgo($product['updated_at']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Product</h3>
                <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete "<?php echo htmlspecialchars($product['name']); ?>"? This action cannot be undone and will also delete the QR code.</p>
                
                <form method="POST" action="products.php" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    
                    <div class="flex items-center justify-center space-x-3">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Delete Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function printQR() {
            const qrImage = document.querySelector('img[alt="QR Code"]');
            if (qrImage) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Print QR Code - <?php echo htmlspecialchars($product['name']); ?></title>
                            <style>
                                body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                                img { max-width: 400px; height: auto; }
                                h1 { color: #333; margin-bottom: 20px; }
                                .info { margin-top: 20px; color: #666; }
                            </style>
                        </head>
                        <body>
                            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                            <img src="${qrImage.src}" alt="QR Code">
                            <div class="info">
                                <p>Price: <?php echo formatPrice($product['price']); ?></p>
                                <p>Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
                                <p>Generated by QR Generator Pro</p>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
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
