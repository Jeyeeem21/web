<?php
session_start();
require_once 'config/db.php';

// Create login_attempts table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        success TINYINT(1) NOT NULL,
        attempt_time DATETIME NOT NULL,
        ban_until DATETIME NULL,
        INDEX idx_username_time (username, attempt_time)
    )");
} catch (PDOException $e) {
    error_log("Error creating login_attempts table: " . $e->getMessage());
}

// Function to log audit events
function logAudit($pdo, $user_id, $username, $action, $status, $ip_address) {
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $action, $status, $ip_address]);
}

// Function to check if user is banned
function isUserBanned($pdo, $username) {
    $stmt = $pdo->prepare("SELECT ban_until FROM login_attempts WHERE username = ? AND ban_until > NOW() ORDER BY attempt_time DESC LIMIT 1");
    $stmt->execute([$username]);
    $result = $stmt->fetch();
    return $result ? $result['ban_until'] : false;
}

// Function to record login attempt
function recordLoginAttempt($pdo, $username, $success) {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (username, success, attempt_time) VALUES (?, ?, NOW())");
    $stmt->execute([$username, $success ? 1 : 0]);
}

// Function to get failed attempts count
function getFailedAttempts($pdo, $username) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$username]);
    return $stmt->fetchColumn();
}

// Function to calculate ban duration
function calculateBanDuration($attempts) {
    $baseDuration = 30; // 30 seconds
    $multiplier = pow(2, floor(($attempts - 1) / 3));
    return $baseDuration * $multiplier;
}

