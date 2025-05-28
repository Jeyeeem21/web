<?php
session_start();
require_once 'config/db.php';

// Function to log audit events
function logAudit($pdo, $user_id, $username, $action, $status, $ip_address) {
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $action, $status, $ip_address]);
}

// Function to check if user is in cooldown
function isInCooldown() {
    if (!isset($_SESSION['login_attempts']) || $_SESSION['login_attempts'] < 3) {
        return false;
    }
    
    $last_attempt_time = $_SESSION['last_attempt_time'] ?? 0;
    $current_time = time();
    $attempt_count = $_SESSION['login_attempts'] ?? 0;
    
    // Calculate cooldown duration based on number of attempts
    $cooldown_duration = 30; // 30 seconds for first 3 attempts
    if ($attempt_count >= 6) {
        $cooldown_duration = 120; // 2 minutes after 6 attempts
    } elseif ($attempt_count >= 3) {
        $cooldown_duration = 60; // 1 minute after 3 attempts
    }
    
    $remaining_time = ($last_attempt_time + $cooldown_duration) - $current_time;
    
    if ($remaining_time > 0) {
        return $remaining_time;
    }
    
    // Reset attempts if cooldown has passed
    if ($remaining_time <= 0) {
        $_SESSION['login_attempts'] = 0;
    }
    
    return false;
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Login
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        // Check if user is in cooldown
        $cooldown_remaining = isInCooldown();
        if ($cooldown_remaining !== false) {
            $_SESSION['error'] = "Too many failed attempts. Please try again in $cooldown_remaining seconds.";
            logAudit($pdo, null, $_POST['username'] ?? '', 'Login Attempt', 'Failure: Account Locked (Cooldown)', $ip_address);
            header("Location: login.php");
            exit();
        }
        
        $username = filter_input(INPUT_POST, 'username');
        $password = $_POST['password'];
        if (empty($username) || empty($password)) {
            $_SESSION['error'] = "Please fill in all fields";
            logAudit($pdo, null, $username, 'Login Attempt', 'Failure: Missing Fields', $ip_address);
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
                // Successful login - reset attempts
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = 0;
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                logAudit($pdo, $user['id'], $user['username'], 'Login', 'Success', $ip_address);

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
                // Failed login attempt
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_attempt_time'] = time();
                
                $attempts_left = 3 - $_SESSION['login_attempts'];
                if ($attempts_left > 0) {
                    $_SESSION['error'] = "Invalid username or password. You have $attempts_left attempts left.";
                } else {
                    $cooldown_duration = 30;
                    $_SESSION['error'] = "Too many failed attempts. Please try again in $cooldown_duration seconds.";
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
}
?>
<!-- HTML content unchanged -->
<!-- You can paste back your full HTML here -->
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
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        },
                        pulse: {
                            '0%, 100%': { opacity: '1' },
                            '50%': { opacity: '0.8' }
                        }
                    },
                    animation: {
                        'slide-up': 'slideUp 0.3s ease-out forwards',
                        'fade-in': 'fadeIn 0.3s ease-out forwards',
                        'pulse': 'pulse 2s ease-in-out infinite'
                    }
                }
            }
        }
    </script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check for cooldown status and show alert if needed
    <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3): ?>
        <?php
        $attempt_count = $_SESSION['login_attempts'];
        $cooldown_duration = 30;
        if ($attempt_count >= 6) {
            $cooldown_duration = 120;
        } elseif ($attempt_count >= 3) {
            $cooldown_duration = 60;
        }
        $remaining_time = ($_SESSION['last_attempt_time'] + $cooldown_duration) - time();
        ?>
        
        if (<?php echo $remaining_time; ?> > 0) {
            let timerInterval;
            Swal.fire({
                title: 'Too Many Attempts!',
                html: `Please wait <b></b> seconds before trying again.`,
                timer: <?php echo $remaining_time * 1000; ?>,
                timerProgressBar: true,
                didOpen: () => {
                    const timer = Swal.getHtmlContainer().querySelector('b');
                    timerInterval = setInterval(() => {
                        const timeLeft = Math.ceil(Swal.getTimerLeft() / 1000);
                        timer.textContent = timeLeft;
                    }, 1000);
                },
                willClose: () => {
                    clearInterval(timerInterval);
                },
                icon: 'error',
                confirmButtonText: 'OK',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'bg-primary-500 text-white px-6 py-3 rounded-xl text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300'
                }
            }).then(() => {
                // Refresh the page to reset the form
                window.location.reload();
            });
        }
    <?php endif; ?>
    
    // Show remaining attempts warning
    <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 0 && $_SESSION['login_attempts'] < 3): ?>
        const attemptsLeft = 3 - <?php echo $_SESSION['login_attempts']; ?>;
        Swal.fire({
            title: 'Incorrect Credentials',
            html: `You have <b>${attemptsLeft}</b> attempt${attemptsLeft > 1 ? 's' : ''} left before your account is temporarily locked.`,
            icon: 'warning',
            confirmButtonText: 'OK',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-primary-500 text-white px-6 py-3 rounded-xl text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300'
            }
        });
    <?php endif; ?>
});
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
            height: 500px;
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
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #475569;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-primary-50 to-accent-100 font-sans flex items-center justify-center min-h-screen p-4 sm:p-6 lg:p-8">
    <!-- Loading Overlay -->
    <div class="fixed inset-0 bg-white z-[9999] flex items-center justify-center transition-opacity duration-400 loading-overlay">
        <div class="w-12 h-12 border-4 border-primary-100 border-t-primary-500 rounded-full animate-spin"></div>
    </div>

    <!-- Main Content -->
    <div class="w-full max-w-lg">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-200 text-red-800 rounded-xl animate-fade-in">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-800 rounded-xl animate-fade-in">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-2xl border-2 border-primary-500/20 overflow-hidden">
            <!-- Form Container -->
            <div id="form-container" class="relative transform-style-preserve-3d">
                <!-- Login Form -->
                <!-- Login Form -->
