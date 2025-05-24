<?php
// Include database connection
require_once 'config/db.php';

// Fetch clinic details
try {
    $stmt = $pdo->query("SELECT * FROM clinic_details LIMIT 1");
    $clinic = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clinic = []; // Fallback to empty array
    error_log("Clinic details query failed: " . $e->getMessage());
}

// Fetch active staff members
try {
    $stmt = $pdo->query("SELECT s.*, dp.name as position_name 
                         FROM staff s 
                         LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                         WHERE s.status = 1 
                         ORDER BY s.role, s.name 
                         LIMIT 10");
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff = []; // Fallback to empty array
    error_log("Staff query failed: " . $e->getMessage());
    // Fallback query without doctor_position join
    try {
        $stmt = $pdo->query("SELECT * FROM staff WHERE status = 1 ORDER BY role, name LIMIT 10");
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fallback staff query failed: " . $e->getMessage());
    }
}

// Fetch active services and group by kind_of_doctor
try {
    $stmt = $pdo->query("SELECT kind_of_doctor, service_name 
                         FROM services 
                         WHERE status = 1 
                         ORDER BY kind_of_doctor, service_name");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Group services by kind_of_doctor
    $grouped_services = [];
    foreach ($services as $service) {
        $grouped_services[$service['kind_of_doctor']][] = $service['service_name'];
    }
} catch (PDOException $e) {
    $grouped_services = []; // Fallback to empty array
    error_log("Services query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bright Smile Dental Clinic - Information</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-neutral-light font-body">
    <div id="information" class="space-y-8 p-6 md:p-8 animate-fade-in bg-white">
        <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Clinic Information</h2>
        
        <!-- Tabs for Home, About, and Other Information -->
        <div class="border-b border-primary-100">
            <ul class="flex flex-wrap -mb-px text-base font-semibold overflow-x-auto">
                <li class="mr-2">
                    <a href="index.php?page=information" class="inline-block px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-t-lg font-bold shadow-sm hover:brightness-110 hover:scale-105 transition-all duration-200">Overview</a>
                </li>
                <li class="mr-2">
                    <a href="index.php?page=home_management" class="inline-block px-4 py-2 text-secondary rounded-t-lg hover:text-primary-500 hover:bg-primary-50 hover:shadow-sm hover:scale-105 transition-all duration-200">Data</a>
                </li>
                <li class="mr-2">
                    <a href="index.php?page=doctor_position_management" class="inline-block px-4 py-2 text-secondary rounded-t-lg hover:text-primary-500 hover:bg-primary-50 hover:shadow-sm hover:scale-105 transition-all duration-200">Services</a>
                </li>
                <li class="mr-2">
                    <a href="index.php?page=staff_management" class="inline-block px-4 py-2 text-secondary rounded-t-lg hover:text-primary-500 hover:bg-primary-50 hover:shadow-sm hover:scale-105 transition-all duration-200">Staff</a>
                </li>
            </ul>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Clinic Details -->
            <div class="bg-white rounded-xl border border-primary-100 p-6 shadow-sm hover:shadow-md transition-all duration-200 animate-slide-up">
                <h3 class="text-lg font-medium text-neutral-dark mb-4">Clinic Details</h3>
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-medium text-secondary">Clinic Name</h4>
                        <p class="text-base text-neutral-dark"><?php echo htmlspecialchars($clinic['clinic_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-secondary">Address</h4>
                        <p class="text-base text-neutral-dark"><?php echo nl2br(htmlspecialchars($clinic['address'] ?? 'N/A')); ?></p>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-secondary">Contact</h4>
                        <p class="text-base text-neutral-dark">
                            Phone: <?php echo htmlspecialchars($clinic['phone'] ?? 'N/A'); ?><br>
                            Email: <?php echo htmlspecialchars($clinic['email'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-secondary">Hours of Operation</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <p class="text-base text-neutral-dark">Monday - Friday</p>
                                <p class="text-sm text-secondary"><?php echo htmlspecialchars($clinic['hours_weekdays'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-base text-neutral-dark">Saturday</p>
                                <p class="text-sm text-secondary"><?php echo htmlspecialchars($clinic['hours_saturday'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-base text-neutral-dark">Sunday</p>
                                <p class="text-sm text-secondary"><?php echo htmlspecialchars($clinic['hours_sunday'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Staff Directory -->
            <div class="bg-white rounded-xl border border-primary-100 p-6 shadow-sm hover:shadow-md transition-all duration-200 animate-slide-up">
                <h3 class="text-lg font-medium text-neutral-dark mb-4">Staff Directory</h3>
                <div class="space-y-4">
                    <?php if (empty($staff)): ?>
                        <p class="text-base text-secondary">No active staff members found.</p>
                    <?php else: ?>
                        <?php foreach ($staff as $member): ?>
                            <div class="flex items-center hover:bg-primary-50 p-2 rounded-lg transition-all duration-200">
                                <?php if (!empty($member['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($member['photo']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" class="w-12 h-12 rounded-full mr-3 border-2 border-primary-100">
                                <?php endif; ?>
                                <div>
                                    <p class="text-base font-medium text-neutral-dark"><?php echo htmlspecialchars($member['name']); ?></p>
                                    <p class="text-sm text-secondary">
                                        <?php 
                                        if ($member['role'] == 'doctor') {
                                            echo htmlspecialchars($member['position_name'] ?? ucfirst($member['role']));
                                        } else {
                                            echo ucfirst(htmlspecialchars($member['role']));
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Services Offered -->
            <div class="bg-white rounded-xl border border-primary-100 p-6 shadow-sm hover:shadow-md transition-all duration-200 lg:col-span-2 animate-slide-up">
                <h3 class="text-lg font-medium text-neutral-dark mb-4">Services Offered</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if (empty($grouped_services)): ?>
                        <p class="text-base text-secondary">No active services available.</p>
                    <?php else: ?>
                        <?php foreach ($grouped_services as $doctor_type => $services): ?>
                            <div class="p-4 bg-primary-50 border border-primary-100 rounded-lg shadow-sm hover:bg-primary-100 transition-all duration-200">
                                <h4 class="text-base font-medium text-primary-500 mb-2"><?php echo htmlspecialchars($doctor_type); ?></h4>
                                <ul class="text-sm text-secondary space-y-1">
                                    <?php foreach ($services as $service): ?>
                                        <li>• <?php echo htmlspecialchars($service); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

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
            transition: all 0.3s ease, transform 0.2s ease;
        }

        /* Mobile adjustments */
        @media (max-width: 640px) {
            #information {
                padding: 4px;
            }
            .grid-cols-1 {
                gap: 4px;
            }
            .text-base {
                font-size: 0.875rem;
            }
            .text-sm {
                font-size: 0.75rem;
            }
            .p-6 {
                padding: 1rem;
            }
            .flex-wrap {
                flex-wrap: nowrap;
                padding-bottom: 0.5rem;
            }
            .inline-block.p-3 {
                padding: 0.5rem;
            }
        }
    </style>
</body>
</html>