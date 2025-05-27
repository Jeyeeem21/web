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

// Get clinic details
$stmt = $pdo->query("SELECT * FROM clinic_details ORDER BY created_at DESC LIMIT 1");
$clinic = $stmt->fetch();

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get admin details
try {
    $stmt = $pdo->prepare("SELECT s.name, s.role 
                          FROM staff s 
                          JOIN users u ON s.id = u.staff_id 
                          WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $admin = ['name' => 'Admin', 'role' => 'admin'];
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Log the logout action
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['username'], 'Logout', 'Success', $ip_address]);
    
    // Clear all session variables
    session_unset();
    // Destroy the session
    session_destroy();
    // Redirect to login page
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['clinic_name']); ?> Dashboard</title>
    <!-- Google Fonts: Inter and Poppins -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap">
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
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#ccfbf1',
                            100: '#99f6e4',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e'
                        },
                        secondary: '#475569',
                        neutral: {
                            light: '#f8fafc',
                            dark: '#1e293b'
                        },
                        accent: {
                            100: '#fef3c7',
                            300: '#fbbf24',
                            400: '#f59e0b',
                            500: '#d97706'
                        },
                        success: {
                            DEFAULT: '#10b981',
                            light: '#d1fae5'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'Poppins', 'sans-serif'],
                        heading: ['Poppins', 'sans-serif']
                    },
                    keyframes: {
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        spin: {
                            '0%': { transform: 'rotate(0deg)' },
                            '100%': { transform: 'rotate(360deg)' }
                        },
                        spinSlow: {
                            '0%': { transform: 'rotate(0deg)' },
                            '100%': { transform: 'rotate(360deg)' }
                        },
                        pulseOnce: {
                            '0%, 100%': { opacity: '1' },
                            '50%': { opacity: '0.8' }
                        },
                        scaleHover: {
                            '0%': { transform: 'scale(1)' },
                            '100%': { transform: 'scale(1.05)' }
                        }
                    },
                    animation: {
                        'slide-up': 'slideUp 0.3s ease-out forwards',
                        'fade-in': 'fadeIn 0.3s ease-out forwards',
                        'spin': 'spin 1s linear infinite',
                        'spin-slow': 'spinSlow 2s linear infinite',
                        'pulse-once': 'pulseOnce 0.5s ease-in-out',
                        'scale-hover': 'scaleHover 0.2s ease-in-out forwards'
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
            color: #1e293b;
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
            background: #14b8a6;
            color: white !important;
            border: 1px solid #14b8a6;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f8fafc;
            color: #1e293b !important;
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
        
        @media (max-width: 640px) {
            .main-content {
                margin-left: 0 !important;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                width: 100%;
                padding-top: 4rem; /* Adjusted to account for smaller header */
            }
            .admin-info {
                display: none;
            }
            /* Reduce header font size on mobile */
            header h1 {
                font-size: 0.875rem; /* Reduced from text-lg (1.125rem) to text-sm */
            }
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
                background-color: #f8fafc;
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
                color: #475569;
                width: 40%;
                margin-right: 0.5rem;
            }
            
            /* Style the content of each cell */
            .mobile-card-view tbody td .cell-content {
                width: 60%;
            }
        }

        /* Enhanced hover effect for sidebar (desktop) and mobile footer navigation */
        .nav-link:hover .nav-inner, .mobile-nav-link:hover {
            background-image: linear-gradient(to right, #14b8a6, #fbbf24);
            color: white;
            transform: scale(1.05);
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-link .nav-inner, .mobile-nav-link {
            transition: all 0.2s ease-in-out;
        }

        /* Active state for mobile navigation */
        .mobile-nav-link.active {
            background-image: linear-gradient(to right, #14b8a6, #fbbf24);
            color: white;
        }
    </style>
</head>
<body class="bg-gradient-to-r from-primary-100 to-accent-100 font-sans text-neutral-dark">
    <div class="min-h-screen flex flex-col">
        <!-- Loading Overlay -->
        <div class="fixed inset-0 bg-white z-[9999] flex items-center justify-center transition-opacity duration-400 loading-overlay">
            <div class="w-12 h-12 border-3 border-primary-100 border-t-primary-500 rounded-full animate-spin"></div>
        </div>

        <!-- Header -->
        <header class="bg-white/95 backdrop-blur-md shadow-md fixed w-full top-0 z-50 animate-fade-in">
            <div class="container mx-auto px-6 py-3 flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <img src="<?php echo htmlspecialchars($clinic['logo']); ?>" alt="Clinic Logo" class="h-8 w-8 object-contain">
                    <h1 class="text-lg sm:text-xl font-heading font-bold bg-gradient-to-r from-primary-500 to-accent-300 bg-clip-text text-transparent"><?php echo htmlspecialchars($clinic['clinic_name']); ?></h1>
                </div>
                <!-- Mobile Admin Menu -->
                <div class="md:hidden relative">
                    <button id="mobileAdminMenuButton" class="flex items-center space-x-2 focus:outline-none">
                        <img class="h-8 w-8 rounded-full object-cover" src="https://randomuser.me/api/portraits/men/1.jpg" alt="Admin profile">
                        <span class="text-sm font-heading font-bold text-primary-500"><?php echo htmlspecialchars($admin['name']); ?></span>
                    </button>
                    <div id="mobileAdminMenu" class="hidden absolute right-0 mt-2 w-48 bg-white border border-primary-100 rounded-md shadow-lg z-50">
                        <div class="px-4 py-2 text-sm font-heading font-bold text-primary-500">Dr. Smith</div>
                        <a href="?action=logout" class="block px-4 py-2 text-xs text-primary-500 hover:text-primary-600 hover:bg-primary-50">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex flex-1 flex-col md:flex-row">
            <!-- Sidebar (Desktop) -->
            <aside id="sidebar" class="sidebar hidden md:flex md:w-56 md:flex-col md:fixed md:inset-y-0 md:pt-14 bg-white border-r border-primary-500/20">
                <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                    <!-- Sidebar Toggle Button -->
                    <div class="px-4 mb-2">
                        <button id="sidebarToggle" class="sidebar-toggle text-secondary hover:text-primary-500 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                            </svg>
                        </button>
                    </div>
                    
                    <nav class="mt-2 px-2 space-y-1">
                        <a href="index.php?page=dashboard" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                            <div class="nav-inner flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'dashboard' ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : 'text-secondary'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span class="nav-text font-sans">Dashboard</span>
                            </div>
                        </a>
                        <a href="index.php?page=records" class="nav-link <?php echo $page === 'records' ? 'active' : ''; ?>">
                            <div class="nav-inner flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'records' ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : 'text-secondary'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="nav-text font-sans">Patient</span>
                            </div>
                        </a>
                        <a href="index.php?page=schedule" class="nav-link <?php echo $page === 'schedule' ? 'active' : ''; ?>">
                            <div class="nav-inner flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'schedule' ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : 'text-secondary'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="nav-text font-sans">Schedule</span>
                            </div>
                        </a>
                        <a href="index.php?page=information" class="nav-link <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'active' : ''; ?>">
                            <div class="nav-inner flex items-center px-3 py-2 text-sm rounded-md <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : 'text-secondary'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="nav-text font-sans">Information</span>
                            </div>
                        </a>
                        <a href="index.php?page=settings" class="nav-link <?php echo $page === 'settings' ? 'active' : ''; ?>">
                            <div class="nav-inner flex items-center px-3 py-2 text-sm rounded-md <?php echo $page === 'settings' ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : 'text-secondary'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="nav-text font-sans">Settings</span>
                            </div>
                        </a>
                    </nav>
                </div>
                <div class="border-t border-primary-500/20 p-4 admin-info">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <img class="h-8 w-8 rounded-full object-cover" src="https://randomuser.me/api/portraits/men/1.jpg" alt="Admin profile">
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-heading font-bold text-primary-500 nav-text"><?php echo htmlspecialchars($admin['name']); ?></p>
                            <a href="?action=logout" class="text-xs text-primary-500 hover:text-primary-600 nav-text">Logout</a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main id="mainContent" class="main-content flex-1 md:ml-56 pt-20 px-4 pb-20 md:pb-4 overflow-x-hidden bg-gradient-to-r from-primary-100 to-accent-100">
                <?php include $content_file; ?>
            </main>
        </div>
        
        <!-- Mobile Footer Navigation (replaces sidebar on mobile) -->
        <footer class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-primary-500/20 z-50">
            <div class="grid grid-cols-5 h-14">
                <a href="index.php?page=dashboard" class="mobile-nav-link flex flex-col items-center justify-center <?php echo $page === 'dashboard' ? 'active text-white' : 'text-secondary'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-xs mt-1 font-sans">Dashboard</span>
                </a>
                <a href="index.php?page=records" class="mobile-nav-link flex flex-col items-center justify-center <?php echo $page === 'records' ? 'active text-white' : 'text-secondary'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-xs mt-1 font-sans">Patient</span>
                </a>
                <a href="index.php?page=schedule" class="mobile-nav-link flex flex-col items-center justify-center <?php echo $page === 'schedule' ? 'active text-white' : 'text-secondary'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs mt-1 font-sans">Schedule</span>
                </a>
                <a href="index.php?page=information" class="mobile-nav-link flex flex-col items-center justify-center <?php echo in_array($page, ['information', 'home_management', 'about_management']) ? 'active text-white' : 'text-secondary'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs mt-1 font-sans">Info</span>
                </a>
                <a href="index.php?page=settings" class="mobile-nav-link flex flex-col items-center justify-center <?php echo $page === 'settings' ? 'active text-white' : 'text-secondary'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-xs mt-1 font-sans">Settings</span>
                </a>
            </div>
        </footer>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-20 right-4 bg-white border border-success-light text-neutral-dark px-4 py-2 rounded-md shadow-sm transform transition-transform duration-300 translate-y-full opacity-0 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-success" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span id="toastMessage" class="text-sm font-sans">Operation successful!</span>
    </div>

    <!-- JavaScript for Mobile Menu, Sidebar Toggle, and Animations -->
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
                    toast.classList.remove('border-accent-100', 'border-blue-200');
                    toast.classList.add('border-success-light');
                } else if (type === 'error') {
                    toast.classList.remove('border-success-light', 'border-blue-200');
                    toast.classList.add('border-accent-100');
                } else if (type === 'info') {
                    toast.classList.remove('border-success-light', 'border-accent-100');
                    toast.classList.add('border-blue-200');
                }
                
                // Show toast
                toast.classList.remove('translate-y-full', 'opacity-0');
                
                // Hide toast after 3 seconds
                setTimeout(function() {
                    toast.classList.add('translate-y-full', 'opacity-0');
                }, 3000);
            };

            // Remove loading overlay
            setTimeout(() => {
                const overlay = document.querySelector('.loading-overlay');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                setTimeout(() => overlay.remove(), 400);
            }, 600);

            // Scroll Animation
            function handleScrollAnimation() {
                const elements = document.querySelectorAll('[class*="animate-"]');
                elements.forEach(element => {
                    const rect = element.getBoundingClientRect();
                    if (rect.top < window.innerHeight - 100 && rect.bottom > 0) {
                        element.classList.add('animate-slide-up', 'animate-pulse-once');
                    }
                });
            }

            // Scroll event
            window.addEventListener('scroll', () => {
                handleScrollAnimation();
            });
            handleScrollAnimation();
        });
    </script>
</body>
</html>
<?php
// Flush the output buffer
ob_end_flush();
?>