<div id="login-form" class="backface-hidden">
    <div class="p-10">
        <div class="relative mb-8">
            <a href="public_view.php" class="absolute left-0 top-0 text-primary-500 hover:text-primary-600 transition-all duration-300">
                <i class="fas fa-home text-2xl"></i>
                <span class="sr-only">Go to Home</span>
            </a>
            <div class="text-center">
                <h2 class="text-4xl font-heading font-bold text-neutral-dark mb-2">Welcome Back</h2>
                <p class="text-secondary text-lg">Please sign in to your account</p>
            </div>
            <!-- SweetAlert2 Trigger Button -->
            <div class="mt-4 text-center">
                <button onclick="showLoginAlert()" class="text-primary-500 hover:text-primary-600 font-semibold text-sm animate-pulse">
                    <i class="fas fa-info-circle mr-2"></i>Important Login Information
                </button>
            </div>
        </div>
        <form action="login.php" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="login">
            <div class="relative">
                <label for="username" class="block text-sm font-medium text-neutral-dark mb-2">Username</label>
                <div class="relative">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" required
                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light"
                        placeholder="Enter your username">
                </div>
            </div>
            <div class="relative">
                <label for="password" class="block text-sm font-medium text-neutral-dark mb-2">Password</label>
                <div class="relative">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" required
                        class="w-full pl-10 pr-10 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light"
                        placeholder="Enter your password">
                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-primary-500 hover:text-primary-700">
                        <svg id="eyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit"
                class="w-full bg-gradient-to-r from-primary-500 to-accent-300 text-white px-6 py-3 rounded-xl text-base font-bold hover:scale-105 hover:shadow-lg transition-all duration-300">
                Sign In
            </button>
        </form>
        <div class="mt-6 text-center">
            <p class="text-secondary text-sm">Don't have an account? 
                <button onclick="flipForm()" class="text-primary-500 font-semibold hover:text-primary-600">Register</button>
            </p>
        </div>
    </div>
