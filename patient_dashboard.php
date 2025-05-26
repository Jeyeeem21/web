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

// Get patient's appointments
$stmt = $pdo->prepare("
    SELECT a.*, s.name as doctor_name, dp.doctor_position as doctor_position
    FROM appointments a
    JOIN staff s ON a.staff_id = s.id
    LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC
");
$stmt->execute([$_SESSION['patient_id']]);
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
    <title>Patient Dashboard</title>
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
</head>
<body class="bg-gray-50 font-sans">
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-heading font-bold text-primary-500">Patient Dashboard</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-secondary">Welcome, <?php echo htmlspecialchars($patient['name']); ?></span>
                    <a href="logout.php" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm hover:scale-105 transition-all duration-200">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Profile Card -->
            <div class="md:col-span-1">
                <div class="bg-gradient-to-br from-primary-50 to-accent-100 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6 h-full flex flex-col justify-between">
                    <div class="text-center mb-6">
                        <img src="<?php echo htmlspecialchars($patient['photo']); ?>" 
                             alt="Profile Photo" 
                             class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-white shadow-md">
                        <h2 class="text-xl font-bold mt-4 text-neutral-dark"><?php echo htmlspecialchars($patient['name']); ?></h2>
                        <p class="text-secondary text-sm">Patient ID: <?php echo htmlspecialchars($patient['id']); ?></p>
                        <button onclick="openEditProfileModal()" class="mt-3 bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-1 rounded-lg text-sm hover:scale-105 transition-all duration-200">
                            <i class="fas fa-edit mr-1"></i> Edit Profile
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-sm font-medium text-secondary">Email</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['email']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-sm font-medium text-secondary">Phone</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['phone']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-sm font-medium text-secondary">Address</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['address']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-birthday-cake text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-sm font-medium text-secondary">Age</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['age']); ?> years old</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-venus-mars text-primary-500 mr-2"></i>
                            <div>
                                <label class="block text-sm font-medium text-secondary">Gender</label>
                                <p class="text-neutral-dark text-sm"><?php echo htmlspecialchars($patient['gender']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="md:col-span-2">
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
            <div class="md:col-span-3 mt-6">
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-base font-medium text-neutral-dark">My Appointments</h2>
                        <button onclick="openAppointmentModal()" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm hover:scale-105 transition-all duration-200">
                            Book New Appointment
                        </button>
                    </div>
                    <!-- Desktop View: Table -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-primary-100">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Doctor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Position</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-primary-100">
                                <?php if (empty($appointments)): ?>
                                    <tr><td colspan="5" class="text-secondary text-center py-4">No appointments found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr class="hover:bg-primary-50 transition-all">
                                            <td class="px-4 py-3 text-neutral-dark text-sm font-medium">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-secondary text-sm">
                                                <?php echo htmlspecialchars($appointment['doctor_position'] ?? 'N/A'); ?>
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
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                                    <div class="mt-2">
                                        <p class="text-xs text-secondary">
                                            <i class="far fa-calendar-alt mr-1"></i><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                        </p>
                                        <p class="text-xs text-secondary">
                                            <i class="far fa-clock mr-1"></i><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
</body>
</html>