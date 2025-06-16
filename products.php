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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = sanitizeInput($_POST['name']);
                $category_id = (int)$_POST['category_id'];
                $price = (float)$_POST['price'];
                $description = sanitizeInput($_POST['description']);
                
                if (empty($name) || empty($category_id) || $price <= 0) {
                    $error = 'Please fill in all required fields with valid values.';
                } else {
                    // Handle image upload
                    $image_path = null;
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $image_path = uploadImage($_FILES['image'], 'assets/products/');
                        if (!$image_path) {
                            $error = 'Failed to upload image. Please try again.';
                            break;
                        }
                    }
                    
                    $query = "INSERT INTO products (user_id, category_id, name, price, description, image) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$user_id, $category_id, $name, $price, $description, $image_path])) {
                        $product_id = $db->lastInsertId();
                        
                        // Generate QR code
                        $product = ['id' => $product_id, 'name' => $name, 'price' => $price, 'description' => $description];
                        $qr_path = QRGenerator::generateProductQR($product, getUserName());
                        
                        if ($qr_path) {
                            $update_query = "UPDATE products SET qr_code_path = ? WHERE id = ?";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->execute([$qr_path, $product_id]);
                        }
                        
                        $success = 'Product created successfully with QR code!';
                    } else {
                        $error = 'Failed to create product.';
                    }
                }
                break;
                
            case 'update':
                $product_id = (int)$_POST['product_id'];
                $name = sanitizeInput($_POST['name']);
                $category_id = (int)$_POST['category_id'];
                $price = (float)$_POST['price'];
                $description = sanitizeInput($_POST['description']);
                
                if (empty($name) || empty($category_id) || $price <= 0) {
                    $error = 'Please fill in all required fields with valid values.';
                } else {
                    // Handle image upload
                    $image_update = '';
                    $image_params = [];
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $image_path = uploadImage($_FILES['image'], 'assets/products/');
                        if ($image_path) {
                            $image_update = ', image = ?';
                            $image_params[] = $image_path;
                        }
                    }
                    
                    $query = "UPDATE products SET name = ?, category_id = ?, price = ?, description = ?" . $image_update . " WHERE id = ? AND user_id = ?";
                    $params = array_merge([$name, $category_id, $price, $description], $image_params, [$product_id, $user_id]);
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute($params)) {
                        // Regenerate QR code with updated info
                        $product = ['id' => $product_id, 'name' => $name, 'price' => $price, 'description' => $description];
                        $qr_path = QRGenerator::generateProductQR($product, getUserName());
                        
                        if ($qr_path) {
                            $update_query = "UPDATE products SET qr_code_path = ? WHERE id = ?";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->execute([$qr_path, $product_id]);
                        }
                        
                        $success = 'Product updated successfully!';
                    } else {
                        $error = 'Failed to update product.';
                    }
                }
                break;
                
            case 'delete':
                $product_id = (int)$_POST['product_id'];
                
                // Get product info for file cleanup
                $get_query = "SELECT image, qr_code_path FROM products WHERE id = ? AND user_id = ?";
                $get_stmt = $db->prepare($get_query);
                $get_stmt->execute([$product_id, $user_id]);
                $product_files = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                $query = "DELETE FROM products WHERE id = ? AND user_id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$product_id, $user_id])) {
                    // Clean up files
                    if ($product_files) {
                        if ($product_files['image'] && file_exists($product_files['image'])) {
                            unlink($product_files['image']);
                        }
                        if ($product_files['qr_code_path'] && file_exists($product_files['qr_code_path'])) {
                            unlink($product_files['qr_code_path']);
                        }
                    }
                    $success = 'Product deleted successfully!';
                } else {
                    $error = 'Failed to delete product.';
                }
                break;
                
            case 'toggle_status':
                $product_id = (int)$_POST['product_id'];
                $is_active = (int)$_POST['is_active'];
                
                $query = "UPDATE products SET is_active = ? WHERE id = ? AND user_id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$is_active, $product_id, $user_id])) {
                    $success = 'Product status updated successfully!';
                } else {
                    $error = 'Failed to update product status.';
                }
                break;
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Build query with filters
$where_conditions = ["p.user_id = ?"];
$params = [$user_id];

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "p.is_active = ?";
    $params[] = ($status_filter === 'active') ? 1 : 0;
}

