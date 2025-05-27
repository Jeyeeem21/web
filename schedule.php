<?php
// Enable error logging
ini_set('display_errors', 0);

// Set timezone to Philippine Standard Time (PHT, UTC+8)
date_default_timezone_set('Asia/Manila');

// Prevent browser caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection
require_once 'config/db.php';

// Initialize variables
$error = null;
$success = null;
$currentDate = date('Y-m-d'); // Uses PHT
$currentTime = date('H:i:s'); // Uses PHT
$selectedDate = isset($_GET['date']) ? $_GET['date'] : $currentDate;

// Handle AJAX requests first, before any HTML output
if (isset($_GET['action'])) {
    // Ensure we're sending JSON response
    header('Content-Type: application/json');
    
    // Get available time slots for a specific date
    if ($_GET['action'] == 'get_time_slots' && isset($_GET['date'], $_GET['service_id'], $_GET['doctor_id'])) {
        try {
            ob_clean();
            
            $date = $_GET['date'];
            $service_id = $_GET['service_id'];
            $doctor_id = $_GET['doctor_id'];
            
            // Validate date
            if (strtotime($date) < strtotime($currentDate)) {
                echo json_encode(['success' => false, 'message' => 'Cannot book appointments for past dates']);
                exit();
            }
            
            // Get clinic details
            $stmt = $pdo->query("SELECT * FROM clinic_details ORDER BY created_at DESC LIMIT 1");
            $clinic = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$clinic) {
                echo json_encode(['success' => false, 'message' => 'Clinic details not found']);
                exit();
            }
            
            // Get day of week for the selected date
            $dayOfWeek = date('l', strtotime($date));
            error_log("Day of Week: $dayOfWeek");
            
            // Get clinic hours for the day
            $clinicHours = '';
            if ($dayOfWeek == 'Sunday') {
                $clinicHours = $clinic['hours_sunday'] ?? 'Closed';
            } else if ($dayOfWeek == 'Saturday') {
                $clinicHours = $clinic['hours_saturday'] ?? 'Closed';
            } else {
                $clinicHours = $clinic['hours_weekdays'] ?? 'Closed';
            }
            error_log("Clinic Hours: $clinicHours");
            
            if ($clinicHours == 'Closed') {
                echo json_encode(['success' => false, 'message' => "Clinic is closed on $dayOfWeek"]);
                exit();
            }
            
            // Parse clinic hours (e.g., "9:00 AM - 5:00 PM")
            $hours = explode(' - ', $clinicHours);
            $startTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[0]))));
            $endTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[1]))));
            error_log("Clinic Start Time: $startTime, End Time: $endTime");
            
            // Get doctor's schedule
            $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day != :rest_day");
            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':rest_day' => $dayOfWeek
            ]);
            $doctorSchedule = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Doctor Schedule: " . print_r($doctorSchedule, true));
            
            if ($doctorSchedule) {
                $startTime = $doctorSchedule['start_time'];
                $endTime = $doctorSchedule['end_time'];
                error_log("Using Doctor's Hours - Start: $startTime, End: $endTime");
            }
            
            // Get existing appointments for the selected date and doctor
            $stmt = $pdo->prepare("SELECT HOUR(appointment_time) as booked_hour 
                                  FROM appointments 
                                  WHERE staff_id = :doctor_id 
                                  AND appointment_date = :date 
                                  AND status != 'Cancelled'");
            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':date' => $date
            ]);
            $bookedHours = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            error_log("Booked Hours: " . print_r($bookedHours, true));
            
            // Generate available time slots (hourly)
            $availableSlots = [];
            $startHour = intval(date('H', strtotime($startTime)));
            $endHour = intval(date('H', strtotime($endTime)));
            
            // If date is today, adjust start hour based on current time
            if ($date === $currentDate) {
                $currentHour = intval(date('H', strtotime($currentTime)));
                $currentMinute = intval(date('i', strtotime($currentTime)));
                $startHour = max($startHour, $currentHour + ($currentMinute > 0 ? 1 : 0));
            }
            
            error_log("Start Hour: $startHour, End Hour: $endHour");
            
            // Convert clinic hours to 24-hour format for comparison
            $clinicStartHour = intval(date('H', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[0])))));
            $clinicEndHour = intval(date('H', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[1])))));
            
            for ($hour = $clinicStartHour; $hour < $clinicEndHour; $hour++) {
                if ($hour != 12) { // Skip lunch hour
                    if (!in_array($hour, $bookedHours)) {
                        $timeStr = sprintf('%02d:00:00', $hour);
                        $formattedSlot = date('h:i A', strtotime($timeStr));
                        $availableSlots[] = $formattedSlot;
                    }
                }
            }
            error_log("Available Slots: " . print_r($availableSlots, true));
            
            echo json_encode(['success' => true, 'slots' => $availableSlots]);
            exit();
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        } catch (Exception $e) {
            error_log("General Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
    }

    // Get appointment details for rescheduling
    if ($_GET['action'] == 'get_appointment_details' && isset($_GET['id'])) {
        try {
            $id = $_GET['id'];
            
            $stmt = $pdo->prepare("SELECT a.*, 
                                  p.id as patient_id,
                                  s.id as service_id,
                                  st.id as doctor_id
                                  FROM appointments a
                                  JOIN patients p ON a.patient_id = p.id
                                  JOIN services s ON a.service_id = s.id
                                  JOIN staff st ON a.staff_id = st.id
                                  WHERE a.id = :id");
            $stmt->execute([':id' => $id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'appointment' => [
                        'id' => $appointment['id'],
                        'patient_id' => $appointment['patient_id'],
                        'service_id' => $appointment['service_id'],
                        'doctor_id' => $appointment['doctor_id'],
                        'appointment_date' => $appointment['appointment_date'],
                        'appointment_time' => date('h:00 A', strtotime($appointment['appointment_time'])),
                        'remarks' => $appointment['remarks'] ?? ''
                    ]
                ]);
                exit();
            } else {
                throw new Exception('Appointment not found');
            }
        } catch (Exception $e) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    // Get appointment details for payment modal
    if ($_GET['action'] == 'get_appointment_payment_details' && isset($_GET['id'])) {
        try {
            $id = $_GET['id'];
            
            $stmt = $pdo->prepare("SELECT a.id, p.name as patient_name, s.service_name, s.price as service_price
                                  FROM appointments a
                                  JOIN patients p ON a.patient_id = p.id
                                  JOIN services s ON a.service_id = s.id
                                  WHERE a.id = :id");
            $stmt->execute([':id' => $id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'appointment' => $appointment
                ]);
                exit();
            } else {
                throw new Exception('Appointment details not found.');
            }
        } catch (Exception $e) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    // Get receipt details
    if ($_GET['action'] === 'get_receipt_details' && isset($_GET['id'])) {
        // Ensure no output before JSON response
        ob_clean();
        header('Content-Type: application/json');
        
        try {
            // Check if payment table exists, if not create it
            $stmt = $pdo->query("SHOW TABLES LIKE 'payment'");
            if ($stmt->rowCount() === 0) {
                // Create payment table
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS payment (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        appointment_id INT NOT NULL,
                        amount DECIMAL(10,2) NOT NULL,
                        payment_method VARCHAR(50) NOT NULL,
                        payment_date DATE NOT NULL,
                        payment_time TIME NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
                    )
                ");
            }

            // Get appointment and payment details
            $stmt = $pdo->prepare("
                SELECT 
                    a.id,
                    a.appointment_date,
                    a.appointment_time,
                    p.name as patient_name,
                    s.service_name,
                    s.price as service_price,
                    COALESCE(pm.payment_date, a.appointment_date) as payment_date,
                    COALESCE(pm.payment_time, a.appointment_time) as payment_time,
                    COALESCE(pm.amount, s.price) as amount,
                    COALESCE(pm.payment_method, 'Cash') as payment_method,
                    st.name as doctor_name,
                    st.id as doctor_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN services s ON a.service_id = s.id
                JOIN staff st ON a.staff_id = st.id
                LEFT JOIN payment pm ON a.id = pm.appointment_id
                WHERE a.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$receipt) {
                throw new Exception("Receipt not found for appointment ID: " . $_GET['id']);
            }

            // Get clinic details with logo
            $stmt = $pdo->query("SELECT * FROM clinic_details ORDER BY created_at DESC LIMIT 1");
            $clinic = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$clinic) {
                throw new Exception("Clinic details not found");
            }

            // Get doctor details
            $stmt = $pdo->prepare("
                SELECT s.*, dp.doctor_position 
                FROM staff s 
                LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                WHERE s.id = ?
            ");
            $stmt->execute([$receipt['doctor_id']]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doctor) {
                throw new Exception("Doctor details not found for ID: " . $receipt['doctor_id']);
            }

            // Format dates and times
            $receipt['payment_date'] = date('F j, Y', strtotime($receipt['payment_date']));
            $receipt['payment_time'] = date('g:i A', strtotime($receipt['payment_time']));
            $receipt['appointment_date'] = date('F j, Y', strtotime($receipt['appointment_date']));
            $receipt['appointment_time'] = date('g:i A', strtotime($receipt['appointment_time']));

            // Prepare clinic details
            $clinicDetails = [
                'name' => $clinic['clinic_name'],
                'logo' => $clinic['logo'],
                'address' => $clinic['address'],
                'phone' => $clinic['phone'],
                'email' => $clinic['email'],
                'hours_weekdays' => $clinic['hours_weekdays'],
                'hours_saturday' => $clinic['hours_saturday'],
                'hours_sunday' => $clinic['hours_sunday']
            ];

            // Log successful response for debugging
            error_log("Receipt details retrieved successfully for appointment ID: " . $_GET['id']);

            echo json_encode([
                'success' => true,
                'receipt' => $receipt,
                'clinic' => $clinicDetails,
                'doctor' => $doctor
            ]);
            exit;
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Error in get_receipt_details: " . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'debug_info' => [
                    'appointment_id' => $_GET['id'] ?? 'not set',
                    'error' => $e->getMessage()
                ]
            ]);
            exit;
        }
    }
}

