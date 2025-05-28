<?php
// Prevent any output before JSON response
ob_start();

// Ensure JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

try {
    // Database connection
    require_once 'config/db.php'; // Ensure $pdo is defined with error mode set to exceptions

    // Get and sanitize parameters
    $period = filter_input(INPUT_GET, 'period') ?? 'daily';
    $date = filter_input(INPUT_GET, 'date') ?? date('Y-m-d');

    // Validate period
    if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
        throw new Exception('Invalid period parameter');
    }

    // Validate date format
    if ($period === 'daily' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid daily date format');
    } elseif ($period === 'monthly' && !preg_match('/^\d{4}-\d{2}$/', $date)) {
        throw new Exception('Invalid monthly date format');
    } elseif ($period === 'yearly' && !preg_match('/^\d{4}$/', $date)) {
        throw new Exception('Invalid yearly date format');
    }

    // Debug log
    error_log("Period: $period, Date: $date");

    // Prepare date range and comparison dates using DateTime
    $dateObj = new DateTime($date, new DateTimeZone('Asia/Manila'));
    switch ($period) {
        case 'daily':
            $currentDate = $dateObj->format('Y-m-d');
            $previousDate = (clone $dateObj)->modify('-1 day')->format('Y-m-d');
            $startDate = $dateObj->modify('first day of this month')->format('Y-m-d');
            $endDate = $dateObj->modify('last day of this month')->format('Y-m-d');
            break;
        case 'monthly':
            $currentDate = $dateObj->format('Y-m-01');
            $previousDate = (clone $dateObj)->modify('-1 month')->format('Y-m-01');
            $startDate = $dateObj->modify('first day of January this year')->format('Y-m-d');
            $endDate = $dateObj->modify('last day of December this year')->format('Y-m-d');
            break;
        case 'yearly':
            $currentDate = $dateObj->format('Y-01-01');
            $previousDate = (clone $dateObj)->modify('-1 year')->format('Y-01-01');
            $startDate = (clone $dateObj)->modify('-4 years')->format('Y-01-01');
            $endDate = $dateObj->format('Y-12-31');
            break;
    }

    // Debug log
    error_log("Current Date: $currentDate");
    error_log("Previous Date: $previousDate");
    error_log("Date Range: $startDate to $endDate");

    // Function to calculate growth percentage
    function calculateGrowth($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }
        $growth = (($current - $previous) / $previous) * 100;
        return ($growth >= 0 ? '+' : '') . round($growth) . '%';
    }

    // Get current and previous period stats using stored procedure
    $statsStmt = $pdo->prepare("CALL GetDashboardStats(:period, :current_date, :previous_date)");
    $result = $statsStmt->execute([
        ':period' => $period,
        ':current_date' => $currentDate,
        ':previous_date' => $previousDate
    ]);
    if (!$result) {
        throw new Exception('Failed to execute stored procedure GetDashboardStats');
    }

    // Fetch current period stats
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($stats === false) {
        error_log("No stats returned for current period");
        $stats = [
            'total_patients' => 0,
            'total_appointments' => 0,
            'scheduled_appointments' => 0,
            'total_revenue' => 0
        ];
    }
    error_log("Current Stats: " . print_r($stats, true));
    $statsStmt->closeCursor();

    // Move to previous period stats
    if (!$statsStmt->nextRowset()) {
        error_log("No previous period stats available");
        $prevStats = [
            'total_patients' => 0,
            'total_appointments' => 0,
            'scheduled_appointments' => 0,
            'total_revenue' => 0
        ];
    } else {
        $prevStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($prevStats === false) {
            error_log("No previous stats returned");
            $prevStats = [
                'total_patients' => 0,
                'total_appointments' => 0,
                'scheduled_appointments' => 0,
                'total_revenue' => 0
            ];
        }
        error_log("Previous Stats: " . print_r($prevStats, true));
    }
    $statsStmt->closeCursor();

    $totalPatients = (int)($stats['total_patients'] ?? 0);
    $totalAppointments = (int)($stats['total_appointments'] ?? 0);
    $scheduledAppointments = (int)($stats['scheduled_appointments'] ?? 0);
    $totalRevenue = (float)($stats['total_revenue'] ?? 0);

    $prevTotalPatients = (int)($prevStats['total_patients'] ?? 0);
    $prevTotalAppointments = (int)($prevStats['total_appointments'] ?? 0);
    $prevScheduledAppointments = (int)($prevStats['scheduled_appointments'] ?? 0);
    $prevTotalRevenue = (float)($prevStats['total_revenue'] ?? 0);

    // Calculate growth percentages
    $patientGrowth = calculateGrowth($totalPatients, $prevTotalPatients);
    $appointmentGrowth = calculateGrowth($totalAppointments, $prevTotalAppointments);
    $scheduledGrowth = calculateGrowth($scheduledAppointments, $prevScheduledAppointments);
    $revenueGrowth = calculateGrowth($totalRevenue, $prevTotalRevenue);

    // Debug log
    error_log("Stats - Patients: $totalPatients, Appointments: $totalAppointments, Scheduled: $scheduledAppointments, Revenue: $totalRevenue");
    error_log("Previous Stats - Patients: $prevTotalPatients, Appointments: $prevTotalAppointments, Scheduled: $prevScheduledAppointments, Revenue: $prevTotalRevenue");

    // Chart data
    $patientsChart = ['labels' => [], 'completed' => [], 'cancelled' => []];
    $revenueChart = ['labels' => [], 'amounts' => []];
    $serviceChart = ['labels' => [], 'data' => [], 'backgroundColor' => [], 'borderColor' => []];

    // Colors for service chart
    $colors = [
        '#14b8a6', '#0d9488', '#10b981', '#059669', '#047857',
        '#d97706', '#b45309', '#92400e', '#78350f', '#854d0e'
    ];
    $borderColors = array_map(function($color) { return $color; }, $colors);
    $backgroundColors = array_map(function($color) { return $color . '99'; }, $colors); // 60% opacity

    if ($period === 'daily') {
        $daysInMonth = $dateObj->format('t');
        $year = $dateObj->format('Y');
        $month = $dateObj->format('m');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $patientsChart['labels'][] = $day;

            // Patient and revenue data
            $query = "
                SELECT
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    (SELECT SUM(amount) FROM payments WHERE DATE(payment_date) = :payment_date) AS revenue
                FROM appointments WHERE appointment_date = :appointment_date";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':payment_date' => $dayStr, ':appointment_date' => $dayStr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $patientsChart['completed'][] = (int)($row['completed'] ?? 0);
            $patientsChart['cancelled'][] = (int)($row['cancelled'] ?? 0);
            $revenueChart['amounts'][] = (float)($row['revenue'] ?? 0);
        }
        $revenueChart['labels'] = $patientsChart['labels'];

        // Service data for selected day using view
        $serviceQuery = "
            SELECT service_name, appointment_count AS count
            FROM TopServicesView
            WHERE appointment_date = ?
            ORDER BY appointment_count DESC
            LIMIT 5";
        $serviceStmt = $pdo->prepare($serviceQuery);
        $serviceStmt->execute([$currentDate]);
        $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        $serviceStmt->closeCursor();

        foreach ($services as $index => $service) {
            $serviceChart['labels'][] = $service['service_name'];
            $serviceChart['data'][] = (int)$service['count'];
            $serviceChart['backgroundColor'][] = $backgroundColors[$index % count($backgroundColors)];
            $serviceChart['borderColor'][] = $borderColors[$index % count($borderColors)];
        }
    } elseif ($period === 'monthly') {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $year = $dateObj->format('Y');
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = sprintf('%04d-%02d', $year, $month);
            $patientsChart['labels'][] = $months[$month - 1];

            // Patient and revenue data
            $query = "
                SELECT
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    (SELECT SUM(amount) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = :payment_date) AS revenue
                FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = :appointment_date";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':payment_date' => $monthStr, ':appointment_date' => $monthStr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $patientsChart['completed'][] = (int)($row['completed'] ?? 0);
            $patientsChart['cancelled'][] = (int)($row['cancelled'] ?? 0);
            $revenueChart['amounts'][] = (float)($row['revenue'] ?? 0);
        }
        $revenueChart['labels'] = $patientsChart['labels'];

        // Service data for selected month using view
        $serviceQuery = "
            SELECT service_name, appointment_count AS count
            FROM TopServicesView
            WHERE DATE_FORMAT(appointment_date, '%Y-%m') = ?
            ORDER BY appointment_count DESC
            LIMIT 5";
        $serviceStmt = $pdo->prepare($serviceQuery);
        $serviceStmt->execute([date('Y-m', strtotime($currentDate))]);
        $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        $serviceStmt->closeCursor();

        foreach ($services as $index => $service) {
            $serviceChart['labels'][] = $service['service_name'];
            $serviceChart['data'][] = (int)$service['count'];
            $serviceChart['backgroundColor'][] = $backgroundColors[$index % count($backgroundColors)];
            $serviceChart['borderColor'][] = $borderColors[$index % count($borderColors)];
        }
    } else { // yearly
        $currentYear = $dateObj->format('Y');
        for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
            $patientsChart['labels'][] = $year;

            // Patient and revenue data
            $query = "
                SELECT
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    (SELECT SUM(amount) FROM payments WHERE YEAR(payment_date) = :payment_date) AS revenue
                FROM appointments WHERE YEAR(appointment_date) = :appointment_date";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':payment_date' => $year, ':appointment_date' => $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $patientsChart['completed'][] = (int)($row['completed'] ?? 0);
            $patientsChart['cancelled'][] = (int)($row['cancelled'] ?? 0);
            $revenueChart['amounts'][] = (float)($row['revenue'] ?? 0);
        }
        $revenueChart['labels'] = $patientsChart['labels'];

        // Service data for selected year using view
        $serviceQuery = "
            SELECT service_name, appointment_count AS count
            FROM TopServicesView
            WHERE YEAR(appointment_date) = ?
            ORDER BY appointment_count DESC
            LIMIT 5";
        $serviceStmt = $pdo->prepare($serviceQuery);
        $serviceStmt->execute([date('Y', strtotime($currentDate))]);
        $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        $serviceStmt->closeCursor();

        foreach ($services as $index => $service) {
            $serviceChart['labels'][] = $service['service_name'];
            $serviceChart['data'][] = (int)$service['count'];
            $serviceChart['backgroundColor'][] = $backgroundColors[$index % count($backgroundColors)];
            $serviceChart['borderColor'][] = $borderColors[$index % count($borderColors)];
        }
    }

    $response = [
        'stats' => [
            'totalPatients' => $totalPatients,
            'patientGrowth' => $patientGrowth,
            'totalAppointments' => $totalAppointments,
            'appointmentGrowth' => $appointmentGrowth,
            'scheduledAppointments' => $scheduledAppointments,
            'scheduledGrowth' => $scheduledGrowth,
            'totalRevenue' => number_format($totalRevenue, 2),
            'revenueGrowth' => $revenueGrowth
        ],
        'charts' => [
            'patients' => $patientsChart,
            'revenue' => $revenueChart,
            'services' => $serviceChart
        ]
    ];

    // Debug log for final response
    error_log("Final Response: " . json_encode($response));

    // Clear output buffer
    ob_clean();

    // Send JSON response
    echo json_encode($response);

} catch (Exception $e) {
    // Clear output buffer
    ob_clean();

    // Log error
    error_log('Error in get_dashboard_data.php: ' . $e->getMessage() . ': ' . $e->getFile() . ': ' . $e->getLine());

    // Return JSON error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// End output buffering
ob_end_flush();
exit;
?>