<?php
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
            $stmt = $pdo->query("SELECT * FROM clinic_details WHERE id = 1");
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
            
            // Parse clinic hours
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
            
            for ($hour = $startHour; $hour < $endHour; $hour++) {
                if ($hour != 12) { // Skip lunch hour
                    if (!in_array($hour, $bookedHours)) {
                        $timeStr = sprintf('%02d:00:00', $hour);
                        $formattedSlot = date('h:00 A', strtotime($timeStr));
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
                        'remarks' => $appointment['remarks']
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
            $error = "All fields are required.";
        } else {
            try {
                // Strict date validation
                $today = date('Y-m-d');
                $selectedDate = date('Y-m-d', strtotime($appointment_date));
                
                if ($selectedDate < $today) {
                    throw new Exception("Cannot book appointments for past dates. Please select today or a future date.");
                }
                
                // If rescheduling, update existing appointment
                if ($appointment_id) {
                    // First check if the appointment exists
                    $checkStmt = $pdo->prepare("SELECT id FROM appointments WHERE id = :id");
                    $checkStmt->execute([':id' => $appointment_id]);
                    $exists = $checkStmt->fetch();
                    
                    if ($exists) {
                        // Update the existing appointment
                        $sql = "UPDATE appointments SET 
                                patient_id = :patient_id,
                                staff_id = :staff_id,
                                service_id = :service_id,
                                appointment_date = :appointment_date,
                                appointment_time = :appointment_time,
                                remarks = :remarks,
                                status = :status,
                                updated_at = NOW()
                                WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':patient_id' => $patient_id,
                            ':staff_id' => $staff_id,
                            ':service_id' => $service_id,
                            ':appointment_date' => $appointment_date,
                            ':appointment_time' => sprintf('%02d:00:00', intval(date('H', strtotime($appointment_time)))),
                            ':remarks' => $remarks,
                            ':status' => $status,
                            ':id' => $appointment_id
                        ]);
                        
                        $success = "Appointment rescheduled successfully!";
                    } else {
                        throw new Exception("Appointment not found.");
                    }
                } else {
                    // Check if patient status is active
                    $stmt = $pdo->prepare("SELECT status FROM patients WHERE id = :id");
                    $stmt->execute([':id' => $patient_id]);
                    $patientStatus = $stmt->fetchColumn();
                    
                    if ($patientStatus != 1) {
                        $error = "Selected patient is not active. Only active patients can be scheduled.";
                    } else {
                        // Check if date is in the past
                        if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
                            $error = "Cannot book appointments for past dates.";
                        } else {
                            // Get day of week for the selected date
                            $dayOfWeek = date('l', strtotime($appointment_date));
                            
                            // Check if it's doctor's rest day
                            $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day = :rest_day");
                            $stmt->execute([':doctor_id' => $staff_id, ':rest_day' => $dayOfWeek]);
                            $restDay = $stmt->fetch();
                            
                            if ($restDay) {
                                $error = "Doctor is not available on " . $dayOfWeek;
                            } else {
                                // Get clinic hours for the day
                                $clinicHours = '';
                                if ($dayOfWeek == 'Sunday') {
                                    $clinicHours = $clinic['hours_sunday'];
                                } else if ($dayOfWeek == 'Saturday') {
                                    $clinicHours = $clinic['hours_saturday'];
                                } else {
                                    $clinicHours = $clinic['hours_weekdays'];
                                }
                                
                                // Check if clinic is closed
                                if ($clinicHours == 'Closed') {
                                    $error = "Clinic is closed on " . $dayOfWeek;
                                } else {
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
                                        $error = "Appointment time is outside clinic hours.";
                                    } else {
                                        // Check if slot is already booked - using hour
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                                                              WHERE staff_id = :staff_id AND appointment_date = :date 
                                                              AND HOUR(appointment_time) = :hour 
                                                              AND status != 'Cancelled'");
                                        $stmt->execute([
                                            ':staff_id' => $staff_id, 
                                            ':date' => $appointment_date, 
                                            ':hour' => $appointmentHour
                                        ]);
                                        $count = $stmt->fetchColumn();
                                        
                                        if ($count > 0) {
                                            $error = "This time slot is already booked.";
                                        } else {
                                            // Create time in database format (HH:00:00)
                                            $dbTime = sprintf('%02d:00:00', $appointmentHour);
                                            
                                            // Insert appointment
                                            $sql = "INSERT INTO appointments (patient_id, staff_id, service_id, appointment_date, 
                                                    appointment_time, status, remarks, created_at) 
                                                    VALUES (:patient_id, :staff_id, :service_id, :appointment_date, 
                                                    :appointment_time, :status, :remarks, NOW())";
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->execute([
                                                ':patient_id' => $patient_id,
                                                ':staff_id' => $staff_id,
                                                ':service_id' => $service_id,
                                                ':appointment_date' => $appointment_date,
                                                ':appointment_time' => $dbTime,
                                                ':status' => $status,
                                                ':remarks' => $remarks
                                            ]);
                                            
                                            $success = "Appointment scheduled successfully!";
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Redirect to prevent form resubmission
                header("Location: index.php?page=schedule&success=appointment_added&date=" . $appointment_date);
                exit();
            } catch (PDOException $e) {
                $error = "Error scheduling appointment: " . $e->getMessage();
                error_log("Appointment Error: " . $e->getMessage());
            }
        }
    }
    
    if ($_POST['action'] == 'update_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        
        try {
            // Update appointment status
            $sql = "UPDATE appointments SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id, ':status' => $status]);
            
            $success = "Appointment status updated successfully!";
            // Redirect to prevent form resubmission
            header("Location: index.php?page=schedule&success=status_updated&date=" . $selectedDate);
            exit();
        } catch (PDOException $e) {
            $error = "Error updating appointment status: " . $e->getMessage();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Schedule</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-neutral-light font-body">
<div id="schedule" class="space-y-8 p-6 md:p-8 animate-fade-in bg-white">
    <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Appointment Schedule</h2>
    
    <!-- Success/Error Message -->
    <?php if (isset($_GET['success']) || $error || $success): ?>
    <div id="alert" class="bg-<?php echo $error ? 'red-100 border-red-200 text-red-800' : 'success-light border-success text-success'; ?> border px-4 py-3 rounded-xl text-sm flex justify-between items-center animate-slide-up">
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
        <button type="button" onclick="document.getElementById('alert').style.display = 'none'" class="text-<?php echo $error ? 'red-600 hover:text-red-800' : 'success hover:text-success-dark'; ?>">
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
    
    <div class="grid grid-cols-1 lg:grid-cols-7 gap-6">
        <!-- Calendar -->
        <div class="lg:col-span-5 bg-white rounded-xl shadow-sm border border-primary-100 p-6 animate-slide-up">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-medium text-neutral-dark"><?php echo $monthName . ' ' . $year; ?></h3>
                <div class="flex space-x-2">
                    <a href="index.php?page=schedule&month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="p-2 bg-primary-50 text-primary-500 rounded-lg hover:bg-primary-100 transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <a href="index.php?page=schedule&month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="p-2 bg-primary-50 text-primary-500 rounded-lg hover:bg-primary-100 transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
            <div class="grid grid-cols-7 gap-2 text-center mb-4">
                <div class="text-xs font-medium text-primary-500 uppercase">Sun</div>
                <div class="text-xs font-medium text-primary-500 uppercase">Mon</div>
                <div class="text-xs font-medium text-primary-500 uppercase">Tue</div>
                <div class="text-xs font-medium text-primary-500 uppercase">Wed</div>
                <div class="text-xs font-medium text-primary-500 uppercase">Thu</div>
                <div class="text-xs font-medium text-primary-500 uppercase">Fri</div>
                <div class="text-xs font-medium text-primary-500 uppercase">Sat</div>
            </div>
            <div class="grid grid-cols-7 gap-2">
                <?php
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
                // Add blank cells for days before the first day of the month
                for ($i = 0; $i < $dayOfWeek; $i++) {
                    echo '<div class="relative h-16 p-1 border border-primary-100 rounded-md text-secondary bg-neutral-light"></div>';
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
                    $selectedClass = ($isSelected && !$isToday) ? 'border-2 border-primary-500' : '';
                    
                    $pastClass = $isPast ? 'bg-neutral-light text-secondary' : '';
                    
                    echo '<div class="relative h-16 p-1 border border-primary-100 rounded-md ' . $appointmentClass . ' ' . $todayClass . ' ' . $selectedClass . ' ' . $pastClass . ' hover:bg-primary-50 transition-all duration-200">
                        <a href="index.php?page=schedule&date=' . $date . '" class="block h-full w-full">
                            <div class="text-sm text-neutral-dark">' . $day . '</div>';
                    
                    if ($appointmentCount > 0) {
                        echo '<div class="text-xs text-primary-500 font-medium">' . $appointmentCount . ' appt</div>';
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
                    echo '<div class="relative h-16 p-1 border border-primary-100 rounded-md text-secondary bg-neutral-light"></div>';
                }
                ?>
            </div>
        </div>
        <!-- Appointments List -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-primary-100 p-6 animate-slide-up">
            <h3 class="text-lg font-medium text-neutral-dark mb-6">
                <?php echo date('F j, Y', strtotime($selectedDate)); ?> Appointments
            </h3>
            <div class="space-y-4">
                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $statusClass = '';
                        $statusTextClass = '';
                        
                        switch ($appointment['status']) {
                            case 'Scheduled':
                                $statusClass = 'bg-primary-50 border-primary-100';
                                $statusTextClass = 'text-primary-500';
                                break;
                            case 'Completed':
                                $statusClass = 'bg-success-light border-success';
                                $statusTextClass = 'text-success';
                                break;
                            case 'Cancelled':
                                $statusClass = 'bg-red-100 border-red-200';
                                $statusTextClass = 'text-red-600';
                                break;
                            case 'Re-scheduled':
                                $statusClass = 'bg-yellow-50 border-yellow-100';
                                $statusTextClass = 'text-yellow-600';
                                break;
                            default:
                                $statusClass = 'bg-primary-50 border-primary-100';
                                $statusTextClass = 'text-primary-500';
                        }
                        ?>
                        <div class="p-4 <?php echo $statusClass; ?> rounded-lg border shadow-sm hover:bg-opacity-75 transition-all duration-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-neutral-dark"><?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                                    <p class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                    <p class="text-xs text-secondary">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium <?php echo $statusTextClass; ?>"><?php echo date('h:00 A', strtotime($appointment['appointment_time'])); ?></p>
                                    <p class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['service_duration']); ?></p>
                                    <p class="text-xs <?php echo $statusTextClass; ?>"><?php echo htmlspecialchars($appointment['status']); ?></p>
                                </div>
                            </div>
                            <?php if ($appointment['status'] == 'Scheduled'): ?>
                                <div class="mt-3 flex justify-end space-x-2">
                                    <button type="button" class="text-xs bg-success text-white px-2 py-1 rounded-lg hover:bg-success-dark transition-all duration-200" 
                                            onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Completed')">
                                        Complete
                                    </button>
                                    <button type="button" class="text-xs bg-yellow-500 text-white px-2 py-1 rounded-lg hover:bg-yellow-600 transition-all duration-200" 
                                            onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Re-scheduled')">
                                        Reschedule
                                    </button>
                                    <button type="button" class="text-xs bg-red-600 text-white px-2 py-1 rounded-lg hover:bg-red-700 transition-all duration-200" 
                                            onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Cancelled')">
                                        Cancel
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 bg-primary-50 rounded-lg border border-primary-100 text-center shadow-sm">
                        <p class="text-sm text-secondary">No appointments scheduled for this date.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-6">
                <button class="w-full bg-gradient-to-r from-primary-500 to-accent-300 text-white py-2 px-4 rounded-lg hover:scale-105 transition-all duration-200 flex items-center justify-center gap-2" onclick="openAppointmentModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add New Appointment
                </button>
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
                <h3 class="text-lg font-medium text-neutral-dark">Record Payment</h3>
                <form id="paymentForm" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="appointment_id" id="paymentAppointmentId">
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Patient</label>
                        <p id="paymentPatientName" class="mt-1 text-neutral-dark text-sm"></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Service</label>
                        <p id="paymentServiceName" class="mt-1 text-neutral-dark text-sm"></p>
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
                        <img src="Uploads/Qrcode/GCash-MyQr.jpg" alt="GCash QR Code" class="mx-auto rounded-lg shadow-sm max-w-xs">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closePaymentModal()" class="px-4 py-2 bg-primary-50 text-primary-500 rounded-lg text-sm hover:bg-primary-100 transition-all duration-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-success to-success-dark text-white rounded-lg text-sm hover:scale-105 transition-all duration-200">Record Payment</button>
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
</div>

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
    } else {
        // Handle Cancel status
        if (confirm(`Are you sure you want to mark this appointment as ${status}?`)) {
            document.getElementById('appointmentId').value = id;
            document.getElementById('appointmentStatus').value = status;
            document.getElementById('statusForm').submit();
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
                    window.location.reload();
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
</script>

<style>
    /* Tailwind custom fonts */
    .font-heading {
        font-family: 'Poppins', sans-serif;
    }
    .font-body {
        font-family: 'Inter', sans-serif;
    }

    /* Custom animations */
    .animate-fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    .animate-slide-up {
        animation: slideUp 0.5s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

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

    /* Mobile adjustments */
    @media (max-width: 640px) {
        #schedule {
            padding: 4px;
        }
        .grid-cols-7 {
            gap: 0.5rem;
        }
        .h-16 {
            height: 4rem;
        }
        .text-sm {
            font-size: 0.75rem;
        }
        .text-xs {
            font-size: 0.625rem;
        }
        .fixed.inset-0 > div {
            width: 90% !important;
            max-width: none !important;
            margin: 0 auto;
        }
    }
</style>
</body>
</html>