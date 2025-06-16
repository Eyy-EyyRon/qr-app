<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

// Get date range filter
$date_range = isset($_GET['range']) ? sanitizeInput($_GET['range']) : '30';
$start_date = date('Y-m-d', strtotime("-{$date_range} days"));
$end_date = date('Y-m-d');

// Get overall statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM products WHERE user_id = ?) as total_products,
    (SELECT COUNT(*) FROM products WHERE user_id = ? AND is_active = 1) as active_products,
    (SELECT COUNT(*) FROM qr_scans qs JOIN products p ON qs.product_id = p.id WHERE p.user_id = ?) as total_scans,
    (SELECT COUNT(*) FROM qr_scans qs JOIN products p ON qs.product_id = p.id WHERE p.user_id = ? AND DATE(qs.scanned_at) >= ?) as recent_scans";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $start_date]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get daily scan data for chart
$daily_scans_query = "SELECT DATE(qs.scanned_at) as scan_date, COUNT(*) as scan_count
                      FROM qr_scans qs 
                      JOIN products p ON qs.product_id = p.id 
                      WHERE p.user_id = ? AND DATE(qs.scanned_at) >= ?
                      GROUP BY DATE(qs.scanned_at)
                      ORDER BY scan_date ASC";
$daily_scans_stmt = $db->prepare($daily_scans_query);
$daily_scans_stmt->execute([$user_id, $start_date]);
$daily_scans = $daily_scans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top performing products
$top_products_query = "SELECT p.name, p.price, c.name as category_name, COUNT(qs.id) as scan_count
                       FROM products p
                       LEFT JOIN categories c ON p.category_id = c.id
                       LEFT JOIN qr_scans qs ON p.id = qs.product_id AND DATE(qs.scanned_at) >= ?
                       WHERE p.user_id = ?
                       GROUP BY p.id
                       ORDER BY scan_count DESC
                       LIMIT 10";
$top_products_stmt = $db->prepare($top_products_query);
$top_products_stmt->execute([$start_date, $user_id]);
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get scan sources/locations
$scan_sources_query = "SELECT 
    CASE 
        WHEN qs.user_id IS NOT NULL THEN 'Registered Users'
        ELSE 'Anonymous Users'
    END as source_type,
    COUNT(*) as scan_count
    FROM qr_scans qs 
    JOIN products p ON qs.product_id = p.id 
    WHERE p.user_id = ? AND DATE(qs.scanned_at) >= ?
    GROUP BY source_type";
$scan_sources_stmt = $db->prepare($scan_sources_query);
$scan_sources_stmt->execute([$user_id, $start_date]);
$scan_sources = $scan_sources_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent scan activity
$recent_activity_query = "SELECT p.name as product_name, qs.scanned_at, qs.ip_address, u.name as user_name
                          FROM qr_scans qs
                          JOIN products p ON qs.product_id = p.id
                          LEFT JOIN users u ON qs.user_id = u.id
                          WHERE p.user_id = ?
                          ORDER BY qs.scanned_at DESC
                          LIMIT 15";
