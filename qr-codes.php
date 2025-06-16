<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getUserId();

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query with filters
$where_conditions = ["p.user_id = ?", "p.qr_code_path IS NOT NULL"];
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

$where_clause = implode(' AND ', $where_conditions);

// Get products with QR codes
$products_query = "SELECT p.*, c.name as category_name, c.color as category_color,
                          (SELECT COUNT(*) FROM qr_scans qs WHERE qs.product_id = p.id) as scan_count
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE $where_clause
                   ORDER BY p.created_at DESC";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute($params);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$categories_query = "SELECT * FROM categories WHERE user_id = ? ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute([$user_id]);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get QR code statistics
$stats_query = "SELECT 
    COUNT(*) as total_qr_codes,
    SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_codes,
    (SELECT COUNT(*) FROM qr_scans qs JOIN products p2 ON qs.product_id = p2.id WHERE p2.user_id = ?) as total_scans
    FROM products p WHERE p.user_id = ? AND p.qr_code_path IS NOT NULL";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes - QR Generator Pro</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">QR Code Gallery</h1>
                    <p class="text-gray-600 mt-2">View, download, and manage your generated QR codes</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="products.php" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all text-center">
                        <i class="fas fa-plus mr-2"></i>Add Product
                    </a>
                    <button onclick="downloadAllQR()" class="bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                        <i class="fas fa-download mr-2"></i>Download All
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-qrcode text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total QR Codes</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_qr_codes']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-teal-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check-circle text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active Codes</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['active_codes']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-eye text-xl text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Scans</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_scans']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow-xl rounded-2xl p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search QR Codes</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by product name..."
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
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- QR Codes Grid -->
        <?php if (empty($products)): ?>
            <div class="bg-white shadow-xl rounded-2xl p-12 text-center">
                <i class="fas fa-qrcode text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No QR codes found</h3>
                <p class="text-gray-500 mb-6">
                    <?php if (!empty($search) || $category_filter > 0): ?>
                        Try adjusting your filters or search terms
                    <?php else: ?>
                        Create products to generate QR codes
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && $category_filter == 0): ?>
                    <a href="products.php" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                        <i class="fas fa-plus mr-2"></i>Create Product
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                    <div class="bg-white shadow-xl rounded-2xl overflow-hidden hover:shadow-2xl transition-shadow">
                        <div class="p-6">
                            <!-- QR Code Display -->
                            <div class="bg-white border-2 border-gray-200 rounded-lg p-4 mb-4 text-center">
                                <?php if ($product['qr_code_path'] && file_exists($product['qr_code_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['qr_code_path']); ?>" 
                                         alt="QR Code for <?php echo htmlspecialchars($product['name']); ?>"
                                         class="w-32 h-32 mx-auto cursor-pointer hover:scale-105 transition-transform"
                                         onclick="viewQRCode('<?php echo htmlspecialchars($product['qr_code_path']); ?>', '<?php echo htmlspecialchars($product['name']); ?>')">
                                <?php else: ?>
                                    <div class="w-32 h-32 mx-auto bg-gray-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-exclamation-triangle text-2xl text-gray-400"></i>
                                    </div>
                                    <p class="text-xs text-red-500 mt-2">QR Code not found</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Product Info -->
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
                            
                            <h3 class="text-lg font-bold text-gray-900 mb-1 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-xl font-bold text-indigo-600 mb-2"><?php echo formatPrice($product['price']); ?></p>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-eye mr-1"></i>
                                    <?php echo $product['scan_count']; ?> scans
                                </div>
                                <div>
                                    <?php echo timeAgo($product['created_at']); ?>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="shareQRCode('<?php echo htmlspecialchars($product['qr_code_path']); ?>', '<?php echo htmlspecialchars($product['name']); ?>')" 
                                        class="bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white px-3 py-2 rounded-lg text-sm transition-all">
                                    <i class="fas fa-share mr-1"></i>Share
                                </button>
                                <a href="<?php echo htmlspecialchars($product['qr_code_path']); ?>" 
                                   download="qr_<?php echo $product['id']; ?>_<?php echo sanitizeInput($product['name']); ?>.png"
                                   class="bg-gradient-to-r from-green-500 to-teal-500 hover:from-green-600 hover:to-teal-600 text-white px-3 py-2 rounded-lg text-sm transition-all text-center">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                            </div>
                            
                            <div class="mt-2">
                                <a href="products.php" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-3 rounded-lg text-sm text-center block transition-colors">
                                    <i class="fas fa-edit mr-1"></i>Edit Product
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- QR Code Viewer Modal -->
    <div id="qrModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4">
            <div class="text-center">
                <h3 class="text-xl font-bold text-gray-900 mb-4" id="qrModalTitle">QR Code</h3>
                <div class="bg-white border-2 border-gray-200 rounded-lg p-6 mb-6">
                    <img id="qrModalImage" src="/placeholder.svg" alt="QR Code" class="w-64 h-64 mx-auto">
                </div>
                <div class="flex items-center justify-center space-x-3">
                    <button onclick="closeQRModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                        Close
                    </button>
                    <button onclick="downloadCurrentQR()" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all">
                        <i class="fas fa-download mr-2"></i>Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Share QR Code</h3>
                <div class="space-y-3">
                    <button onclick="copyQRLink()" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-copy mr-2"></i>Copy Link
                    </button>
                    <button onclick="shareViaEmail()" class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-envelope mr-2"></i>Share via Email
                    </button>
                    <button onclick="printQRCode()" class="w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-print mr-2"></i>Print QR Code
                    </button>
                </div>
                <div class="mt-4 text-center">
                    <button onclick="closeShareModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentQRPath = '';
        let currentQRName = '';

        function viewQRCode(qrPath, productName) {
            document.getElementById('qrModalImage').src = qrPath;
            document.getElementById('qrModalTitle').textContent = productName;
            document.getElementById('qrModal').classList.remove('hidden');
            currentQRPath = qrPath;
            currentQRName = productName;
        }

        function closeQRModal() {
            document.getElementById('qrModal').classList.add('hidden');
        }

        function downloadCurrentQR() {
            const link = document.createElement('a');
            link.href = currentQRPath;
            link.download = `qr_${currentQRName.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.png`;
            link.click();
        }

        function shareQRCode(qrPath, productName) {
            currentQRPath = qrPath;
            currentQRName = productName;
            document.getElementById('shareModal').classList.remove('hidden');
        }

        function closeShareModal() {
            document.getElementById('shareModal').classList.add('hidden');
        }

        function copyQRLink() {
            const fullUrl = window.location.origin + '/' + currentQRPath;
            navigator.clipboard.writeText(fullUrl).then(() => {
                alert('QR code link copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = fullUrl;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('QR code link copied to clipboard!');
            });
        }

        function shareViaEmail() {
            const subject = encodeURIComponent(`QR Code for ${currentQRName}`);
            const body = encodeURIComponent(`Hi,\n\nI'm sharing the QR code for "${currentQRName}".\n\nYou can view it here: ${window.location.origin}/${currentQRPath}\n\nBest regards`);
            window.open(`mailto:?subject=${subject}&body=${body}`);
        }

        function printQRCode() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Print QR Code - ${currentQRName}</title>
                        <style>
                            body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                            img { max-width: 300px; height: auto; }
                            h1 { color: #333; margin-bottom: 20px; }
                            .info { margin-top: 20px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <h1>${currentQRName}</h1>
                        <img src="${currentQRPath}" alt="QR Code for ${currentQRName}">
                        <div class="info">
                            <p>Generated by QR Generator Pro</p>
                            <p>Scan this code to view product details</p>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function downloadAllQR() {
            const qrImages = document.querySelectorAll('img[src*="qr_codes"]');
            if (qrImages.length === 0) {
                alert('No QR codes available for download.');
                return;
            }

            qrImages.forEach((img, index) => {
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = img.src;
                    link.download = `qr_code_${index + 1}.png`;
                    link.click();
                }, index * 500); // Delay to prevent browser blocking
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const qrModal = document.getElementById('qrModal');
            const shareModal = document.getElementById('shareModal');
            
            if (event.target === qrModal) closeQRModal();
            if (event.target === shareModal) closeShareModal();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeQRModal();
                closeShareModal();
            }
        });
    </script>
</body>
</html>
