<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if email already exists
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email address is already registered.';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (name, email, password, phone, address, role, status) VALUES (?, ?, ?, ?, ?, 'user', 'pending')";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$name, $email, $hashed_password, $phone, $address])) {
                $success = 'Registration successful! Your account is pending approval. You will be able to login once an administrator approves your account.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-[#3A0519] via-[#670D2F] to-[#A53860] min-h-screen flex items-center justify-center py-12"> 
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="bg-white rounded-2xl shadow-2xl p-8 border border-[#EF88AD]">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold bg-gradient-to-r from-[#A53860] to-[#EF88AD] bg-clip-text text-transparent mb-2">
                    <i class="fas fa-qrcode text-[#A53860] mr-2"></i>QR Generator Pro
                </h2>
                <p class="text-[#670D2F] mb-8">Create your account</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-[#FDECEC] border border-[#F8C0C8] text-[#A53860] px-4 py-3 rounded-lg mb-4 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-[#E6F9F0] border border-[#B4E3D2] text-[#3A0519] px-4 py-3 rounded-lg mb-4 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-[#3A0519] mb-2">Full Name *</label>
                    <div class="relative">
                        <input type="text" id="name" name="name" required 
                               class="appearance-none block w-full px-4 py-3 pl-12 border border-[#EF88AD] rounded-lg placeholder-[#A53860] focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all"
                               placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-user text-[#EF88AD]"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-[#3A0519] mb-2">Email Address *</label>
                    <div class="relative">
                        <input type="email" id="email" name="email" required 
                               class="appearance-none block w-full px-4 py-3 pl-12 border border-[#EF88AD] rounded-lg placeholder-[#A53860] focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all"
                               placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-[#EF88AD]"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-[#3A0519] mb-2">Phone Number</label>
                    <div class="relative">
                        <input type="tel" id="phone" name="phone" 
                               class="appearance-none block w-full px-4 py-3 pl-12 border border-[#EF88AD] rounded-lg placeholder-[#A53860] focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all"
                               placeholder="Enter your phone number" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-phone text-[#EF88AD]"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-[#3A0519] mb-2">Address</label>
                    <div class="relative">
                        <textarea id="address" name="address" rows="2"
                                  class="appearance-none block w-full px-4 py-3 pl-12 border border-[#EF88AD] rounded-lg placeholder-[#A53860] focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all resize-none"
                                  placeholder="Enter your address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        <div class="absolute top-3 left-0 pl-4 flex items-start pointer-events-none">
                            <i class="fas fa-map-marker-alt text-[#EF88AD]"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-[#3A0519] mb-2">Password *</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required 
                               class="appearance-none block w-full px-4 py-3 pl-12 border border-[#EF88AD] rounded-lg placeholder-[#A53860] focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all"
                               placeholder="Enter your password">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-[#EF88AD]"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-[#3A0519] mb-2">Confirm Password *</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               class="appearance-none block w-full px-4 py-3 pl-12 border border-[#EF88AD] rounded-lg placeholder-[#A53860] focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all"
                               placeholder="Confirm your password">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-[#EF88AD]"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-[#A53860] to-[#EF88AD] hover:from-[#670D2F] hover:to-[#A53860] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#A53860] transition-all transform hover:scale-105">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-user-plus text-[#FBD2E0] group-hover:text-white"></i>
                        </span>
                        Create Account
                    </button>
                </div>
            </form>

            <div class="mt-8 text-center">
                <p class="text-sm text-[#670D2F]">
                    Already have an account? 
                    <a href="login.php" class="font-medium text-[#A53860] hover:text-[#EF88AD] transition-colors">Sign in here</a>
                </p>
                <p class="mt-2 text-sm">
                    <a href="index.php" class="text-[#A53860] hover:text-[#EF88AD] transition-colors">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>
