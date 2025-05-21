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

// Get active services
$stmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY created_at DESC");
$services = $stmt->fetchAll();

// Get active doctors
$stmt = $pdo->query("SELECT s.*, dp.doctor_position as position_name 
                     FROM staff s 
                     LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                     WHERE s.status = 1 AND s.role = 'doctor' 
                     ORDER BY s.createdDate DESC");
$doctors = $stmt->fetchAll();

// Handle AJAX requests
if (isset($_GET['action'])) {
    // Get available time slots for a specific date
    if ($_GET['action'] == 'get_time_slots' && isset($_GET['date']) && isset($_GET['service_id']) && isset($_GET['doctor_id'])) {
        try {
            $date = $_GET['date'];
            $service_id = $_GET['service_id'];
            $doctor_id = $_GET['doctor_id'];
            
            // Check if date is in the past
            if (strtotime($date) < strtotime(date('Y-m-d'))) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Cannot book appointments for past dates']);
                exit();
            }
            
            // Get day of week for the selected date
            $dayOfWeek = date('l', strtotime($date));
            
            // Check if it's doctor's rest day
            $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day = :rest_day");
            $stmt->execute([':doctor_id' => $doctor_id, ':rest_day' => $dayOfWeek]);
            $restDay = $stmt->fetch();
            
            if ($restDay) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Doctor is not available on ' . $dayOfWeek]);
                exit();
            }
            
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
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Clinic is closed on ' . $dayOfWeek]);
                exit();
            }
            
            // Parse clinic hours
            $hours = explode(' - ', $clinicHours);
            $startTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[0]))));
            $endTime = date('H:i:s', strtotime(str_replace(' AM', 'am', str_replace(' PM', 'pm', $hours[1]))));
            
            // Get doctor's schedule
            $stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id AND rest_day != :rest_day");
            $stmt->execute([':doctor_id' => $doctor_id, ':rest_day' => $dayOfWeek]);
            $doctorSchedule = $stmt->fetch();
            
            if ($doctorSchedule) {
                // Use doctor's specific hours if available
                $startTime = $doctorSchedule['start_time'];
                $endTime = $doctorSchedule['end_time'];
            }
            
            // Get existing appointments for the selected date and doctor
            $stmt = $pdo->prepare("SELECT HOUR(appointment_time) as booked_hour 
                                  FROM appointments 
                                  WHERE staff_id = :doctor_id AND appointment_date = :date 
                                  AND status != 'Cancelled'");
            $stmt->execute([':doctor_id' => $doctor_id, ':date' => $date]);
            $bookedHours = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            // Generate available time slots (hourly)
            $availableSlots = [];
            
            // Round start time to the next hour if not already on the hour
            $startHour = intval(date('H', strtotime($startTime)));
            $startMinute = intval(date('i', strtotime($startTime)));
            if ($startMinute > 0) {
                $startHour++; // Move to the next hour
            }
            
            $endHour = intval(date('H', strtotime($endTime)));
            
            // Generate slots for each hour
            for ($hour = $startHour; $hour < $endHour; $hour++) {
                // Skip lunch hour (typically 12 PM)
                if ($hour != 12) {
                    if (!in_array($hour, $bookedHours)) {
                        $timeStr = sprintf('%02d:00:00', $hour);
                        $formattedSlot = date('h:00 A', strtotime($timeStr));
                        $availableSlots[] = $formattedSlot;
                    }
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['clinic_name']); ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">

    <style>
        :root {
            --primary-color: #0d9488;
            --primary-dark: #0f766e;
            --primary-light: #ccfbf1;
            --secondary-color: #475569;
            --accent-color: #f59e0b;
            --accent-light: #fef3c7;
            --neutral-light: #f8fafc;
            --neutral-dark: #1e293b;
            --success-color: #10b981;
            --success-light: #d1fae5;
        }
        
        /* Animation Keyframes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Animation Classes */
        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .animate-fadeIn {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .animate-slideInLeft {
            animation: slideInLeft 0.6s ease-out forwards;
        }

        .animate-slideInRight {
            animation: slideInRight 0.6s ease-out forwards;
        }

        .animate-slideInUp {
            animation: slideInUp 0.6s ease-out forwards;
        }

        .animate-slideInDown {
            animation: slideInDown 0.6s ease-out forwards;
        }

        .animation-delay-200 {
            animation-delay: 200ms;
        }

        .animation-delay-400 {
            animation-delay: 400ms;
        }

        .animation-delay-600 {
            animation-delay: 600ms;
        }

        .hero-section {
            background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('<?php echo htmlspecialchars($home['homePic']); ?>');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            width: 100%;
            position: relative;
            margin-top: 0;
            padding: 120px 0 80px;
        }

        /* Ensure the hero section is visible */
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .hero-section > * {
            position: relative;
            z-index: 2;
        }

        /* Fix header overlap */
        header {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
        }

        /* Ensure content is visible */
        .container {
            position: relative;
            z-index: 2;
        }

        .service-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .service-image {
            height: 240px;
            position: relative;
        }

        .service-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .service-content {
            padding: 1.5rem;
        }

        .service-title {
            font-size: 1.25rem;
            color: var(--neutral-dark);
            margin-bottom: 0.5rem;
        }

        .service-price {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.125rem;
        }

        .gradient-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.8) 100%);
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .text-primary-600 {
            color: var(--primary-color);
        }

        .hover\:text-primary-700:hover {
            color: var(--primary-dark);
        }

        .bg-primary-600 {
            background-color: var(--primary-color);
        }

        .hover\:bg-primary-700:hover {
            background-color: var(--primary-dark);
        }

        .bg-primary-50 {
            background-color: var(--primary-light);
        }

        .hover\:bg-primary-100:hover {
            background-color: #99f6e4;
        }

        .service-card img {
            height: 12rem;
            object-fit: cover;
        }

        .service-card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .service-card-footer {
            margin-top: auto;
        }

        .nav-link.active {
            color: var(--primary-color);
        }
        
        .nav-link.active span {
            width: 100%;
        }

        .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
            color: var(--secondary-color);
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Add these new animation keyframes */
        @keyframes pageLoad {
            0% {
                opacity: 0;
                transform: scale(0.98);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideInFromTop {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.95);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Add these new animation classes */
        .page-load {
            animation: pageLoad 0.8s ease-out forwards;
        }

        .slide-in-top {
            animation: slideInFromTop 0.6s ease-out forwards;
        }

        .fade-in-scale {
            animation: fadeInScale 0.6s ease-out forwards;
        }

        /* Add loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease-out;
        }

        .loading-overlay.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--primary-light);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Add these new styles */
        .scroll-animation {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }

        .scroll-animation.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .page-transition {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Section Spacing */
        section {
            padding: 100px 0;
        }

        .section-title {
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: var(--neutral-dark);
            margin-bottom: 1rem;
        }

        .section-title p {
            color: var(--secondary-color);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Contact Cards */
        .contact-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .contact-icon {
            width: 3rem;
            height: 3rem;
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        /* About Section */
        .about-image {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .about-content {
            padding: 2rem;
        }

        /* Footer */
        footer {
            background-color: var(--neutral-dark);
            color: white;
            padding: 4rem 0 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .footer-section h3 {
            color: var(--primary-light);
            margin-bottom: 1.5rem;
        }

        .footer-section p {
            color: #94a3b8;
        }

        /* Responsive Spacing */
        @media (max-width: 768px) {
            section {
                padding: 60px 0;
            }

            .section-title {
                margin-bottom: 40px;
            }

            .section-title h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 page-transition">
    <!-- Add loading overlay at the start of body -->
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Header -->
    <header class="bg-white shadow-md fixed w-full top-0 z-50 animate-slide-down">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <a href="#" class="text-2xl font-medium text-primary-600"><?php echo htmlspecialchars($clinic['clinic_name']); ?></a>
                </div>
                <div class="flex items-center space-x-8">
                    <div class="hidden md:flex space-x-8">
                        <a href="#home" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="home">
                            Home
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                        <a href="#about" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="about">
                            About
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                        <a href="#services" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="services">
                            Services
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                        <a href="#doctors" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="doctors">
                            Doctors
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                        <a href="#contact" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="contact">
                            Contact
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                    </div>
                    <a href="login.php" class="bg-primary-600 text-white px-6 py-2 rounded-md hover:bg-primary-700 transition-colors">Login</a>
                    <button class="md:hidden" id="mobile-menu-button">
                        <i class="fas fa-bars text-gray-600 text-xl"></i>
                    </button>
                </div>
            </div>
            <!-- Mobile Menu -->
            <div class="md:hidden hidden" id="mobile-menu">
                <div class="flex flex-col space-y-3 mt-4 pb-3">
                    <a href="#home" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="home">
                        Home
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#about" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="about">
                        About
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#services" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="services">
                        Services
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#doctors" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="doctors">
                        Doctors
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#contact" class="nav-link text-gray-600 hover:text-primary-600 transition-all duration-300 relative group" data-section="contact">
                        Contact
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary-600 transition-all duration-300 group-hover:w-full"></span>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero-section flex items-center justify-center text-center text-white animate-fade-in">
        <div class="container mx-auto px-4">
            <h1 class="text-4xl md:text-6xl font-medium mb-6 animate-slide-up"><?php echo htmlspecialchars($home['maintext']); ?></h1>
            <p class="text-xl md:text-2xl mb-6 animate-slide-up animation-delay-200"><?php echo htmlspecialchars($home['secondtext']); ?></p>
            <p class="text-lg md:text-xl mb-8 animate-slide-up animation-delay-400"><?php echo htmlspecialchars($home['thirdtext']); ?></p>
            <a href="#contact" class="bg-primary-600 text-white px-8 py-3 rounded-md text-lg hover:bg-primary-700 transition-colors inline-block animate-slide-up animation-delay-600">Book Appointment</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-24 bg-white">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center gap-12">
                <div class="md:w-1/2">
                    <div class="about-image">
                        <img src="<?php echo htmlspecialchars($about['aboutPic']); ?>" alt="About Us" class="w-full">
                    </div>
                </div>
                <div class="md:w-1/2 about-content">
                    <h2 class="text-3xl font-medium text-neutral-dark mb-6">About Us</h2>
                    <p class="text-secondary-color leading-relaxed"><?php echo htmlspecialchars($about['aboutText']); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-24 bg-neutral-light">
        <div class="container mx-auto px-4">
            <div class="section-title text-center">
                <h2 class="text-3xl font-medium text-neutral-dark mb-4">Our Services</h2>
                <p class="text-secondary-color">Experience comprehensive dental care with our range of professional services.</p>
            </div>
            
            <?php
            // Group services by doctor position
            $grouped_services = [];
            foreach ($services as $service) {
                $grouped_services[$service['kind_of_doctor']][] = $service;
            }
            
            foreach ($grouped_services as $position => $position_services):
            ?>
            <div class="mb-12 scroll-animation">
                <h3 class="text-xl font-medium text-gray-800 mb-4 animate-slide-up"><?php echo htmlspecialchars($position); ?></h3>
                <div class="relative">
                    <div class="overflow-hidden">
                        <div class="flex transition-transform duration-300 ease-in-out" id="slider-<?php echo md5($position); ?>">
                            <?php foreach ($position_services as $index => $service): ?>
                            <div class="w-full md:w-1/3 lg:w-1/4 flex-shrink-0 px-3 scroll-animation" style="transition-delay: <?php echo $index * 100; ?>ms">
                                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-all duration-300 h-full flex flex-col transform hover:-translate-y-2">
                                    <div class="relative h-48">
                                        <img src="<?php echo htmlspecialchars($service['service_picture']); ?>" 
                                             alt="<?php echo htmlspecialchars($service['service_name']); ?>" 
                                             class="w-full h-full object-cover rounded-t-lg">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                        <div class="absolute bottom-0 left-0 right-0 p-4">
                                            <h4 class="text-lg font-medium text-white mb-1"><?php echo htmlspecialchars($service['service_name']); ?></h4>
                                            <span class="text-white/80 text-xs"><?php echo htmlspecialchars($service['time']); ?></span>
                                        </div>
                                    </div>
                                    <div class="p-4 flex-grow flex flex-col">
                                        <p class="text-gray-600 text-sm mb-4 line-clamp-2 flex-grow"><?php echo htmlspecialchars($service['service_description']); ?></p>
                                        <div class="flex justify-between items-center mt-auto">
                                            <span class="text-primary-600 font-medium">₱<?php echo number_format($service['price'], 2); ?></span>
                                            <a href="#contact" class="inline-flex items-center justify-center w-28 px-3 py-2 bg-primary-50 text-primary-600 rounded-md hover:bg-primary-100 text-sm font-medium transition-colors">
                                                Book Now
                                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button class="absolute left-0 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-primary-50 text-gray-700 hover:text-primary-600 p-1.5 rounded-full shadow-md transition-all duration-300" 
                            onclick="slideLeft('<?php echo md5($position); ?>')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button class="absolute right-0 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-primary-50 text-gray-700 hover:text-primary-600 p-1.5 rounded-full shadow-md transition-all duration-300" 
                            onclick="slideRight('<?php echo md5($position); ?>')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Doctors Section -->
    <section id="doctors" class="py-12 bg-white">
        <div class="container mx-auto px-4">
            <div class="section-title text-center mb-8">
                <h2 class="text-2xl font-medium text-neutral-dark mb-2">Our Expert Doctors</h2>
                <p class="text-secondary-color text-sm">Meet our team of experienced dental professionals</p>
            </div>
            
            <div class="relative">
                <div class="overflow-hidden">
                    <div class="flex transition-transform duration-300 ease-in-out" id="doctors-slider">
                        <?php foreach ($doctors as $doctor): ?>
                        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 flex-shrink-0 px-2">
                            <div class="doctor-card group flex flex-col items-center">
                                <div class="relative w-32 h-32 sm:w-36 sm:h-36 md:w-40 md:h-40 rounded-full overflow-hidden mb-3 ring-2 ring-primary-600/10 group-hover:ring-primary-600/30 transition-all duration-300">
                                    <img src="<?php echo htmlspecialchars($doctor['photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($doctor['name']); ?>" 
                                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                </div>
                                <div class="text-center transform translate-y-0 group-hover:translate-y-[-4px] transition-transform duration-300">
                                    <h3 class="text-sm font-medium text-neutral-dark mb-0.5"><?php echo htmlspecialchars($doctor['name']); ?></h3>
                                    <p class="text-secondary-color text-xs"><?php echo htmlspecialchars($doctor['position_name']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <button class="absolute left-0 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-primary-50 text-gray-700 hover:text-primary-600 p-2 rounded-full shadow-md transition-all duration-300" 
                        onclick="slideDoctors('left')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <button class="absolute right-0 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-primary-50 text-gray-700 hover:text-primary-600 p-2 rounded-full shadow-md transition-all duration-300" 
                        onclick="slideDoctors('right')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-24 bg-neutral-light">
        <div class="container mx-auto px-4">
            <div class="section-title text-center">
                <h2 class="text-3xl font-medium text-neutral-dark mb-4">Contact Us</h2>
                <p class="text-secondary-color">Get in touch with us for any questions or to schedule an appointment.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-medium mb-2">Address</h3>
                    <p class="text-secondary-color"><?php echo htmlspecialchars($clinic['address']); ?></p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-phone text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-medium mb-2">Phone</h3>
                    <p class="text-secondary-color"><?php echo htmlspecialchars($clinic['phone']); ?></p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-medium mb-2">Hours</h3>
                    <p class="text-secondary-color">
                        Weekdays: <?php echo htmlspecialchars($clinic['hours_weekdays']); ?><br>
                        Saturday: <?php echo htmlspecialchars($clinic['hours_saturday']); ?><br>
                        Sunday: <?php echo htmlspecialchars($clinic['hours_sunday']); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-xl font-medium mb-4"><?php echo htmlspecialchars($clinic['clinic_name']); ?></h3>
                    <p class="text-gray-400"><?php echo htmlspecialchars($clinic['address']); ?></p>
                </div>
                <div>
                    <h3 class="text-xl font-medium mb-4">Contact Info</h3>
                    <p class="text-gray-400">
                        Phone: <?php echo htmlspecialchars($clinic['phone']); ?><br>
                        Email: <?php echo htmlspecialchars($clinic['email']); ?>
                    </p>
                </div>
                <div>
                    <h3 class="text-xl font-medium mb-4">Business Hours</h3>
                    <p class="text-gray-400">
                        Weekdays: <?php echo htmlspecialchars($clinic['hours_weekdays']); ?><br>
                        Saturday: <?php echo htmlspecialchars($clinic['hours_saturday']); ?><br>
                        Sunday: <?php echo htmlspecialchars($clinic['hours_sunday']); ?>
                    </p>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($clinic['clinic_name']); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        let sliderPositions = {};

        function initializeSlider(sliderId) {
            const slider = document.getElementById(`slider-${sliderId}`);
            if (!sliderPositions[sliderId]) {
                sliderPositions[sliderId] = 0;
            }
            updateSliderPosition(sliderId);
        }

        function slideLeft(sliderId) {
            const slider = document.getElementById(`slider-${sliderId}`);
            const items = slider.children;
            const itemWidth = items[0].offsetWidth;
            const maxPosition = -(items.length - 4) * itemWidth;
            
            sliderPositions[sliderId] = Math.min(0, sliderPositions[sliderId] + itemWidth);
            updateSliderPosition(sliderId);
        }

        function slideRight(sliderId) {
            const slider = document.getElementById(`slider-${sliderId}`);
            const items = slider.children;
            const itemWidth = items[0].offsetWidth;
            const maxPosition = -(items.length - 4) * itemWidth;
            
            sliderPositions[sliderId] = Math.max(maxPosition, sliderPositions[sliderId] - itemWidth);
            updateSliderPosition(sliderId);
        }

        function updateSliderPosition(sliderId) {
            const slider = document.getElementById(`slider-${sliderId}`);
            slider.style.transform = `translateX(${sliderPositions[sliderId]}px)`;
        }

        // Initialize all sliders on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($grouped_services as $position => $services): ?>
            initializeSlider('<?php echo md5($position); ?>');
            <?php endforeach; ?>
        });

        function updateActiveNavLink() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            
            sections.forEach(section => {
                const rect = section.getBoundingClientRect();
                const navLink = document.querySelector(`.nav-link[data-section="${section.id}"]`);
                
                if (rect.top <= 100 && rect.bottom >= 100) {
                    navLinks.forEach(link => link.classList.remove('active'));
                    if (navLink) {
                        navLink.classList.add('active');
                    }
                }
            });
        }

        // Update active link on scroll
        window.addEventListener('scroll', updateActiveNavLink);
        
        // Update active link on page load
        document.addEventListener('DOMContentLoaded', updateActiveNavLink);

        // Update active link when clicking navigation links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });

        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-fadeInUp, .animate-slideInLeft, .animate-slideInRight, .animate-scaleIn');
            
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementBottom = element.getBoundingClientRect().bottom;
                
                if (elementTop < window.innerHeight && elementBottom > 0) {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }
            });
        }

        // Run animation check on scroll
        window.addEventListener('scroll', animateOnScroll);
        
        // Run animation check on page load
        document.addEventListener('DOMContentLoaded', animateOnScroll);

        // Add this to your existing script
        document.addEventListener('DOMContentLoaded', function() {
            // Remove loading overlay after page loads
            setTimeout(() => {
                const overlay = document.querySelector('.loading-overlay');
                overlay.classList.add('fade-out');
                setTimeout(() => {
                    overlay.remove();
                }, 500);
            }, 800);

            // Animate sections on scroll
            const sections = document.querySelectorAll('section');
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in-scale');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            sections.forEach(section => {
                section.style.opacity = '0';
                observer.observe(section);
            });

            // Animate navigation items
            const navItems = document.querySelectorAll('.nav-link');
            navItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease-out';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 100 * index);
            });

            // Animate service cards
            const serviceCards = document.querySelectorAll('.service-card');
            serviceCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 200 * index);
            });

            // Ensure hero section is visible
            const heroSection = document.querySelector('.hero-section');
            if (heroSection) {
                heroSection.style.display = 'flex';
                heroSection.style.visibility = 'visible';
                heroSection.style.opacity = '1';
            }

            // Check if home data is loaded
            console.log('Home data:', <?php echo json_encode($home); ?>);
        });

        // Add this to your existing script
        function handleScrollAnimation() {
            const elements = document.querySelectorAll('.scroll-animation');
            
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementBottom = element.getBoundingClientRect().bottom;
                
                if (elementTop < window.innerHeight - 100 && elementBottom > 0) {
                    element.classList.add('visible');
                }
            });
        }

        // Run on scroll
        window.addEventListener('scroll', handleScrollAnimation);
        
        // Run on page load
        document.addEventListener('DOMContentLoaded', () => {
            handleScrollAnimation();
            
            // Add page transition effect
            document.body.classList.add('page-transition');
        });

        // Handle navigation clicks
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    // Add fade out to current section
                    const currentSection = document.querySelector('section:target');
                    if (currentSection) {
                        currentSection.style.opacity = '0';
                        currentSection.style.transition = 'opacity 0.3s ease-out';
                    }
                    
                    // Scroll to target
                    targetElement.scrollIntoView({
                        behavior: 'smooth'
                    });
                    
                    // Add fade in to target section
                    setTimeout(() => {
                        targetElement.style.opacity = '1';
                        targetElement.style.transition = 'opacity 0.3s ease-in';
                    }, 300);
                }
            });
        });

        // Add this to your existing script section
        let doctorsSliderPosition = 0;
        const doctorsSlider = document.getElementById('doctors-slider');
        const doctorsItems = doctorsSlider.children;
        let itemWidth = doctorsItems[0].offsetWidth;
        let maxPosition = -(doctorsItems.length - 4) * itemWidth;

        function updateSliderDimensions() {
            itemWidth = doctorsItems[0].offsetWidth;
            const visibleItems = window.innerWidth >= 1024 ? 4 : window.innerWidth >= 768 ? 3 : window.innerWidth >= 640 ? 2 : 1;
            maxPosition = -(doctorsItems.length - visibleItems) * itemWidth;
            doctorsSliderPosition = Math.max(maxPosition, Math.min(0, doctorsSliderPosition));
            doctorsSlider.style.transform = `translateX(${doctorsSliderPosition}px)`;
        }

        function slideDoctors(direction) {
            if (direction === 'left') {
                doctorsSliderPosition = Math.min(0, doctorsSliderPosition + itemWidth);
            } else {
                doctorsSliderPosition = Math.max(maxPosition, doctorsSliderPosition - itemWidth);
            }
            doctorsSlider.style.transform = `translateX(${doctorsSliderPosition}px)`;
        }

        // Initialize slider on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Your existing initialization code...
            
            // Initialize doctors slider
            if (doctorsSlider) {
                updateSliderDimensions();
                window.addEventListener('resize', updateSliderDimensions);
            }
        });

        // Initialize DataTable for appointments if needed
        $(document).ready(function() {
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
                
                // Reset doctor selection
                $('#doctorSelect').val('');
                
                // Clear time slots
                $('#timeSelect').html('<option value="">Select Time</option>');
            });
            
            // Update available time slots when date, service, or doctor changes
            $('#appointmentDate, #serviceSelect, #doctorSelect').on('change', function() {
                updateTimeSlots();
            });
        });

        function openAppointmentModal() {
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
            
            // Clear existing options
            $('#timeSelect').html('<option value="">Loading time slots...</option>');
            
            // Fetch available time slots
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
                        
                        if (response.slots && response.slots.length > 0) {
                            response.slots.forEach(function(slot) {
                                options += `<option value="${slot}">${slot}</option>`;
                            });
                        } else {
                            options = '<option value="">No available time slots</option>';
                        }
                        
                        $('#timeSelect').html(options);
                    } else {
                        $('#timeSelect').html('<option value="">No available time slots</option>');
                        if (response.message) {
                            alert(response.message);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response Text:', xhr.responseText);
                    $('#timeSelect').html('<option value="">Error loading time slots</option>');
                    
                    // Show more detailed error message
                    let errorMessage = 'Error loading time slots. ';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage += response.message;
                        }
                    } catch (e) {
                        errorMessage += 'Please try again.';
                    }
                    alert(errorMessage);
                }
            });
        }

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