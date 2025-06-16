<?php
// Common navigation component that can be included in all pages
function renderNavigation($current_page = '') {
    $user_name = getUserName();
    $is_admin = isAdmin();
    $profile_image_html = renderProfileImage('w-8 h-8');
    
    $nav_items = [
        'dashboard.php' => ['icon' => 'fas fa-home', 'label' => 'Dashboard'],
        'products.php' => ['icon' => 'fas fa-box', 'label' => 'Products'],
        'categories.php' => ['icon' => 'fas fa-folder', 'label' => 'Categories'],
        'qr-codes.php' => ['icon' => 'fas fa-qrcode', 'label' => 'QR Codes'],
        'analytics.php' => ['icon' => 'fas fa-chart-bar', 'label' => 'Analytics'],
        'scan.php' => ['icon' => 'fas fa-camera', 'label' => 'Scan QR']
    ];
    
    if ($is_admin) {
        $nav_items = [
            'admin/dashboard.php' => ['icon' => 'fas fa-home', 'label' => 'Dashboard'],
            'admin/manage-users.php' => ['icon' => 'fas fa-users', 'label' => 'Users'],
            'admin/view-products.php' => ['icon' => 'fas fa-box', 'label' => 'Products'],
            'admin/scan-analytics.php' => ['icon' => 'fas fa-chart-line', 'label' => 'Analytics']
        ];
    }
    
    echo '<nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="' . ($is_admin ? 'admin/' : '') . 'dashboard.php" class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                        <i class="fas fa-qrcode mr-2 text-indigo-600"></i>QR Generator Pro' . ($is_admin ? ' - Admin' : '') . '
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-4">';
    
    foreach ($nav_items as $url => $item) {
        $active_class = (basename($_SERVER['PHP_SELF']) === basename($url)) ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:text-indigo-600';
        echo '<a href="' . $url . '" class="' . $active_class . ' px-3 py-2 rounded-md text-sm font-medium transition-colors">
                <i class="' . $item['icon'] . ' mr-1"></i>' . $item['label'] . '
              </a>';
    }
    
    echo '</div>
                <div class="flex items-center space-x-4">
                    <!-- Mobile menu button -->
                    <button onclick="toggleMobileMenu()" class="md:hidden text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Profile dropdown -->
                    <div class="relative group">
                        <button class="flex items-center text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            ' . $profile_image_html . '
                            <span class="ml-2">' . htmlspecialchars($user_name) . '</span>
                            <i class="fas fa-chevron-down ml-2"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                            <div class="border-t border-gray-100"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mobile menu -->
            <div id="mobile-menu" class="md:hidden hidden">
                <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 border-t border-gray-200">';
    
    foreach ($nav_items as $url => $item) {
        $active_class = (basename($_SERVER['PHP_SELF']) === basename($url)) ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:text-indigo-600';
        echo '<a href="' . $url . '" class="' . $active_class . ' block px-3 py-2 rounded-md text-base font-medium">
                <i class="' . $item['icon'] . ' mr-2"></i>' . $item['label'] . '
              </a>';
    }
    
    echo '</div>
            </div>
        </div>
    </nav>
    
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById("mobile-menu");
            menu.classList.toggle("hidden");
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener("click", function(event) {
            const menu = document.getElementById("mobile-menu");
            const button = event.target.closest("button[onclick=\'toggleMobileMenu()\']");
            
            if (!button && !menu.contains(event.target)) {
                menu.classList.add("hidden");
            }
        });
    </script>';
}
?>