// Get clinic details for operating hours
try {
    $stmt = $pdo->query("SELECT * FROM clinic_details WHERE id = 1");
    $clinic = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$clinic) {
        throw new Exception("No clinic details found.");
    }
} catch (PDOException | Exception $e) {
    $error = "Error fetching clinic details: " . $e->getMessage();
    error_log("Clinic Details Error: " . $e->getMessage());
    $clinic = [];
}

// Process form submission for adding/editing appointments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_appointment') {
        $patient_id = $_POST['patient_id'] ?? '';
        $staff_id = $_POST['staff_id'] ?? '';
        $service_id = $_POST['service_id'] ?? '';
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $appointment_id = $_POST['appointment_id'] ?? null;
        $status = 'Scheduled';
        
        // Validate inputs
        if (empty($patient_id) || empty($staff_id) || empty($service_id) || empty($appointment_date) || empty($appointment_time)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }

        try {
            // Strict date validation
            $today = date('Y-m-d');
            $selectedDate = date('Y-m-d', strtotime($appointment_date));
            
            if ($selectedDate < $today) {
                throw new Exception("Cannot book appointments for past dates. Please select today or a future date.");
            }

            // Check if patient status is active
            $stmt = $pdo->prepare("SELECT status FROM patients WHERE id = :id");
            $stmt->execute([':id' => $patient_id]);
            $patientStatus = $stmt->fetchColumn();
            
            if ($patientStatus != 1) {
                throw new Exception("Selected patient is not active. Only active patients can be scheduled.");
            }

            // Get day of week for the selected date
            $dayOfWeek = date('l', strtotime($appointment_date));
            
            // Check if it's doctor's rest day
            $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day = :rest_day");
            $stmt->execute([':doctor_id' => $staff_id, ':rest_day' => $dayOfWeek]);
            $restDay = $stmt->fetch();
            
            if ($restDay) {
                throw new Exception("Doctor is not available on " . $dayOfWeek);
            }

            // Get clinic hours for the day
            $clinicHours = '';
            if ($dayOfWeek == 'Sunday') {
                $clinicHours = $clinic['hours_sunday'] ?? 'Closed';
            } else if ($dayOfWeek == 'Saturday') {
                $clinicHours = $clinic['hours_saturday'] ?? 'Closed';
            } else {
                $clinicHours = $clinic['hours_weekdays'] ?? 'Closed';
            }

            if ($clinicHours == 'Closed') {
                throw new Exception("Clinic is closed on " . $dayOfWeek);
            }

            // Parse clinic hours
            $hours = explode(' - ', $clinicHours);
            $startTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[0]))));
            $endTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[1]))));
            
            // Get doctor's schedule
            $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day != :rest_day");
            $stmt->execute([':doctor_id' => $staff_id, ':rest_day' => $dayOfWeek]);
            $doctorSchedule = $stmt->fetch();
            
            if ($doctorSchedule) {
                $startTime = $doctorSchedule['start_time'];
                $endTime = $doctorSchedule['end_time'];
            }
            
            // Extract hour from appointment time (e.g., "9:00 AM" -> 9)
            $appointmentHour = intval(date('H', strtotime($appointment_time)));
            
            // Check if appointment time is within clinic hours
            $startHour = intval(date('H', strtotime($startTime)));
            $endHour = intval(date('H', strtotime($endTime)));
            
            if ($appointmentHour < $startHour || $appointmentHour >= $endHour) {
                throw new Exception("Appointment time is outside clinic hours.");
            }

            // Check if slot is already booked (excluding the current appointment if rescheduling)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                                  WHERE staff_id = :staff_id 
                                  AND appointment_date = :date 
                                  AND HOUR(appointment_time) = :hour 
                                  AND status != 'Cancelled'
                                  AND id != :appointment_id");
            $stmt->execute([
                ':staff_id' => $staff_id, 
                ':date' => $appointment_date, 
                ':hour' => $appointmentHour,
                ':appointment_id' => $appointment_id ?? 0
            ]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("This time slot is already booked.");
            }

            // Create time in database format (HH:00:00)
            $dbTime = sprintf('%02d:00:00', $appointmentHour);
            
            if ($appointment_id) {
                // Update existing appointment
                $sql = "UPDATE appointments SET 
                        patient_id = :patient_id,
                        staff_id = :staff_id,
                        service_id = :service_id,
                        appointment_date = :appointment_date,
                        appointment_time = :appointment_time,
                        status = 'Scheduled',
                        remarks = :remarks,
                        updated_at = NOW()
                        WHERE id = :appointment_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':patient_id' => $patient_id,
                    ':staff_id' => $staff_id,
                    ':service_id' => $service_id,
                    ':appointment_date' => $appointment_date,
                    ':appointment_time' => $dbTime,
                    ':remarks' => $remarks,
                    ':appointment_id' => $appointment_id
                ]);
                $message = 'Appointment rescheduled successfully!';
            } else {
                // Insert new appointment
                $sql = "INSERT INTO appointments (patient_id, staff_id, service_id, appointment_date, 
                        appointment_time, status, remarks, created_at) 
                        VALUES (:patient_id, :staff_id, :service_id, :appointment_date, 
                        :appointment_time, 'Scheduled', :remarks, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':patient_id' => $patient_id,
                    ':staff_id' => $staff_id,
                    ':service_id' => $service_id,
                    ':appointment_date' => $appointment_date,
                    ':appointment_time' => $dbTime,
                    ':remarks' => $remarks
                ]);
                $message = 'Appointment scheduled successfully!';
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit();

        } catch (Exception $e) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }
    
    if ($_POST['action'] == 'update_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        
        try {
            // Update appointment status
            $sql = "UPDATE appointments SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([':id' => $id, ':status' => $status]);
            
            if ($result) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Appointment status updated successfully!']);
                exit();
            } else {
                throw new Exception("Failed to update appointment status");
            }
        } catch (PDOException $e) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        } catch (Exception $e) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }

    // Handle recording payment
    if ($_POST['action'] == 'record_payment' && isset($_POST['appointment_id'], $_POST['amount'], $_POST['payment_method'])) {
        try {
            $appointment_id = $_POST['appointment_id'];
            $amount = $_POST['amount'];
            $payment_method = $_POST['payment_method'];
            $status = 'Completed';

            // Start transaction
            $pdo->beginTransaction();

            // Update appointment status to Completed
            $sql = "UPDATE appointments SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $appointment_id, ':status' => $status]);

            // Insert payment record
            $sql = "INSERT INTO payments (appointment_id, amount, payment_method, payment_date) 
                    VALUES (:appointment_id, :amount, :payment_method, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':appointment_id' => $appointment_id,
                ':amount' => $amount,
                ':payment_method' => $payment_method
            ]);

            // Commit transaction
            $pdo->commit();

            // Send success response
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Payment recorded and appointment completed!']);
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error recording payment: ' . $e->getMessage()]);
            exit();
        }
    }
}

