<?php
date_default_timezone_set('Asia/Manila'); // Set timezone to Philippine time
session_start();
require_once 'config/db.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

// Get doctor information
$stmt = $pdo->prepare("
    SELECT s.*, dp.doctor_position 
    FROM staff s 
    LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
    WHERE s.id = ? AND s.role = 'doctor'
");
$stmt->execute([$_SESSION['staff_id']]);
$doctor = $stmt->fetch();

// Map the fields correctly
$doctor['email'] = $doctor['gmail'] ?? 'N/A';
$doctor['phone'] = $doctor['contact'] ?? 'N/A';
$doctor['address'] = $doctor['address'] ?? 'N/A';
$doctor['gender'] = $doctor['gender'] ?? 'N/A';
$doctor['photo'] = $doctor['photo'] ?? 'path/to/default/doctor/photo.jpg';

// Get clinic details
try {
    $stmt = $pdo->query("SELECT * FROM clinic_details ORDER BY created_at DESC LIMIT 1");
    $clinic = $stmt->fetch();
} catch (PDOException $e) {
    $clinic = []; // Fallback to empty array
    error_log("Clinic details query failed: " . $e->getMessage());
}

// Prepare date range and chart data based on period
$period = $_GET['period'] ?? 'daily'; // Default to daily
$date = $_GET['date'] ?? date('Y-m-d'); // Default to today

// Determine start and end dates based on period
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
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $dateFormat = '%Y-%m-%d';
        $interval = 'DAY';
        $period = 'daily';
        $date = date('Y-m-d');
}

// Get appointment statistics for summary cards based on selected period
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
        SUM(CASE WHEN status = 'Scheduled' OR status = 'Pending' THEN 1 ELSE 0 END) as scheduled_appointments
    FROM appointments 
    WHERE staff_id = ? 
    AND appointment_date BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['staff_id'], $startDate, $endDate]);
$statistics = $stmt->fetch();

// Get chart data (completed and cancelled appointments over time) based on selected period
$chartData = ['labels' => [], 'completed' => [], 'cancelled' => []];

$sql = "SELECT
        DATE_FORMAT(appointment_date, '$dateFormat') as time_period,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM appointments
    WHERE staff_id = ? AND appointment_date BETWEEN ? AND ?
    GROUP BY time_period
        ORDER BY time_period";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['staff_id'], $startDate, $endDate]);
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

// Get today's appointments
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT a.*, p.name as patient_name, p.phone as patient_phone,
           s.service_name, s.time as service_duration
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN services s ON a.service_id = s.id
    WHERE a.staff_id = ? AND a.appointment_date = ?
    ORDER BY a.appointment_time ASC
");
$stmt->execute([$_SESSION['staff_id'], $today]);
$todayAppointments = $stmt->fetchAll();

// Debug information
error_log("Today's date: " . $today);
error_log("Staff ID: " . $_SESSION['staff_id']);
error_log("Number of today's appointments: " . count($todayAppointments));

