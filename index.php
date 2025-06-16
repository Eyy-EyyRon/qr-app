<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Generator Pro - Professional QR Code Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'bounce-slow': 'bounce 2s infinite',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-[#3A0519] via-white to-[#A53860] min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold bg-gradient-to-r from-[#670D2F] to-[#A53860] bg-clip-text text-transparent">
                            <i class="fas fa-qrcode mr-2 text-[#A53860]"></i>QR Generator
                        </h1>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-gray-700 hover:text-[#A53860] px-3 py-2 rounded-md text-sm font-medium transition-colors">Login</a>
                    <a href="register.php" class="bg-gradient-to-r from-[#670D2F] to-[#A53860] hover:from-[#3A0519] hover:to-[#670D2F] text-white px-6 py-2 rounded-full text-sm font-medium transition-all transform hover:scale-105">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left animate-fade-in">
                        <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                            <span class="block xl:inline">Generate Professional</span>
                            <span class="block bg-gradient-to-r from-[#670D2F] to-[#A53860] bg-clip-text text-transparent xl:inline">QR Codes</span>
                        </h1>
                       
                        <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                            <div class="rounded-md shadow">
                                <a href="register.php" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-gradient-to-r from-[#670D2F] to-[#A53860] hover:from-[#3A0519] hover:to-[#670D2F] md:py-4 md:text-lg md:px-10 transition-all transform hover:scale-105">
                                    Get Started Free
                                </a>
                            </div>
                            <div class="mt-3 sm:mt-0 sm:ml-3">
                                <a href="#features" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-[#3A0519] bg-[#EF88AD] hover:bg-[#A53860] md:py-4 md:text-lg md:px-10 transition-all">
                                    Learn More
                                </a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2">
            <div class="h-56 w-full bg-gradient-to-br from-[#670D2F] via-[#A53860] to-[#EF88AD] sm:h-72 md:h-96 lg:w-full lg:h-full flex items-center justify-center">
                <div class="text-white text-center animate-bounce-slow">
                    <i class="fas fa-qrcode text-8xl mb-4 opacity-90"></i>
                    <p class="text-xl font-semibold">Professional QR Solutions</p>
                    <p class="text-sm opacity-80 mt-2">Trusted by 1000+ businesses</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Features -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-[#3A0519]">Features</h2>
                <p class="text-gray-600 mt-4">Everything you need to manage and track your QR codes effectively.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white rounded-lg shadow-lg p-6 text-center border-t-4 border-[#A53860]">
                    <i class="fas fa-cogs text-4xl text-[#A53860] mb-4"></i>
                    <h3 class="text-xl font-semibold text-[#3A0519]">Easy to Use</h3>
                    <p class="text-gray-600 mt-2">User-friendly interface that simplifies QR code generation.</p>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6 text-center border-t-4 border-[#670D2F]">
                    <i class="fas fa-database text-4xl text-[#670D2F] mb-4"></i>
                    <h3 class="text-xl font-semibold text-[#3A0519]">Database Integration</h3>
                    <p class="text-gray-600 mt-2">Automatically store and manage your scanned data.</p>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6 text-center border-t-4 border-[#EF88AD]">
                    <i class="fas fa-chart-line text-4xl text-[#EF88AD] mb-4"></i>
                    <h3 class="text-xl font-semibold text-[#3A0519]">Real-time Analytics</h3>
                    <p class="text-gray-600 mt-2">Track scan history and performance in real-time.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-br from-[#A53860] to-[#670D2F] text-white">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-4xl font-bold">Ready to Enhance Your Workflow?</h2>
            <p class="mt-4 text-lg">Join thousands of professionals using QR Generator Pro to manage their products efficiently.</p>
            <a href="register.php" class="mt-6 inline-block bg-white text-[#670D2F] px-8 py-3 rounded-full text-lg font-medium hover:bg-[#EF88AD] transition-all">Create Free Account</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[#3A0519] text-white py-6">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
            <p class="text-sm">&copy; 2025 QR Generator Pro. All rights reserved.</p>
            <div class="flex space-x-4 mt-4 md:mt-0">
                <a href="#" class="hover:text-[#EF88AD]"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="hover:text-[#EF88AD]"><i class="fab fa-twitter"></i></a>
                <a href="#" class="hover:text-[#EF88AD]"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </footer>
</body>

</html>
