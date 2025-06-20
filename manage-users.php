<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $user_id = (int)$_POST['user_id'];
        
        switch ($_POST['action']) {
            case 'approve':
                $query = "UPDATE users SET status = 'approved' WHERE id = ? AND role = 'user'";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$user_id])) {
                    $success = 'User approved successfully!';
                } else {
                    $error = 'Failed to approve user.';
                }
                break;
                
            case 'block':
                $query = "UPDATE users SET status = 'blocked' WHERE id = ? AND role = 'user'";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$user_id])) {
                    $success = 'User blocked successfully!';
                } else {
                    $error = 'Failed to block user.';
                }
                break;
                
            case 'unblock':
                $query = "UPDATE users SET status = 'approved' WHERE id = ? AND role = 'user'";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$user_id])) {
                    $success = 'User unblocked successfully!';
                } else {
                    $error = 'Failed to unblock user.';
                }
                break;
                
            case 'delete':
                // Get user data for cleanup
                $get_query = "SELECT profile_image FROM users WHERE id = ? AND role = 'user'";
                $get_stmt = $db->prepare($get_query);
                $get_stmt->execute([$user_id]);
                $user_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                $query = "DELETE FROM users WHERE id = ? AND role = 'user'";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$user_id])) {
                    // Clean up profile image
                    if ($user_data && $user_data['profile_image'] && file_exists($user_data['profile_image'])) {
                        unlink($user_data['profile_image']);
                    }
                    $success = 'User deleted successfully!';
                } else {
                    $error = 'Failed to delete user.';
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query with filters
$where_conditions = ["role = 'user'"];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get users with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$users_query = "SELECT u.*, 
                       (SELECT COUNT(*) FROM products WHERE user_id = u.id) as product_count,
                       (SELECT COUNT(*) FROM qr_scans qs JOIN products p ON qs.product_id = p.id WHERE p.user_id = u.id) as scan_count
                FROM users u 
                WHERE $where_clause
                ORDER BY u.created_at DESC 
                LIMIT $per_page OFFSET $offset";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute($params);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM users WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Get user statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_users,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_users,
    SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_users
    FROM users WHERE role = 'user'";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - QR Generator Pro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-xl font-bold bg-gradient-to-r from-red-600 to-pink-600 bg-clip-text text-transparent">
                        <i class="fas fa-qrcode mr-2 text-red-600"></i>QR Generator Pro - Admin
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-700 hover:text-red-600 px-3 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-home mr-1"></i>Dashboard
                    </a>
                    <a href="view-products.php" class="text-gray-700 hover:text-red-600 px-3 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-box mr-1"></i>Products
                    </a>
                    <div class="relative group">
                        <button class="flex items-center text-gray-700 hover:text-red-600 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-user-shield mr-2"></i>
                            <?php echo htmlspecialchars(getUserName()); ?>
                            <i class="fas fa-chevron-down ml-2"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-xl rounded-2xl p-8 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="mb-4 md:mb-0">
                    <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
                    <p class="text-gray-600 mt-2">Manage user accounts, approvals, and permissions</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?php echo $stats['pending_users']; ?> Pending Approval
                    </span>
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

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-users text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_users']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Pending</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_users']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-teal-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check-circle text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Approved</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['approved_users']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-pink-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-ban text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Blocked</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['blocked_users']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name or email..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="blocked" <?php echo $status_filter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white shadow-xl rounded-2xl overflow-hidden">
            <?php if (empty($users)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-900 mb-2">No users found</h3>
                    <p class="text-gray-500">
                        <?php if (!empty($search) || $status_filter !== 'all'): ?>
                            Try adjusting your filters or search terms
                        <?php else: ?>
                            No users have registered yet
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <?php if ($user['profile_image']): ?>
                                                    <img class="h-10 w-10 rounded-full object-cover" src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="">
                                                <?php else: ?>
                                                    <div class="h-10 w-10 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-white"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="text-sm text-gray-500">ID: <?php echo $user['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php if ($user['phone']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch($user['status']) {
                                                case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'blocked': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div><?php echo $user['product_count']; ?> products</div>
                                        <div><?php echo $user['scan_count']; ?> scans</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <button onclick="viewUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-50">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($user['status'] === 'pending'): ?>
                                                <button onclick="confirmAction(<?php echo $user['id']; ?>, 'approve', 'approve this user')" 
                                                        class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-50">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'approved'): ?>
                                                <button onclick="confirmAction(<?php echo $user['id']; ?>, 'block', 'block this user')" 
                                                        class="text-yellow-600 hover:text-yellow-900 p-1 rounded hover:bg-yellow-50">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'blocked'): ?>
                                                <button onclick="confirmAction(<?php echo $user['id']; ?>, 'unblock', 'unblock this user')" 
                                                        class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button onclick="confirmAction(<?php echo $user['id']; ?>, 'delete', 'permanently delete this user')" 
                                                    class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_users); ?> of <?php echo $total_users; ?> users
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                                       class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                                       class="px-3 py-2 <?php echo $i === $page ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition-colors">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
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
    </div>

    <!-- View User Modal -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">User Details</h3>
                    <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="userContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Confirm Action</h3>
                <p class="text-sm text-gray-500 mb-6">Are you sure you want to <span id="confirmActionText"></span>?</p>
                
                <form method="POST" id="confirmForm">
                    <input type="hidden" name="action" id="confirmAction">
                    <input type="hidden" name="user_id" id="confirmUserId">
                    
                    <div class="flex items-center justify-center space-x-3">
                        <button type="button" onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function viewUser(user) {
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="text-center">
                        ${user.profile_image ? 
                            `<img src="${user.profile_image}" alt="${user.name}" class="w-32 h-32 rounded-full mx-auto mb-4 object-cover">` :
                            `<div class="w-32 h-32 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center mx-auto mb-4">
                                <span class="text-4xl font-bold text-white">${user.name.charAt(0).toUpperCase()}</span>
                            </div>`
                        }
                        <h4 class="text-xl font-bold text-gray-900">${user.name}</h4>
                        <p class="text-gray-600">${user.email}</p>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Status</label>
                            <span class="px-3 py-1 rounded-full text-sm font-medium 
                                ${user.status === 'approved' ? 'bg-green-100 text-green-800' : 
                                  user.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                  'bg-red-100 text-red-800'}">
                                ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                            </span>
                        </div>
                        ${user.phone ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Phone</label>
                                <p class="text-gray-900">${user.phone}</p>
                            </div>
                        ` : ''}
                        ${user.address ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Address</label>
                                <p class="text-gray-900">${user.address}</p>
                            </div>
                        ` : ''}
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Activity</label>
                            <p class="text-gray-900">${user.product_count} products, ${user.scan_count} scans</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Member Since</label>
                            <p class="text-gray-900">${new Date(user.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('userContent').innerHTML = content;
            document.getElementById('userModal').classList.remove('hidden');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        function confirmAction(userId, action, actionText) {
            document.getElementById('confirmUserId').value = userId;
            document.getElementById('confirmAction').value = action;
            document.getElementById('confirmActionText').textContent = actionText;
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const userModal = document.getElementById('userModal');
            const confirmModal = document.getElementById('confirmModal');
            
            if (event.target === userModal) closeUserModal();
            if (event.target === confirmModal) closeConfirmModal();
        }
    </script>
</body>
</html>
