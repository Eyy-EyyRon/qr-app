<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get date range filter
$date_range = isset($_GET['range']) ? sanitizeInput($_GET['range']) : '30';
$start_date = date('Y-m-d', strtotime("-{$date_range} days"));
$end_date = date('Y-m-d');

// Get overall scan statistics
$stats_query = "SELECT 
    COUNT(*) as total_scans,
    COUNT(DISTINCT product_id) as scanned_products,
    COUNT(DISTINCT user_id) as scanning_users,
    COUNT(CASE WHEN DATE(scanned_at) >= ? THEN 1 END) as recent_scans
    FROM qr_scans";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$start_date]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get daily scan data for chart
$daily_scans_query = "SELECT DATE(scanned_at) as scan_date, COUNT(*) as scan_count
                      FROM qr_scans 
                      WHERE DATE(scanned_at) >= ?
                      GROUP BY DATE(scanned_at)
                      ORDER BY scan_date ASC";
$daily_scans_stmt = $db->prepare($daily_scans_query);
$daily_scans_stmt->execute([$start_date]);
$daily_scans = $daily_scans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top scanned products
$top_products_query = "SELECT p.name, p.price, u.name as owner_name, c.name as category_name, COUNT(qs.id) as scan_count
                       FROM qr_scans qs
                       JOIN products p ON qs.product_id = p.id
                       JOIN users u ON p.user_id = u.id
                       LEFT JOIN categories c ON p.category_id = c.id
                       WHERE DATE(qs.scanned_at) >= ?
                       GROUP BY p.id
                       ORDER BY scan_count DESC
                       LIMIT 10";
$top_products_stmt = $db->prepare($top_products_query);
$top_products_stmt->execute([$start_date]);
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get most active users (product owners)
$active_users_query = "SELECT u.name, u.email, COUNT(qs.id) as scan_count,
                              COUNT(DISTINCT p.id) as product_count
                       FROM users u
                       JOIN products p ON u.id = p.user_id
                       LEFT JOIN qr_scans qs ON p.id = qs.product_id AND DATE(qs.scanned_at) >= ?
                       WHERE u.role = 'user'
                       GROUP BY u.id
                       ORDER BY scan_count DESC
                       LIMIT 10";
$active_users_stmt = $db->prepare($active_users_query);
$active_users_stmt->execute([$start_date]);
$active_users = $active_users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get scan sources
$scan_sources_query = "SELECT 
    CASE 
        WHEN qs.user_id IS NOT NULL THEN 'Registered Users'
        ELSE 'Anonymous Users'
    END as source_type,
    COUNT(*) as scan_count
    FROM qr_scans qs 
    WHERE DATE(qs.scanned_at) >= ?
    GROUP BY source_type";
$scan_sources_stmt = $db->prepare($scan_sources_query);
$scan_sources_stmt->execute([$start_date]);
$scan_sources = $scan_sources_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent scan activity
$recent_activity_query = "SELECT p.name as product_name, u.name as owner_name, qs.scanned_at, qs.ip_address, 
                                 scanner.name as scanner_name
                          FROM qr_scans qs
                          JOIN products p ON qs.product_id = p.id
                          JOIN users u ON p.user_id = u.id
                          LEFT JOIN users scanner ON qs.user_id = scanner.id
                          ORDER BY qs.scanned_at DESC
                          LIMIT 50";
$recent_activity_stmt = $db->prepare($recent_activity_query);
$recent_activity_stmt->execute();
$recent_activity = $recent_activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get hourly distribution
$hourly_scans_query = "SELECT HOUR(scanned_at) as hour, COUNT(*) as scan_count
                       FROM qr_scans 
                       WHERE DATE(scanned_at) >= ?
                       GROUP BY HOUR(scanned_at)
                       ORDER BY hour";
