<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection
require_once 'config/db.php';

// Default page to load if none is specified
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Define allowed pages
$allowed_pages = ['dashboard', 'records', 'schedule', 'information', 'settings', 'home_management', 'about_management', 'doctor_position_management', 'staff_management'];
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Include the requested page
$content_file = $page . '.php';

// Get active home content
$stmt = $pdo->query("SELECT * FROM home WHERE status = 1 ORDER BY createdDate DESC LIMIT 1");
$homeContent = $stmt->fetch();

// Get active about content
$stmt = $pdo->query("SELECT * FROM about WHERE status = 1 ORDER BY createdDate DESC LIMIT 1");
$aboutContent = $stmt->fetch();
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
    <!-- jQuery -->
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            200: '#99f6e4',
                            300: '#5eead4',
                            400: '#2dd4bf',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                            950: '#042f2e',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Minimal styling for DataTables */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, 
        .dataTables_wrapper .dataTables_processing, 
        .dataTables_wrapper .dataTables_paginate {
            color: #374151;
            margin-bottom: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
            margin: 0 0.25rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0d9488;
            color: white !important;
            border: 1px solid #0d9488;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f9fafb;
            color: #111827 !important;
            border: 1px solid #e5e7eb;
        }
        
        /* Collapsible sidebar */
        .sidebar {
            transition: width 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .nav-text {
            display: none;
        }
        
        .sidebar.collapsed .admin-info span {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-toggle svg {
            transform: rotate(180deg);
        }
        
        /* Main content adjustment */
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        /* Mobile card view for tables */
        @media (max-width: 640px) {
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                text-align: left;
                float: none;
            }
            
            .dataTables_wrapper .dataTables_filter {
                margin-top: 0.5rem;
            }
            
            /* Hide table headers on mobile */
            .mobile-card-view thead {
                display: none;
            }
            
            /* Style each row as a card */
            .mobile-card-view tbody tr {
                display: block;
                margin-bottom: 1rem;
                border-radius: 0.375rem;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
                background-color: white;
                padding: 1rem;
                border: 1px solid #e5e7eb;
            }
            
            /* Style each cell as a flex container */
            .mobile-card-view tbody td {
                display: flex;
                padding: 0.5rem 0;
                border-bottom: 1px solid #f3f4f6;
                text-align: left;
            }
            
            .mobile-card-view tbody td:last-child {
                border-bottom: none;
            }
            
            /* Add labels for each cell */
            .mobile-card-view tbody td:before {
                content: attr(data-label);
                font-weight: 500;
                color: #4b5563;
                width: 40%;
                margin-right: 0.5rem;
            }
            
            /* Style the content of each cell */
            .mobile-card-view tbody td .cell-content {
                width: 60%;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-800">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white border-b border-gray-100 py-3 px-4 flex justify-between items-center sticky top-0 z-50">
            <div class="flex items-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <h1 class="text-lg font-medium">Bright Smile Dental</h1>
            </div>
            
        </header>

        <div class="flex flex-1 flex-col md:flex-row">
            <!-- Sidebar (Desktop) -->
            <aside id="sidebar" class="sidebar hidden md:flex md:w-56 md:flex-col md:fixed md:inset-y-0 md:pt-14 bg-white border-r border-gray-100">
                <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                    <!-- Sidebar Toggle Button -->
                    <div class="px-4 mb-2">
                        <button id="sidebarToggle" class="sidebar-toggle text-gray-500 hover:text-gray-700 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                            </svg>
                        </button>
                    </div>
                    
                    <nav class="mt-2 px-2 space-y-1">
                        <a href="index.php?page=dashboard" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                            <div class="flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'dashboard' ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span class="nav-text">Dashboard</span>
                            </div>
                        </a>
                        <a href="index.php?page=records" class="nav-link <?php echo $page === 'records' ? 'active' : ''; ?>">
                            <div class="flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'records' ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="nav-text">Records</span>
                            </div>
                        </a>
                        <a href="index.php?page=schedule" class="nav-link <?php echo $page === 'schedule' ? 'active' : ''; ?>">
                            <div class="flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'schedule' ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="nav-text">Schedule</span>
                            </div>
                        </a>
                        <a href="index.php?page=information" class="nav-link <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'active' : ''; ?>">
                            <div class="flex items-center px-3 py-2 text-sm rounded-md <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="nav-text">Information</span>
                            </div>
                        </a>
                        <a href="index.php?page=settings" class="nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>">
                            <div class="flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'settings' ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="nav-text">Settings</span>
                            </div>
                        </a>
                    </nav>
                </div>
                <div class="border-t border-gray-100 p-4 admin-info">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <img class="h-8 w-8 rounded-full object-cover" src="https://randomuser.me/api/portraits/men/1.jpg" alt="Admin profile">
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium nav-text">Dr. Smith</p>
                            <a href="#" class="text-xs text-primary-600 hover:text-primary-800 nav-text">Logout</a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main id="mainContent" class="main-content flex-1 md:ml-56 pt-4 px-4 pb-20 md:pb-4 overflow-x-hidden">
                <?php include $content_file; ?>
            </main>
        </div>
        
        <!-- Mobile Footer Navigation (replaces sidebar on mobile) -->
        <footer class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t">
            <div class="grid grid-cols-5 h-14">
                <a href="index.php?page=dashboard" class="flex flex-col items-center justify-center <?php echo $page === 'dashboard' ? 'text-primary-600' : 'text-gray-500'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-xs mt-1">Dashboard</span>
                </a>
                <a href="index.php?page=records" class="flex flex-col items-center justify-center <?php echo $page === 'records' ? 'text-primary-600' : 'text-gray-500'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-xs mt-1">Records</span>
                </a>
                <a href="index.php?page=schedule" class="flex flex-col items-center justify-center <?php echo $page === 'schedule' ? 'text-primary-600' : 'text-gray-500'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs mt-1">Schedule</span>
                </a>
                <a href="index.php?page=information" class="flex flex-col items-center justify-center <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'text-primary-600' : 'text-gray-500'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs mt-1">Info</span>
                </a>
                <a href="index.php?page=settings" class="flex flex-col items-center justify-center <?php echo $page === 'settings' ? 'text-primary-600' : 'text-gray-500'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-xs mt-1">Settings</span>
                </a>
            </div>
        </footer>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-20 right-4 bg-white border border-gray-100 text-gray-800 px-4 py-2 rounded-md shadow-sm transform transition-transform duration-300 translate-y-full opacity-0 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-primary-600" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span id="toastMessage" class="text-sm">Operation successful!</span>
    </div>

    <!-- JavaScript for Mobile Menu and Sidebar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            // Check if sidebar state is stored in localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            // Initialize sidebar state
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.style.marginLeft = '70px';
            }
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.style.marginLeft = '70px';
                        localStorage.setItem('sidebarCollapsed', 'true');
                    } else {
                        mainContent.style.marginLeft = '14rem';
                        localStorage.setItem('sidebarCollapsed', 'false');
                    }
                });
            }
            
            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            
            if (mobileMenuButton) {
                mobileMenuButton.addEventListener('click', function() {
                    // Toggle mobile menu here if needed
                });
            }
            
            // Convert tables to card view on mobile
            function setupMobileCardView() {
                const tables = document.querySelectorAll('table.mobile-card-view');
                
                tables.forEach(table => {
                    // Get all headers
                    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
                    
                    // Process each row
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        cells.forEach((cell, index) => {
                            if (headers[index]) {
                                // Add data-label attribute for mobile view
                                cell.setAttribute('data-label', headers[index]);
                                
                                // Wrap content in a div for styling
                                const content = cell.innerHTML;
                                cell.innerHTML = `<div class="cell-content">${content}</div>`;
                            }
                        });
                    });
                });
            }
            
            // Run setup on page load
            setupMobileCardView();
            
            // Toast notification function
            window.showToast = function(message, type = 'success') {
                const toast = document.getElementById('toast');
                const toastMessage = document.getElementById('toastMessage');
                
                // Set message
                toastMessage.textContent = message;
                
                // Set color based on type
                if (type === 'success') {
                    toast.classList.remove('border-red-200', 'border-blue-200');
                    toast.classList.add('border-green-200');
                } else if (type === 'error') {
                    toast.classList.remove('border-green-200', 'border-blue-200');
                    toast.classList.add('border-red-200');
                } else if (type === 'info') {
                    toast.classList.remove('border-green-200', 'border-red-200');
                    toast.classList.add('border-blue-200');
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