// Function to record login history
function recordLoginHistory($pdo, $user_id, $name, $role) {
    $stmt = $pdo->prepare("INSERT INTO login_history (user_id, name, role) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $name, $role]);
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Login
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = "Please fill in all fields";
            logAudit($pdo, null, $username, 'Login Attempt', 'Failure: Missing Fields', $ip_address);
            header("Location: login.php");
            exit();
        }

        // Check if user is banned
        $banUntil = isUserBanned($pdo, $username);
        if ($banUntil) {
            $_SESSION['error'] = "Account is temporarily locked. Please try again after " . date('H:i:s', strtotime($banUntil));
            header("Location: login.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT u.*, s.role, s.id as staff_id, p.id as patient_id, p.name as patient_name, s.name as staff_name 
                                  FROM users u 
                                  LEFT JOIN staff s ON u.staff_id = s.id 
                                  LEFT JOIN patients p ON u.patient_id = p.id 
                                  WHERE u.username = ? AND u.status = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['pass'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                logAudit($pdo, $user['id'], $user['username'], 'Login', 'Success', $ip_address);
                recordLoginAttempt($pdo, $username, true);

                // Record login history
                $role = $user['patient_id'] ? 'patient' : $user['role'];
                $name = $user['patient_id'] ? $user['patient_name'] : $user['staff_name'];
                recordLoginHistory($pdo, $user['id'], $name, $role);

                if ($user['patient_id']) {
                    $_SESSION['user_role'] = 'patient';
                    $_SESSION['patient_id'] = $user['patient_id'];
                    header("Location: patient_dashboard.php");
                } else if ($user['staff_id']) {
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['staff_id'] = $user['staff_id'];
                    
                    if ($user['role'] === 'admin') {
                        header("Location: index.php");
                    } else if ($user['role'] === 'doctor') {
                        header("Location: doctor_dashboard.php");
                    } else if ($user['role'] === 'assistant') {
                        $stmt = $pdo->prepare("SELECT d.doctor_id, s.name as doctor_name 
                                              FROM doctor d 
                                              JOIN staff s ON d.doctor_id = s.id 
                                              WHERE d.assistant_id = ?");
                        $stmt->execute([$user['staff_id']]);
                        $doctor = $stmt->fetch();
                        if ($doctor) {
                            $_SESSION['assigned_doctor_id'] = $doctor['doctor_id'];
                            $_SESSION['assigned_doctor_name'] = $doctor['doctor_name'];
                            header("Location: doctor_dashboard.php");
                        } else {
                            logAudit($pdo, $user['id'], $user['username'], 'Login Attempt', 'Failure: No Doctor Assigned', $ip_address);
                            $_SESSION['error'] = "Invalid username or password";
                            header("Location: login.php");
                        }
                    }
                }
                exit();
            } else {
                recordLoginAttempt($pdo, $username, false);
                $failedAttempts = getFailedAttempts($pdo, $username);
                
                if ($failedAttempts >= 3) {
                    $banDuration = calculateBanDuration($failedAttempts);
                    $stmt = $pdo->prepare("INSERT INTO login_attempts (username, success, attempt_time, ban_until) VALUES (?, 0, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))");
                    $stmt->execute([$username, $banDuration]);
                    
                    $_SESSION['error'] = "Too many failed attempts. Account is locked for " . $banDuration . " seconds.";
                } else {
                    $_SESSION['error'] = "Invalid username or password. " . (3 - $failedAttempts) . " attempts remaining before temporary lockout.";
                }
                
                logAudit($pdo, $user['id'] ?? null, $username, 'Login Attempt', $user ? 'Failure: Incorrect Password' : 'Failure: Username Not Found', $ip_address);
                header("Location: login.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "An error occurred. Please try again later.";
            error_log($e->getMessage());
            logAudit($pdo, null, $username, 'Login Attempt', 'Failure: Database Error', $ip_address);
            header("Location: login.php");
            exit();
        }
    }

    // Handle Patient Registration
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $birthdate = $_POST['birthdate'];
        $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
        $status = 1;

        if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($birthdate) || empty($gender)) {
            $_SESSION['error'] = "Please fill in all required fields";
            header("Location: login.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Email already registered";
                header("Location: login.php");
                exit();
            }

            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/patients/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png'];
                if (!in_array($file_extension, $allowed_extensions)) {
                    $_SESSION['error'] = "Invalid file type. Please upload JPG, JPEG, or PNG files only.";
                    header("Location: login.php");
                    exit();
                }
                $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
                $photo_path = $upload_dir . $new_filename;
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                    throw new Exception("Failed to upload photo");
                }
            }

            $birthdate_obj = new DateTime($birthdate);
            $today = new DateTime();
            $age = $birthdate_obj->diff($today)->y;

            $stmt = $pdo->prepare("
                INSERT INTO patients (name, email, phone, address, birthdate, age, gender, photo, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $name, $email, $phone, $address, $birthdate, $age, $gender, $photo_path, $status
            ]);

            $patient_id = $pdo->lastInsertId();

            if ($patient_id) {
                $_SESSION['success'] = "Registration successful! Please create a user account for the patient.";
                $_SESSION['show_credentials_modal'] = true;
                $_SESSION['new_patient_id'] = $patient_id;
                header("Location: login.php");
                exit();
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "An error occurred during registration: " . $e->getMessage();
            error_log($e->getMessage());
            header("Location: login.php");
            exit();
        }
    }

    // Handle User Account Creation
    if (isset($_POST['action']) && $_POST['action'] === 'add_credentials') {
        $patient_id = $_POST['patient_id'] ?? '';
        $username = htmlspecialchars(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $status = 1;

        if (strlen($password) < 8) {
            $_SESSION['error'] = "Password must be at least 8 characters long.";
            $_SESSION['show_credentials_modal'] = true;
            $_SESSION['new_patient_id'] = $patient_id;
            header("Location: login.php");
            exit();
        }

        try {
            $check_sql = "SELECT COUNT(*) FROM users WHERE username = :username";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':username' => $username]);
            if ($check_stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Username already exists. Please choose a different username.";
                $_SESSION['show_credentials_modal'] = true;
                $_SESSION['new_patient_id'] = $patient_id;
                header("Location: login.php");
                exit();
            }

            $sql = "INSERT INTO users (username, pass, patient_id, status) 
                    VALUES (:username, :password, :patient_id, :status)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':patient_id' => $patient_id,
                ':status' => $status
            ]);

            $_SESSION['success'] = "User account created successfully!";
            unset($_SESSION['show_credentials_modal']);
            unset($_SESSION['new_patient_id']);
            header("Location: login.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error creating user account: " . $e->getMessage();
            $_SESSION['show_credentials_modal'] = true;
            $_SESSION['new_patient_id'] = $patient_id;
            header("Location: login.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Login</title>
    <!-- Google Fonts: Inter and Poppins -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Tailwind CSS CDN with Custom Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                        }
                    },
                    animation: {
                        'slide-up': 'slideUp 0.3s ease-out forwards',
                        'fade-in': 'fadeIn 0.3s ease-out forwards'
                    }
                }
            }
        }
    </script>
    <style>
        .transform-style-preserve-3d {
            transform-style: preserve-3d;
        }
        .backface-hidden {
            backface-visibility: hidden;
        }
        .rotate-y-180 {
            transform: rotateY(180deg);
        }
        #form-container {
            transition: transform 0.6s, height 0.6s;
            height: 450px;
        }
        #form-container.register-mode {
            height: 800px;
        }
        #login-form, #register-form {
            position: absolute;
            width: 100%;
            height: 100%;
            transition: transform 0.6s;
        }
        #register-form {
            transform: rotateY(180deg);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans flex items-center justify-center min-h-screen p-4 sm:p-6 lg:p-8">
    <!-- Loading Overlay -->
    <div class="fixed inset-0 bg-white z-[9999] flex items-center justify-center transition-opacity duration-400 loading-overlay">
        <div class="w-12 h-12 border-4 border-primary-100 border-t-primary-500 rounded-full animate-spin"></div>
    </div>

    <!-- Main Content -->
    <div class="w-full max-w-md">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-800 rounded-lg animate-fade-in">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-200 text-green-800 rounded-lg animate-fade-in">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl border-2 border-primary-500/20 overflow-hidden">
            <!-- Form Container -->
            <div id="form-container" class="relative h-[800px] transform-style-preserve-3d">
                <!-- Login Form -->
                <div id="login-form" class="backface-hidden">
                    <div class="p-8">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-heading font-bold text-neutral-dark mb-2">Welcome Back</h2>
                            <p class="text-secondary">Please sign in to your account</p>
                        </div>
                        <form action="login.php" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="login">
                            <div>
                                <label for="username" class="block text-sm font-medium text-neutral-dark mb-2">Username</label>
                                <input type="text" id="username" name="username" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your username">
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-neutral-dark mb-2">Password</label>
                                <input type="password" id="password" name="password" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your password">
                            </div>
                            <button type="submit"
                                class="w-full bg-primary-500 text-white px-6 py-3 rounded-lg text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300">
                                Sign In
                            </button>
                        </form>
                        <div class="mt-6 text-center">
                            <p class="text-secondary">Don't have an account? 
                                <button onclick="flipForm()" class="text-primary-500 font-semibold hover:text-primary-600">Register</button>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Register Form -->
                <div id="register-form" class="backface-hidden rotate-y-180">
                    <div class="p-8 h-full overflow-y-auto">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-heading font-bold text-neutral-dark mb-2">Create Account</h2>
                            <p class="text-secondary">Please fill in your details</p>
                        </div>
                        <form action="login.php" method="POST" class="space-y-6" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="register">
                            <div>
                                <label for="reg_name" class="block text-sm font-medium text-neutral-dark mb-2">Full Name</label>
                                <input type="text" id="reg_name" name="name" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your full name">
                            </div>
                            <div>
                                <label for="reg_email" class="block text-sm font-medium text-neutral-dark mb-2">Email</label>
                                <input type="email" id="reg_email" name="email" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your email">
                            </div>
                            <div>
                                <label for="reg_phone" class="block text-sm font-medium text-neutral-dark mb-2">Phone</label>
                                <input type="tel" id="reg_phone" name="phone" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your phone number">
                            </div>
                            <div>
                                <label for="reg_address" class="block text-sm font-medium text-neutral-dark mb-2">Address</label>
                                <textarea id="reg_address" name="address" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your address" rows="2"></textarea>
                            </div>
                            <div>
                                <label for="reg_birthdate" class="block text-sm font-medium text-neutral-dark mb-2">Birthdate</label>
                                <input type="date" id="reg_birthdate" name="birthdate" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium">
                            </div>
                            <div>
                                <label for="reg_gender" class="block text-sm font-medium text-neutral-dark mb-2">Gender</label>
                                <select id="reg_gender" name="gender" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="reg_photo" class="block text-sm font-medium text-neutral-dark mb-2">Photo</label>
                                <input type="file" id="reg_photo" name="photo" accept="image/*"
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium">
                            </div>
                            <div class="flex space-x-4">
                                <button type="button" onclick="flipForm()"
                                    class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg text-base font-bold hover:bg-gray-300 hover:scale-105 hover:shadow-lg transition-all duration-300">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="flex-1 bg-primary-500 text-white px-6 py-3 rounded-lg text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300">
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add User Account Modal -->
        <div id="addUserModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-neutral-dark">Create User Account</h3>
                    <form id="addUserForm" method="POST" class="mt-4 space-y-4">
                        <input type="hidden" name="action" value="add_credentials">
                        <input type="hidden" name="patient_id" id="addUserPatientId">
                        
                        <div>
                            <label class="block text-sm font-medium text-neutral-dark">Username</label>
                            <input type="text" name="username" id="addUserUsername" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                        </div>
                        
                        <div class="relative">
                            <label class="block text-sm font-medium text-neutral-dark">Password</label>
                            <input type="password" name="password" id="addUserPassword" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3 pr-10">
                            <button type="button" id="toggleAddUserPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 mt-6 text-primary-500 hover:text-primary-700">
                                <svg id="addUserEyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeAddUserModal()" class="px-4 py-2 bg-primary-50 text-primary-500 rounded-lg text-sm hover:bg-primary-100 transition-all duration-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg text-sm hover:scale-105 transition-all duration-200">Create Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function flipForm() {
            const container = document.getElementById('form-container');
            const isLogin = container.style.transform !== 'rotateY(180deg)';
            if (isLogin) {
                container.classList.add('register-mode');
            } else {
                container.classList.remove('register-mode');
            }
            container.style.transform = isLogin ? 'rotateY(180deg)' : 'rotateY(0deg)';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.add('hidden');
        }

        // Password toggle for add user modal
        document.getElementById('toggleAddUserPassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('addUserPassword');
            const eyeIcon = document.getElementById('addUserEyeIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        });

        // Hide loading overlay when page is loaded
        window.addEventListener('load', function() {
            document.querySelector('.loading-overlay').style.opacity = '0';
            setTimeout(() => {
                document.querySelector('.loading-overlay').style.display = 'none';
            }, 400);
            // Trigger user account modal if needed
            <?php if (isset($_SESSION['show_credentials_modal']) && $_SESSION['show_credentials_modal'] && isset($_SESSION['new_patient_id'])): ?>
                document.getElementById('addUserPatientId').value = '<?php echo htmlspecialchars($_SESSION['new_patient_id']); ?>';
                document.getElementById('addUserModal').classList.remove('hidden');
            <?php endif; ?>
        });
    </script>
</body>
</html>