<?php
// Prevent any output before JSON response
ob_start();

// Ensure JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

try {
    // Database connection
    require_once 'config/db.php';

    // Get parameters
    $period = $_GET['period'] ?? 'daily';
    $date = $_GET['date'] ?? date('Y-m-d');

    // Debug log
    error_log("Period: " . $period . ", Date: " . $date);

    // Prepare date range and comparison dates
    switch ($period) {
        case 'daily':
            $currentDate = $date;
            $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));
            $startDate = date('Y-m-01', strtotime($date));
            $endDate = date('Y-m-t', strtotime($date));
            break;
        case 'monthly':
            $currentDate = $date . '-01'; // Add day 1 to make it a full date
            $previousDate = date('Y-m-01', strtotime($date . ' -1 month'));
            $startDate = date('Y-01-01', strtotime($date));
            $endDate = date('Y-12-31', strtotime($date));
            break;
        case 'yearly':
            $currentDate = $date . '-01-01'; // Add month and day to make it a full date
            $previousDate = ($date - 1) . '-01-01';
            $startDate = ($date - 4) . '-01-01';
            $endDate = $date . '-12-31';
            break;
        default:
            throw new Exception('Invalid period');
    }

    // Debug log
    error_log("Current Date: " . $currentDate);
    error_log("Previous Date: " . $previousDate);
    error_log("Date Range: " . $startDate . " to " . $endDate);

    // Function to calculate growth percentage
    function calculateGrowth($current, $previous) {
        if ($previous == 0) return $current > 0 ? '+100%' : '0%';
        $growth = (($current - $previous) / $previous) * 100;
        return ($growth >= 0 ? '+' : '') . round($growth) . '%';
    }

    // Get current period stats based on period type
    switch ($period) {
        case 'daily':
            $totalPatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE appointment_date = '$currentDate'")->fetch(PDO::FETCH_ASSOC)['count'];
            $totalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$currentDate'")->fetch(PDO::FETCH_ASSOC)['count'];
            $pendingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending' AND appointment_date = '$currentDate'")->fetch(PDO::FETCH_ASSOC)['count'];
            $totalRevenue = $pdo->query("SELECT SUM(amount) as sum FROM payments WHERE DATE(payment_date) = '$currentDate'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
            break;
        case 'monthly':
            $monthYear = date('Y-m', strtotime($currentDate));
            $totalPatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = '$monthYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $totalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = '$monthYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $pendingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending' AND DATE_FORMAT(appointment_date, '%Y-%m') = '$monthYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $totalRevenue = $pdo->query("SELECT SUM(amount) as sum FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$monthYear'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
            break;
        case 'yearly':
            $year = date('Y', strtotime($currentDate));
            $totalPatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE YEAR(appointment_date) = '$year'")->fetch(PDO::FETCH_ASSOC)['count'];
            $totalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE YEAR(appointment_date) = '$year'")->fetch(PDO::FETCH_ASSOC)['count'];
            $pendingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending' AND YEAR(appointment_date) = '$year'")->fetch(PDO::FETCH_ASSOC)['count'];
            $totalRevenue = $pdo->query("SELECT SUM(amount) as sum FROM payments WHERE YEAR(payment_date) = '$year'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
            break;
    }

    // Get previous period stats for growth calculation
    switch ($period) {
        case 'daily':
            $prevTotalPatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE appointment_date = '$previousDate'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevTotalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$previousDate'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevPendingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending' AND appointment_date = '$previousDate'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevTotalRevenue = $pdo->query("SELECT SUM(amount) as sum FROM payments WHERE DATE(payment_date) = '$previousDate'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
            break;
        case 'monthly':
            $prevMonthYear = date('Y-m', strtotime($previousDate));
            $prevTotalPatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = '$prevMonthYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevTotalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = '$prevMonthYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevPendingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending' AND DATE_FORMAT(appointment_date, '%Y-%m') = '$prevMonthYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevTotalRevenue = $pdo->query("SELECT SUM(amount) as sum FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$prevMonthYear'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
            break;
        case 'yearly':
            $prevYear = date('Y', strtotime($previousDate));
            $prevTotalPatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE YEAR(appointment_date) = '$prevYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevTotalAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE YEAR(appointment_date) = '$prevYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevPendingAppointments = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending' AND YEAR(appointment_date) = '$prevYear'")->fetch(PDO::FETCH_ASSOC)['count'];
            $prevTotalRevenue = $pdo->query("SELECT SUM(amount) as sum FROM payments WHERE YEAR(payment_date) = '$prevYear'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
            break;
    }

    // Calculate growth percentages
    $patientGrowth = calculateGrowth($totalPatients, $prevTotalPatients);
    $appointmentGrowth = calculateGrowth($totalAppointments, $prevTotalAppointments);
    $pendingGrowth = calculateGrowth($pendingAppointments, $prevPendingAppointments);
    $revenueGrowth = calculateGrowth($totalRevenue, $prevTotalRevenue);

    // Debug log
    error_log("Stats - Patients: $totalPatients, Appointments: $totalAppointments, Pending: $pendingAppointments, Revenue: $totalRevenue");
    error_log("Previous Stats - Patients: $prevTotalPatients, Appointments: $prevTotalAppointments, Pending: $prevPendingAppointments, Revenue: $prevTotalRevenue");

    // Chart data
    $patientsChart = ['labels' => [], 'completed' => [], 'cancelled' => []];
    $revenueChart = ['labels' => [], 'amounts' => []];

    if ($period === 'daily') {
        // Daily data for the month (1-31)
        $daysInMonth = date('t', strtotime($startDate));
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $patientsChart['labels'][] = $day;
            
            $completed = $pdo->query("SELECT COUNT(*) as count FROM appointments 
                WHERE status = 'Completed' 
                AND appointment_date = '$dayStr'")->fetch(PDO::FETCH_ASSOC)['count'];
                
            $cancelled = $pdo->query("SELECT COUNT(*) as count FROM appointments 
                WHERE status = 'Cancelled' 
                AND appointment_date = '$dayStr'")->fetch(PDO::FETCH_ASSOC)['count'];
                
            $patientsChart['completed'][] = (int)$completed;
            $patientsChart['cancelled'][] = (int)$cancelled;
            
            $revenue = $pdo->query("SELECT SUM(amount) as sum FROM payments 
                WHERE DATE(payment_date) = '$dayStr'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
                
            $revenueChart['amounts'][] = (float)$revenue;
        }
        $revenueChart['labels'] = $patientsChart['labels'];
    } elseif ($period === 'monthly') {
        // Monthly data for the year (Jan-Dec)
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $year = date('Y', strtotime($date));
        
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = sprintf('%04d-%02d', $year, $month);
            $patientsChart['labels'][] = $months[$month - 1];
            
            $completed = $pdo->query("SELECT COUNT(*) as count FROM appointments 
                WHERE status = 'Completed' 
                AND DATE_FORMAT(appointment_date, '%Y-%m') = '$monthStr'")->fetch(PDO::FETCH_ASSOC)['count'];
                
            $cancelled = $pdo->query("SELECT COUNT(*) as count FROM appointments 
                WHERE status = 'Cancelled' 
                AND DATE_FORMAT(appointment_date, '%Y-%m') = '$monthStr'")->fetch(PDO::FETCH_ASSOC)['count'];
                
            $patientsChart['completed'][] = (int)$completed;
            $patientsChart['cancelled'][] = (int)$cancelled;
            
            $revenue = $pdo->query("SELECT SUM(amount) as sum FROM payments 
                WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$monthStr'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
                
            $revenueChart['amounts'][] = (float)$revenue;
        }
        $revenueChart['labels'] = $patientsChart['labels'];
    } else {
        // Yearly data (last 5 years)
        $currentYear = date('Y', strtotime($date));
        for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
            $patientsChart['labels'][] = $year;
            
            $completed = $pdo->query("SELECT COUNT(*) as count FROM appointments 
                WHERE status = 'Completed' 
                AND YEAR(appointment_date) = '$year'")->fetch(PDO::FETCH_ASSOC)['count'];
                
            $cancelled = $pdo->query("SELECT COUNT(*) as count FROM appointments 
                WHERE status = 'Cancelled' 
                AND YEAR(appointment_date) = '$year'")->fetch(PDO::FETCH_ASSOC)['count'];
                
            $patientsChart['completed'][] = (int)$completed;
            $patientsChart['cancelled'][] = (int)$cancelled;
            
            $revenue = $pdo->query("SELECT SUM(amount) as sum FROM payments 
                WHERE YEAR(payment_date) = '$year'")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;
                
            $revenueChart['amounts'][] = (float)$revenue;
        }
        $revenueChart['labels'] = $patientsChart['labels'];
    }

    $response = [
        'stats' => [
            'totalPatients' => $totalPatients,
            'patientGrowth' => $patientGrowth,
            'totalAppointments' => $totalAppointments,
            'appointmentGrowth' => $appointmentGrowth,
            'pendingAppointments' => $pendingAppointments,
            'pendingGrowth' => $pendingGrowth,
            'totalRevenue' => number_format($totalRevenue, 2),
            'revenueGrowth' => $revenueGrowth
        ],
        'charts' => [
            'patients' => $patientsChart,
            'revenue' => $revenueChart
        ]
    ];

    // Clear any output buffer
    ob_clean();
    
    // Send JSON response
    echo json_encode($response);

} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    // Log error
    error_log('Error in get_dashboard_data.php: ' . $e->getMessage());
    
    // Return JSON error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// End output buffering and send
ob_end_flush();
exit;
?>