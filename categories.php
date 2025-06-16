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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $color = sanitizeInput($_POST['color']) ?: generateRandomColor();
                
                if (empty($name)) {
                    $error = 'Category name is required.';
                } else {
                    $query = "INSERT INTO categories (user_id, name, description, color) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$user_id, $name, $description, $color])) {
                        $success = 'Category created successfully!';
                    } else {
                        $error = 'Failed to create category.';
                    }
                }
                break;
                
            case 'update':
                $category_id = (int)$_POST['category_id'];
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $color = sanitizeInput($_POST['color']);
                
                if (empty($name)) {
                    $error = 'Category name is required.';
                } else {
                    $query = "UPDATE categories SET name = ?, description = ?, color = ? WHERE id = ? AND user_id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$name, $description, $color, $category_id, $user_id])) {
                        $success = 'Category updated successfully!';
                    } else {
                        $error = 'Failed to update category.';
                    }
                }
                break;
                
            case 'delete':
                $category_id = (int)$_POST['category_id'];
                
                // Check if category has products
                $check_query = "SELECT COUNT(*) FROM products WHERE category_id = ? AND user_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$category_id, $user_id]);
                $product_count = $check_stmt->fetchColumn();
                
                if ($product_count > 0) {
                    $error = 'Cannot delete category with existing products. Please move or delete products first.';
                } else {
                    $query = "DELETE FROM categories WHERE id = ? AND user_id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$category_id, $user_id])) {
                        $success = 'Category deleted successfully!';
                    } else {
                        $error = 'Failed to delete category.';
                    }
                }
                break;
        }
    }
}

// Get all categories for the user
$categories_query = "SELECT c.*, COUNT(p.id) as product_count 
                     FROM categories c 
                     LEFT JOIN products p ON c.id = p.category_id 
                     WHERE c.user_id = ? 
                     GROUP BY c.id 
                     ORDER BY c.created_at DESC";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute([$user_id]);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - QR Generator Pro</title>
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
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Product Categories</h1>
                    <p class="text-gray-600 mt-2">Organize your products into categories for better management</p>
                </div>
                <button onclick="openCreateModal()" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>Add Category
                </button>
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

        <!-- Categories Grid -->
        <?php if (empty($categories)): ?>
            <div class="bg-white shadow-xl rounded-2xl p-12 text-center">
                <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No categories yet</h3>
                <p class="text-gray-500 mb-6">Create your first category to start organizing your products</p>
                <button onclick="openCreateModal()" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                    <i class="fas fa-plus mr-2"></i>Create Category
                </button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($categories as $category): ?>
                    <div class="bg-white shadow-xl rounded-2xl overflow-hidden hover:shadow-2xl transition-shadow">
                        <div class="h-4" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h3>
                                <div class="flex items-center space-x-2">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($category)); ?>)" class="text-indigo-600 hover:text-indigo-800">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($category['description']): ?>
                                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($category['description']); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-sm text-gray-500">
                                    <i class="fas fa-box mr-2"></i>
                                    <?php echo $category['product_count']; ?> products
                                </div>
                                <div class="text-xs text-gray-400">
                                    Created <?php echo timeAgo($category['created_at']); ?>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="products.php?category=<?php echo $category['id']; ?>" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-4 rounded-lg text-center block transition-colors">
                                    View Products
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Modal -->
    <div id="categoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-bold text-gray-900 mb-4" id="modalTitle">Add Category</h3>
                <form method="POST" id="categoryForm">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="category_id" id="categoryId">
                    
                    <div class="mb-4">
                        <label for="categoryName" class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                        <input type="text" id="categoryName" name="name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label for="categoryDescription" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="categoryDescription" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="categoryColor" class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" id="categoryColor" name="color" value="#3B82F6"
                                   class="w-12 h-10 border border-gray-300 rounded-lg cursor-pointer">
                            <span class="text-sm text-gray-500">Choose a color for this category</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all">
                            <span id="submitText">Create Category</span>
                        </button>
                    </div>
                </form>
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
                <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Category</h3>
                <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete "<span id="deleteCategoryName"></span>"? This action cannot be undone.</p>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" id="deleteCategoryId">
                    
                    <div class="flex items-center justify-center space-x-3">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Delete Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitText').textContent = 'Create Category';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryColor').value = '#3B82F6';
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function openEditModal(category) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('formAction').value = 'update';
            document.getElementById('submitText').textContent = 'Update Category';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('categoryName').value = category.name;
            document.getElementById('categoryDescription').value = category.description || '';
            document.getElementById('categoryColor').value = category.color;
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('categoryModal').classList.add('hidden');
        }

        function confirmDelete(categoryId, categoryName) {
            document.getElementById('deleteCategoryId').value = categoryId;
            document.getElementById('deleteCategoryName').textContent = categoryName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const categoryModal = document.getElementById('categoryModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === categoryModal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