$hourly_scans_stmt = $db->prepare($hourly_scans_query);
$hourly_scans_stmt->execute([$start_date]);
$hourly_scans = $hourly_scans_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Analytics - QR Generator Pro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="manage-users.php" class="text-gray-700 hover:text-red-600 px-3 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-users mr-1"></i>Users
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
                    <h1 class="text-3xl font-bold text-gray-900">Scan Analytics</h1>
                    <p class="text-gray-600 mt-2">Comprehensive QR code scanning analytics and insights</p>
                </div>
                <div class="flex items-center space-x-3">
                    <select onchange="changeTimeRange(this.value)" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Last year</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-eye text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Scans</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_scans']; ?></p>
                        <p class="text-xs text-blue-600">All time</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-teal-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-chart-line text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Recent Scans</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['recent_scans']; ?></p>
                        <p class="text-xs text-green-600">Last <?php echo $date_range; ?> days</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-qrcode text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Scanned Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['scanned_products']; ?></p>
                        <p class="text-xs text-purple-600">Unique products</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-users text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active Scanners</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['scanning_users']; ?></p>
                        <p class="text-xs text-orange-600">Registered users</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Daily Scan Trends -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Daily Scan Trends</h3>
                <canvas id="dailyScansChart" width="400" height="200"></canvas>
            </div>

            <!-- Hourly Distribution -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Hourly Distribution</h3>
                <canvas id="hourlyScansChart" width="400" height="200"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Scan Sources -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Scan Sources</h3>
                <canvas id="scanSourcesChart" width="400" height="200"></canvas>
            </div>

            <!-- Top Scanned Products -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Top Scanned Products</h3>
                <?php if (empty($top_products)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-chart-bar text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No scan data available</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($top_products as $index => $product): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-red-500 to-pink-500 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-sm font-bold text-white"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($product['owner_name']); ?> • 
                                            <?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?> • 
                                            <?php echo formatPrice($product['price']); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-red-600"><?php echo $product['scan_count']; ?></p>
                                    <p class="text-xs text-gray-500">scans</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Most Active Users -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Most Active Product Owners</h3>
                <?php if (empty($active_users)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No user data available</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($active_users as $index => $user): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-sm font-bold text-white"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($user['email']); ?> • 
                                            <?php echo $user['product_count']; ?> products
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-indigo-600"><?php echo $user['scan_count']; ?></p>
                                    <p class="text-xs text-gray-500">scans</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Scan Activity -->
            <div class="bg-white shadow-xl rounded-2xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Recent Scan Activity</h3>
                <?php if (empty($recent_activity)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No recent activity</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach (array_slice($recent_activity, 0, 15) as $activity): ?>
                            <div class="flex items-center justify-between p-3 border-l-4 border-red-500 bg-red-50">
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($activity['product_name']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        Owner: <?php echo htmlspecialchars($activity['owner_name']); ?> • 
                                        Scanner: <?php echo $activity['scanner_name'] ? htmlspecialchars($activity['scanner_name']) : 'Anonymous'; ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500"><?php echo timeAgo($activity['scanned_at']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($activity['ip_address']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Daily Scans Chart
        const dailyScansCtx = document.getElementById('dailyScansChart').getContext('2d');
        const dailyScansData = <?php echo json_encode($daily_scans); ?>;
        
        // Fill missing dates with 0 scans
        const dateRange = <?php echo $date_range; ?>;
        const dates = [];
        const scanCounts = [];
        
        for (let i = dateRange - 1; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            dates.push(date.toLocaleDateString());
            
            const dayData = dailyScansData.find(d => d.scan_date === dateStr);
            scanCounts.push(dayData ? parseInt(dayData.scan_count) : 0);
        }
        
        new Chart(dailyScansCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Scans',
                    data: scanCounts,
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Hourly Scans Chart
        const hourlyScansCtx = document.getElementById('hourlyScansChart').getContext('2d');
        const hourlyScansData = <?php echo json_encode($hourly_scans); ?>;
        
        // Fill missing hours with 0 scans
        const hours = [];
        const hourlyCounts = [];
        
        for (let i = 0; i < 24; i++) {
            hours.push(i + ':00');
            const hourData = hourlyScansData.find(h => parseInt(h.hour) === i);
            hourlyCounts.push(hourData ? parseInt(hourData.scan_count) : 0);
        }
        
        new Chart(hourlyScansCtx, {
            type: 'bar',
            data: {
                labels: hours,
                datasets: [{
                    label: 'Scans',
                    data: hourlyCounts,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Scan Sources Chart
        const scanSourcesCtx = document.getElementById('scanSourcesChart').getContext('2d');
        const scanSourcesData = <?php echo json_encode($scan_sources); ?>;
        
        new Chart(scanSourcesCtx, {
            type: 'doughnut',
            data: {
                labels: scanSourcesData.map(s => s.source_type),
                datasets: [{
                    data: scanSourcesData.map(s => parseInt(s.scan_count)),
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(99, 102, 241, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function changeTimeRange(range) {
            window.location.href = `scan-analytics.php?range=${range}`;
        }
    </script>
</body>
</html>
