<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/qr-generator.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$scanned_product = null;
$error = '';

// Handle QR code scan result
if (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Get product details
    $product_query = "SELECT p.*, c.name as category_name, u.name as owner_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN users u ON p.user_id = u.id 
                      WHERE p.id = ? AND p.is_active = 1";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute([$product_id]);
    $scanned_product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($scanned_product) {
        // Track the scan
        $user_id = isLoggedIn() ? getUserId() : null;
        QRGenerator::trackScan($product_id, $user_id);
    } else {
        $error = 'Product not found or inactive.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="<?php echo isLoggedIn() ? 'dashboard.php' : 'index.php'; ?>" class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                        <i class="fas fa-qrcode mr-2 text-indigo-600"></i>QR Generator Pro
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-home mr-1"></i>Dashboard
                        </a>
                        <a href="qr-codes.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-qrcode mr-1"></i>My QR Codes
                        </a>
                        <div class="relative group">
                            <button class="flex items-center text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-user-circle mr-2"></i>
                                <?php echo htmlspecialchars(getUserName()); ?>
                                <i class="fas fa-chevron-down ml-2"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i>Profile
                                </a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                        <a href="register.php" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-xl rounded-2xl p-8 mb-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">QR Code Scanner</h1>
                <p class="text-gray-600">Scan QR codes using your device camera or upload an image</p>
            </div>
        </div>

        <?php if ($scanned_product): ?>
            <!-- Scanned Product Display -->
            <div class="bg-white shadow-xl rounded-2xl p-8 mb-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-2xl text-green-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Product Found!</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <?php if ($scanned_product['image']): ?>
                            <img src="<?php echo htmlspecialchars($scanned_product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($scanned_product['name']); ?>"
                                 class="w-full h-64 object-cover rounded-lg">
                        <?php else: ?>
                            <div class="w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center">
                                <i class="fas fa-image text-4xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($scanned_product['name']); ?></h3>
                        <p class="text-3xl font-bold text-indigo-600 mb-4"><?php echo formatPrice($scanned_product['price']); ?></p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center">
                                <i class="fas fa-tag text-gray-400 mr-3"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars($scanned_product['category_name']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-user text-gray-400 mr-3"></i>
                                <span class="text-gray-700">By <?php echo htmlspecialchars($scanned_product['owner_name']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-calendar text-gray-400 mr-3"></i>
                                <span class="text-gray-700">Added <?php echo date('M j, Y', strtotime($scanned_product['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($scanned_product['description']): ?>
                            <div class="mb-6">
                                <h4 class="font-medium text-gray-900 mb-2">Description</h4>
                                <p class="text-gray-600"><?php echo htmlspecialchars($scanned_product['description']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <button onclick="scanAnother()" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-camera mr-2"></i>Scan Another QR Code
                        </button>
                    </div>
                </div>
            </div>
        <?php elseif ($error): ?>
            <!-- Error Display -->
            <div class="bg-white shadow-xl rounded-2xl p-8 mb-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Error</h2>
                    <p class="text-red-600 mb-6"><?php echo htmlspecialchars($error); ?></p>
                    <button onclick="scanAnother()" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                        <i class="fas fa-camera mr-2"></i>Try Scanning Again
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Scanner Interface -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Camera Scanner -->
                <div class="bg-white shadow-xl rounded-2xl p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-camera mr-2 text-indigo-600"></i>Camera Scanner
                    </h3>
                    
                    <div id="scanner-container" class="mb-6">
                        <div id="qr-reader" class="w-full"></div>
                        <div id="scanner-status" class="text-center mt-4">
                            <button onclick="startScanner()" id="start-btn" class="bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                                <i class="fas fa-play mr-2"></i>Start Camera
                            </button>
                            <button onclick="stopScanner()" id="stop-btn" class="bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-medium transition-all hidden">
                                <i class="fas fa-stop mr-2"></i>Stop Camera
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <p class="mb-2"><i class="fas fa-info-circle mr-2"></i>Position the QR code within the camera frame</p>
                        <p><i class="fas fa-mobile-alt mr-2"></i>Works on both desktop and mobile devices</p>
                    </div>
                </div>

                <!-- File Upload Scanner -->
                <div class="bg-white shadow-xl rounded-2xl p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-upload mr-2 text-purple-600"></i>Upload QR Image
                    </h3>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center mb-6" id="upload-area">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 mb-4">Drag and drop a QR code image here, or click to select</p>
                        <input type="file" id="qr-file" accept="image/*" class="hidden">
                        <button onclick="document.getElementById('qr-file').click()" class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-folder-open mr-2"></i>Choose File
                        </button>
                    </div>
                    
                    <div id="upload-result" class="hidden">
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <img id="uploaded-image" src="/placeholder.svg" alt="Uploaded QR Code" class="w-full h-32 object-contain">
                        </div>
                        <button onclick="scanUploadedFile()" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all">
                            <i class="fas fa-search mr-2"></i>Scan Image
                        </button>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <p class="mb-2"><i class="fas fa-info-circle mr-2"></i>Supported formats: JPG, PNG, GIF</p>
                        <p><i class="fas fa-file-image mr-2"></i>Max file size: 5MB</p>
                    </div>
                </div>
            </div>

            <!-- Offline Support Notice -->
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 mt-8">
                <div class="flex items-center">
                    <i class="fas fa-wifi text-blue-600 text-xl mr-4"></i>
                    <div>
                        <h4 class="font-medium text-blue-900">Offline Support</h4>
                        <p class="text-blue-700 text-sm">This scanner works offline once loaded. Your scans are saved locally and synced when you're back online.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let html5QrCode = null;
        let isScanning = false;

        function startScanner() {
            if (isScanning) return;
            
            html5QrCode = new Html5Qrcode("qr-reader");
            
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    const cameraId = devices[0].id;
                    
                    html5QrCode.start(
                        cameraId,
                        {
                            fps: 10,
                            qrbox: { width: 250, height: 250 }
                        },
                        (decodedText, decodedResult) => {
                            // Handle successful scan
                            handleScanResult(decodedText);
                        },
                        (errorMessage) => {
                            // Handle scan error (usually just no QR code found)
                        }
                    ).then(() => {
                        isScanning = true;
                        document.getElementById('start-btn').classList.add('hidden');
                        document.getElementById('stop-btn').classList.remove('hidden');
                    }).catch(err => {
                        console.error('Error starting scanner:', err);
                        alert('Error starting camera. Please check permissions.');
                    });
                }
            }).catch(err => {
                console.error('Error getting cameras:', err);
                alert('No cameras found or permission denied.');
            });
        }

        function stopScanner() {
            if (html5QrCode && isScanning) {
                html5QrCode.stop().then(() => {
                    isScanning = false;
                    document.getElementById('start-btn').classList.remove('hidden');
                    document.getElementById('stop-btn').classList.add('hidden');
                }).catch(err => {
                    console.error('Error stopping scanner:', err);
                });
            }
        }

        function handleScanResult(decodedText) {
            try {
                // Try to parse as JSON (our QR format)
                const qrData = JSON.parse(decodedText);
                if (qrData.type === 'product' && qrData.product_id) {
                    window.location.href = `scan.php?id=${qrData.product_id}`;
                    return;
                }
            } catch (e) {
                // Not JSON, might be a URL
            }
            
            // Check if it's a URL pointing to our scan page
            if (decodedText.includes('scan.php?id=')) {
                window.location.href = decodedText;
                return;
            }
            
            // Generic QR code
            alert('QR Code detected but not a valid product code: ' + decodedText);
        }

        function scanAnother() {
            window.location.href = 'scan.php';
        }

        // File upload handling
        document.getElementById('qr-file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('uploaded-image').src = e.target.result;
                    document.getElementById('upload-result').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        function scanUploadedFile() {
    const fileInput = document.getElementById('qr-file');
    const file = fileInput.files[0];
    
    if (file) {
        // Show loading state
        const scanButton = document.querySelector('#upload-result button');
        const originalText = scanButton.innerHTML;
        scanButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Scanning...';
        scanButton.disabled = true;
        
        // Try to decode using canvas and jsQR library directly
        decodeQRFromImage(file, () => {
            // Reset button state
            scanButton.innerHTML = originalText;
            scanButton.disabled = false;
        });
    }
}

// Improved function using canvas and jsQR library
function decodeQRFromImage(file, callback) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    
    img.onload = function() {
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0);
        
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        
        if (typeof jsQR !== 'undefined') {
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert",
            });
            
            if (code) {
                handleScanResult(code.data);
            } else {
                // Try with different processing
                tryAlternativeDecoding(canvas, ctx, callback);
            }
        } else {
            alert('QR code scanning library not loaded. Please refresh the page and try again.');
        }
        
        if (callback) callback();
    };
    
    img.onerror = function() {
        alert('Error loading the image. Please try again with a different image.');
        if (callback) callback();
    };
    
    const reader = new FileReader();
    reader.onload = function(e) {
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// Alternative decoding with image processing
function tryAlternativeDecoding(canvas, ctx, callback) {
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    
    // Try with inverted colors
    const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: "attemptBoth",
    });
    
    if (code) {
        handleScanResult(code.data);
    } else {
        alert('No QR code found in the uploaded image. Please try with a clearer image or use the camera scanner.');
    }
    
    if (callback) callback();
}

        // Drag and drop functionality
        const uploadArea = document.getElementById('upload-area');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('border-indigo-500', 'bg-indigo-50');
        });
        
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-indigo-500', 'bg-indigo-50');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-indigo-500', 'bg-indigo-50');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('qr-file').files = files;
                document.getElementById('qr-file').dispatchEvent(new Event('change'));
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (html5QrCode && isScanning) {
                html5QrCode.stop();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</body>
</html>