// Get all active services
try {
    $stmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY service_name ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching services: " . $e->getMessage();
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
    $error = "Error fetching doctors: " . $e->getMessage();
    $doctors = [];
}

// Get all ACTIVE patients (status = 1)
try {
    $stmt = $pdo->query("SELECT * FROM patients WHERE status = 1 ORDER BY name ASC");
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching patients: " . $e->getMessage();
    $patients = [];
}

// Get appointments for the selected date
try {
    $stmt = $pdo->prepare("SELECT a.*, 
                          p.id as patient_id, 
                          p.name as patient_name,
                          s.id as service_id, 
                          s.service_name,
                          s.time as service_duration,
                          st.id as doctor_id,
                          st.name as doctor_name,
                          dp.doctor_position
                          FROM appointments a
                          JOIN patients p ON a.patient_id = p.id
                          JOIN services s ON a.service_id = s.id
                          JOIN staff st ON a.staff_id = st.id
                          JOIN doctor_position dp ON st.doctor_position_id = dp.id
                          WHERE a.appointment_date = :date
                          ORDER BY a.appointment_time ASC");
    $stmt->execute([':date' => $selectedDate]);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching appointments: " . $e->getMessage();
    $appointments = [];
}

// Generate calendar data
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Ensure valid month and year
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$monthName = $dateComponents['month'];
$dayOfWeek = $dateComponents['wday'];

// Get appointment counts for each day of the month
$appointmentCounts = [];
try {
    $startDate = date('Y-m-d', $firstDayOfMonth);
    $endDate = date('Y-m-d', mktime(0, 0, 0, $month, $numberDays, $year));
    
    $stmt = $pdo->prepare("SELECT appointment_date, COUNT(*) as count 
                          FROM appointments 
                          WHERE appointment_date BETWEEN :start_date AND :end_date 
                          AND status != 'Cancelled'
                          GROUP BY appointment_date");
    $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    
    while ($row = $stmt->fetch()) {
        $appointmentCounts[$row['appointment_date']] = $row['count'];
    }
} catch (PDOException $e) {
    $error = "Error fetching appointment counts: " . $e->getMessage();
}

// Previous and next month links
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// At the top of the PHP file, add this to handle pagination AJAX request
if (isset($_GET['action']) && $_GET['action'] == 'get_paginated_appointments') {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 5; // Items per page
    $offset = ($page - 1) * $perPage;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    try {
        // Base query
        $baseQuery = "FROM appointments a
                     JOIN patients p ON a.patient_id = p.id
                     JOIN services s ON a.service_id = s.id
                     JOIN staff st ON a.staff_id = st.id
                     JOIN doctor_position dp ON st.doctor_position_id = dp.id
                     WHERE a.appointment_date = :date";
        
        // Add search condition if search query exists
        if (!empty($search)) {
            $baseQuery .= " AND (
                p.name LIKE :search 
                OR s.service_name LIKE :search 
                OR st.name LIKE :search 
                OR a.status LIKE :search
            )";
        }
        
        // Count total appointments for pagination
        $countStmt = $pdo->prepare("SELECT COUNT(*) " . $baseQuery);
        $countParams = [':date' => $selectedDate];
        if (!empty($search)) {
            $countParams[':search'] = "%$search%";
        }
        $countStmt->execute($countParams);
        $totalAppointments = $countStmt->fetchColumn();
        $totalPages = ceil($totalAppointments / $perPage);
        
        // Get paginated appointments
        $stmt = $pdo->prepare("SELECT a.*, 
                             p.id as patient_id, 
                             p.name as patient_name,
                             s.id as service_id, 
                             s.service_name,
                             s.time as service_duration,
                             st.id as doctor_id,
                             st.name as doctor_name,
                             dp.doctor_position
                             " . $baseQuery . "
                             ORDER BY 
                                CASE a.status
                                    WHEN 'Scheduled' THEN 1
                                    WHEN 'Re-scheduled' THEN 1
                                    WHEN 'Completed' THEN 2
                                    WHEN 'Cancelled' THEN 3
                                    ELSE 4
                                END,
                                a.appointment_time ASC
                             LIMIT :offset, :perPage");
        
        $stmt->bindValue(':date', $selectedDate, PDO::PARAM_STR);
        if (!empty($search)) {
            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'appointments' => $appointments,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ]);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error fetching appointments: ' . $e->getMessage()]);
        exit();
    }
}
?>

<div id="schedule" class="space-y-8 bg-neutral-light p-6 md:p-8 animate-fade-in">
    <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Appointment Schedule</h2>
    
    <!-- Success/Error Message -->
    <?php if (isset($_GET['success']) || $error || $success): ?>
    <div id="alert" class="bg-<?php echo $error ? 'red' : 'success'; ?>-50 border border-<?php echo $error ? 'red' : 'success'; ?>-200 text-<?php echo $error ? 'red' : 'success'; ?>-800 px-3 py-2 rounded-md text-sm flex justify-between items-center">
        <span>
            <?php 
            if ($error) {
                echo htmlspecialchars($error);
            } elseif ($success) {
                echo htmlspecialchars($success);
            } elseif ($_GET['success'] == 'appointment_added') {
                echo 'Appointment scheduled successfully!';
            } elseif ($_GET['success'] == 'status_updated') {
                echo 'Appointment status updated successfully!';
            }
            ?>
        </span>
        <button type="button" onclick="document.getElementById('alert').style.display = 'none'" class="text-<?php echo $error ? 'red' : 'success'; ?>-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <script>
        // Auto hide alert after 5 seconds
        setTimeout(function() {
            const alert = document.getElementById('alert');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);
    </script>
    <?php endif; ?>
    
    <div class="space-y-6">
        <!-- Calendar Section -->
        <div class="bg-white rounded-xl shadow-sm border border-primary-100 p-6 animate-slide-up">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-neutral-dark"><?php echo $monthName . ' ' . $year; ?></h3>
                <div class="flex space-x-2">
                    <a href="index.php?page=schedule&month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="p-2 rounded-lg hover:bg-primary-50 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <a href="index.php?page=schedule&month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="p-2 rounded-lg hover:bg-primary-50 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
            <div class="grid grid-cols-7 gap-2 text-center mb-2">
                <div class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Sun</div>
                <div class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Mon</div>
                <div class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Tue</div>
                <div class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Wed</div>
                <div class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Thu</div>
                <div class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Fri</div>
                <div class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Sat</div>
            </div>
            <div class="grid grid-cols-7 gap-2">
                <?php
                // Add blank cells for days before the first day of the month
                for ($i = 0; $i < $dayOfWeek; $i++) {
                    echo '<div class="relative h-16 p-1 border rounded-lg text-gray-400"></div>';
                }
                
                // Add cells for each day of the month
                for ($day = 1; $day <= $numberDays; $day++) {
                    $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                    
                    // Use timestamp comparison for more accurate date checks
                    $dateTimestamp = strtotime($date);
                    $currentTimestamp = strtotime($currentDate);
                    $selectedTimestamp = strtotime($selectedDate);
                    
                    // Explicitly check if this date is the current date to apply 'today' class
                    $isToday = ($dateTimestamp === $currentTimestamp);
                    $isSelected = ($dateTimestamp === $selectedTimestamp);
                    $isPast = ($dateTimestamp < $currentTimestamp);
                    
                    // Check if there are appointments for this day
                    $appointmentCount = isset($appointmentCounts[$date]) ? $appointmentCounts[$date] : 0;
                    $appointmentClass = $appointmentCount > 0 ? 'bg-primary-50' : '';
                    
                    // Apply todayClass ONLY if it is the current date
                    $todayClass = $isToday ? 'bg-primary-100 font-bold border-2 border-primary-500' : '';
                    
                    // Apply selectedClass if it is the selected date, but NOT today
                    $selectedClass = ($isSelected && !$isToday) ? 'border-primary-500 border-2' : '';
                    
                    $pastClass = $isPast ? 'bg-gray-100 text-gray-500' : '';
                    
                    echo '<div class="relative h-16 p-1 border rounded-lg ' . $appointmentClass . ' ' . $todayClass . ' ' . $selectedClass . ' ' . $pastClass . '">
                        <a href="index.php?page=schedule&date=' . $date . '&month=' . $month . '&year=' . $year . '" class="block h-full w-full">
                            <div class="text-sm">' . $day . '</div>';
                    
                    if ($appointmentCount > 0) {
                        echo '<div class="text-xs text-primary-600 font-medium">' . $appointmentCount . ' appt</div>';
                    }
                    
                    if ($appointmentCount > 0) {
                        echo '<div class="absolute bottom-1 left-0 right-0 flex justify-center"><div class="h-1 w-1 rounded-full bg-primary-500"></div></div>';
                    }
                    
                    echo '</a></div>';
                }
                
                // Add blank cells for days after the last day of the month
                $totalCells = $dayOfWeek + $numberDays;
                $remainingCells = 42 - $totalCells; // 6 rows of 7 days
                if ($remainingCells > 7) $remainingCells -= 7; // Don't show an extra row if not needed
                
                for ($i = 0; $i < $remainingCells; $i++) {
                    echo '<div class="relative h-16 p-1 border rounded-lg text-gray-400"></div>';
                }
                ?>
            </div>
        </div>

        <!-- Appointments Section -->
        <div class="bg-white rounded-xl shadow-sm border border-primary-100 p-4 animate-slide-up">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-medium text-neutral-dark">
                    <?php echo date('F j, Y', strtotime($selectedDate)); ?> Appointments
                </h3>
                <button class="bg-gradient-to-r from-primary-500 to-accent-300 text-white py-1.5 px-3 rounded-lg hover:scale-105 transition-all duration-200 text-sm" onclick="openAppointmentModal()">
                    Add New Appointment
                </button>
            </div>
            
            <!-- Appointment List Container -->
            <div id="appointmentListContainer">
                <!-- Search Bar -->
                <div class="mb-4">
                    <div class="relative">
                        <input type="text" id="searchAppointment" placeholder="Search appointments..." 
                               class="w-full px-4 py-2 rounded-lg border border-primary-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div id="appointmentList" class="space-y-3">
                    <!-- Appointments will be loaded here via AJAX -->
                </div>
                <div id="paginationControls" class="flex justify-center items-center space-x-2 mt-4">
                    <button id="prevPage" class="px-3 py-1 bg-primary-50 text-primary-500 rounded-lg hover:bg-primary-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200" disabled>&lt; Previous</button>
                    <span id="pageInfo" class="text-sm text-neutral-dark mx-2"></span>
                    <button id="nextPage" class="px-3 py-1 bg-primary-50 text-primary-500 rounded-lg hover:bg-primary-100 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200" disabled>Next &gt;</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Appointment Modal -->
    <div id="appointmentModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-neutral-dark">Schedule New Appointment</h3>
                <form id="appointmentForm" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="add_appointment">
                    <input type="hidden" name="appointment_id" id="appointmentId">
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Patient</label>
                        <select name="patient_id" id="patientSelect" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                               min="<?php echo $currentDate; ?>" 
                               value="<?php echo $selectedDate; ?>" 
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
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-black">Record Payment</h3>
                <form id="paymentForm" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="appointment_id" id="paymentAppointmentId">
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Patient</label>
                        <p id="paymentPatientName" class="mt-1 text-neutral-dark text-sm py-2 px-3"></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Service</label>
                        <p id="paymentServiceName" class="mt-1 text-neutral-dark text-sm py-2 px-3"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Amount</label>
                        <input type="number" name="amount" id="paymentAmount" step="0.01" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Payment Method</label>
                        <select name="payment_method" id="paymentMethod" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                        </select>
                    </div>

                    <!-- QR Code Container (initially hidden) -->
                    <div id="qrCodeContainer" class="mb-4 text-center hidden">
                        <label class="block text-sm font-medium text-neutral-dark mb-2">Scan to Pay (GCash)</label>
                        <img src="<?php echo htmlspecialchars($clinic['qrcode'] ?? ''); ?>" alt="GCash QR Code" class="mx-auto rounded-lg shadow-sm max-w-xs">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closePaymentModal()" class="px-4 py-2 bg-primary-50 text-primary-500 rounded-lg text-sm hover:bg-primary-100 transition-all duration-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-success-500 to-success-600 text-black rounded-lg text-sm hover:scale-105 transition-all duration-200">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Status Update Form (Hidden) -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" id="appointmentId">
        <input type="hidden" name="status" id="appointmentStatus">
    </form>

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
</div>

<style>
/* Custom scrollbar */
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

/* Smooth transitions */
.transition-all {
    transition: all 0.3s ease;
}

/* Doctor position specific styles */
.doctor-position {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.25rem;
}

.doctor-position.specialist {
    background-color: var(--info-color);
}

.doctor-position.consultant {
    background-color: var(--warning-color);
}

.doctor-position.general {
    background-color: var(--success-color);
}

/* Mobile card view */
@media (max-width: 640px) {
    .mobile-card-view thead {
        display: none;
    }
    
    .mobile-card-view tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1rem;
        background: white;
    }
    
    .mobile-card-view tbody td {
        display: flex;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .mobile-card-view tbody td:last-child {
        border-bottom: none;
    }
    
    .mobile-card-view tbody td:before {
        content: attr(data-label);
        font-weight: 600;
        width: 40%;
        color: var(--secondary-color);
    }
    
    .mobile-card-view tbody td > div {
        width: 60%;
    }

    .doctor-position {
        margin-top: 0.5rem;
    }
}
</style>

<script>
// Server-provided PHT date and time
const serverPHTDate = '<?php echo $currentDate; ?>'; // e.g., '2025-05-22'
const serverPHTTime = '<?php echo $currentTime; ?>'; // e.g., '00:24:00'
const selectedDatePHT = '<?php echo $selectedDate; ?>'; // e.g., '2025-05-24'

// Global variables for PHT date/time (from server)
let todayStrPHT = serverPHTDate;
let currentTimePHT = serverPHTTime;

function openAppointmentModal(appointmentData = null) {
    // Reset form
    $('#appointmentForm')[0].reset();
    
    // Set default date to selected date, but ensure it's not in the past
    const defaultDate = selectedDatePHT >= todayStrPHT ? selectedDatePHT : todayStrPHT;
    $('#appointmentDate').attr('min', todayStrPHT);
    $('#appointmentDate').val(defaultDate);
    
    if (appointmentData) {
        // Only set the date if it's today or future in PHT
        const appointmentDate = new Date(appointmentData.appointment_date);
        appointmentDate.setHours(0, 0, 0, 0);
        const todayPHT = new Date(todayStrPHT);
        todayPHT.setHours(0, 0, 0, 0);
        
        if (appointmentDate >= todayPHT) {
            $('#appointmentDate').val(appointmentData.appointment_date);
        }
        
        // Pre-fill other form fields
        $('#patientSelect').val(appointmentData.patient_id);
        $('#serviceSelect').val(appointmentData.service_id);
        $('#doctorSelect').val(appointmentData.doctor_id);
        $('#remarks').val(appointmentData.remarks);
        
        // Add hidden input for appointment ID if rescheduling
        if (appointmentData.id) {
            if (!$('#appointmentId').length) {
                $('<input>').attr({
                    type: 'hidden',
                    id: 'appointmentId',
                    name: 'appointment_id'
                }).appendTo('#appointmentForm');
            }
            $('#appointmentId').val(appointmentData.id);
        }
        
        // Trigger change events to load time slots
        $('#serviceSelect, #doctorSelect').trigger('change');
    }
    
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
                    // If date is today, filter out past time slots
                    if (date === todayStrPHT) {
                        // Convert current time in PHT to minutes for comparison
                        const [hours, minutes] = currentTimePHT.split(':').map(Number);
                        const currentMinutes = hours * 60 + minutes;
                        
                        response.slots = response.slots.filter(slot => {
                            // Convert slot time (e.g., "09:00 AM") to minutes
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

function updateStatus(id, status) {
    if (status === 'Re-scheduled') {
        // Get appointment details
        $.ajax({
            url: 'schedule.php',
            type: 'GET',
            data: {
                action: 'get_appointment_details',
                id: id
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    openAppointmentModal(response.appointment);
                } else {
                    alert('Error loading appointment details: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading appointment details. Please try again.');
            }
        });
    } else if (status === 'Completed') {
        // Fetch appointment details and show payment modal
        $.ajax({
            url: 'schedule.php',
            type: 'GET',
            data: {
                action: 'get_appointment_payment_details',
                id: id
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    openPaymentModal(response.appointment);
                } else {
                    alert('Error loading payment details: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading payment details. Please try again.');
            }
        });
    } else if (status === 'Cancelled') {
        // Handle Cancel status
        if (confirm('Are you sure you want to cancel this appointment?')) {
            $.ajax({
                url: 'schedule.php',
                type: 'POST',
                data: {
                    action: 'update_status',
                    id: id,
                    status: status
                },
                success: function(response) {
                    window.location.reload();
                },
                error: function() {
                    alert('Error updating appointment status. Please try again.');
                }
            });
        }
    }
}

function openPaymentModal(appointmentDetails) {
    $('#paymentAppointmentId').val(appointmentDetails.id);
    $('#paymentPatientName').text(appointmentDetails.patient_name);
    $('#paymentServiceName').text(appointmentDetails.service_name);
    $('#paymentAmount').val(appointmentDetails.service_price);
    
    document.getElementById('paymentModal').classList.remove('hidden');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    $('#paymentForm')[0].reset();
    $('#qrCodeContainer').addClass('hidden');
}

$(document).ready(function() {
    // Set minimum date to server-provided PHT date
    $('#appointmentDate').attr('min', todayStrPHT);
    $('#appointmentDate').val(selectedDatePHT >= todayStrPHT ? selectedDatePHT : todayStrPHT);
    
    // Strict date validation on change
    $('#appointmentDate').on('change', function() {
        const selectedDate = $(this).val();
        if (selectedDate < todayStrPHT) {
            alert('Cannot book appointments for past dates. Please select today or a future date.');
            $(this).val(todayStrPHT);
            updateTimeSlots();
            return false;
        }
        updateTimeSlots();
    });
    
    // Filter doctors based on selected service
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
    
    // Update time slots when service or doctor changes
    $('#serviceSelect, #doctorSelect').on('change', function() {
        updateTimeSlots();
    });
    
    // Validate form submission
    $('#appointmentForm').on('submit', function(e) {
        e.preventDefault();
        
        const selectedDate = $('#appointmentDate').val();
        const selectedTime = $('#timeSelect').val();
        
        if (selectedDate === todayStrPHT && selectedTime) {
            // Convert selected time to minutes
            const slotHour = parseInt(selectedTime.split(':')[0]);
            const isPM = selectedTime.includes('PM') && slotHour !== 12;
            const slotMinutes = (isPM ? slotHour + 12 : slotHour) * 60;
            
            // Convert current PHT time to minutes
            const [hours, minutes] = currentTimePHT.split(':').map(Number);
            const currentMinutes = hours * 60 + minutes;
            
            if (slotMinutes <= currentMinutes) {
                alert('Cannot book appointments for past times in Philippine Time. Please select a future time.');
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
            success: function(response) {
                closeAppointmentModal();
                window.location.reload();
            },
            error: function() {
                alert('Error saving appointment. Please try again.');
                $('#appointmentForm').find('button[type="submit"]').prop('disabled', false);
            }
        });
    });

    // Handle payment form submission
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const appointmentId = $('#paymentAppointmentId').val();
        $(this).find('button[type="submit"]').prop('disabled', true);
        
        $.ajax({
            url: 'schedule.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    closePaymentModal();
                    // Show receipt after successful payment
                    printReceipt(appointmentId);
                    // Wait for receipt to be shown before reloading
                    setTimeout(function() {
                        window.location.reload();
                    }, 4000);
                } else {
                    alert(response.message || 'Error recording payment. Please try again.');
                }
            },
            error: function() {
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
});

$(document).ready(function() {
    let currentPage = 1;
    let totalPages = 1;
    let searchQuery = '';
    
    // Function to load appointments for a specific page
    function loadAppointments(page) {
        $.ajax({
            url: 'schedule.php',
            type: 'GET',
            data: {
                action: 'get_paginated_appointments',
                date: selectedDatePHT,
                page: page,
                search: searchQuery
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    currentPage = response.currentPage;
                    totalPages = response.totalPages;
                    
                    // Update pagination controls
                    $('#pageInfo').text(`Page ${currentPage} of ${totalPages}`);
                    $('#prevPage').prop('disabled', currentPage === 1);
                    $('#nextPage').prop('disabled', currentPage === totalPages);
                    
                    // Render appointments
                    let html = '';
                    if (response.appointments.length > 0) {
                        // Group appointments by status
                        let groupedAppointments = {
                            'Scheduled': [],
                            'Completed': [],
                            'Cancelled': []
                        };
                        
                        response.appointments.forEach(function(appointment) {
                            if (appointment.status === 'Re-scheduled') {
                                groupedAppointments['Scheduled'].push(appointment);
                            } else {
                                groupedAppointments[appointment.status].push(appointment);
                            }
                        });
                        
                        // Display appointments in order: Scheduled, Completed, Cancelled
                        ['Scheduled', 'Completed', 'Cancelled'].forEach(function(status) {
                            if (groupedAppointments[status].length > 0) {
                                html += `<div class="text-sm font-medium text-black mb-2 mt-4">${status} Appointments</div>`;
                                
                                groupedAppointments[status].forEach(function(appointment) {
                                    let statusClass = '';
                                    let statusTextClass = '';
                                    
                                    switch (appointment.status) {
                                        case 'Scheduled':
                                        case 'Re-scheduled':
                                            statusClass = 'bg-primary-50 border-primary-100';
                                            statusTextClass = 'text-primary-600';
                                            break;
                                        case 'Completed':
                                            statusClass = 'bg-success-50 border-success-100';
                                            statusTextClass = 'text-success-600';
                                            break;
                                        case 'Cancelled':
                                            statusClass = 'bg-danger-50 border-danger-100';
                                            statusTextClass = 'text-danger-600';
                                            break;
                                        default:
                                            statusClass = 'bg-gray-50 border-gray-100';
                                            statusTextClass = 'text-black';
                                    }
                                    
                                    html += `
                                        <div class="p-3 ${statusClass} rounded-lg border hover:bg-primary-50">
                                            <div class="flex flex-col space-y-2">
                                                <div class="flex justify-between items-start">
                                                    <div class="space-y-0.5">
                                                        <div class="flex items-center space-x-2">
                                                            <p class="text-sm font-medium text-black">${appointment.patient_name}</p>
                                                            <span class="text-xs px-2 py-0.5 rounded-lg ${
                                                                appointment.status === 'Completed' ? 'bg-gradient-to-r from-success-700 to-success-800 text-black' :
                                                                appointment.status === 'Cancelled' ? 'bg-gradient-to-r from-danger-700 to-danger-800 text-black' :
                                                                appointment.status === 'Re-scheduled' ? 'bg-gradient-to-r from-warning-700 to-warning-800 text-black' :
                                                                appointment.status === 'Scheduled' ? 'bg-gradient-to-r from-primary-700 to-primary-800 text-black' :
                                                                statusTextClass
                                                            }">${appointment.status}</span>
                                                        </div>
                                                        <p class="text-xs text-black">${appointment.service_name}</p>
                                                    </div>
                                                    <div class="text-right space-y-0.5">
                                                        <p class="text-sm font-medium ${statusTextClass}">${formatTime(appointment.appointment_time)}</p>
                                                        <p class="text-xs text-neutral-dark">${appointment.service_duration}</p>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                                                    <div class="flex items-center space-x-2">
                                                        <p class="text-xs text-black">Dr. ${appointment.doctor_name}</p>
                                                        ${appointment.doctor_position ? `<span class="doctor-position ${appointment.doctor_position.toLowerCase()} text-black text-xs">${appointment.doctor_position}</span>` : ''}
                                                    </div>
                                                    ${appointment.status === 'Scheduled' ? `
                                                        <div class="flex space-x-2">
                                                            <button type="button" class="text-xs bg-gradient-to-r from-success-700 to-success-800 text-black px-2.5 py-1 rounded-lg hover:from-success-800 hover:to-success-900 transition-all duration-200 shadow-sm" 
                                                                    onclick="updateStatus(${appointment.id}, 'Completed')">
                                                                Complete
                                                            </button>
                                                            <button type="button" class="text-xs bg-gradient-to-r from-warning-700 to-warning-800 text-black px-2.5 py-1 rounded-lg hover:from-warning-800 hover:to-warning-900 transition-all duration-200 shadow-sm" 
                                                                    onclick="updateStatus(${appointment.id}, 'Re-scheduled')">
                                                                Reschedule
                                                            </button>
                                                            <button type="button" class="text-xs bg-gradient-to-r from-danger-700 to-danger-800 text-black px-2.5 py-1 rounded-lg hover:from-danger-800 hover:to-danger-900 transition-all duration-200 shadow-sm" 
                                                                    onclick="updateStatus(${appointment.id}, 'Cancelled')">
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    ` : appointment.status === 'Completed' ? `
                                                        <div class="flex space-x-2">
                                                            <button type="button" class="text-xs bg-gradient-to-r from-primary-700 to-primary-800 text-black px-2.5 py-1 rounded-lg hover:from-primary-800 hover:to-primary-900 transition-all duration-200 shadow-sm" 
                                                                    onclick="printReceipt(${appointment.id})">
                                                                Receipt
                                                            </button>
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>`;
                                });
                            }
                        });
                    } else {
                        html = '<div class="p-3 bg-gray-50 rounded-lg border border-gray-100 text-center">' +
                               '<p class="text-sm text-secondary">No appointments found.</p>' +
                               '</div>';
                    }
                    
                    $('#appointmentList').html(html);
                } else {
                    $('#appointmentList').html('<div class="p-3 bg-gray-50 rounded-lg border border-gray-100 text-center">' +
                                              '<p class="text-sm text-secondary">Error loading appointments: ' + response.message + '</p>' +
                                              '</div>');
                }
            },
            error: function() {
                $('#appointmentList').html('<div class="p-3 bg-gray-50 rounded-lg border border-gray-100 text-center">' +
                                          '<p class="text-sm text-secondary">Error loading appointments. Please try again.</p>' +
                                          '</div>');
            }
        });
    }
    
    // Initial load
    loadAppointments(currentPage);
    
    // Search functionality
    let searchTimeout;
    $('#searchAppointment').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchQuery = $('#searchAppointment').val().trim();
            currentPage = 1; // Reset to first page when searching
            loadAppointments(currentPage);
        }, 300); // Debounce search for 300ms
    });
    
    // Pagination button handlers
    $('#prevPage').on('click', function() {
        if (currentPage > 1) {
            loadAppointments(currentPage - 1);
        }
    });
    
    $('#nextPage').on('click', function() {
        if (currentPage < totalPages) {
            loadAppointments(currentPage + 1);
        }
    });

    // Reload appointments when date changes
    $('.calendar-day').on('click', function() {
        currentPage = 1; // Reset to first page when date changes
        searchQuery = ''; // Clear search when date changes
        $('#searchAppointment').val(''); // Clear search input
        loadAppointments(currentPage);
    });
});

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
function formatTime(timeStr) {
    if (!timeStr) return '';
    
    // Remove any seconds if present
    timeStr = timeStr.split(':').slice(0, 2).join(':');
    
    // Convert to 12-hour format
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}
</script>

</body>