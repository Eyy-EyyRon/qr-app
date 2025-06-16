<?php
require_once 'config/database.php';
require_once 'config/session.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, name, email, password, role, status FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'approved') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    if ($user['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit();
                } elseif ($user['status'] === 'pending') {
                    $error = 'Your account is pending approval. Please wait for admin approval.';
                } else {
                    $error = 'Your account has been blocked. Please contact administrator.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - QR Generator Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-[#3A0519] via-white to-[#A53860] min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="bg-white rounded-2xl shadow-2xl p-8 border border-gray-100">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold bg-gradient-to-r from-[#670D2F] to-[#A53860] bg-clip-text text-transparent mb-2">
                    <i class="fas fa-qrcode text-[#A53860] mr-2"></i>QR Generator Pro
                </h2>
                <p class="text-gray-600 mb-8">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <input type="email" id="email" name="email" required 
                               class="appearance-none block w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all"
                               placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required 
                               class="appearance-none block w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#A53860] focus:border-transparent transition-all"
                               placeholder="Enter your password">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-[#670D2F] to-[#A53860] hover:from-[#3A0519] hover:to-[#670D2F] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#A53860] transition-all transform hover:scale-105">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-sign-in-alt text-[#EF88AD] group-hover:text-white"></i>
                        </span>
                        Sign In
                    </button>
                </div>
            </form>

            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="font-medium text-[#670D2F] hover:text-[#3A0519] transition-colors">Sign up here</a>
                </p>
                <p class="mt-2 text-sm">
                    <a href="index.php" class="text-[#670D2F] hover:text-[#3A0519] transition-colors">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>
