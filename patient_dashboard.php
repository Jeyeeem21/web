<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

// Get patient information
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$_SESSION['patient_id']]);
$patient = $stmt->fetch();

// Get clinic details
$stmt = $pdo->query("SELECT * FROM clinic_details LIMIT 1");
$clinic = $stmt->fetch();

// Pagination settings
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total number of appointments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
$stmt->execute([$_SESSION['patient_id']]);
$total_appointments = $stmt->fetchColumn();
$total_pages = ceil($total_appointments / $items_per_page);

// Get paginated appointments
$stmt = $pdo->prepare("
    SELECT a.*, s.name as doctor_name, dp.doctor_position as doctor_position, sp.service_name
    FROM appointments a
    JOIN staff s ON a.staff_id = s.id
    LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id
    JOIN services sp ON a.service_id = sp.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC
    LIMIT ?, ?
");
$stmt->bindValue(1, $_SESSION['patient_id'], PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->bindValue(3, $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$appointments = $stmt->fetchAll();

// Get all active services
try {
    $stmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY service_name ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $services = [];
}

// Get all active doctors
try {
    $stmt = $pdo->query("SELECT s.*, dp.doctor_position 
                         FROM staff s 
                         JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                         JOIN doctor d ON s.id = d.doctor_id
                         WHERE s.status = 1 AND s.role = 'doctor'
                         ORDER BY s.name ASC");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $doctors = [];
}

// Prepare date range and chart data
$period = $_GET['period'] ?? 'daily'; // Default to daily
$date = $_GET['date'] ?? date('Y-m-d', strtotime('2025-05-25')); // Current date: May 25, 2025

// Prepare date range
switch ($period) {
    case 'daily':
        $startDate = date('Y-m-01', strtotime($date));
        $endDate = date('Y-m-t', strtotime($date));
        $dateFormat = '%Y-%m-%d';
        $interval = 'DAY';
        break;
    case 'monthly':
        $startDate = date('Y-01-01', strtotime($date));
        $endDate = date('Y-12-31', strtotime($date));
        $dateFormat = '%Y-%m';
        $interval = 'MONTH';
        break;
    case 'yearly':
        $startDate = '2024-01-01';
        $endDate = date('Y-12-31', strtotime($date));
        $dateFormat = '%Y';
        $interval = 'YEAR';
        break;
    default:
        $startDate = date('Y-m-01', strtotime($date));
        $endDate = date('Y-m-t', strtotime($date));
        $dateFormat = '%Y-%m-%d';
        $interval = 'DAY';
}

// Get appointment statistics for summary cards
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
        SUM(CASE WHEN status = 'Scheduled' OR status = 'Pending' THEN 1 ELSE 0 END) as scheduled_appointments
    FROM appointments 
    WHERE patient_id = ? 
    AND appointment_date BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['patient_id'], $startDate, $endDate]);
$statistics = $stmt->fetch();

// Get chart data (completed and cancelled appointments over time)
$chartData = ['labels' => [], 'completed' => [], 'cancelled' => []];

$sql = "SELECT
            DATE_FORMAT(appointment_date, '$dateFormat') as time_period,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM appointments
        WHERE patient_id = ? AND appointment_date BETWEEN ? AND ?
        GROUP BY time_period
        ORDER BY time_period";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['patient_id'], $startDate, $endDate]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Populate chart data
$periodData = [];
foreach ($results as $row) {
    $periodData[$row['time_period']] = ['completed' => $row['completed_count'], 'cancelled' => $row['cancelled_count']];
}

$current = strtotime($startDate);
$end = strtotime($endDate);

while ($current <= $end) {
    $periodValue = date(str_replace('%', '', $dateFormat), $current);
    
    if ($interval === 'DAY') {
        $formattedLabel = date('j', $current);
    } elseif ($interval === 'MONTH') {
        $formattedLabel = date('M', $current);
    } elseif ($interval === 'YEAR') {
        $formattedLabel = date('Y', $current);
    }
    
    $chartData['labels'][] = $formattedLabel;
    $chartData['completed'][] = $periodData[$periodValue]['completed'] ?? 0;
    $chartData['cancelled'][] = $periodData[$periodValue]['cancelled'] ?? 0;

    if ($interval === 'DAY') {
        $current = strtotime('+1 day', $current);
    } elseif ($interval === 'MONTH') {
        $current = strtotime('+1 month', $current);
    } elseif ($interval === 'YEAR') {
        $current = strtotime('+1 year', $current);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['clinic_name']); ?> - Patient Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#ccfbf1',
                            100: '#99f6e4',
                            300: '#4eead3',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e'
                        },
                        accent: {
                            100: '#fef3c7',
                            300: '#f59e0b',
                            500: '#d97706'
                        },
                        secondary: '#475569',
                        neutral: {
                            light: '#f8fafc',
                            dark: '#1e293b'
                        },
                        success: {
                            light: '#d1fae5',
                            DEFAULT: '#10b981',
                            dark: '#065f46'
                        },
                        danger: {
                            light: '#fee2e2',
                            DEFAULT: '#ef4444',
                            dark: '#991b1b'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'Poppins', 'sans-serif'],
                        heading: ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <header class="bg-white shadow-sm border-b border-gray-100">
        <div class="container mx-auto px-4 sm:px-6 py-4">
            <!-- Main header flex container -->
            <div class="flex justify-between items-center">
                <!-- Left side: Logo and Clinic Name -->
                <div class="flex items-center space-x-3">
                    <div class="relative w-10 h-10 rounded-lg overflow-hidden bg-gradient-to-br from-primary-50 to-accent-100 shadow-sm">
                        <?php if (!empty($clinic['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($clinic['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($clinic['clinic_name']); ?>" 
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-hospital text-primary-500 text-2xl absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-heading font-bold bg-gradient-to-r from-primary-500 to-accent-300 bg-clip-text text-transparent">
                            <?php echo htmlspecialchars($clinic['clinic_name']); ?>
                        </h1>
                        <p class="text-xs text-secondary">Welcome, <?php echo htmlspecialchars($patient['name']); ?></p>
                    </div>
                </div>

                <!-- Right side: Navigation and Profile -->
                <div class="flex items-center space-x-4">
                    <!-- Desktop Navigation -->
                    <nav class="hidden md:flex items-center space-x-6">
                        <a href="#statistics" class="nav-link text-neutral-dark hover:text-primary-500 transition-all duration-200 flex items-center space-x-2">
                            <i class="fas fa-chart-line text-primary-500"></i>
                            <span class="text-sm font-medium">Statistics</span>
                        </a>
                        <a href="#appointments" class="nav-link text-neutral-dark hover:text-primary-500 transition-all duration-200 flex items-center space-x-2">
                            <i class="fas fa-calendar-check text-primary-500"></i>
                            <span class="text-sm font-medium">Appointments</span>
                        </a>
                    </nav>

                    <!-- Patient Menu -->
                    <div class="relative">
                        <button id="patientMenuTrigger" class="flex items-center space-x-3 bg-gradient-to-r from-primary-50 to-accent-50 px-4 py-2 rounded-lg hover:shadow-md transition-all duration-200">
                            <div class="w-8 h-8 rounded-full overflow-hidden border-2 border-white shadow-sm">
                                <img src="<?php echo htmlspecialchars($patient['photo']); ?>" 
                                     alt="Profile" 
                                     class="w-full h-full object-cover">
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-medium text-neutral-dark"><?php echo htmlspecialchars($patient['name']); ?></p>
                                <p class="text-xs text-secondary">Patient</p>
                            </div>
                            <i class="fas fa-chevron-down text-primary-500 text-xs"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="patientMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-primary-100 hidden z-50">
                            <div class="py-1">
                                <a href="#" onclick="openSettingsModal()" class="flex items-center px-4 py-2 text-sm text-neutral-dark hover:bg-primary-50 transition-colors duration-200">
                                    <i class="fas fa-cog text-primary-500 mr-2"></i>
                                    Settings
                                </a>
                                <div class="border-t border-primary-100 my-1"></div>
                                <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-danger hover:bg-danger-light transition-colors duration-200">
                                    <i class="fas fa-sign-out-alt text-danger mr-2"></i>
                        Logout
                    </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-primary-100 z-40">
        <nav class="flex justify-around items-center h-16">
            <a href="#statistics" class="mobile-nav-link flex flex-col items-center justify-center w-full h-full text-neutral-dark hover:text-primary-500 transition-all duration-200">
                <i class="fas fa-chart-line text-lg"></i>
                <span class="text-xs mt-1">Statistics</span>
                <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-primary-500 to-accent-300"></span>
            </a>
            <a href="#appointments" class="mobile-nav-link flex flex-col items-center justify-center w-full h-full text-neutral-dark hover:text-primary-500 transition-all duration-200">
                <i class="fas fa-calendar-check text-lg"></i>
                <span class="text-xs mt-1">Appointments</span>
                <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-primary-500 to-accent-300"></span>
            </a>
        </nav>
    </div>

    <main class="container mx-auto px-6 py-8">
        <!-- Add IDs to the sections for navigation -->
        <div class="grid grid-cols-1 md:grid-cols-4">
            <!-- Profile Card -->
            <div class="md:col-span-1">
                <div class="bg-gradient-to-br from-primary-50 to-accent-100 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-4 h-full flex flex-col justify-between">
                    <!-- Desktop View -->
                    <div class="hidden md:block text-center">
                        <img src="<?php echo htmlspecialchars($patient['photo']); ?>" 
                             alt="Profile Photo" 
                             class="w-24 h-24 rounded-full mx-auto object-cover border-4 border-white shadow-md">
                        <h2 class="text-lg font-bold mt-3 text-neutral-dark"><?php echo htmlspecialchars($patient['name']); ?></h2>
                        <p class="text-secondary text-xs">Patient ID: <?php echo htmlspecialchars($patient['id']); ?></p>
                        <button onclick="openEditProfileModal()" class="mt-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white px-3 py-1 rounded-lg text-xs hover:scale-105 transition-all duration-200">
                            <i class="fas fa-edit mr-1"></i> Edit Profile
                        </button>
                    </div>

                    <!-- Mobile View -->
                    <div class="md:hidden flex items-center space-x-4 mb-4">
                        <img src="<?php echo htmlspecialchars($patient['photo']); ?>" 
                             alt="Profile Photo" 
                             class="w-16 h-16 rounded-full object-cover border-4 border-white shadow-md">
                        <div>
                            <h2 class="text-base font-bold text-neutral-dark"><?php echo htmlspecialchars($patient['name']); ?></h2>
                            <p class="text-secondary text-xs">Patient ID: <?php echo htmlspecialchars($patient['id']); ?></p>
                            <button onclick="openEditProfileModal()" class="mt-1 bg-gradient-to-r from-primary-500 to-accent-300 text-white px-2 py-0.5 rounded-lg text-xs hover:scale-105 transition-all duration-200">
                                <i class="fas fa-edit mr-1"></i> Edit Profile
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Email</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($patient['email']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Phone</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($patient['phone']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Address</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($patient['address']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-birthday-cake text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Age</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($patient['age']); ?> years old</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-venus-mars text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Gender</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($patient['gender']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div id="statistics" class="md:col-span-3">
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6 h-full">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-base font-medium text-neutral-dark">Patient Statistics</h2>
                        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center relative">
                            <div class="inline-flex rounded-lg shadow-sm" role="group">
                                <button onclick="updateStatistics('daily')" class="period-btn px-4 py-2 text-sm font-medium text-neutral-dark bg-white border border-primary-100 rounded-l-lg hover:bg-gradient-to-r hover:from-primary-500 hover:to-accent-300 hover:text-white hover:scale-105 focus:z-10 focus:ring-2 focus:ring-primary-500 transition-all duration-200 <?php echo $period === 'daily' ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : ''; ?>">
                                    Daily
                                </button>
                                <button onclick="updateStatistics('monthly')" class="period-btn px-4 py-2 text-sm font-medium text-neutral-dark bg-white border-t border-b border-primary-100 hover:bg-gradient-to-r hover:from-primary-500 hover:to-accent-300 hover:text-white hover:scale-105 focus:z-10 focus:ring-2 focus:ring-primary-500 transition-all duration-200 <?php echo $period === 'monthly' ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : ''; ?>">
                                    Monthly
                                </button>
                                <button onclick="updateStatistics('yearly')" class="period-btn px-4 py-2 text-sm font-medium text-neutral-dark bg-white border border-primary-100 rounded-r-lg hover:bg-gradient-to-r hover:from-primary-500 hover:to-accent-300 hover:text-white hover:scale-105 focus:z-10 focus:ring-2 focus:ring-primary-500 transition-all duration-200 <?php echo $period === 'yearly' ? 'bg-gradient-to-r from-primary-500 to-accent-300 text-white' : ''; ?>">
                                    Yearly
                                </button>
                            </div>
                            <!-- Indicator for active period -->
                            <div class="absolute -top-2 left-0 text-xs font-medium text-primary-500">
                                <?php echo ucfirst($period); ?>
                            </div>
                            <div id="dailyDateSelector" class="date-selector">
                                <input type="date" id="dailyDate" value="<?php echo $date; ?>" class="block w-full rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm text-neutral-dark focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-all">
                            </div>
                            <div id="monthlyDateSelector" class="date-selector hidden">
                                <input type="month" id="monthlyDate" value="<?php echo substr($date, 0, 7); ?>" class="block w-full rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm text-neutral-dark focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-all">
                            </div>
                            <div id="yearlyDateSelector" class="date-selector hidden">
                                <select id="yearlyDate" class="block w-full rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm text-neutral-dark focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-all">
                                    <?php
                                    $currentYear = date('Y', strtotime('2025-05-25')); // Current year: 2025
                                    $startYear = $currentYear - 10;
                                    $endYear = $currentYear + 10;
                                    for ($year = $endYear; $year >= $startYear; $year--) {
                                        $selected = ($year == substr($date, 0, 4)) ? 'selected' : '';
                                        echo "<option value='$year' $selected>$year</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                            <h3 class="text-sm font-medium text-neutral-dark">Total Appointments</h3>
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['total_appointments']; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                            <h3 class="text-sm font-medium text-neutral-dark">Completed</h3>
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['completed_appointments']; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                            <h3 class="text-sm font-medium text-neutral-dark">Scheduled</h3>
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['scheduled_appointments']; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                            <h3 class="text-sm font-medium text-neutral-dark">Cancelled</h3>
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['cancelled_appointments']; ?></p>
                        </div>
                    </div>
                    <div class="h-64 md:h-64 sm:h-96">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Appointments -->
            <div id="appointments" class="md:col-span-4 mt-6">
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-base font-medium text-neutral-dark">My Appointments</h2>
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <input type="text" id="appointmentSearch" placeholder="Search appointments..." 
                                       class="block w-full rounded-lg border border-primary-100 bg-white px-4 py-2 text-sm text-neutral-dark focus:border-primary-500 focus:ring-2 focus:ring-primary-500">
                                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-secondary"></i>
                            </div>
                        <button onclick="openAppointmentModal()" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm hover:scale-105 transition-all duration-200">
                                <i class="fas fa-plus mr-1"></i> Book New Appointment
                        </button>
                        </div>
                    </div>
                    <!-- Desktop View: Table -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-primary-100">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Doctor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Position</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Service</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-primary-100">
                                <?php if (empty($appointments)): ?>
                                    <tr><td colspan="7" class="text-secondary text-center py-4">No appointments found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr class="hover:bg-primary-50 transition-all appointment-row" 
                                            data-doctor="<?php echo htmlspecialchars($appointment['doctor_name']); ?>"
                                            data-service="<?php echo htmlspecialchars($appointment['service_name']); ?>"
                                            data-status="<?php echo htmlspecialchars($appointment['status']); ?>">
                                            <td class="px-4 py-3 text-neutral-dark text-sm font-medium">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <?php echo htmlspecialchars($appointment['doctor_position'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <?php echo htmlspecialchars($appointment['service_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <i class="far fa-calendar-alt mr-1"></i><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <i class="far fa-clock mr-1"></i><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                                    <?php
                                                    switch(strtolower($appointment['status'])) {
                                                        case 'pending':
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'scheduled':
                                                            echo 'bg-accent-100 text-accent-500';
                                                            break;
                                                        case 'completed':
                                                            echo 'bg-success-light text-success';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                        default:
                                                            echo 'bg-gray-100 text-gray-800';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <div class="flex items-center space-x-2">
                                                    <?php if ($appointment['status'] !== 'Completed' && $appointment['status'] !== 'Cancelled'): ?>
                                                        <button onclick="openRescheduleModal(<?php echo $appointment['id']; ?>)" 
                                                                class="inline-flex items-center px-3 py-1.5 border border-primary-100 text-xs font-medium rounded-lg text-primary-500 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                                                            <i class="fas fa-calendar-alt mr-1.5"></i>
                                                            Re-schedule
                                                        </button>
                                                        <button onclick="updateAppointmentStatus(<?php echo $appointment['id']; ?>, 'Cancelled')" 
                                                                class="inline-flex items-center px-3 py-1.5 border border-red-100 text-xs font-medium rounded-lg text-red-500 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                            <i class="fas fa-times mr-1.5"></i>
                                                            Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="flex items-center justify-between border-t border-primary-100 bg-white px-4 py-3 sm:px-6 mt-4">
                            <div class="flex flex-1 justify-between sm:hidden">
                                <?php if ($current_page > 1): ?>
                                    <a href="?page=<?php echo $current_page - 1; ?>#appointments" class="relative inline-flex items-center rounded-md border border-primary-100 bg-white px-4 py-2 text-sm font-medium text-neutral-dark hover:bg-primary-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?page=<?php echo $current_page + 1; ?>#appointments" class="relative ml-3 inline-flex items-center rounded-md border border-primary-100 bg-white px-4 py-2 text-sm font-medium text-neutral-dark hover:bg-primary-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                    </div>
                            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-secondary">
                                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                        <span class="font-medium"><?php echo min($offset + $items_per_page, $total_appointments); ?></span> of 
                                        <span class="font-medium"><?php echo $total_appointments; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                        <?php if ($current_page > 1): ?>
                                            <a href="?page=<?php echo $current_page - 1; ?>#appointments" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-neutral-dark ring-1 ring-inset ring-primary-100 hover:bg-primary-50 focus:z-20 focus:outline-offset-0">
                                                <span class="sr-only">Previous</span>
                                                <i class="fas fa-chevron-left text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium bg-primary-500 text-white ring-1 ring-inset ring-primary-100 focus:z-20 focus:outline-offset-0">
                                            <?php echo $current_page; ?>
                                        </span>
                                        
                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="?page=<?php echo $current_page + 1; ?>#appointments" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-neutral-dark ring-1 ring-inset ring-primary-100 hover:bg-primary-50 focus:z-20 focus:outline-offset-0">
                                                <span class="sr-only">Next</span>
                                                <i class="fas fa-chevron-right text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mobile View: Cards -->
                    <div class="block sm:hidden space-y-4">
                        <?php if (empty($appointments)): ?>
                            <div class="text-secondary text-center py-4">No appointments found.</div>
                        <?php else: ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-sm font-medium text-neutral-dark">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                                            <p class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['doctor_position'] ?? 'N/A'); ?></p>
                                            <p class="text-xs text-secondary mt-1"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium
                                                    <?php
                                                    switch(strtolower($appointment['status'])) {
                                                        case 'pending':
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'scheduled':
                                                            echo 'bg-accent-100 text-accent-500';
                                                            break;
                                                        case 'completed':
                                                            echo 'bg-success-light text-success';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                        default:
                                                            echo 'bg-gray-100 text-gray-800';
                                                    }
                                                    ?>">
                                            <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="mt-2 space-y-1">
                                        <p class="text-xs text-secondary">
                                            <i class="far fa-calendar-alt mr-1"></i><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                        </p>
                                        <p class="text-xs text-secondary">
                                            <i class="far fa-clock mr-1"></i><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </p>
                                    </div>
                                    <?php if ($appointment['status'] !== 'Completed' && $appointment['status'] !== 'Cancelled'): ?>
                                        <div class="mt-3 flex space-x-2">
                                            <button onclick="openRescheduleModal(<?php echo $appointment['id']; ?>)" 
                                                    class="flex-1 inline-flex items-center justify-center px-3 py-1.5 border border-primary-100 text-xs font-medium rounded-lg text-primary-500 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                                                <i class="fas fa-calendar-alt mr-1.5"></i>
                                                Re-schedule
                                            </button>
                                            <button onclick="updateAppointmentStatus(<?php echo $appointment['id']; ?>, 'Cancelled')" 
                                                    class="flex-1 inline-flex items-center justify-center px-3 py-1.5 border border-red-100 text-xs font-medium rounded-lg text-red-500 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                <i class="fas fa-times mr-1.5"></i>
                                                Cancel
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Mobile Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="flex items-center justify-between mt-4">
                                <div class="flex-1 flex justify-between">
                                    <?php if ($current_page > 1): ?>
                                        <a href="?page=<?php echo $current_page - 1; ?>#appointments" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-neutral-dark bg-white border border-primary-100 rounded-md hover:bg-primary-50">
                                            <i class="fas fa-chevron-left mr-1 text-xs"></i>
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium bg-primary-500 text-white border border-primary-100 rounded-md">
                                        <?php echo $current_page; ?>
                                    </span>
                                    <?php if ($current_page < $total_pages): ?>
                                        <a href="?page=<?php echo $current_page + 1; ?>#appointments" class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-neutral-dark bg-white border border-primary-100 rounded-md hover:bg-primary-50">
                                            Next
                                            <i class="fas fa-chevron-right ml-1 text-xs"></i>
                                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                            <div class="text-center text-sm text-secondary mt-2">
                                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-xl bg-gradient-to-br from-primary-50 to-accent-100">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-neutral-dark">Edit Profile</h3>
                <form id="editProfileForm" method="POST" action="update_profile.php" class="mt-4 space-y-4" enctype="multipart/form-data">
                    <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($patient['name']); ?>" required class="mt-1 block w-full rounded-md border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Photo</label>
                        <input type="file" name="photo" accept="image/*" class="mt-1 block w-full text-sm text-neutral-dark file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-100 file:text-primary-500 hover:file:bg-primary-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>" required class="mt-1 block w-full rounded-md border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>" required class="mt-1 block w-full rounded-md border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Address</label>
                        <textarea name="address" required class="mt-1 block w-full rounded-md border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-primary-500"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditProfileModal()" class="px-4 py-2 bg-white text-neutral-dark rounded-md hover:bg-gray-100 border border-primary-100">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-md hover:scale-105 transition-all duration-200">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Appointment Modal -->
    <div id="appointmentModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-neutral-dark">Schedule New Appointment</h3>
                <form id="appointmentForm" method="POST" action="schedule.php" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="add_appointment">
                    <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Patient</label>
                        <p class="mt-1 text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['name']); ?></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Service</label>
                        <select name="service_id" id="serviceSelect" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                            <option value="">Select Service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" data-doctor="<?php echo htmlspecialchars($service['kind_of_doctor']); ?>">
                                    <?php echo htmlspecialchars($service['service_name'] . ' (' . $service['time'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Doctor</label>
                        <select name="staff_id" id="doctorSelect" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" data-position="<?php echo htmlspecialchars($doctor['doctor_position']); ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Date</label>
                        <input type="date" name="appointment_date" id="appointmentDate" required 
                               min="<?php echo date('Y-m-d', strtotime('2025-05-25')); ?>" 
                               value="<?php echo date('Y-m-d', strtotime('2025-05-25')); ?>" 
                               class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Time</label>
                        <select name="appointment_time" id="timeSelect" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                            <option value="">Select Time</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Remarks</label>
                        <textarea name="remarks" id="remarks" class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAppointmentModal()" class="px-4 py-2 bg-primary-50 text-primary-500 rounded-lg text-sm hover:bg-primary-100 transition-all duration-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg text-sm hover:scale-105 transition-all duration-200">Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-4xl shadow-lg rounded-xl bg-white border-primary-100">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-medium text-neutral-dark">Settings</h3>
                <button onclick="closeSettingsModal()" class="text-neutral-dark hover:text-primary-500 transition-colors duration-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Profile Information -->
                <div class="space-y-4">
                    <h4 class="text-lg font-medium text-neutral-dark mb-4">Profile Information</h4>
                    <div class="flex items-center space-x-4">
                        <img src="<?php echo htmlspecialchars($patient['photo']); ?>" 
                             alt="Profile Photo" 
                             class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-md">
                        <div>
                            <h5 class="text-base font-medium text-neutral-dark"><?php echo htmlspecialchars($patient['name']); ?></h5>
                            <p class="text-sm text-secondary">Patient ID: <?php echo htmlspecialchars($patient['id']); ?></p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Email</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['email']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Phone</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['phone']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Address</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['address']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Settings -->
                <div class="space-y-4">
                    <h4 class="text-lg font-medium text-neutral-dark mb-4">Account Settings</h4>
                    <form id="settingsForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-neutral-dark">Change Password</label>
                            <div class="mt-2 space-y-3">
                                <div class="relative">
                                    <input type="password" name="current_password" id="currentPassword" placeholder="Current Password" 
                                           class="block w-full rounded-lg border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3 pr-10">
                                    <button type="button" id="toggleCurrentPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-primary-500 hover:text-primary-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="relative">
                                    <input type="password" name="new_password" id="newPassword" placeholder="New Password" 
                                           class="block w-full rounded-lg border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3 pr-10">
                                    <button type="button" id="toggleNewPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-primary-500 hover:text-primary-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="relative">
                                    <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm New Password" 
                                           class="block w-full rounded-lg border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3 pr-10">
                                    <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-primary-500 hover:text-primary-700">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeSettingsModal()" class="px-4 py-2 bg-neutral-light text-neutral-dark rounded-lg hover:bg-primary-50 transition-colors duration-200">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg hover:scale-105 transition-all duration-200">
                                <i class="fas fa-save mr-1.5"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="notification" class="fixed top-4 right-4 transform transition-transform duration-300 translate-x-full">
        <div class="bg-white rounded-lg shadow-lg p-4 max-w-sm">
            <div class="flex items-center">
                <div id="notificationIcon" class="flex-shrink-0 mr-3">
                    <i class="fas fa-check-circle text-success text-xl"></i>
                </div>
                <div class="flex-1">
                    <p id="notificationMessage" class="text-sm font-medium text-neutral-dark"></p>
                </div>
                <button onclick="hideNotification()" class="ml-4 text-neutral-dark hover:text-primary-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" role="dialog" aria-labelledby="rescheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content bg-gradient-to-br from-primary-50 to-accent-100 border border-primary-100 rounded-xl shadow-lg">
                <div class="modal-header border-b border-primary-100 px-6 py-4">
                    <h5 class="modal-title text-lg font-medium text-neutral-dark" id="rescheduleModalLabel">Reschedule Appointment</h5>
                    <button type="button" class="close text-neutral-dark hover:text-primary-500 transition-colors duration-200" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body px-6 py-4">
                    <form id="rescheduleForm" class="space-y-4">
                        <input type="hidden" id="rescheduleAppointmentId" name="appointment_id">
                        <input type="hidden" id="reschedulePatientId" name="patient_id">
                        <input type="hidden" id="rescheduleDoctorId" name="staff_id">
                        <input type="hidden" id="rescheduleServiceId" name="service_id">
                        
                        <div>
                            <label for="rescheduleDate" class="block text-sm font-medium text-neutral-dark mb-1">Date</label>
                            <input type="date" class="form-control block w-full rounded-lg border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3" 
                                   id="rescheduleDate" name="appointment_date" required>
                        </div>
                        
                        <div>
                            <label for="rescheduleTime" class="block text-sm font-medium text-neutral-dark mb-1">Time</label>
                            <select class="form-control block w-full rounded-lg border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3" 
                                    id="rescheduleTime" name="appointment_time" required>
                                <option value="">Select a time slot</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="rescheduleRemarks" class="block text-sm font-medium text-neutral-dark mb-1">Remarks (Optional)</label>
                            <textarea class="form-control block w-full rounded-lg border-primary-100 bg-white shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3" 
                                      id="rescheduleRemarks" name="remarks" rows="3"></textarea>
                        </div>
                        
                        <div class="modal-footer border-t border-primary-100 px-6 py-4 mt-6 flex justify-end space-x-3">
                            <button type="button" class="px-4 py-2 bg-white text-neutral-dark rounded-lg hover:bg-primary-50 border border-primary-100 transition-all duration-200" 
                                    data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg hover:scale-105 transition-all duration-200">
                                Reschedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Server-provided PHT date and time
        const serverPHTDate = '<?php echo date('Y-m-d', strtotime('2025-05-25')); ?>';
        const serverPHTTime = '<?php echo date('H:i:s', strtotime('2025-05-25 03:20:00')); ?>';

        // Chart Options
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'center',
                    labels: {
                        color: '#1e293b',
                        font: {
                            size: 12,
                            family: "'Inter', sans-serif",
                            weight: '500'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        boxWidth: 6,
                        boxHeight: 6
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.98)',
                    titleColor: '#1e293b',
                    bodyColor: '#475569',
                    borderColor: '#ccfbf1',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#475569',
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        padding: 10,
                        maxRotation: 0
                    },
                    border: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f3f4f6',
                        drawBorder: false,
                        lineWidth: 1
                    },
                    ticks: {
                        color: '#475569',
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        padding: 10,
                        callback: function(value) {
                            return Math.round(value);
                        }
                    },
                    border: {
                        display: false
                    }
                }
            }
        };

        // Initialize Chart
        const ctx = document.getElementById('appointmentsChart').getContext('2d');
        const appointmentsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartData['labels']); ?>,
                datasets: [
                    {
                        label: 'Completed Appointments',
                        data: <?php echo json_encode($chartData['completed']); ?>,
                        borderColor: '#14b8a6',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#14b8a6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Cancelled Appointments',
                        data: <?php echo json_encode($chartData['cancelled']); ?>,
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.1)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#d97706',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: chartOptions
        });

        // Update statistics based on period
        function updateStatistics(period) {
            let date;
            if (period === 'daily') {
                date = document.getElementById('dailyDate').value || serverPHTDate;
            } else if (period === 'monthly') {
                date = document.getElementById('monthlyDate').value || serverPHTDate.slice(0, 7);
            } else if (period === 'yearly') {
                date = document.getElementById('yearlyDate').value || serverPHTDate.slice(0, 4);
            }

            window.location.href = `patient_dashboard.php?period=${period}&date=${date}`;
        }

        // Toggle Date Selectors
        document.addEventListener('DOMContentLoaded', function() {
            const period = '<?php echo $period; ?>';
            const dailySelector = document.getElementById('dailyDateSelector');
            const monthlySelector = document.getElementById('monthlyDateSelector');
            const yearlySelector = document.getElementById('yearlyDateSelector');

            // Show the correct date selector based on period
            if (period === 'daily') {
                dailySelector.classList.remove('hidden');
                monthlySelector.classList.add('hidden');
                yearlySelector.classList.add('hidden');
            } else if (period === 'monthly') {
                dailySelector.classList.add('hidden');
                monthlySelector.classList.remove('hidden');
                yearlySelector.classList.add('hidden');
            } else if (period === 'yearly') {
                dailySelector.classList.add('hidden');
                monthlySelector.classList.add('hidden');
                yearlySelector.classList.remove('hidden');
            }

            // Add event listeners to period buttons
            document.querySelectorAll('.period-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const period = this.textContent.toLowerCase();
                    if (period === 'daily') {
                        dailySelector.classList.remove('hidden');
                        monthlySelector.classList.add('hidden');
                        yearlySelector.classList.add('hidden');
                    } else if (period === 'monthly') {
                        dailySelector.classList.add('hidden');
                        monthlySelector.classList.remove('hidden');
                        yearlySelector.classList.add('hidden');
                    } else if (period === 'yearly') {
                        dailySelector.classList.add('hidden');
                        monthlySelector.classList.add('hidden');
                        yearlySelector.classList.remove('hidden');
                    }
                });
            });

            // Add event listeners to date inputs
            document.getElementById('dailyDate').addEventListener('change', function() {
                updateStatistics('daily');
            });
            document.getElementById('monthlyDate').addEventListener('change', function() {
                updateStatistics('monthly');
            });
            document.getElementById('yearlyDate').addEventListener('change', function() {
                updateStatistics('yearly');
            });

            // Initial setup for period buttons active state
            document.querySelectorAll('.period-btn').forEach(button => {
                if (button.textContent.toLowerCase() === period) {
                    button.classList.add('bg-gradient-to-r', 'from-primary-500', 'to-accent-300', 'text-white');
                    button.classList.remove('bg-white', 'text-neutral-dark');
                } else {
                    button.classList.remove('bg-gradient-to-r', 'from-primary-500', 'to-accent-300', 'text-white');
                    button.classList.add('bg-white', 'text-neutral-dark');
                }
            });

            // Patient Menu Toggle
            const patientMenuTrigger = document.getElementById('patientMenuTrigger');
            const patientMenu = document.getElementById('patientMenu');

            if (patientMenuTrigger && patientMenu) {
                patientMenuTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    patientMenu.classList.toggle('hidden');
                    patientMenu.classList.toggle('show');
                });

                // Close the dropdown if the user clicks outside of it
                document.addEventListener('click', function(event) {
                    if (!patientMenuTrigger.contains(event.target) && !patientMenu.contains(event.target)) {
                        patientMenu.classList.add('hidden');
                        patientMenu.classList.remove('show');
                    }
                });
            }

            // Navigation Active State
            function updateActiveNavLink() {
                const sections = document.querySelectorAll('section[id]');
                const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
                
                let currentSection = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (window.scrollY >= (sectionTop - sectionHeight / 3)) {
                        currentSection = section.getAttribute('id');
                    }
                });
                
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href').substring(1) === currentSection) {
                        link.classList.add('active');
                    }
                });
            }

            window.addEventListener('scroll', updateActiveNavLink);
            window.addEventListener('load', updateActiveNavLink);
        });

        // Profile Modal Functions
        function openEditProfileModal() {
            document.getElementById('editProfileModal').classList.remove('hidden');
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').classList.add('hidden');
        }

        // Settings Modal Functions
        function openSettingsModal() {
            document.getElementById('settingsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeSettingsModal() {
            document.getElementById('settingsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Password toggle functions
        function togglePasswordVisibility(inputId, buttonId) {
            const input = document.getElementById(inputId);
            const button = document.getElementById(buttonId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Add event listeners for password toggles
        document.getElementById('toggleCurrentPassword').addEventListener('click', () => togglePasswordVisibility('currentPassword', 'toggleCurrentPassword'));
        document.getElementById('toggleNewPassword').addEventListener('click', () => togglePasswordVisibility('newPassword', 'toggleNewPassword'));
        document.getElementById('toggleConfirmPassword').addEventListener('click', () => togglePasswordVisibility('confirmPassword', 'toggleConfirmPassword'));

        // Notification functions
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notificationMessage');
            const notificationIcon = document.getElementById('notificationIcon');
            
            notificationMessage.textContent = message;
            
            // Update icon based on type
            const icon = notificationIcon.querySelector('i');
            icon.className = '';
            if (type === 'success') {
                icon.className = 'fas fa-check-circle text-success text-xl';
            } else {
                icon.className = 'fas fa-exclamation-circle text-danger text-xl';
            }
            
            // Show notification
            notification.classList.remove('translate-x-full');
            
            // Hide after 3 seconds
            setTimeout(hideNotification, 3000);
        }

        function hideNotification() {
            const notification = document.getElementById('notification');
            notification.classList.add('translate-x-full');
        }

        // Handle settings form submission
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('patient_id', '<?php echo $_SESSION['patient_id']; ?>');
            
            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Updating...';
            
            fetch('update_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new TypeError("Response was not JSON");
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeSettingsModal();
                    // Clear form
                    this.reset();
                } else {
                    showNotification(data.message || 'Failed to update password', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating password. Please try again.', 'error');
            })
            .finally(() => {
                // Reset button state
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });

        // Close modal when clicking outside
        document.getElementById('settingsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSettingsModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('settingsModal').classList.contains('hidden')) {
                closeSettingsModal();
            }
        });

        // Appointment Modal Functions
        function openAppointmentModal() {
            $('#appointmentForm')[0].reset();
            $('#appointmentDate').val(serverPHTDate);
            $('#timeSelect').html('<option value="">Select Time</option>');
            document.getElementById('appointmentModal').classList.remove('hidden');
        }

        function closeAppointmentModal() {
            document.getElementById('appointmentModal').classList.add('hidden');
        }

        function updateTimeSlots() {
            const date = $('#appointmentDate').val();
            const serviceId = $('#serviceSelect').val();
            const doctorId = $('#doctorSelect').val();
            
            if (!date || !serviceId || !doctorId) {
                $('#timeSelect').html('<option value="">Please select all required fields</option>');
                return;
            }
            
            $('#timeSelect').html('<option value="">Loading time slots...</option>');
            
            $.ajax({
                url: 'schedule.php',
                type: 'GET',
                data: {
                    action: 'get_time_slots',
                    date: date,
                    service_id: serviceId,
                    doctor_id: doctorId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Select Time</option>';
                        
                        if (response.slots && response.slots.length > 0) {
                            if (date === serverPHTDate) {
                                const [hours, minutes] = serverPHTTime.split(':').map(Number);
                                const currentMinutes = hours * 60 + minutes;
                                
                                response.slots = response.slots.filter(slot => {
                                    const slotHour = parseInt(slot.split(':')[0]);
                                    const isPM = slot.includes('PM') && slotHour !== 12;
                                    const slotMinutes = (isPM ? slotHour + 12 : slotHour) * 60;
                                    return slotMinutes > currentMinutes;
                                });
                            }
                            
                            if (response.slots.length > 0) {
                                response.slots.forEach(function(slot) {
                                    options += `<option value="${slot}">${slot}</option>`;
                                });
                            } else {
                                options = '<option value="">No available time slots</option>';
                            }
                        } else {
                            options = '<option value="">No available time slots</option>';
                        }
                        
                        $('#timeSelect').html(options);
                    } else {
                        $('#timeSelect').html('<option value="">No available time slots</option>');
                        alert(response.message || 'Error loading time slots. Please try again.');
                    }
                },
                error: function() {
                    $('#timeSelect').html('<option value="">Error loading time slots</option>');
                }
            });
        }

        $(document).ready(function() {
            $('#appointmentDate').attr('min', serverPHTDate);
            $('#appointmentDate').val(serverPHTDate);

            $('#appointmentDate').on('change', function() {
                const selectedDate = $(this).val();
                if (selectedDate < serverPHTDate) {
                    alert('Cannot book appointments for past dates. Please select today or a future date.');
                    $(this).val(serverPHTDate);
                    updateTimeSlots();
                    return false;
                }
                updateTimeSlots();
            });

            $('#serviceSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const requiredDoctor = selectedOption.data('doctor');
                
                if (requiredDoctor) {
                    $('#doctorSelect option').each(function() {
                        const doctorPosition = $(this).data('position');
                        if (doctorPosition === requiredDoctor || $(this).val() === '') {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                } else {
                    $('#doctorSelect option').show();
                }
                
                $('#doctorSelect').val('');
                $('#timeSelect').html('<option value="">Select Time</option>');
                updateTimeSlots();
            });

            $('#serviceSelect, #doctorSelect').on('change', function() {
                updateTimeSlots();
            });

            $('#appointmentForm').on('submit', function(e) {
                e.preventDefault();
                
                const selectedDate = $('#appointmentDate').val();
                const selectedTime = $('#timeSelect').val();
                
                if (selectedDate === serverPHTDate && selectedTime) {
                    const slotHour = parseInt(selectedTime.split(':')[0]);
                    const isPM = selectedTime.includes('PM') && slotHour !== 12;
                    const slotMinutes = (isPM ? slotHour + 12 : slotHour) * 60;
                    
                    const [hours, minutes] = serverPHTTime.split(':').map(Number);
                    const currentMinutes = hours * 60 + minutes;
                    
                    if (slotMinutes <= currentMinutes) {
                        alert('Cannot book appointments for past times. Please select a future time.');
                        updateTimeSlots();
                        return false;
                    }
                }
                
                const formData = $(this).serialize();
                $(this).find('button[type="submit"]').prop('disabled', true);
                
                $.ajax({
                    url: 'schedule.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Appointment scheduled successfully!');
                            closeAppointmentModal();
                            window.location.reload();
                        } else {
                            alert(response.message || 'Error saving appointment. Please try again.');
                            $('#appointmentForm').find('button[type="submit"]').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error saving appointment. Please try again.');
                        $('#appointmentForm').find('button[type="submit"]').prop('disabled', false);
                        console.error('Error:', error);
                    }
                });
            });
        });

        // Search functionality
        document.getElementById('appointmentSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.appointment-row');
            
            rows.forEach(row => {
                const doctor = row.dataset.doctor.toLowerCase();
                const service = row.dataset.service.toLowerCase();
                const status = row.dataset.status.toLowerCase();
                
                if (doctor.includes(searchTerm) || 
                    service.includes(searchTerm) || 
                    status.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Re-schedule Modal Functions
        function openRescheduleModal(appointmentId) {
            console.log('Opening reschedule modal for appointment:', appointmentId);
            
            // Reset form
            $('#rescheduleForm')[0].reset();
            $('#rescheduleAppointmentId').val(appointmentId);
            
            // Get appointment details
            $.ajax({
                url: 'schedule.php',
                type: 'GET',
                data: {
                    action: 'get_appointment_details',
                    id: appointmentId
                },
                success: function(response) {
                    console.log('Appointment details:', response);
                    if (response.success && response.appointment) {
                        const appointment = response.appointment;
                        
                        // Set form values
                        $('#reschedulePatientId').val(appointment.patient_id);
                        $('#rescheduleDoctorId').val(appointment.doctor_id);
                        $('#rescheduleServiceId').val(appointment.service_id);
                        $('#rescheduleDate').val(appointment.appointment_date);
                        $('#rescheduleRemarks').val(appointment.remarks || '');
                        
                        // Update time slots
                        updateRescheduleTimeSlots();
                        
                        // Show modal using Bootstrap 5
                        const rescheduleModal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
                        rescheduleModal.show();
                    } else {
                        alert(response.message || 'Error loading appointment details. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.log('Response:', xhr.responseText);
                    alert('Error loading appointment details. Please try again.');
                }
            });
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').classList.add('hidden');
        }

        function updateRescheduleTimeSlots() {
            const date = $('#rescheduleDate').val();
            const serviceId = $('#rescheduleServiceId').val();
            const doctorId = $('#rescheduleDoctorId').val();
            
            if (!date) {
                $('#rescheduleTime').html('<option value="">Please select a date</option>');
                return;
            }
            
            $('#rescheduleTime').html('<option value="">Loading time slots...</option>');
            
            $.ajax({
                url: 'schedule.php',
                type: 'GET',
                data: {
                    action: 'get_time_slots',
                    date: date,
                    service_id: serviceId,
                    doctor_id: doctorId
                },
                success: function(response) {
                    console.log('Time slots response:', response);
                    if (response.success && response.slots) {
                        let options = '<option value="">Select a time slot</option>';
                        response.slots.forEach(slot => {
                            options += `<option value="${slot}">${slot}</option>`;
                        });
                        $('#rescheduleTime').html(options);
                    } else {
                        console.error('Failed to get time slots:', response.message);
                        $('#rescheduleTime').html('<option value="">No available time slots</option>');
                        alert(response.message || 'Error loading time slots. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.log('Response:', xhr.responseText);
                    $('#rescheduleTime').html('<option value="">Error loading time slots</option>');
                }
            });
        }

        // Initialize reschedule form
        $(document).ready(function() {
            $('#rescheduleDate').attr('min', serverPHTDate);

            $('#rescheduleDate').on('change', function() {
                const selectedDate = $(this).val();
                if (selectedDate < serverPHTDate) {
                    alert('Cannot reschedule appointments for past dates. Please select today or a future date.');
                    $(this).val(serverPHTDate);
                    return false;
                }
                updateRescheduleTimeSlots();
            });

            // Handle reschedule form submission
            $('#rescheduleForm').on('submit', function(e) {
                e.preventDefault();
                var formData = {
                    action: 'add_appointment',
                    appointment_id: $('#rescheduleAppointmentId').val(),
                    patient_id: $('#reschedulePatientId').val(),
                    staff_id: $('#rescheduleDoctorId').val(),
                    service_id: $('#rescheduleServiceId').val(),
                    appointment_date: $('#rescheduleDate').val(),
                    appointment_time: $('#rescheduleTime').val(),
                    remarks: $('#rescheduleRemarks').val()
                };

                $.ajax({
                    url: 'schedule.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            const rescheduleModal = bootstrap.Modal.getInstance(document.getElementById('rescheduleModal'));
                            rescheduleModal.hide();
                            location.reload();
                        } else {
                            alert(response.message || 'Error rescheduling appointment. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.log('Response:', xhr.responseText);
                        alert('Error rescheduling appointment. Please try again.');
                    }
                });
            });

            // Add session check at the start of the page
            $.ajax({
                url: 'check_session.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (!response.active) {
                        window.location.href = 'login.php';
                    }
                },
                error: function() {
                    window.location.href = 'login.php';
                }
            });
        });

        // Update appointment status
        function updateAppointmentStatus(appointmentId, status) {
            if (status === 'Cancelled') {
                if (confirm('Are you sure you want to cancel this appointment?')) {
                    $.ajax({
                        url: 'schedule.php',
                        type: 'POST',
                        data: {
                            action: 'update_status',
                            id: appointmentId,
                            status: status
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert(response.message || 'Error updating appointment status. Please try again.');
                            }
                        },
                        error: function() {
                            alert('Error updating appointment status. Please try again.');
                        }
                    });
                }
            }
        }
    </script>

    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: #ccfbf1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #99f6e4;
        }
        .transition-all {
            transition: all 0.3s ease;
        }
        .hover\:bg-primary-50:hover {
            background-color: #ccfbf1;
        }
        .nav-link {
            position: relative;
            padding: 0.5rem 0;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(to right, #14b8a6, #f59e0b);
            transition: width 0.3s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .nav-link.active {
            color: #14b8a6;
        }
        .nav-link.active::after {
            width: 100%;
        }
        .mobile-nav-link {
            position: relative;
        }
        .mobile-nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(to right, #14b8a6, #f59e0b);
            transition: width 0.3s ease;
        }
        .mobile-nav-link:hover::after {
            width: 100%;
        }
        .mobile-nav-link.active {
            color: #14b8a6;
        }
        .mobile-nav-link.active::after {
            width: 100%;
        }
        @media (max-width: 768px) {
            main {
                padding-bottom: 5rem;
            }
        }
    </style>
</body>
</html>