// Get upcoming appointments (next 7 days from today)
$nextWeek = date('Y-m-d', strtotime('+7 days'));
$stmt = $pdo->prepare("
    SELECT a.*, p.name as patient_name, p.phone as patient_phone,
           s.service_name, s.time as service_duration
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN services s ON a.service_id = s.id
    WHERE a.staff_id = ? 
    AND a.appointment_date BETWEEN ? AND ? 
    AND a.appointment_date != ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute([$_SESSION['staff_id'], $today, $nextWeek, $today]);
$upcomingAppointments = $stmt->fetchAll();

// Get all active services for appointment modal
try {
    $stmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY service_name ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $services = [];
}

// Get all active patients for appointment modal
try {
    $stmt = $pdo->query("SELECT * FROM patients WHERE status = 1 ORDER BY name ASC");
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
}

// Get all active doctors
try {
    $stmt = $pdo->query("SELECT s.*, dp.doctor_position 
                         FROM staff s 
                         JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                         WHERE s.status = 1 AND s.role = 'doctor'
                         ORDER BY s.name ASC");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $doctors = [];
}

// Get current date for date pickers
$serverPHTDate = date('Y-m-d');
$serverPHTTime = date('H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['clinic_name'] ?? 'Clinic'); ?> Doctor Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <?php if (isset($clinic['logo']) && !empty($clinic['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($clinic['logo']); ?>" alt="Clinic Logo" class="h-8 w-auto mr-3">
                    <?php endif; ?>
                    <h1 class="text-xl font-heading font-bold text-primary-500"><?php echo htmlspecialchars($clinic['clinic_name'] ?? 'Clinic'); ?> Doctor Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <span class="text-neutral-dark font-medium cursor-pointer hover:text-primary-500 transition-colors duration-100" id="doctorMenuTrigger">Dr. <?php echo htmlspecialchars($doctor['name']); ?> <i class="fas fa-chevron-down text-xs ml-1"></i></span>
                        <div id="doctorMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden">
                            <a href="#settings" class="block px-4 py-2 text-sm text-neutral-dark hover:bg-primary-50 transition-colors duration-100">Settings</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-neutral-dark hover:bg-primary-50 transition-colors duration-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 md:grid-cols-4">
            <!-- Profile Card -->
            <div class="md:col-span-1">
                <div class="bg-gradient-to-br from-primary-50 to-accent-100 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-4 h-full flex flex-col justify-between">
                    <div class="text-center">
                        <img src="<?php echo htmlspecialchars($doctor['photo']); ?>" 
                             alt="Doctor Photo" 
                             class="w-24 h-24 rounded-full mx-auto object-cover border-4 border-white shadow-md">
                        <h2 class="text-lg font-bold mt-3 text-neutral-dark">Dr. <?php echo htmlspecialchars($doctor['name'] ?? 'N/A'); ?></h2>
                        <p class="text-secondary text-xs"><?php echo htmlspecialchars($doctor['doctor_position'] ?? 'N/A'); ?></p>
                        <button onclick="openEditProfileModal()" class="mt-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white px-3 py-1 rounded-lg text-xs hover:scale-105 transition-all duration-200">
                            <i class="fas fa-edit mr-1"></i> Edit Profile
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Email</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($doctor['email']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Phone</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($doctor['phone']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Address</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($doctor['address']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-venus-mars text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-xs font-medium text-secondary">Gender</label>
                                <p class="text-neutral-dark text-xs"><?php echo htmlspecialchars($doctor['gender']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="md:col-span-3">
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6 w-full">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-base font-medium text-neutral-dark">Doctor Statistics</h2>
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
                                    $currentYear = date('Y');
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
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['total_appointments'] ?? 0; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                            <h3 class="text-sm font-medium text-neutral-dark">Completed</h3>
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['completed_appointments'] ?? 0; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                            <h3 class="text-sm font-medium text-neutral-dark">Scheduled</h3>
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['scheduled_appointments'] ?? 0; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-4 rounded-lg shadow-sm">
                            <h3 class="text-sm font-medium text-neutral-dark">Cancelled</h3>
                            <p class="text-2xl font-bold text-neutral-dark"><?php echo $statistics['cancelled_appointments'] ?? 0; ?></p>
                        </div>
                    </div>
                    <div class="h-[28rem] md:h-[20rem]">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="md:col-span-4 mt-6 pt-8 md:pt-0">
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6 w-full">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                        <h2 class="text-base font-medium text-neutral-dark mb-4 sm:mb-0">Today's Appointments</h2>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-4 w-full sm:w-auto">
                            <div class="relative w-full sm:w-64">
                                <input type="text" id="todayAppointmentSearch" placeholder="Search appointments..."
                                       class="w-full pl-10 pr-4 py-2 rounded-lg border border-primary-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-primary-500"></i>
                            </div>
                            <button onclick="openAppointmentModal()" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm hover:scale-105 transition-all duration-200 w-full sm:w-auto">
                                <i class="fas fa-plus mr-1"></i> Add Appointment
                            </button>
                        </div>
                    </div>
                    <!-- Desktop View: Table -->
                    <div class="hidden sm:block overflow-x-auto w-full">
                        <table class="min-w-full divide-y divide-primary-100">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Patient</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Service</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="todayAppointmentsTableBody" class="bg-white divide-y divide-primary-100">
                                <?php if (empty($todayAppointments)): ?>
                                    <tr><td colspan="5" class="text-secondary text-center py-4">No appointments scheduled for today.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($todayAppointments as $appointment): ?>
                                        <tr class="hover:bg-primary-50 transition-all appointment-row" 
                                            data-patient="<?php echo htmlspecialchars($appointment['patient_name']); ?>"
                                            data-service="<?php echo htmlspecialchars($appointment['service_name']); ?>"
                                            data-status="<?php echo htmlspecialchars($appointment['status']); ?>">
                                            <td class="px-4 py-3 text-neutral-dark text-sm font-medium">
                                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                <p class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <?php echo htmlspecialchars($appointment['service_name']); ?>
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
                                                    <?php if ($appointment['status'] === 'Scheduled'): ?>
                                                        <button onclick="updateAppointmentStatus(<?php echo $appointment['id']; ?>, 'Completed')" 
                                                                class="inline-flex items-center px-3 py-1.5 border border-success text-xs font-medium rounded-lg text-success bg-white hover:bg-success-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-success transition-all duration-200">
                                                            <i class="fas fa-check mr-1.5"></i>
                                                            Complete
                                                        </button>
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
                                                    <?php elseif ($appointment['status'] === 'Completed'): ?>
                                                        <button onclick="printReceipt(<?php echo $appointment['id']; ?>)" 
                                                                class="inline-flex items-center px-3 py-1.5 border border-primary-100 text-xs font-medium rounded-lg text-primary-500 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                                                            <i class="fas fa-receipt mr-1.5"></i>
                                                            Receipt
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile View: Cards -->
                    <div class="sm:hidden space-y-4" id="todayAppointmentsMobile">
                        <?php if (empty($todayAppointments)): ?>
                            <div class="text-secondary text-center py-4">No appointments scheduled for today.</div>
                        <?php else: ?>
                            <?php foreach ($todayAppointments as $appointment): ?>
                                <div class="bg-white rounded-lg shadow-sm p-4 appointment-card" 
                                     data-patient="<?php echo htmlspecialchars($appointment['patient_name']); ?>"
                                     data-service="<?php echo htmlspecialchars($appointment['service_name']); ?>"
                                     data-status="<?php echo htmlspecialchars($appointment['status']); ?>">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="text-sm font-medium text-neutral-dark"><?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
                                            <p class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
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
                                    <div class="space-y-2 text-sm">
                                        <p class="text-secondary">
                                            <i class="fas fa-briefcase-medical mr-2"></i>
                                            <?php echo htmlspecialchars($appointment['service_name']); ?>
                                        </p>
                                        <p class="text-secondary">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </p>
                                    </div>
                                    <?php if ($appointment['status'] === 'Scheduled'): ?>
                                        <div class="mt-4 flex flex-col space-y-2">
                                            <button onclick="updateAppointmentStatus(<?php echo $appointment['id']; ?>, 'Completed')" 
                                                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-success text-xs font-medium rounded-lg text-success bg-white hover:bg-success-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-success transition-all duration-200">
                                                <i class="fas fa-check mr-1.5"></i>
                                                Complete
                                </button>
                                            <button onclick="openRescheduleModal(<?php echo $appointment['id']; ?>)" 
                                                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-primary-100 text-xs font-medium rounded-lg text-primary-500 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                                                <i class="fas fa-calendar-alt mr-1.5"></i>
                                                Re-schedule
                                            </button>
                                            <button onclick="updateAppointmentStatus(<?php echo $appointment['id']; ?>, 'Cancelled')" 
                                                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-red-100 text-xs font-medium rounded-lg text-red-500 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                <i class="fas fa-times mr-1.5"></i>
                                                Cancel
                                </button>
                            </div>
                                    <?php elseif ($appointment['status'] === 'Completed'): ?>
                                        <div class="mt-4">
                                            <button onclick="printReceipt(<?php echo $appointment['id']; ?>)" 
                                                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-primary-100 text-xs font-medium rounded-lg text-primary-500 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                                                <i class="fas fa-receipt mr-1.5"></i>
                                                Receipt
                                            </button>
                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="flex justify-between items-center mt-4 px-4">
                        <div class="text-sm text-secondary">
                            Showing <span id="todayCurrentPageStart">1</span> to <span id="todayCurrentPageEnd">5</span> of <span id="todayTotalAppointments"><?php echo count($todayAppointments); ?></span> appointments
                        </div>
                        <div class="flex space-x-2">
                            <button id="todayPrevPage" class="px-3 py-1 border border-primary-100 rounded-lg text-sm text-primary-500 hover:bg-primary-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <button id="todayNextPage" class="px-3 py-1 border border-primary-100 rounded-lg text-sm text-primary-500 hover:bg-primary-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="md:col-span-4 mt-6">
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6 w-full">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                        <h2 class="text-base font-medium text-neutral-dark">Upcoming Appointments</h2>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-secondary">Next 7 days</span>
                        </div>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="relative w-full">
                            <input type="text" id="upcomingAppointmentSearch" placeholder="Search appointments..." 
                                   class="w-full pl-10 pr-4 py-2 rounded-lg border border-primary-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-primary-500"></i>
                        </div>
                        <div class="flex gap-4">
                            <input type="date" id="upcomingAppointmentDateFilter" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   class="flex-1 rounded-lg border border-primary-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                        </div>
                    </div>

                    <!-- Desktop View: Table -->
                    <div class="hidden sm:block overflow-x-auto w-full">
                        <table class="w-full divide-y divide-primary-100">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Patient</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Service</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="upcomingAppointmentsTableBody" class="bg-white divide-y divide-primary-100">
                                <?php if (empty($upcomingAppointments)): ?>
                                    <tr><td colspan="6" class="text-secondary text-center py-4">No upcoming appointments scheduled.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($upcomingAppointments as $appointment): ?>
                                        <tr class="hover:bg-primary-50 transition-all appointment-row" 
                                            data-patient="<?php echo htmlspecialchars($appointment['patient_name']); ?>"
                                            data-service="<?php echo htmlspecialchars($appointment['service_name']); ?>"
                                            data-status="<?php echo htmlspecialchars($appointment['status']); ?>"
                                            data-date="<?php echo htmlspecialchars($appointment['appointment_date']); ?>">
                                            <td class="px-4 py-3 text-neutral-dark text-sm font-medium">
                                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                <p class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <?php echo htmlspecialchars($appointment['service_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
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
                    </div>

                    <!-- Mobile View: Cards -->
                    <div class="sm:hidden space-y-4">
                        <?php if (empty($upcomingAppointments)): ?>
                            <div class="text-secondary text-center py-4">No upcoming appointments scheduled.</div>
                        <?php else: ?>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div class="bg-white rounded-lg shadow-sm p-4 appointment-card" 
                                     data-patient="<?php echo htmlspecialchars($appointment['patient_name']); ?>"
                                     data-service="<?php echo htmlspecialchars($appointment['service_name']); ?>"
                                     data-date="<?php echo htmlspecialchars($appointment['appointment_date']); ?>">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="text-sm font-medium text-neutral-dark"><?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
                                            <p class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                            </div>
                        </div>
                                    <div class="space-y-2 text-sm">
                                        <p class="text-secondary">
                                            <i class="fas fa-briefcase-medical mr-2"></i>
                                            <?php echo htmlspecialchars($appointment['service_name']); ?>
                                        </p>
                                        <p class="text-secondary">
                                            <i class="far fa-calendar mr-2"></i>
                                            <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                        </p>
                                        <p class="text-secondary">
                                            <i class="far fa-clock mr-2"></i>
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </p>
                    </div>
                                    <?php if ($appointment['status'] !== 'Completed' && $appointment['status'] !== 'Cancelled'): ?>
                                        <div class="mt-4 flex flex-col space-y-2">
                                            <button onclick="openRescheduleModal(<?php echo $appointment['id']; ?>)" 
                                                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-primary-100 text-xs font-medium rounded-lg text-primary-500 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                                    <i class="fas fa-calendar-alt mr-1.5"></i>
                                    Re-schedule
                                </button>
                                            <button onclick="updateAppointmentStatus(<?php echo $appointment['id']; ?>, 'Cancelled')" 
                                                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-red-100 text-xs font-medium rounded-lg text-red-500 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                    <i class="fas fa-times mr-1.5"></i>
                                    Cancel
                                </button>
                            </div>
                                    <?php endif; ?>
                                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination for Upcoming Appointments -->
                    <div class="flex justify-between items-center mt-4 px-4">
                        <div class="text-sm text-secondary">
                            Showing <span id="upcomingCurrentPageStart">1</span> to <span id="upcomingCurrentPageEnd">5</span> of <span id="upcomingTotalAppointments"><?php echo count($upcomingAppointments); ?></span> appointments
                </div>
                        <div class="flex space-x-2">
                            <button id="upcomingPrevPage" class="px-3 py-1 border border-primary-100 rounded-lg text-sm text-primary-500 hover:bg-primary-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <button id="upcomingNextPage" class="px-3 py-1 border border-primary-100 rounded-lg text-sm text-primary-500 hover:bg-primary-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                </div>
                </div>
        </div>
    </div>
</div>
    </main>

    <!-- Receipt Modal -->
<div id="receiptModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-neutral-dark">Payment Receipt</h3>
                <button onclick="closeReceiptModal()" class="text-neutral-dark hover:text-primary-500 transition-colors duration-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="receiptContent" class="space-y-4">
                <!-- Receipt content will be loaded here -->
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="closeReceiptModal()" class="px-4 py-2 bg-neutral-light text-neutral-dark rounded-lg hover:bg-primary-50 transition-colors duration-200">
                    Close
                </button>
                <button onclick="printReceiptContent()" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg hover:scale-105 transition-all duration-200">
                    <i class="fas fa-print mr-2"></i>Print Receipt
                </button>
        </div>
    </div>
</div>

<script>
    const serverPHTDate = '<?php echo $serverPHTDate; ?>';
    const serverPHTTime = '<?php echo $serverPHTTime; ?>';
    const loggedInDoctorId = '<?php echo $doctor['id']; ?>';
    const loggedInDoctorPosition = '<?php echo htmlspecialchars($doctor['doctor_position']); ?>';

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

        window.location.href = `doctor_dashboard.php?period=${period}&date=${date}`;
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
    });

    // Profile Modal Functions
    function openEditProfileModal() {
        document.getElementById('editProfileModal').classList.remove('hidden');
    }

    function closeEditProfileModal() {
        document.getElementById('editProfileModal').classList.add('hidden');
    }

    // Appointment Modal Functions
    function openAppointmentModal() {
        $('#appointmentForm')[0].reset();
        
        // Use server-provided Philippine time date
        $('#appointmentDate').attr('min', serverPHTDate);
        $('#appointmentDate').val(serverPHTDate);
        
        $('#timeSelect').html('<option value="">Select Time</option>');
        
        // Set logged-in doctor and disable the select
        $('#doctorSelect').val(loggedInDoctorId).prop('disabled', true);

        // Filter services based on doctor's position
        filterServicesByDoctorPosition(loggedInDoctorPosition);
        
        document.getElementById('appointmentModal').classList.remove('hidden');
    }

    function closeAppointmentModal() {
        document.getElementById('appointmentModal').classList.add('hidden');
    }

    function filterServicesByDoctorPosition(position) {
        $('#serviceSelect option').each(function() {
            const serviceDoctor = $(this).data('doctor');
            if (serviceDoctor === position || $(this).val() === '') {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // Reset service selection if the currently selected one is hidden
        if ($('#serviceSelect option:selected').is(':hidden')) {
            $('#serviceSelect').val('');
        }
        // Trigger change to update time slots
        $('#serviceSelect').trigger('change');
    }

    function updateTimeSlots() {
        const date = $('#appointmentDate').val();
        const serviceId = $('#serviceSelect').val();
        const doctorId = loggedInDoctorId; // Always use logged-in doctor

        if (!date || !serviceId) {
            $('#timeSelect').html('<option value="">Please select date and service</option>');
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
                        // If date is today, filter out past time slots
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
        // Set initial date to server-provided Philippine time
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

        $('#serviceSelect').on('change', updateTimeSlots);

        $('#appointmentForm').on('submit', function(e) {
            e.preventDefault();
            
            const selectedDate = $('#appointmentDate').val();
            const selectedTime = $('#timeSelect').val();
            
            if (!selectedDate || !selectedTime || !$('#patientSelect').val() || !$('#serviceSelect').val()) {
                alert('Please fill in all required fields.');
                return false;
            }

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

            // Serialize the form data, including the disabled doctor_id
            const formData = $(this).serializeArray();
            formData.push({name: 'staff_id', value: loggedInDoctorId});

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
                        // Log the specific error message from schedule.php
                        console.error('Error saving appointment:', response.message);
                        alert(response.message || 'Error saving appointment. Please try again.');
                        $('#appointmentForm').find('button[type="submit"]').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                        console.error('AJAX Error:', {xhr, status, error});
                    console.error('Response:', xhr.responseText);
                    alert('Error saving appointment. Please try again.');
                    $('#appointmentForm').find('button[type="submit"]').prop('disabled', false);
                }
            });
        });

        // Reschedule Modal Initialization
        $('#rescheduleDate').attr('min', serverPHTDate);
        $('#rescheduleDate').val(serverPHTDate);

        $('#rescheduleDate').on('change', function() {
            const selectedDate = $(this).val();
            if (selectedDate < serverPHTDate) {
                alert('Cannot reschedule appointments for past dates. Please select today or a future date.');
                $(this).val(serverPHTDate);
                updateRescheduleTimeSlots();
                return false;
            }
            updateRescheduleTimeSlots();
        });

        $('#serviceSelect').on('change', updateRescheduleTimeSlots);

        $('#rescheduleForm').on('submit', function(e) {
            e.preventDefault();
            
            const selectedDate = $('#rescheduleDate').val();
            const selectedTime = $('#rescheduleTimeSelect').val();
            
            if (!selectedDate || !selectedTime) {
                alert('Please fill in all required fields.');
                return false;
            }

            if (selectedDate === serverPHTDate && selectedTime) {
                const slotHour = parseInt(selectedTime.split(':')[0]);
                const isPM = selectedTime.includes('PM') && slotHour !== 12;
                const slotMinutes = (isPM ? slotHour + 12 : slotHour) * 60;
                
                const [hours, minutes] = serverPHTTime.split(':').map(Number);
                const currentMinutes = hours * 60 + minutes;

                if (slotMinutes <= currentMinutes) {
                    alert('Cannot reschedule appointments for past times. Please select a future time.');
                    updateRescheduleTimeSlots();
                    return false;
                }
            }

            // Serialize the form data
            const formData = $(this).serializeArray();

            $(this).find('button[type="submit"]').prop('disabled', true);

            $.ajax({
                url: 'update_appointment.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Appointment rescheduled successfully!');
                        closeRescheduleModal();
                        window.location.reload();
                    } else {
                        // Log the specific error message
                        console.error('Error rescheduling appointment:', response.message);
                        alert(response.message || 'Error rescheduling appointment. Please try again.');
                        $('#rescheduleForm').find('button[type="submit"]').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                        console.error('AJAX Error:', {xhr, status, error});
                    console.error('Response:', xhr.responseText);
                    alert('Error rescheduling appointment. Please try again.');
                    $('#rescheduleForm').find('button[type="submit"]').prop('disabled', false);
                }
            });
        });
    });

    function updateAppointmentStatus(appointmentId, status) {
        if (!appointmentId) {
            alert('Invalid appointment ID');
            return;
        }

        if (status === 'Completed') {
            // Fetch appointment details and show payment modal
            $.ajax({
                url: 'schedule.php',
                type: 'GET',
                data: {
                    action: 'get_appointment_payment_details',
                    id: appointmentId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Set the appointment details in the payment modal
                        $('#paymentAppointmentId').val(appointmentId);
                        $('#paymentPatientName').text(response.appointment.patient_name);
                        $('#paymentServiceName').text(response.appointment.service_name);
                        $('#paymentAmount').val(response.appointment.service_price);
                        
                        // Show the payment modal
                        document.getElementById('paymentModal').classList.remove('hidden');
                    } else {
                        alert('Error loading payment details: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading payment details:', {xhr, status, error});
                    alert('Error loading payment details. Please try again.');
                }
            });
        } else if (status === 'Cancelled') {
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

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
        $('#paymentForm')[0].reset();
        $('#qrCodeContainer').addClass('hidden');
    }

    // Handle payment form submission
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        const appointmentId = $('#paymentAppointmentId').val();
        if (!appointmentId) {
            alert('Invalid appointment ID. Please try again.');
            return;
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
                    alert(response.message);
                    closePaymentModal();
                    // Show receipt after successful payment
                    printReceipt(appointmentId);
                    // Reload the page to update the status
                    window.location.reload();
                } else {
                    alert(response.message || 'Error recording payment. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Payment Error:', {xhr, status, error});
                alert('Error recording payment. Please try again.');
            },
            complete: function() {
                $('#paymentForm').find('button[type="submit"]').prop('disabled', false);
            }
        });
    });

    // Show/hide QR code based on payment method
    $('#paymentMethod').on('change', function() {
        if ($(this).val() === 'GCash') {
            $('#qrCodeContainer').removeClass('hidden');
        } else {
            $('#qrCodeContainer').addClass('hidden');
        }
    });

    // Doctor Menu Dropdown Toggle
    const doctorMenuTrigger = document.getElementById('doctorMenuTrigger');
    const doctorMenu = document.getElementById('doctorMenu');

    if (doctorMenuTrigger && doctorMenu) {
        doctorMenuTrigger.addEventListener('click', function(event) {
            doctorMenu.classList.toggle('hidden');
            event.stopPropagation(); // Prevent document click from closing immediately
        });

        // Close the dropdown if the user clicks outside of it
        document.addEventListener('click', function(event) {
            if (!doctorMenuTrigger.contains(event.target) && !doctorMenu.contains(event.target)) {
                doctorMenu.classList.add('hidden');
            }
        });
    }

    // Reschedule Modal Functions
    function openRescheduleModal(appointmentId) {
        $('#rescheduleAppointmentId').val(appointmentId);
        $('#rescheduleDate').val(serverPHTDate);
        $('#rescheduleTimeSelect').html('<option value="">Select Time</option>');
        
        document.getElementById('rescheduleModal').classList.remove('hidden');
    }

    function closeRescheduleModal() {
        document.getElementById('rescheduleModal').classList.add('hidden');
    }

    function updateRescheduleTimeSlots() {
        const date = $('#rescheduleDate').val();
        const serviceId = $('#serviceSelect').val();
        const doctorId = loggedInDoctorId; // Always use logged-in doctor

        if (!date || !serviceId) {
            $('#rescheduleTimeSelect').html('<option value="">Please select date and service</option>');
            return;
        }

        $('#rescheduleTimeSelect').html('<option value="">Loading time slots...</option>');

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
                        // If date is today, filter out past time slots
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
                    
                    $('#rescheduleTimeSelect').html(options);
                } else {
                    $('#rescheduleTimeSelect').html('<option value="">No available time slots</option>');
                    alert(response.message || 'Error loading time slots. Please try again.');
                }
            },
            error: function() {
                $('#rescheduleTimeSelect').html('<option value="">Error loading time slots</option>');
            }
        });
    }

    function printReceipt(appointmentId) {
            console.log('printReceipt called with ID:', appointmentId);
        if (!appointmentId) {
                console.error('Invalid appointment ID');
            alert('Invalid appointment ID');
            return;
        }

        // Show loading state
        $('#receiptContent').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-primary-500"></i> Loading receipt...</div>');
        document.getElementById('receiptModal').classList.remove('hidden');

            console.log('Making AJAX request to schedule.php');
        $.ajax({
            url: 'schedule.php',
            type: 'GET',
            data: {
                action: 'get_receipt_details',
                id: appointmentId
            },
            dataType: 'json',
            success: function(response) {
                    console.log('AJAX response received:', response);
                try {
                    if (response.success) {
                        const receipt = response.receipt;
                        const clinic = response.clinic;
                        const doctor = response.doctor;
                        
                        // Validate required data
                        if (!receipt || !clinic || !doctor) {
                                console.error('Missing required data:', { receipt, clinic, doctor });
                            $('#receiptContent').html('<div class="text-center py-4 text-red-500">Error: Missing required data</div>');
                            return;
                        }

                        const receiptHtml = `
                            <div class="text-center mb-6">
                                <img src="${clinic.logo}" alt="${clinic.name}" class="h-16 mx-auto mb-2">
                                <h2 class="text-xl font-bold">${clinic.name}</h2>
                                <p class="text-sm">${clinic.address}</p>
                                <p class="text-sm">Tel: ${clinic.phone}</p>
                                <p class="text-sm">Email: ${clinic.email}</p>
                                <div class="mt-2 text-xs">
                                    <p><strong>Hours:</strong></p>
                                    <p>Weekdays: ${clinic.hours_weekdays}</p>
                                    <p>Saturday: ${clinic.hours_saturday}</p>
                                    <p>Sunday: ${clinic.hours_sunday}</p>
                                </div>
                            </div>
                            <div class="border-t border-b border-gray-200 py-4 mb-4">
                                <h3 class="text-lg font-semibold mb-2">Payment Receipt</h3>
                                <p class="text-sm">Receipt No: ${receipt.id}</p>
                                <p class="text-sm">Date: ${receipt.payment_date}</p>
                                <p class="text-sm">Time: ${receipt.payment_time}</p>
                            </div>
                            <div class="mb-4">
                                <p class="text-sm"><strong>Patient:</strong> ${receipt.patient_name}</p>
                                <p class="text-sm"><strong>Service:</strong> ${receipt.service_name}</p>
                                <p class="text-sm"><strong>Doctor:</strong> Dr. ${doctor.name}</p>
                                <p class="text-sm"><strong>Appointment Date:</strong> ${receipt.appointment_date}</p>
                                <p class="text-sm"><strong>Appointment Time:</strong> ${receipt.appointment_time}</p>
                                <p class="text-sm"><strong>Payment Method:</strong> ${receipt.payment_method}</p>
                                <p class="text-sm"><strong>Amount:</strong> ₱${parseFloat(receipt.amount).toFixed(2)}</p>
                            </div>
                            <div class="border-t border-gray-200 pt-4">
                                <p class="text-sm text-center">Thank you for choosing our services!</p>
                            </div>
                        `;
                        
                        $('#receiptContent').html(receiptHtml);
                    } else {
                        // Show detailed error message
                        const errorMessage = response.message || 'Unknown error occurred';
                        const debugInfo = response.debug_info ? JSON.stringify(response.debug_info) : '';
                        $('#receiptContent').html(`
                            <div class="text-center py-4">
                                <div class="text-red-500 mb-2">${errorMessage}</div>
                                ${debugInfo ? `<div class="text-xs text-gray-500">Debug Info: ${debugInfo}</div>` : ''}
                            </div>
                        `);
                    }
                } catch (e) {
                    console.error('Error processing receipt:', e);
                    $('#receiptContent').html(`
                        <div class="text-center py-4">
                            <div class="text-red-500 mb-2">Error processing receipt data</div>
                            <div class="text-xs text-gray-500">${e.message}</div>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr, status, error});
                let errorMessage = 'Error loading receipt details';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    console.error('Error parsing response:', e);
                }
                
                $('#receiptContent').html(`
                    <div class="text-center py-4">
                        <div class="text-red-500 mb-2">${errorMessage}</div>
                        <div class="text-xs text-gray-500">
                            Status: ${status}<br>
                            Error: ${error}
                        </div>
                    </div>
                `);
            }
        });
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
    }

    function printReceiptContent() {
        const receiptContent = document.getElementById('receiptContent').innerHTML;
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Payment Receipt</title>
                    <style>
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                        body {
                            width: 80mm;
                            margin: 0;
                            padding: 5mm;
                            font-family: 'Courier New', monospace;
                            font-size: 12px;
                            line-height: 1.2;
                        }
                        .receipt-header {
                            text-align: center;
                            margin-bottom: 8px;
                        }
                        .receipt-header img {
                            max-width: 25mm;
                            height: auto;
                            margin-bottom: 2px;
                        }
                        .receipt-header h2 {
                            font-size: 11px;
                            font-weight: bold;
                            margin: 2px 0;
                        }
                        .receipt-header p {
                            margin: 1px 0;
                            font-size: 8px;
                        }
                        .receipt-details {
                            border-top: 1px dashed #000;
                            border-bottom: 1px dashed #000;
                            padding: 2px 0;
                            margin: 2px 0;
                        }
                        .receipt-details h3 {
                            font-size: 10px;
                            font-weight: bold;
                            margin: 2px 0;
                        }
                        .receipt-details p {
                            margin: 1px 0;
                            font-size: 8px;
                        }
                        .receipt-info p {
                            margin: 1px 0;
                            font-size: 8px;
                        }
                        .receipt-footer {
                            text-align: center;
                            margin-top: 5px;
                            border-top: 1px dashed #000;
                            padding-top: 2px;
                        }
                        .receipt-footer p {
                            margin: 1px 0;
                            font-size: 8px;
                        }
                        @media print {
                            body { 
                                padding: 0;
                                margin: 0;
                            }
                            .no-print { 
                                display: none; 
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-header">
                        <img src="${document.querySelector('#receiptContent img')?.src || ''}" alt="Clinic Logo">
                        <h2>${document.querySelector('#receiptContent h2')?.textContent || 'Clinic Name'}</h2>
                        <p>${document.querySelector('#receiptContent p:nth-of-type(1)')?.textContent || 'Address'}</p>
                        <p>${document.querySelector('#receiptContent p:nth-of-type(2)')?.textContent || 'Phone'}</p>
                        <p>${document.querySelector('#receiptContent p:nth-of-type(3)')?.textContent || 'Email'}</p>
                    </div>
                    <div class="receipt-details">
                        <h3>Payment Receipt</h3>
                        <p>Receipt No: ${document.querySelector('#receiptContent .border-t p:nth-of-type(1)')?.textContent.replace('Receipt No: ', '') || ''}</p>
                        <p>Date: ${document.querySelector('#receiptContent .border-t p:nth-of-type(2)')?.textContent.replace('Date: ', '') || ''}</p>
                        <p>Time: ${document.querySelector('#receiptContent .border-t p:nth-of-type(3)')?.textContent.replace('Time: ', '') || ''}</p>
                    </div>
                    <div class="receipt-info">
                        <p>${document.querySelector('#receiptContent .mb-4 p:nth-of-type(1)')?.textContent || ''}</p>
                        <p>${document.querySelector('#receiptContent .mb-4 p:nth-of-type(2)')?.textContent || ''}</p>
                        <p>${document.querySelector('#receiptContent .mb-4 p:nth-of-type(3)')?.textContent || ''}</p>
                        <p>${document.querySelector('#receiptContent .mb-4 p:nth-of-type(4)')?.textContent || ''}</p>
                        <p>${document.querySelector('#receiptContent .mb-4 p:nth-of-type(5)')?.textContent || ''}</p>
                        <p>${document.querySelector('#receiptContent .mb-4 p:nth-of-type(6)')?.textContent || ''}</p>
                        <p>${document.querySelector('#receiptContent .mb-4 p:nth-of-type(7)')?.textContent || ''}</p>
                    </div>
                    <div class="receipt-footer">
                        <p>Thank you for choosing our services!</p>
                    </div>
                </body>
            </html>
        `);
        
        printWindow.document.close();
        
        // Automatically trigger print after a short delay
        setTimeout(() => {
            printWindow.print();
            // Close the window after printing
            setTimeout(() => {
                printWindow.close();
            }, 1000);
        }, 500);
    }

    // Add this to your existing JavaScript code
    document.addEventListener('DOMContentLoaded', function() {
        // Pagination for Today's Appointments
        const appointmentsPerPage = 5;
        let currentPage = 1;
        let filteredAppointments = [];
        const searchInput = document.getElementById('todayAppointmentSearch');
        const tableBody = document.getElementById('todayAppointmentsTableBody');
        const mobileContainer = document.getElementById('todayAppointmentsMobile');
        const prevButton = document.getElementById('todayPrevPage');
        const nextButton = document.getElementById('todayNextPage');
        const currentPageStart = document.getElementById('todayCurrentPageStart');
        const currentPageEnd = document.getElementById('todayCurrentPageEnd');
        const totalAppointments = document.getElementById('todayTotalAppointments');

        // Initialize with all appointments
        const allAppointments = Array.from(tableBody.getElementsByClassName('appointment-row'));
        const allMobileAppointments = Array.from(mobileContainer.getElementsByClassName('appointment-card'));
        filteredAppointments = [...allAppointments];
        filteredMobileAppointments = [...allMobileAppointments];
        updateTodayPagination();

        // Search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            filteredAppointments = allAppointments.filter(row => {
                const patientName = row.dataset.patient.toLowerCase();
                const serviceName = row.dataset.service.toLowerCase();
                const status = row.dataset.status.toLowerCase();
                return patientName.includes(searchTerm) || 
                       serviceName.includes(searchTerm) || 
                       status.includes(searchTerm);
            });
            filteredMobileAppointments = allMobileAppointments.filter(card => {
                const patientName = card.dataset.patient.toLowerCase();
                const serviceName = card.dataset.service.toLowerCase();
                const status = card.dataset.status.toLowerCase();
                return patientName.includes(searchTerm) || 
                       serviceName.includes(searchTerm) || 
                       status.includes(searchTerm);
            });
            currentPage = 1;
            updateTodayPagination();
        });

        // Pagination buttons
        prevButton.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                updateTodayPagination();
            }
        });

        nextButton.addEventListener('click', function() {
            if (currentPage < Math.ceil(filteredAppointments.length / appointmentsPerPage)) {
                currentPage++;
                updateTodayPagination();
            }
        });

        function updateTodayPagination() {
            // Hide all appointments
            allAppointments.forEach(row => row.style.display = 'none');
            allMobileAppointments.forEach(card => card.style.display = 'none');

            // Calculate start and end indices
            const startIndex = (currentPage - 1) * appointmentsPerPage;
            const endIndex = Math.min(startIndex + appointmentsPerPage, filteredAppointments.length);

            // Show appointments for current page
            for (let i = startIndex; i < endIndex; i++) {
                if (filteredAppointments[i]) {
                    filteredAppointments[i].style.display = '';
                }
                if (filteredMobileAppointments[i]) {
                    filteredMobileAppointments[i].style.display = '';
                }
            }

            // Update pagination info
            currentPageStart.textContent = filteredAppointments.length === 0 ? 0 : startIndex + 1;
            currentPageEnd.textContent = endIndex;
            totalAppointments.textContent = filteredAppointments.length;

            // Update button states
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage >= Math.ceil(filteredAppointments.length / appointmentsPerPage);
        }
    });
</script>
</body>
</html>