</div>
                 

                <!-- Register Form -->
                <div id="register-form" class="backface-hidden rotate-y-180">
                    <div class="p-10 h-full overflow-y-auto">
                        <div class="text-center mb-8">
                            <h2 class="text-4xl font-heading font-bold text-neutral-dark mb-2">Create Account</h2>
                            <p class="text-secondary text-lg">Please fill in your details</p>
                        </div>
                        <form action="login.php" method="POST" class="space-y-6" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="register">
                            <div class="relative">
                                <label for="reg_name" class="block text-sm font-medium text-neutral-dark mb-2">Full Name</label>
                                <div class="relative">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" id="reg_name" name="name" required
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light"
                                        placeholder="Enter your full name">
                                </div>
                            </div>
                            <div class="relative">
                                <label for="reg_email" class="block text-sm font-medium text-neutral-dark mb-2">Email</label>
                                <div class="relative">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" id="reg_email" name="email" required
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light"
                                        placeholder="Enter your email">
                                </div>
                            </div>
                            <div class="relative">
                                <label for="reg_phone" class="block text-sm font-medium text-neutral-dark mb-2">Phone</label>
                                <div class="relative">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" id="reg_phone" name="phone" required
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light"
                                        placeholder="Enter your phone number">
                                </div>
                            </div>
                            <div class="relative">
                                <label for="reg_address" class="block text-sm font-medium text-neutral-dark mb-2">Address</label>
                                <div class="relative">
                                    <i class="fas fa-map-marker-alt input-icon"></i>
                                    <textarea id="reg_address" name="address" required
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light"
                                        placeholder="Enter your address" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="reg_birthdate" class="block text-sm font-medium text-neutral-dark mb-2">Birthdate</label>
                                <div class="relative">
                                    <i class="fas fa-calendar-alt input-icon"></i>
                                    <input type="date" id="reg_birthdate" name="birthdate" required
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light">
                                </div>
                            </div>
                            <div class="relative">
                                <label for="reg_gender" class="block text-sm font-medium text-neutral-dark mb-2">Gender</label>
                                <div class="relative">
                                    <i class="fas fa-venus-mars input-icon"></i>
                                    <select id="reg_gender" name="gender" required
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="reg_photo" class="block text-sm font-medium text-neutral-dark mb-2">Photo</label>
                                <div class="relative">
                                    <i class="fas fa-image input-icon"></i>
                                    <input type="file" id="reg_photo" name="photo" accept="image/*"
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium bg-neutral-light">
                                </div>
                            </div>
                            <div class="flex space-x-4">
                                <button type="button" onclick="flipForm()"
                                    class="flex-1 bg-neutral-light text-neutral-dark px-6 py-3 rounded-xl text-base font-bold hover:bg-neutral-dark hover:text-white hover:scale-105 hover:shadow-lg transition-all duration-300">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="flex-1 bg-gradient-to-r from-primary-500 to-accent-300 text-white px-6 py-3 rounded-xl text-base font-bold hover:scale-105 hover:shadow-lg transition-all duration-300">
                                    Register
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add User Account Modal -->
        <div id="addUserModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-xl bg-white border-primary-100">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-neutral-dark">Create User Account</h3>
                    <form id="addUserForm" method="POST" class="mt-4 space-y-4">
                        <input type="hidden" name="action" value="add_credentials">
                        <input type="hidden" name="patient_id" id="addUserPatientId">
                        <div class="relative">
                            <label class="block text-sm font-medium text-neutral-dark">Username</label>
                            <div class="relative">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="username" id="addUserUsername" required
                                    class="mt-1 block w-full pl-10 pr-4 py-2 rounded-xl border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm bg-neutral-light">
                            </div>
                        </div>
                        <div class="relative">
                            <label class="block text-sm font-medium text-neutral-dark">Password</label>
                            <div class="relative">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password" id="addUserPassword" required
                                    class="mt-1 block w-full pl-10 pr-4 py-2 rounded-xl border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm bg-neutral-light">
                                <button type="button" id="toggleAddUserPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 mt-6 text-primary-500 hover:text-primary-700">
                                    <svg id="addUserEyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeAddUserModal()"
                                class="px-4 py-2 bg-primary-50 text-primary-500 rounded-xl text-sm hover:bg-primary-100 transition-all duration-200">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-xl text-sm hover:scale-105 transition-all duration-200">
                                Create Account
                            </button>
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

        function showLoginAlert() {
            Swal.fire({
                title: 'Login Information',
                text: 'Please use your registered username and password to sign in. If you are a new user, click "Register" to create an account.',
                icon: 'info',
                confirmButtonText: 'Got it',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'bg-primary-500 text-white px-6 py-3 rounded-xl text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300'
                }
            });
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

        // Handle query parameter to show register form and SweetAlert2 success
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('show') === 'register') {
                flipForm();
            }

            // Show success SweetAlert after user account creation
            <?php if (isset($_SESSION['success']) && $_SESSION['success'] === "User account created successfully!"): ?>
                Swal.fire({
                    title: 'Success!',
                    text: 'User account created successfully! Please login to continue.',
                    icon: 'success',
                    confirmButtonText: 'Go to Login',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'bg-primary-500 text-white px-6 py-3 rounded-xl text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300'
                    }
                }).then(() => {
                    // Clear success message to prevent re-display
                    <?php unset($_SESSION['success']); ?>;
                    // Ensure login form is shown
                    document.getElementById('form-container').style.transform = 'rotateY(0deg)';
                    document.getElementById('form-container').classList.remove('register-mode');
                });
            <?php endif; ?>
        });

        // Password toggle for login form
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
    }
});
    </script>
</body>
</html>