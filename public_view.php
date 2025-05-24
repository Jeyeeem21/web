<?php
require_once 'config/db.php';

// Get latest about data
$stmt = $pdo->query("SELECT * FROM about WHERE status = 1 ORDER BY createdDate DESC LIMIT 1");
$about = $stmt->fetch();

// Get latest home data
$stmt = $pdo->query("SELECT * FROM home WHERE status = 1 ORDER BY createdDate DESC LIMIT 1");
$home = $stmt->fetch();

// Get clinic details
$stmt = $pdo->query("SELECT * FROM clinic_details ORDER BY created_at DESC LIMIT 1");
$clinic = $stmt->fetch();

// Get 10 oldest active services
$stmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY created_at ASC LIMIT 10");
$services = $stmt->fetchAll();

// Get 5 oldest active doctors
$stmt = $pdo->query("SELECT s.*, dp.doctor_position as position_name 
                     FROM staff s 
                     LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                     WHERE s.status = 1 AND s.role = 'doctor' 
                     ORDER BY s.createdDate ASC LIMIT 5");
$doctors = $stmt->fetchAll();

// Get all active doctors for modal
$stmt = $pdo->query("SELECT s.*, dp.doctor_position as position_name 
                     FROM staff s 
                     LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                     WHERE s.status = 1 AND s.role = 'doctor'");
$all_doctors = $stmt->fetchAll();

// Get all active services for modal
$stmt = $pdo->query("SELECT * FROM services WHERE status = 1");
$all_services = $stmt->fetchAll();

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] == 'get_time_slots' && isset($_GET['date']) && isset($_GET['service_id']) && isset($_GET['doctor_id'])) {
    try {
        $date = $_GET['date'];
        $service_id = $_GET['service_id'];
        $doctor_id = $_GET['doctor_id'];
        
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cannot book appointments for past dates']);
            exit();
        }
        
        $dayOfWeek = date('l', strtotime($date));
        
        $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day = :rest_day");
        $stmt->execute([':doctor_id' => $doctor_id, ':rest_day' => $dayOfWeek]);
        $restDay = $stmt->fetch();
        
        if ($restDay) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Doctor is not available on ' . $dayOfWeek]);
            exit();
        }
        
        $clinicHours = '';
        if ($dayOfWeek == 'Sunday') {
            $clinicHours = $clinic['hours_sunday'];
        } else if ($dayOfWeek == 'Saturday') {
            $clinicHours = $clinic['hours_saturday'];
        } else {
            $clinicHours = $clinic['hours_weekdays'];
        }
        
        if ($clinicHours == 'Closed') {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Clinic is closed on ' . $dayOfWeek]);
            exit();
        }
        
        $hours = explode(' - ', $clinicHours);
        $startTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[0]))));
        $endTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[1]))));
        
        $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day != :rest_day");
        $stmt->execute([':doctor_id' => $doctor_id, ':rest_day' => $dayOfWeek]);
        $doctorSchedule = $stmt->fetch();
        
        if ($doctorSchedule) {
            $startTime = $doctorSchedule['start_time'];
            $endTime = $doctorSchedule['end_time'];
        }
        
        $stmt = $pdo->prepare("SELECT HOUR(appointment_time) as booked_hour 
                              FROM appointments 
                              WHERE staff_id = :doctor_id AND appointment_date = :date 
                              AND status != 'Cancelled'");
        $stmt->execute([':doctor_id' => $doctor_id, ':date' => $date]);
        $bookedHours = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        $availableSlots = [];
        $startHour = intval(date('H', strtotime($startTime)));
        $startMinute = intval(date('i', strtotime($startTime)));
        if ($startMinute > 0) {
            $startHour++;
        }
        $endHour = intval(date('H', strtotime($endTime)));
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            if ($hour != 12 && !in_array($hour, $bookedHours)) {
                $timeStr = sprintf('%02d:00:00', $hour);
                $availableSlots[] = date('h:00 A', strtotime($timeStr));
            }
        }
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'slots' => $availableSlots]);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['clinic_name']); ?></title>
    
    <!-- Google Fonts: Inter and Poppins -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap">
    
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
                        }
                    },
                    animation: {
                        'slide-up': 'slideUp 0.3s ease-out forwards',
                        'fade-in': 'fadeIn 0.3s ease-out forwards',
                        'spin': 'spin 1s linear infinite',
                        'spin-slow': 'spinSlow 2s linear infinite',
                        'pulse-once': 'pulseOnce 0.5s ease-in-out'
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <!-- Loading Overlay -->
    <div class="fixed inset-0 bg-white z-[9999] flex items-center justify-center transition-opacity duration-400 loading-overlay">
        <div class="w-12 h-12 border-3 border-primary-100 border-t-primary-500 rounded-full animate-spin"></div>
    </div>

    <!-- Header -->
    <header class="bg-white/95 backdrop-blur-md shadow-md fixed w-full top-0 z-50 animate-fade-in">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <a href="#" class="text-2xl font-heading font-bold text-primary-500"><?php echo htmlspecialchars($clinic['clinic_name']); ?></a>
                </div>
                <div class="flex items-center space-x-8">
                    <div class="hidden md:flex space-x-8">
                        <a href="#home" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="home">Home</a>
                        <a href="#about" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="about">About</a>
                        <a href="#services-doctors" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="services-doctors">Services & Doctors</a>
                        <a href="#contact" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="contact">Contact</a>
                    </div>
                    <a href="login.php" class="bg-primary-500 text-white px-4 py-2 rounded-lg hover:bg-primary-600 hover:scale-110 transition-all duration-300 text-sm font-bold hover:shadow-lg">Login</a>
                    <button class="md:hidden" id="mobile-menu-button" aria-label="Toggle mobile menu">
                        <i class="fas fa-bars text-secondary text-xl"></i>
                    </button>
                </div>
            </div>
            <!-- Mobile Menu -->
            <div class="md:hidden hidden" id="mobile-menu">
                <div class="flex flex-col space-y-3 mt-4 pb-3">
                    <a href="#home" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="home">Home</a>
                    <a href="#about" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="about">About</a>
                    <a href="#services-doctors" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="services-doctors">Services & Doctors</a>
                    <a href="#contact" class="relative text-secondary hover:text-primary-500 transition-all duration-300 nav-link after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-primary-500 after:transition-all after:duration-300 hover:after:w-full text-base font-semibold" data-section="contact">Contact</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="home" class="bg-cover bg-center min-h-[80vh] flex items-center justify-center text-center text-white relative animate-pulse-once" style="background-image: linear-gradient(rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.75)), url('<?php echo htmlspecialchars($home['homePic']); ?>');">
        <div class="container mx-auto px-6 relative z-10">
            <h1 class="text-4xl md:text-6xl font-heading font-extrabold leading-tight mb-4 animate-slide-up"><?php echo htmlspecialchars($home['maintext']); ?></h1>
            <p class="text-lg md:text-2xl font-semibold leading-relaxed mb-4 animate-slide-up animation-delay-100"><?php echo htmlspecialchars($home['secondtext']); ?></p>
            <p class="text-base md:text-lg font-medium leading-relaxed mb-6 animate-slide-up animation-delay-200"><?php echo htmlspecialchars($home['thirdtext']); ?></p>
            <a href="#contact" class="inline-flex items-center bg-primary-500 text-white px-6 py-3 rounded-lg text-base font-bold hover:bg-primary-600 hover:scale-110 hover:shadow-lg transition-all duration-300 animate-slide-up animation-delay-300 hover:animate-bounce">
                <i class="fas fa-calendar-check mr-2 text-lg"></i> Book Appointment
            </a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-12 bg-gradient-to-r from-primary-100 to-accent-100">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center gap-8">
                <div class="md:w-1/2">
                    <img src="<?php echo htmlspecialchars($about['aboutPic']); ?>" alt="About Us" class="w-full rounded-2xl shadow-xl hover:shadow-2xl hover:scale-[1.02] transition-all duration-300 border-2 border-primary-500/50">
                </div>
                <div class="md:w-1/2">
                    <h2 class="text-4xl font-heading font-extrabold text-neutral-dark leading-tight mb-4 animate-slide-up">About Us</h2>
                    <p class="text-base font-medium text-secondary leading-relaxed animate-slide-up animation-delay-100"><?php echo htmlspecialchars($about['aboutText']); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services and Doctors Section -->
    <section id="services-doctors" class="py-12 bg-gradient-to-r from-primary-100 to-accent-100">
        <div class="container mx-auto px-6">
            <div class="text-center mb-10">
                <h2 class="text-4xl font-heading font-extrabold text-neutral-dark leading-tight mb-3 animate-slide-up">Our Services & Doctors</h2>
                <p class="text-base font-medium text-secondary leading-relaxed max-w-lg mx-auto animate-slide-up animation-delay-100">Meet our expert team and explore our premium dental services.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Doctors Column -->
                <div class="bg-primary-50 rounded-2xl shadow-xl border-2 border-primary-500/50 w-full">
                    <div class="flex justify-between items-center p-6 bg-gradient-to-r from-primary-500 to-accent-300 text-white">
                        <h3 class="text-2xl font-heading font-bold">Our Doctors</h3>
                        <button data-modal="doctors-modal" class="inline-flex items-center bg-white text-primary-500 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gradient-to-r hover:from-primary-100 hover:to-accent-100 hover:scale-110 hover:shadow-lg transition-all duration-300 view-all-btn" aria-label="View all doctors">
                            <i class="fas fa-list mr-2 text-lg hover:animate-spin-slow"></i> View All
                        </button>
                    </div>
                    <div class="p-6 max-h-[432px] overflow-y-auto scrollbar-thin scrollbar-thumb-primary-500 scrollbar-track-primary-100">
                        <div class="grid grid-cols-2 gap-4 md:space-y-4 md:grid-cols-1">
                            <?php foreach ($doctors as $index => $doctor): ?>
                            <div class="flex flex-col gap-2 p-4 bg-gray-50 rounded-2xl hover:shadow-2xl hover:scale-[1.02] hover:bg-primary-100/50 transition-all duration-300 animate-slide-up animate-pulse-once h-auto min-h-24 md:flex-row md:items-center md:h-24 border-2 border-primary-500/50" style="animation-delay: <?php echo $index * 100; ?>ms">
                                <img src="<?php echo htmlspecialchars($doctor['photo']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>" class="w-12 h-12 rounded-full object-cover border-2 border-primary-100 mx-auto md:mx-0">
                                <div class="flex-1 text-center md:text-left">
                                    <p class="text-sm font-semibold text-neutral-dark"><?php echo htmlspecialchars($doctor['name']); ?></p>
                                    <span class="inline-block text-xs font-medium text-primary-500 bg-primary-100 px-2 py-1 rounded-full mt-1"><?php echo htmlspecialchars($doctor['position_name']); ?></span>
                                </div>
                                <a href="#contact" class="inline-flex items-center px-4 py-2 bg-accent-300 text-white rounded-lg hover:bg-accent-400 hover:scale-110 hover:shadow-lg text-sm font-bold transition-all duration-300 hover:animate-bounce mx-auto md:mx-0" aria-label="Book with <?php echo htmlspecialchars($doctor['name']); ?>">
                                    <i class="fas fa-calendar-check mr-1 text-lg"></i> Book
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- Services Column -->
                <div class="bg-white rounded-2xl shadow-xl border-2 border-primary-500/50 w-full">
                    <div class="flex justify-between items-center p-6 bg-gradient-to-r from-primary-500 to-accent-300 text-white">
                        <h3 class="text-2xl font-heading font-bold">Our Services</h3>
                        <button data-modal="services-modal" class="inline-flex items-center bg-white text-primary-500 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gradient-to-r hover:from-primary-100 hover:to-accent-100 hover:scale-110 hover:shadow-lg transition-all duration-300 view-all-btn" aria-label="View all services">
                            <i class="fas fa-list mr-2 text-lg hover:animate-spin-slow"></i> View All
                        </button>
                    </div>
                    <div class="p-6 max-h-[432px] overflow-y-auto scrollbar-thin scrollbar-thumb-primary-500 scrollbar-track-gray-100">
                        <?php
                        $grouped_services = [];
                        foreach ($services as $service) {
                            $grouped_services[$service['kind_of_doctor']][] = $service;
                        }
                        foreach ($grouped_services as $position => $position_services):
                        ?>
                        <div class="py-4">
                            <h4 class="text-xl font-heading font-bold text-neutral-dark mb-3 relative">
                                <?php echo htmlspecialchars($position); ?>
                                <span class="absolute bottom-0 left-0 w-16 h-1 bg-gradient-to-r from-primary-500 to-accent-300"></span>
                            </h4>
                            <!-- Mobile: Card Layout -->
                            <div class="grid grid-cols-2 gap-4 md:hidden">
                                <?php foreach ($position_services as $index => $service): ?>
                                <div class="flex flex-col gap-2 p-4 bg-gray-50 rounded-2xl hover:shadow-2xl hover:scale-[1.02] hover:bg-primary-100/50 transition-all duration-300 animate-slide-up animate-pulse-once h-auto min-h-24 border-2 border-primary-500/50" style="animation-delay: <?php echo $index * 100; ?>ms">
                                    <img src="<?php echo htmlspecialchars($service['service_picture']); ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>" class="h-12 w-12 object-cover rounded-md border-2 border-primary-500/50 mx-auto">
                                    <p class="text-sm font-semibold text-neutral-dark text-center truncate"><?php echo htmlspecialchars($service['service_name']); ?></p>
                                    <p class="text-xs font-medium text-secondary text-center truncate group relative">
                                        <?php echo htmlspecialchars($service['service_description']); ?>
                                        <span class="absolute hidden group-hover:block bg-neutral-dark text-white text-xs rounded-lg p-3 z-10 w-64 shadow-2xl"><?php echo htmlspecialchars($service['service_description']); ?></span>
                                    </p>
                                    <p class="text-sm font-semibold text-primary-500 text-center">₱<?php echo number_format($service['price'], 2); ?></p>
                                    <p class="text-xs font-medium text-secondary text-center"><?php echo htmlspecialchars($service['time']); ?></p>
                                    <a href="#contact" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 hover:scale-110 hover:shadow-lg text-sm font-bold transition-all duration-300 hover:animate-bounce w-full justify-center" aria-label="Book <?php echo htmlspecialchars($service['service_name']); ?>">
                                        <i class="fas fa-calendar-check mr-1 text-lg"></i> Book
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Desktop: Row Layout -->
                            <div class="hidden md:block">
                                <div class="grid grid-cols-12 gap-4 border-b border-gray-100 pb-2 mb-2">
                                    <div class="col-span-2 font-semibold text-sm text-gray-600">Image</div>
                                    <div class="col-span-5 font-semibold text-sm text-gray-600">Service</div>
                                    <div class="col-span-3 font-semibold text-sm text-gray-600">Price</div>
                                    <div class="col-span-2 font-semibold text-sm text-gray-600">Action</div>
                                </div>
                                <?php foreach ($position_services as $index => $service): ?>
                                <div class="grid grid-cols-12 gap-4 items-center py-2 border-b border-gray-100 hover:bg-primary-100/70 hover:shadow-md transition-all duration-300 animate-slide-up animate-pulse-once h-24" style="animation-delay: <?php echo $index * 100; ?>ms">
                                    <div class="col-span-2">
                                        <img src="<?php echo htmlspecialchars($service['service_picture']); ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>" class="h-12 w-12 object-cover rounded-md border-2 border-primary-500/50">
                                    </div>
                                    <div class="col-span-5">
                                        <p class="text-base font-semibold text-neutral-dark"><?php echo htmlspecialchars($service['service_name']); ?></p>
                                        <p class="text-xs font-medium text-secondary line-clamp-1 group relative">
                                            <?php echo htmlspecialchars($service['service_description']); ?>
                                            <span class="absolute hidden group-hover:block bg-neutral-dark text-white text-xs rounded-lg p-3 z-10 w-64 shadow-2xl"><?php echo htmlspecialchars($service['service_description']); ?></span>
                                        </p>
                                    </div>
                                    <div class="col-span-3">
                                        <p class="text-base font-semibold text-primary-500">₱<?php echo number_format($service['price'], 2); ?></p>
                                        <p class="text-xs font-medium text-secondary"><?php echo htmlspecialchars($service['time']); ?></p>
                                    </div>
                                    <div class="col-span-2 flex items-center">
                                        <a href="#contact" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 hover:scale-110 hover:shadow-lg text-sm font-bold transition-all duration-300 hover:animate-bounce" aria-label="Book <?php echo htmlspecialchars($service['service_name']); ?>">
                                            <i class="fas fa-calendar-check mr-1 text-lg"></i> Book
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Doctors Modal -->
    <div id="doctors-modal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[1000] hidden" onclick="if(event.target===this) document.getElementById('doctors-modal').classList.add('hidden')">
        <div class="bg-primary-50 w-full max-w-4xl max-h-[80vh] rounded-2xl shadow-2xl border-2 border-primary-500/20 overflow-hidden flex flex-col m-4">
            <div class="flex justify-between items-center p-6 bg-gradient-to-r from-primary-500 to-accent-300 text-white">
                <h3 class="text-2xl font-heading font-bold">All Doctors</h3>
                <button class="text-white hover:text-accent-100 close-modal hover:animate-spin-slow" data-modal="doctors-modal" aria-label="Close modal">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-6">
                <input type="text" id="doctors-search" class="w-full p-3 text-base font-medium border border-primary-500/50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent mb-4 bg-white shadow-sm" placeholder="Search doctors by name..." aria-label="Search doctors">
                <div class="max-h-[60vh] overflow-y-auto scrollbar-thin scrollbar-thumb-primary-500 scrollbar-track-primary-100" id="doctors-list">
                    <div class="grid grid-cols-2 gap-4 md:space-y-4 md:grid-cols-1">
                        <?php foreach ($all_doctors as $index => $doctor): ?>
                        <div class="flex flex-col gap-2 p-4 bg-gray-50 rounded-2xl hover:shadow-2xl hover:scale-[1.02] hover:bg-primary-100/50 transition-all duration-300 doctor-item h-auto min-h-24 md:flex-row md:items-center md:h-24 border-2 border-primary-500/50" data-name="<?php echo htmlspecialchars(strtolower($doctor['name'])); ?>" style="animation-delay: <?php echo $index * 100; ?>ms">
                            <img src="<?php echo htmlspecialchars($doctor['photo']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>" class="w-12 h-12 rounded-full object-cover border-2 border-primary-100 mx-auto md:mx-0">
                            <div class="flex-1 text-center md:text-left">
                                <p class="text-sm font-semibold text-neutral-dark"><?php echo htmlspecialchars($doctor['name']); ?></p>
                                <span class="inline-block text-xs font-medium text-primary-500 bg-primary-100 px-2 py-1 rounded-full mt-1"><?php echo htmlspecialchars($doctor['position_name']); ?></span>
                            </div>
                            <a href="#contact" class="inline-flex items-center px-4 py-2 bg-accent-300 text-white rounded-lg hover:bg-accent-400 hover:scale-110 hover:shadow-lg text-sm font-bold transition-all duration-300 hover:animate-bounce mx-auto md:mx-0" aria-label="Book with <?php echo htmlspecialchars($doctor['name']); ?>">
                                <i class="fas fa-calendar-check mr-1 text-lg"></i> Book
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-base font-medium text-secondary text-center hidden" id="doctors-no-results">No doctors found.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Services Modal -->
    <div id="services-modal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[1000] hidden" onclick="if(event.target===this) document.getElementById('services-modal').classList.add('hidden')">
        <div class="bg-white w-full max-w-4xl max-h-[80vh] rounded-2xl shadow-2xl border-2 border-primary-500/20 overflow-hidden flex flex-col m-4">
            <div class="flex justify-between items-center p-6 bg-gradient-to-r from-primary-500 to-accent-300 text-white">
                <h3 class="text-2xl font-heading font-bold">All Services</h3>
                <button class="text-white hover:text-accent-100 close-modal hover:animate-spin-slow" data-modal="services-modal" aria-label="Close modal">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-6">
                <input type="text" id="services-search" class="w-full p-3 text-base font-medium border border-primary-500/50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent mb-4 bg-white shadow-sm" placeholder="Search services by name..." aria-label="Search services">
                <div class="max-h-[60vh] overflow-y-auto scrollbar-thin scrollbar-thumb-primary-500 scrollbar-track-gray-100" id="services-list">
                    <?php
                    $grouped_all_services = [];
                    foreach ($all_services as $service) {
                        $grouped_all_services[$service['kind_of_doctor']][] = $service;
                    }
                    foreach ($grouped_all_services as $position => $position_services):
                    ?>
                    <div class="py-4">
                        <h4 class="text-xl font-heading font-bold text-neutral-dark mb-3 relative">
                            <?php echo htmlspecialchars($position); ?>
                            <span class="absolute bottom-0 left-0 w-16 h-1 bg-gradient-to-r from-primary-500 to-accent-300"></span>
                        </h4>
                        <!-- Mobile: Card Layout -->
                        <div class="grid grid-cols-2 gap-4 md:hidden">
                            <?php foreach ($position_services as $index => $service): ?>
                            <div class="flex flex-col gap-2 p-4 bg-gray-50 rounded-2xl hover:shadow-2xl hover:scale-[1.02] hover:bg-primary-100/50 transition-all duration-300 service-item h-auto min-h-24 border-2 border-primary-500/50" data-name="<?php echo htmlspecialchars(strtolower($service['service_name'])); ?>" style="animation-delay: <?php echo $index * 100; ?>ms">
                                <img src="<?php echo htmlspecialchars($service['service_picture']); ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>" class="h-12 w-12 object-cover rounded-md border-2 border-primary-500/50 mx-auto">
                                <p class="text-sm font-semibold text-neutral-dark text-center truncate"><?php echo htmlspecialchars($service['service_name']); ?></p>
                                <p class="text-xs font-medium text-secondary text-center truncate group relative">
                                    <?php echo htmlspecialchars($service['service_description']); ?>
                                    <span class="absolute hidden group-hover:block bg-neutral-dark text-white text-xs rounded-lg p-3 z-10 w-64 shadow-2xl"><?php echo htmlspecialchars($service['service_description']); ?></span>
                                </p>
                                <p class="text-sm font-semibold text-primary-500 text-center">₱<?php echo number_format($service['price'], 2); ?></p>
                                <p class="text-xs font-medium text-secondary text-center"><?php echo htmlspecialchars($service['time']); ?></p>
                                <a href="#contact" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 hover:scale-110 hover:shadow-lg text-sm font-bold transition-all duration-300 hover:animate-bounce w-full justify-center" aria-label="Book <?php echo htmlspecialchars($service['service_name']); ?>">
                                    <i class="fas fa-calendar-check mr-1 text-lg"></i> Book
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Desktop: Row Layout -->
                        <div class="hidden md:block">
                            <div class="grid grid-cols-12 gap-4 border-b border-gray-100 pb-2 mb-2">
                                <div class="col-span-2 font-semibold text-sm text-gray-600">Image</div>
                                <div class="col-span-5 font-semibold text-sm text-gray-600">Service</div>
                                <div class="col-span-3 font-semibold text-sm text-gray-600">Price</div>
                                <div class="col-span-2 font-semibold text-sm text-gray-600">Action</div>
                            </div>
                            <?php foreach ($position_services as $index => $service): ?>
                            <div class="grid grid-cols-12 gap-4 items-center py-2 border-b border-gray-100 hover:bg-primary-100/70 hover:shadow-md transition-all duration-300 service-item h-24" data-name="<?php echo htmlspecialchars(strtolower($service['service_name'])); ?>" style="animation-delay: <?php echo $index * 100; ?>ms">
                                <div class="col-span-2">
                                    <img src="<?php echo htmlspecialchars($service['service_picture']); ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>" class="h-12 w-12 object-cover rounded-md border-2 border-primary-500/50">
                                </div>
                                <div class="col-span-5">
                                    <p class="text-base font-semibold text-neutral-dark"><?php echo htmlspecialchars($service['service_name']); ?></p>
                                    <p class="text-xs font-medium text-secondary line-clamp-1 group relative">
                                        <?php echo htmlspecialchars($service['service_description']); ?>
                                        <span class="absolute hidden group-hover:block bg-neutral-dark text-white text-xs rounded-lg p-3 z-10 w-64 shadow-2xl"><?php echo htmlspecialchars($service['service_description']); ?></span>
                                    </p>
                                </div>
                                <div class="col-span-3">
                                    <p class="text-base font-semibold text-primary-500">₱<?php echo number_format($service['price'], 2); ?></p>
                                    <p class="text-xs font-medium text-secondary"><?php echo htmlspecialchars($service['time']); ?></p>
                                </div>
                                <div class="col-span-2 flex items-center">
                                    <a href="#contact" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 hover:scale-110 hover:shadow-lg text-sm font-bold transition-all duration-300 hover:animate-bounce" aria-label="Book <?php echo htmlspecialchars($service['service_name']); ?>">
                                        <i class="fas fa-calendar-check mr-1 text-lg"></i> Book
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <p class="text-base font-medium text-secondary text-center hidden" id="services-no-results">No services found.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Section -->
    <section id="contact" class="py-12 bg-gradient-to-r from-primary-100 to-accent-100">
        <div class="container mx-auto px-6">
            <div class="text-center mb-10">
                <h2 class="text-4xl font-heading font-extrabold text-neutral-dark leading-tight mb-3 animate-slide-up">Contact Us</h2>
                <p class="text-base font-medium text-secondary leading-relaxed max-w-lg mx-auto animate-slide-up animation-delay-100">Reach out to schedule an appointment or ask any questions.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-xl hover:shadow-2xl hover:scale-[1.02] transition-all duration-300 animate-slide-up border-2 border-primary-500/50">
                    <div class="w-12 h-12 bg-primary-100 text-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-map-marker-alt text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold font-heading mb-2">Address</h3>
                    <p class="text-sm font-medium text-secondary"><?php echo htmlspecialchars($clinic['address']); ?></p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-xl hover:shadow-2xl hover:scale-[1.02] transition-all duration-300 animate-slide-up animation-delay-100 border-2 border-primary-500/50">
                    <div class="w-12 h-12 bg-primary-100 text-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-phone text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold font-heading mb-2">Phone</h3>
                    <p class="text-sm font-medium text-secondary"><?php echo htmlspecialchars($clinic['phone']); ?></p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-xl hover:shadow-2xl hover:scale-[1.02] transition-all duration-300 animate-slide-up animation-delay-200 border-2 border-primary-500/50">
                    <div class="w-12 h-12 bg-primary-100 text-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-clock text-lg"></i>
                    </div>
                    <h3 class="text-base font-bold font-heading mb-2">Hours</h3>
                    <p class="text-sm font-medium text-secondary">
                        Weekdays: <?php echo htmlspecialchars($clinic['hours_weekdays']); ?><br>
                        Saturday: <?php echo htmlspecialchars($clinic['hours_saturday']); ?><br>
                        Sunday: <?php echo htmlspecialchars($clinic['hours_sunday']); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-neutral-dark text-white py-12">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-lg font-heading font-bold text-primary-100 mb-4"><?php echo htmlspecialchars($clinic['clinic_name']); ?></h3>
                    <p class="text-sm font-medium text-gray-400"><?php echo htmlspecialchars($clinic['address']); ?></p>
                </div>
                <div>
                    <h3 class="text-lg font-heading font-bold text-primary-100 mb-4">Contact Info</h3>
                    <p class="text-sm font-medium text-gray-400">
                        Phone: <?php echo htmlspecialchars($clinic['phone']); ?><br>
                        Email: <?php echo htmlspecialchars($clinic['email']); ?>
                    </p>
                </div>
                <div>
                    <h3 class="text-lg font-heading font-bold text-primary-100 mb-4">Business Hours</h3>
                    <p class="text-sm font-medium text-gray-400">
                        Weekdays: <?php echo htmlspecialchars($clinic['hours_weekdays']); ?><br>
                        Saturday: <?php echo htmlspecialchars($clinic['hours_saturday']); ?><br>
                        Sunday: <?php echo htmlspecialchars($clinic['hours_sunday']); ?>
                    </p>
                </div>
            </div>
            <div class="border-t border-gray-700 pt-6 text-center text-sm font-medium text-gray-400">
                <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($clinic['clinic_name']); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', e => {
                e.preventDefault();
                document.querySelector(anchor.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
            });
        });

        // Navigation Active Link
        function updateActiveNavLink() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            sections.forEach(section => {
                const rect = section.getBoundingClientRect();
                const navLink = document.querySelector(`.nav-link[data-section="${section.id}"]`);
                if (rect.top <= 100 && rect.bottom >= 100) {
                    navLinks.forEach(link => {
                        link.classList.remove('active', 'text-primary-500', 'after:w-full');
                    });
                    if (navLink) {
                        navLink.classList.add('active', 'text-primary-500', 'after:w-full');
                    }
                }
            });
        }

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

        // Modal Handling
        document.querySelectorAll('.view-all-btn').forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.dataset.modal;
                document.getElementById(modalId).classList.remove('hidden');
            });
        });

        document.querySelectorAll('.close-modal').forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.dataset.modal;
                document.getElementById(modalId).classList.add('hidden');
                // Reset search
                const searchInput = document.getElementById(modalId === 'doctors-modal' ? 'doctors-search' : 'services-search');
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
            });
        });

        // Doctors Search
        document.getElementById('doctors-search').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const items = document.querySelectorAll('#doctors-list .doctor-item');
            const noResults = document.getElementById('doctors-no-results');
            let hasResults = false;

            items.forEach(item => {
                const name = item.dataset.name;
                if (name.includes(query)) {
                    item.style.display = '';
                    hasResults = true;
                } else {
                    item.style.display = 'none';
                }
            });

            noResults.style.display = hasResults ? 'none' : 'block';
        });

        // Services Search
        document.getElementById('services-search').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const items = document.querySelectorAll('#services-list .service-item');
            const noResults = document.getElementById('services-no-results');
            let hasResults = false;

            items.forEach(item => {
                const name = item.dataset.name;
                if (name.includes(query)) {
                    item.style.display = '';
                    hasResults = true;
                } else {
                    item.style.display = 'none';
                }
            });

            noResults.style.display = hasResults ? 'none' : 'block';
        });

        // Page Load
        document.addEventListener('DOMContentLoaded', () => {
            // Remove loading overlay
            setTimeout(() => {
                const overlay = document.querySelector('.loading-overlay');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                setTimeout(() => overlay.remove(), 400);
            }, 600);

            // Scroll and nav events
            window.addEventListener('scroll', () => {
                updateActiveNavLink();
                handleScrollAnimation();
            });
            updateActiveNavLink();
            handleScrollAnimation();

            // Nav link click
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    document.querySelectorAll('.nav-link').forEach(l => {
                        l.classList.remove('active', 'text-primary-500', 'after:w-full');
                    });
                    this.classList.add('active', 'text-primary-500', 'after:w-full');
                });
            });

            // Debug home data
            console.log('Home data:', <?php echo json_encode($home); ?>);
        });

        // AJAX for Time Slots
        $(document).ready(function() {
            $('#serviceSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const requiredDoctor = selectedOption.data('doctor');
                $('#doctorSelect option').each(function() {
                    const doctorPosition = $(this).data('position');
                    if (doctorPosition === requiredDoctor || $(this).val() === '') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
                $('#doctorSelect').val('');
                $('#timeSelect').html('<option value="">Select Time</option>');
            });

            $('#appointmentDate, #serviceSelect, #doctorSelect').on('change', function() {
                const date = $('#appointmentDate').val();
                const serviceId = $('#serviceSelect').val();
                const doctorId = $('#doctorSelect').val();
                if (!date || !serviceId || !doctorId) {
                    $('#timeSelect').html('<option value="">Please select all required fields</option>');
                    return;
                }
                $('#timeSelect').html('<option value="">Loading time slots...</option>');
                $.ajax({
                    url: 'index.php',
                    type: 'GET',
                    data: {
                        page: 'schedule',
                        action: 'get_time_slots',
                        date: date,
                        service_id: serviceId,
                        doctor_id: doctorId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let options = '<option value="">Select Time</option>';
                            if (response.slots?.length) {
                                response.slots.forEach(slot => {
                                    options += `<option value="${slot}">${slot}</option>`;
                                });
                            } else {
                                options = '<option value="">No available time slots</option>';
                            }
                            $('#timeSelect').html(options);
                        } else {
                            $('#timeSelect').html('<option value="">No available time slots</option>');
                            if (response.message) alert(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error, xhr.responseText);
                        $('#timeSelect').html('<option value="">Error loading time slots</option>');
                        let errorMessage = 'Error loading time slots. ';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) errorMessage += response.message;
                        } catch (e) {
                            errorMessage += 'Please try again.';
                        }
                        alert(errorMessage);
                    }
                });
            });
        });

        function updateStatus(id, status) {
            if (confirm(`Are you sure you want to mark this appointment as ${status}?`)) {
                document.getElementById('appointmentId').value = id;
                document.getElementById('appointmentStatus').value = status;
                document.getElementById('statusForm').submit();
            }
        }
    </script>
</body>
</html>