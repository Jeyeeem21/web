<?php
// Include database connection
require_once 'config/db.php';
?>

<div id="information" class="space-y-6">
    <h2 class="text-xl font-medium text-gray-800">Clinic Information</h2>
    
    <!-- Tabs for Home, About, and Other Information -->
    <div class="border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm">
            <li class="mr-2">
                <a href="index.php?page=information" class="inline-block p-3 border-b-2 border-primary-600 text-primary-600">Overview</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=home_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Data</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=doctor_position_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Services</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=staff_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Staff</a>
            </li>
        </ul>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-md border border-gray-100 p-4">
            <h3 class="text-sm font-medium text-gray-800 mb-3">Clinic Details</h3>
            <div class="space-y-3">
                <div>
                    <h4 class="text-xs font-medium text-gray-500">Clinic Name</h4>
                    <p class="text-sm text-gray-800">Bright Smile Dental Clinic</p>
                </div>
                <div>
                    <h4 class="text-xs font-medium text-gray-500">Address</h4>
                    <p class="text-sm text-gray-800">123 Dental Street, Suite 101<br>Healthville, CA 90210</p>
                </div>
                <div>
                    <h4 class="text-xs font-medium text-gray-500">Contact</h4>
                    <p class="text-sm text-gray-800">Phone: (555) 123-4567<br>Email: info@brightsmile.com</p>
                </div>
                <div>
                    <h4 class="text-xs font-medium text-gray-500">Hours of Operation</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-sm text-gray-800">Monday - Friday</p>
                            <p class="text-xs text-gray-600">8:00 AM - 5:00 PM</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-800">Saturday</p>
                            <p class="text-xs text-gray-600">9:00 AM - 2:00 PM</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-800">Sunday</p>
                            <p class="text-xs text-gray-600">Closed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-md border border-gray-100 p-4">
            <h3 class="text-sm font-medium text-gray-800 mb-3">Staff Directory</h3>
            <div class="space-y-3">
                <div class="flex items-center">
                    <img class="h-8 w-8 rounded-full mr-3" src="https://randomuser.me/api/portraits/men/1.jpg" alt="Dr. Smith">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Dr. John Smith</p>
                        <p class="text-xs text-gray-600">Lead Dentist</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <img class="h-8 w-8 rounded-full mr-3" src="https://randomuser.me/api/portraits/women/5.jpg" alt="Dr. Johnson">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Dr. Lisa Johnson</p>
                        <p class="text-xs text-gray-600">Orthodontist</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <img class="h-8 w-8 rounded-full mr-3" src="https://randomuser.me/api/portraits/men/6.jpg" alt="Dr. Williams">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Dr. Robert Williams</p>
                        <p class="text-xs text-gray-600">Oral Surgeon</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <img class="h-8 w-8 rounded-full mr-3" src="https://randomuser.me/api/portraits/women/7.jpg" alt="Sarah">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Sarah Thompson</p>
                        <p class="text-xs text-gray-600">Dental Hygienist</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <img class="h-8 w-8 rounded-full mr-3" src="https://randomuser.me/api/portraits/women/8.jpg" alt="Jessica">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Jessica Martinez</p>
                        <p class="text-xs text-gray-600">Receptionist</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-md border border-gray-100 p-4 md:col-span-2">
            <h3 class="text-sm font-medium text-gray-800 mb-3">Services Offered</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 border rounded-md">
                    <h4 class="text-sm font-medium text-gray-800 mb-2">General Dentistry</h4>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• Teeth Cleaning</li>
                        <li>• Fillings</li>
                        <li>• Root Canals</li>
                        <li>• Extractions</li>
                    </ul>
                </div>
                <div class="p-3 border rounded-md">
                    <h4 class="text-sm font-medium text-gray-800 mb-2">Cosmetic Dentistry</h4>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• Teeth Whitening</li>
                        <li>• Veneers</li>
                        <li>• Bonding</li>
                        <li>• Smile Makeovers</li>
                    </ul>
                </div>
                <div class="p-3 border rounded-md">
                    <h4 class="text-sm font-medium text-gray-800 mb-2">Specialized Services</h4>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• Orthodontics</li>
                        <li>• Dental Implants</li>
                        <li>• Oral Surgery</li>
                        <li>• Pediatric Dentistry</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
