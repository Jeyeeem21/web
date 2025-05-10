<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Default page to load if none is specified
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Validate the page parameter to prevent directory traversal
$allowed_pages = ['dashboard', 'records', 'schedule', 'information', 'settings', 'home_management', 'about_management'];
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Include the requested page
$content_file = $page . '.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Clinic Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Heroicons (for icons) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@heroicons/react@2.0.18/outline/esm/index.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <!-- jQuery -->
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    
    <style>
        /* Custom styles for DataTables */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, 
        .dataTables_wrapper .dataTables_processing, 
        .dataTables_wrapper .dataTables_paginate {
            color: #374151;
            margin-bottom: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.25rem 2rem 0.25rem 0.75rem;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            margin-left: 0.5rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            margin: 0 0.25rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0d9488;
            color: white !important;
            border: 1px solid #0d9488;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f3f4f6;
            color: #111827 !important;
            border: 1px solid #d1d5db;
        }
        
        /* Responsive table styles */
        @media (max-width: 640px) {
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                text-align: left;
                float: none;
            }
            
            .dataTables_wrapper .dataTables_filter {
                margin-top: 0.5rem;
            }
            
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: center;
                float: none;
                display: block;
            }
            
            .dataTables_wrapper .dataTables_paginate {
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center sticky top-0 z-50">
            <div class="flex items-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <h1 class="text-xl font-bold text-gray-800">Bright Smile Dental Clinic</h1>
            </div>
            
            <!-- Mobile Admin Menu -->
            <div class="md:hidden relative">
                <button id="mobileAdminMenuButton" class="flex items-center space-x-1 text-gray-700 hover:text-teal-600 transition-colors">
                    <span>Dr. Smith</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="mobileAdminMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden">
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white transition-colors">Profile</a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white transition-colors">Settings</a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white transition-colors">Logout</a>
                </div>
            </div>
            
            <!-- Desktop Admin Menu -->
            <div class="hidden md:flex items-center space-x-4">
                <div class="relative">
                    <button id="notificationButton" class="text-gray-500 hover:text-teal-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">3</span>
                    </button>
                </div>
                <div class="border-l border-gray-200 h-6"></div>
                <div class="flex items-center space-x-2">
                    <img class="h-8 w-8 rounded-full object-cover" src="https://randomuser.me/api/portraits/men/1.jpg" alt="Admin profile">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Dr. Smith</p>
                        <p class="text-xs text-gray-500">Administrator</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex flex-1 flex-col md:flex-row">
            <!-- Sidebar (Desktop) -->
            <aside class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 md:pt-16 bg-teal-700 text-white">
                <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                    <nav class="mt-5 px-2 space-y-1">
                        <a href="index.php?page=dashboard" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                            <div class="flex items-center px-4 py-3 text-white rounded-md <?php echo $page === 'dashboard' ? 'bg-teal-800' : 'hover:bg-teal-800'; ?> group transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Dashboard
                            </div>
                        </a>
                        <a href="index.php?page=records" class="nav-link <?php echo $page === 'records' ? 'active' : ''; ?>">
                            <div class="flex items-center px-4 py-3 text-white rounded-md <?php echo $page === 'records' ? 'bg-teal-800' : 'hover:bg-teal-800'; ?> group transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Records
                            </div>
                        </a>
                        <a href="index.php?page=schedule" class="nav-link <?php echo $page === 'schedule' ? 'active' : ''; ?>">
                            <div class="flex items-center px-4 py-3 text-white rounded-md <?php echo $page === 'schedule' ? 'bg-teal-800' : 'hover:bg-teal-800'; ?> group transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Schedule
                            </div>
                        </a>
                        <a href="index.php?page=information" class="nav-link <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'active' : ''; ?>">
                            <div class="flex items-center px-4 py-3 text-white rounded-md <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'bg-teal-800' : 'hover:bg-teal-800'; ?> group transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Information
                            </div>
                        </a>
                        <a href="index.php?page=settings" class="nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>">
                            <div class="flex items-center px-4 py-3 text-white rounded-md <?php echo $page === 'settings' ? 'bg-teal-800' : 'hover:bg-teal-800'; ?> group transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Settings
                            </div>
                        </a>
                    </nav>
                </div>
                <div class="border-t border-teal-800 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <img class="h-10 w-10 rounded-full object-cover" src="https://randomuser.me/api/portraits/men/1.jpg" alt="Admin profile">
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white">Dr. Smith</p>
                            <a href="#" class="text-xs font-medium text-teal-300 hover:text-white transition-colors">Logout</a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 md:ml-64 pt-5 px-4 pb-24 md:pb-5">
                <?php include $content_file; ?>
            </main>
        </div>
        
        <!-- Mobile Footer Navigation (replaces sidebar on mobile) -->
        <footer class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t shadow-md">
            <div class="grid grid-cols-5 h-16">
                <a href="index.php?page=dashboard" class="flex flex-col items-center justify-center <?php echo $page === 'dashboard' ? 'text-teal-600' : 'text-gray-500'; ?> transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-xs mt-1">Dashboard</span>
                </a>
                <a href="index.php?page=records" class="flex flex-col items-center justify-center <?php echo $page === 'records' ? 'text-teal-600' : 'text-gray-500'; ?> transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-xs mt-1">Records</span>
                </a>
                <a href="index.php?page=schedule" class="flex flex-col items-center justify-center <?php echo $page === 'schedule' ? 'text-teal-600' : 'text-gray-500'; ?> transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs mt-1">Schedule</span>
                </a>
                <a href="index.php?page=information" class="flex flex-col items-center justify-center <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'text-teal-600' : 'text-gray-500'; ?> transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs mt-1">Info</span>
                </a>
                <a href="index.php?page=settings" class="flex flex-col items-center justify-center <?php echo $page === 'settings' ? 'text-teal-600' : 'text-gray-500'; ?> transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-xs mt-1">Settings</span>
                </a>
            </div>
        </footer>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-20 right-5 bg-green-500 text-white px-6 py-3 rounded-md shadow-lg transform transition-transform duration-300 translate-y-full opacity-0 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span id="toastMessage">Operation successful!</span>
    </div>

    <!-- JavaScript for Mobile Admin Menu -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile admin menu toggle
            const mobileAdminMenuButton = document.getElementById('mobileAdminMenuButton');
            const mobileAdminMenu = document.getElementById('mobileAdminMenu');
            
            if (mobileAdminMenuButton && mobileAdminMenu) {
                mobileAdminMenuButton.addEventListener('click', function() {
                    mobileAdminMenu.classList.toggle('hidden');
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!mobileAdminMenuButton.contains(event.target) && !mobileAdminMenu.contains(event.target)) {
                        mobileAdminMenu.classList.add('hidden');
                    }
                });
            }
            
            // Toast notification function
            window.showToast = function(message, type = 'success') {
                const toast = document.getElementById('toast');
                const toastMessage = document.getElementById('toastMessage');
                
                // Set message
                toastMessage.textContent = message;
                
                // Set color based on type
                if (type === 'success') {
                    toast.classList.remove('bg-red-500', 'bg-blue-500');
                    toast.classList.add('bg-green-500');
                } else if (type === 'error') {
                    toast.classList.remove('bg-green-500', 'bg-blue-500');
                    toast.classList.add('bg-red-500');
                } else if (type === 'info') {
                    toast.classList.remove('bg-green-500', 'bg-red-500');
                    toast.classList.add('bg-blue-500');
                }
                
                // Show toast
                toast.classList.remove('translate-y-full', 'opacity-0');
                
                // Hide toast after 3 seconds
                setTimeout(function() {
                    toast.classList.add('translate-y-full', 'opacity-0');
                }, 3000);
            };
        });
    </script>
</body>
</html>
<?php
// Flush the output buffer
ob_end_flush();
?>
