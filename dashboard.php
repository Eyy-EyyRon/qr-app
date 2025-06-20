<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get admin statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
    (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
    (SELECT COUNT(*) FROM products) as total_products,
    (SELECT COUNT(*) FROM qr_scans) as total_scans";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent users
$users_query = "SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$recent_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get scan statistics per user
$scan_stats_query = "SELECT u.name, u.email, COUNT(qs.id) as scan_count 
                     FROM users u 
                     LEFT JOIN qr_scans qs ON u.id = qs.user_id 
                     WHERE u.role = 'user' 
                     GROUP BY u.id 
                     ORDER BY scan_count DESC 
                     LIMIT 10";
$scan_stats_stmt = $db->prepare($scan_stats_query);
$scan_stats_stmt->execute();
$scan_stats = $scan_stats_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-[#F4E7E1] via-white to-[#FF9B45] min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-[#D5451B]">
                        <i class="fas fa-qrcode mr-2"></i>QR Generator Pro - Admin
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-[#521C0D]">Welcome, <?php echo htmlspecialchars(getUserName()); ?></span>
                    <a href="../logout.php" class="text-[#D5451B] hover:text-[#521C0D]">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-2xl text-[#FF9B45]"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-[#521C0D] truncate">Total Users</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_users']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock text-2xl text-[#FF9B45]"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-[#521C0D] truncate">Pending Approval</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['pending_users']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-box text-2xl text-[#FF9B45]"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-[#521C0D] truncate">Total Products</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_products']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-eye text-2xl text-[#D5451B]"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-[#521C0D] truncate">Total Scans</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_scans']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-[#521C0D] mb-4">Admin Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="manage-users.php" class="bg-[#F4E7E1] hover:bg-[#FF9B45]/20 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-users-cog text-2xl text-[#FF9B45] mb-2"></i>
                        <p class="text-sm font-medium text-[#521C0D]">Manage Users</p>
                    </a>
                    <a href="view-products.php" class="bg-[#F4E7E1] hover:bg-[#FF9B45]/20 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-boxes text-2xl text-[#FF9B45] mb-2"></i>
                        <p class="text-sm font-medium text-[#521C0D]">View All Products</p>
                    </a>
                    <a href="scan-analytics.php" class="bg-[#F4E7E1] hover:bg-[#FF9B45]/20 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-chart-bar text-2xl text-[#D5451B] mb-2"></i>
                        <p class="text-sm font-medium text-[#521C0D]">Scan Analytics</p>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Users -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-[#521C0D] mb-4">Recent Users</h3>
                    <div class="space-y-4">
                        <?php foreach ($recent_users as $user): ?>
                            <div class="flex items-center justify-between p-3 bg-[#F4E7E1] rounded-lg">
                                <div>
                                    <p class="text-sm font-medium text-[#521C0D]"><?php echo htmlspecialchars($user['name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $user['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                                   ($user['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="manage-users.php" class="text-[#D5451B] hover:text-[#521C0D] text-sm font-medium">Manage all users →</a>
                    </div>
                </div>
            </div>

            <!-- Scan Statistics -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-[#521C0D] mb-4">Top Users by Scans</h3>
                    <div class="space-y-4">
                        <?php foreach ($scan_stats as $index => $stat): ?>
                            <div class="flex items-center justify-between p-3 bg-[#F4E7E1] rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 h-8 bg-[#FF9B45]/20 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-medium text-[#D5451B]"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-[#521C0D]"><?php echo htmlspecialchars($stat['name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($stat['email']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900"><?php echo $stat['scan_count']; ?> scans</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