$where_clause = implode(' AND ', $where_conditions);

// Get products with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$products_query = "SELECT p.*, c.name as category_name, c.color as category_color,
                          (SELECT COUNT(*) FROM qr_scans qs WHERE qs.product_id = p.id) as scan_count
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE $where_clause
                   ORDER BY p.created_at DESC 
                   LIMIT $per_page OFFSET $offset";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute($params);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM products p WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// Get categories for dropdown
$categories_query = "SELECT * FROM categories WHERE user_id = ? ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute([$user_id]);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - QR Generator Pro</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Product Management</h1>
                    <p class="text-gray-600 mt-2">Create and manage your products with QR codes</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="qr-codes.php" class="bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white px-6 py-3 rounded-lg font-medium transition-all text-center">
                        <i class="fas fa-qrcode mr-2"></i>View QR Codes
                    </a>
                    <button onclick="openCreateModal()" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                        <i class="fas fa-plus mr-2"></i>Add Product
                    </button>
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

        <!-- Filters -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name or description..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="bg-white shadow-xl rounded-2xl p-12 text-center">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No products found</h3>
                <p class="text-gray-500 mb-6">
                    <?php if (!empty($search) || $category_filter > 0 || $status_filter !== 'all'): ?>
                        Try adjusting your filters or search terms
                    <?php else: ?>
                        Create your first product to get started
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && $category_filter == 0 && $status_filter === 'all'): ?>
                    <button onclick="openCreateModal()" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                        <i class="fas fa-plus mr-2"></i>Create Product
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                <?php foreach ($products as $product): ?>
                    <div class="bg-white shadow-xl rounded-2xl overflow-hidden hover:shadow-2xl transition-shadow">
                        <?php if ($product['image']): ?>
                            <div class="h-48 bg-gray-200 overflow-hidden">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="w-full h-full object-cover">
                            </div>
                        <?php else: ?>
                            <div class="h-48 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                <i class="fas fa-image text-4xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                      style="background-color: <?php echo htmlspecialchars($product['category_color']); ?>20; color: <?php echo htmlspecialchars($product['category_color']); ?>">
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </span>
                                <div class="flex items-center space-x-1">
                                    <?php if ($product['is_active']): ?>
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                        <span class="text-xs text-green-600">Active</span>
                                    <?php else: ?>
                                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                        <span class="text-xs text-red-600">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-2xl font-bold text-indigo-600 mb-2"><?php echo formatPrice($product['price']); ?></p>
                            
                            <?php if ($product['description']): ?>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($product['description']); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-eye mr-1"></i>
                                    <?php echo $product['scan_count']; ?> scans
                                </div>
                                <div>
                                    <?php echo timeAgo($product['created_at']); ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <button onclick="viewProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                            class="text-indigo-600 hover:text-indigo-800 p-2 rounded-lg hover:bg-indigo-50 transition-colors">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                            class="text-green-600 hover:text-green-800 p-2 rounded-lg hover:bg-green-50 transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleStatus(<?php echo $product['id']; ?>, <?php echo $product['is_active'] ? 0 : 1; ?>)" 
                                            class="text-yellow-600 hover:text-yellow-800 p-2 rounded-lg hover:bg-yellow-50 transition-colors">
                                        <i class="fas fa-<?php echo $product['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" 
                                            class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php if ($product['qr_code_path']): ?>
                                    <a href="<?php echo htmlspecialchars($product['qr_code_path']); ?>" 
                                       download="qr_<?php echo $product['id']; ?>.png"
                                       class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-3 py-1 rounded-lg text-xs transition-all">
                                        <i class="fas fa-download mr-1"></i>QR
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white shadow-xl rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_products); ?> of <?php echo $total_products; ?> products
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                                   class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                                   class="px-3 py-2 <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition-colors">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                                   class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Product Modal -->
    <div id="productModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-bold text-gray-900 mb-4" id="modalTitle">Add Product</h3>
                <form method="POST" id="productForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="product_id" id="productId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label for="productName" class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                            <input type="text" id="productName" name="name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div>
                            <label for="productCategory" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                            <select id="productCategory" name="category_id" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="productPrice" class="block text-sm font-medium text-gray-700 mb-2">Price (PHP) *</label>
                            <input type="number" id="productPrice" name="price" step="0.01" min="0" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="productImage" class="block text-sm font-medium text-gray-700 mb-2">Product Image</label>
                            <input type="file" id="productImage" name="image" accept="image/*" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-500 mt-1">Max file size: 5MB. Supported formats: JPG, PNG, GIF</p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="productDescription" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea id="productDescription" name="description" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all">
                            <span id="submitText">Create Product</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Product Modal -->
    <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Product Details</h3>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="viewContent">
                    <!-- Content will be populated by JavaScript -->
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
                <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete "<span id="deleteProductName"></span>"? This action cannot be undone.</p>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    
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

    <!-- Toggle Status Form -->
    <form method="POST" id="toggleForm" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="product_id" id="toggleProductId">
        <input type="hidden" name="is_active" id="toggleStatus">
    </form>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitText').textContent = 'Create Product';
            document.getElementById('productForm').reset();
            document.getElementById('productModal').classList.remove('hidden');
        }

        function editProduct(product) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('formAction').value = 'update';
            document.getElementById('submitText').textContent = 'Update Product';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productCategory').value = product.category_id;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productModal').classList.remove('hidden');
        }

        function viewProduct(product) {
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        ${product.image ? 
                            `<img src="${product.image}" alt="${product.name}" class="w-full h-64 object-cover rounded-lg">` :
                            `<div class="w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center">
                                <i class="fas fa-image text-4xl text-gray-400"></i>
                            </div>`
                        }
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-gray-900 mb-2">${product.name}</h4>
                        <p class="text-2xl font-bold text-indigo-600 mb-2">₱${parseFloat(product.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <div class="flex items-center mb-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  style="background-color: ${product.category_color}20; color: ${product.category_color}">
                                ${product.category_name}
                            </span>
                            <span class="ml-2 text-sm ${product.is_active == 1 ? 'text-green-600' : 'text-red-600'}">
                                ${product.is_active == 1 ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        ${product.description ? `<p class="text-gray-600 mb-4">${product.description}</p>` : ''}
                        <div class="text-sm text-gray-500">
                            <p><i class="fas fa-eye mr-2"></i>${product.scan_count} scans</p>
                            <p><i class="fas fa-calendar mr-2"></i>Created ${new Date(product.created_at).toLocaleDateString()}</p>
                        </div>
                        ${product.qr_code_path ? 
                            `<div class="mt-4">
                                <a href="${product.qr_code_path}" download="qr_${product.id}.png" 
                                   class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all">
                                    <i class="fas fa-download mr-2"></i>Download QR Code
                                </a>
                            </div>` : ''
                        }
                    </div>
                </div>
            `;
            document.getElementById('viewContent').innerHTML = content;
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function confirmDelete(productId, productName) {
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function toggleStatus(productId, newStatus) {
            document.getElementById('toggleProductId').value = productId;
            document.getElementById('toggleStatus').value = newStatus;
            document.getElementById('toggleForm').submit();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const productModal = document.getElementById('productModal');
            const viewModal = document.getElementById('viewModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === productModal) closeModal();
            if (event.target === viewModal) closeViewModal();
            if (event.target === deleteModal) closeDeleteModal();
        }
    </script>
</body>
</html>