$recent_activity_stmt = $db->prepare($recent_activity_query);
$recent_activity_stmt->execute([$user_id]);
$recent_activity = $recent_activity_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Prevent chart container from causing scroll issues */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Smooth animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Gradient backgrounds */
        .gradient-bg-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .gradient-bg-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .gradient-bg-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .gradient-bg-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        
        /* Card hover effects */
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Navigation -->
    <?php require_once 'includes/navigation.php'; ?>
    <?php renderNavigation(); ?>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="bg-white shadow-2xl rounded-3xl p-8 mb-8 fade-in">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-6 lg:mb-0">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-4xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                                Analytics Dashboard
                            </h1>
                            <p class="text-gray-600 mt-2">Track your QR code performance and user engagement</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <select onchange="changeTimeRange(this.value)" 
                                class="appearance-none bg-white border-2 border-gray-200 rounded-xl px-6 py-3 pr-10 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200">
                            <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 days</option>
                            <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Last year</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    </div>
                    <button onclick="refreshData()" class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-6 py-3 rounded-xl hover:from-indigo-600 hover:to-purple-700 transition-all duration-200 flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white shadow-xl rounded-2xl p-6 fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Products</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['total_products']); ?></p>
                        <p class="text-sm text-green-600 mt-1">
                            <i class="fas fa-check-circle mr-1"></i>
                            <?php echo $stats['active_products']; ?> active
                        </p>
                    </div>
                    <div class="w-16 h-16 gradient-bg-1 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-box text-2xl text-white"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white shadow-xl rounded-2xl p-6 fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Scans</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['total_scans']); ?></p>
                        <p class="text-sm text-blue-600 mt-1">
                            <i class="fas fa-infinity mr-1"></i>
                            All time
                        </p>
                    </div>
                    <div class="w-16 h-16 gradient-bg-2 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-eye text-2xl text-white"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white shadow-xl rounded-2xl p-6 fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Recent Scans</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($stats['recent_scans']); ?></p>
                        <p class="text-sm text-purple-600 mt-1">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            Last <?php echo $date_range; ?> days
                        </p>
                    </div>
                    <div class="w-16 h-16 gradient-bg-3 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-chart-line text-2xl text-white"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white shadow-xl rounded-2xl p-6 fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Avg. per Product</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">
                            <?php echo $stats['total_products'] > 0 ? number_format($stats['total_scans'] / $stats['total_products'], 1) : 0; ?>
                        </p>
                        <p class="text-sm text-orange-600 mt-1">
                            <i class="fas fa-calculator mr-1"></i>
                            scans/product
                        </p>
                    </div>
                    <div class="w-16 h-16 gradient-bg-4 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-percentage text-2xl text-white"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <!-- Scan Trends Chart -->
            <div class="bg-white shadow-xl rounded-2xl p-8 fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Scan Trends</h3>
                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <div class="w-3 h-3 bg-indigo-500 rounded-full"></div>
                        <span>Daily Scans</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="scanTrendsChart"></canvas>
                </div>
            </div>

            <!-- Scan Sources Chart -->
            <div class="bg-white shadow-xl rounded-2xl p-8 fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Scan Sources</h3>
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-users mr-1"></i>
                        User Types
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="scanSourcesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <!-- Top Performing Products -->
            <div class="bg-white shadow-xl rounded-2xl p-8 fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Top Performing Products</h3>
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-trophy mr-1"></i>
                        Most Scanned
                    </div>
                </div>
                <?php if (empty($top_products)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-chart-bar text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 text-lg">No scan data available</p>
                        <p class="text-gray-400 text-sm mt-2">Start sharing your QR codes to see analytics</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 max-h-80 overflow-y-auto custom-scrollbar">
                        <?php foreach ($top_products as $index => $product): ?>
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl hover:from-indigo-50 hover:to-purple-50 transition-all duration-200">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-4">
                                        <span class="text-sm font-bold text-white"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-tag mr-1"></i>
                                            <?php echo htmlspecialchars($product['category_name']); ?> • 
                                            <span class="font-medium"><?php echo formatPrice($product['price']); ?></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-indigo-600"><?php echo number_format($product['scan_count']); ?></p>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">scans</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white shadow-xl rounded-2xl p-8 fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Recent Activity</h3>
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        Live Feed
                    </div>
                </div>
                <?php if (empty($recent_activity)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-history text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 text-lg">No recent activity</p>
                        <p class="text-gray-400 text-sm mt-2">Activity will appear here when users scan your QR codes</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-80 overflow-y-auto custom-scrollbar">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="flex items-center justify-between p-4 border-l-4 border-indigo-500 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-r-xl hover:from-indigo-100 hover:to-purple-100 transition-all duration-200">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-qrcode text-white text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($activity['product_name']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-user mr-1"></i>
                                            Scanned by <?php echo $activity['user_name'] ? htmlspecialchars($activity['user_name']) : 'Anonymous User'; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-700"><?php echo timeAgo($activity['scanned_at']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($activity['ip_address']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Scan Trends Chart
            const scanTrendsCtx = document.getElementById('scanTrendsChart');
            if (scanTrendsCtx) {
                const ctx = scanTrendsCtx.getContext('2d');
                const dailyScansData = <?php echo json_encode($daily_scans); ?>;
                
                // Fill missing dates with 0 scans
                const dateRange = <?php echo $date_range; ?>;
                const dates = [];
                const scanCounts = [];
                
                for (let i = dateRange - 1; i >= 0; i--) {
                    const date = new Date();
                    date.setDate(date.getDate() - i);
                    const dateStr = date.toISOString().split('T')[0];
                    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                    
                    const dayData = dailyScansData.find(d => d.scan_date === dateStr);
                    scanCounts.push(dayData ? parseInt(dayData.scan_count) : 0);
                }
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Daily Scans',
                            data: scanCounts,
                            borderColor: 'rgb(99, 102, 241)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: 'rgb(99, 102, 241)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#6b7280'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f3f4f6'
                                },
                                ticks: {
                                    stepSize: 1,
                                    color: '#6b7280'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: 'rgb(99, 102, 241)',
                                borderWidth: 1
                            }
                        }
                    }
                });
            }

            // Scan Sources Chart
            const scanSourcesCtx = document.getElementById('scanSourcesChart');
            if (scanSourcesCtx) {
                const ctx = scanSourcesCtx.getContext('2d');
                const scanSourcesData = <?php echo json_encode($scan_sources); ?>;
                
                if (scanSourcesData.length > 0) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: scanSourcesData.map(s => s.source_type),
                            datasets: [{
                                data: scanSourcesData.map(s => parseInt(s.scan_count)),
                                backgroundColor: [
                                    'rgba(99, 102, 241, 0.8)',
                                    'rgba(16, 185, 129, 0.8)',
                                    'rgba(245, 158, 11, 0.8)',
                                    'rgba(239, 68, 68, 0.8)'
                                ],
                                borderWidth: 3,
                                borderColor: '#fff',
                                hoverBorderWidth: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        color: '#6b7280'
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#fff',
                                    bodyColor: '#fff'
                                }
                            }
                        }
                    });
                } else {
                    // Show empty state for chart
                    ctx.fillStyle = '#f3f4f6';
                    ctx.fillRect(0, 0, scanSourcesCtx.width, scanSourcesCtx.height);
                    ctx.fillStyle = '#6b7280';
                    ctx.font = '16px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('No data available', scanSourcesCtx.width / 2, scanSourcesCtx.height / 2);
                }
            }
        }

        function changeTimeRange(range) {
            // Add loading state
            document.body.style.cursor = 'wait';
            window.location.href = `analytics.php?range=${range}`;
        }

        function refreshData() {
            // Add loading animation
            const button = event.target.closest('button');
            const icon = button.querySelector('i');
            icon.classList.add('fa-spin');
            
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>
