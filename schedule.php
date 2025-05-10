<div id="schedule">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Appointment Schedule</h2>
    <div class="grid grid-cols-1 lg:grid-cols-7 gap-4">
        <div class="lg:col-span-5 bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">May 2025</h3>
                <div class="flex space-x-2">
                    <button class="p-2 rounded-md hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button class="p-2 rounded-md hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-7 gap-2 text-center mb-2">
                <div class="text-sm font-medium text-gray-500">Sun</div>
                <div class="text-sm font-medium text-gray-500">Mon</div>
                <div class="text-sm font-medium text-gray-500">Tue</div>
                <div class="text-sm font-medium text-gray-500">Wed</div>
                <div class="text-sm font-medium text-gray-500">Thu</div>
                <div class="text-sm font-medium text-gray-500">Fri</div>
                <div class="text-sm font-medium text-gray-500">Sat</div>
            </div>
            <div class="grid grid-cols-7 gap-2">
                <?php
                // Calendar days would normally be generated dynamically
                // For demo purposes, we'll just output a static calendar for May 2025
                $days = [
                    ['day' => '', 'appointments' => 0, 'class' => 'text-gray-400'],
                    ['day' => '', 'appointments' => 0, 'class' => 'text-gray-400'],
                    ['day' => '', 'appointments' => 0, 'class' => 'text-gray-400'],
                    ['day' => '1', 'appointments' => 5, 'class' => ''],
                    ['day' => '2', 'appointments' => 3, 'class' => ''],
                    ['day' => '3', 'appointments' => 0, 'class' => ''],
                    ['day' => '4', 'appointments' => 0, 'class' => ''],
                    ['day' => '5', 'appointments' => 8, 'class' => ''],
                    ['day' => '6', 'appointments' => 4, 'class' => ''],
                    ['day' => '7', 'appointments' => 6, 'class' => ''],
                    ['day' => '8', 'appointments' => 7, 'class' => ''],
                    ['day' => '9', 'appointments' => 5, 'class' => ''],
                    ['day' => '10', 'appointments' => 2, 'class' => ''],
                    ['day' => '11', 'appointments' => 0, 'class' => 'bg-teal-100 font-bold'],
                    ['day' => '12', 'appointments' => 6, 'class' => ''],
                    ['day' => '13', 'appointments' => 4, 'class' => ''],
                    ['day' => '14', 'appointments' => 3, 'class' => ''],
                    ['day' => '15', 'appointments' => 5, 'class' => ''],
                    ['day' => '16', 'appointments' => 7, 'class' => ''],
                    ['day' => '17', 'appointments' => 1, 'class' => ''],
                    ['day' => '18', 'appointments' => 0, 'class' => ''],
                    ['day' => '19', 'appointments' => 4, 'class' => ''],
                    ['day' => '20', 'appointments' => 6, 'class' => ''],
                    ['day' => '21', 'appointments' => 5, 'class' => ''],
                    ['day' => '22', 'appointments' => 3, 'class' => ''],
                    ['day' => '23', 'appointments' => 4, 'class' => ''],
                    ['day' => '24', 'appointments' => 2, 'class' => ''],
                    ['day' => '25', 'appointments' => 0, 'class' => ''],
                    ['day' => '26', 'appointments' => 5, 'class' => ''],
                    ['day' => '27', 'appointments' => 7, 'class' => ''],
                    ['day' => '28', 'appointments' => 4, 'class' => ''],
                    ['day' => '29', 'appointments' => 6, 'class' => ''],
                    ['day' => '30', 'appointments' => 3, 'class' => ''],
                    ['day' => '31', 'appointments' => 2, 'class' => ''],
                ];
                
                foreach ($days as $day) {
                    $appointmentClass = $day['appointments'] > 0 ? 'bg-teal-50' : '';
                    $appointmentDot = $day['appointments'] > 0 ? '<div class="absolute bottom-1 left-0 right-0 flex justify-center"><div class="h-1 w-1 rounded-full bg-teal-500"></div></div>' : '';
                    
                    echo '<div class="relative h-16 p-1 border rounded-md ' . $appointmentClass . ' ' . $day['class'] . '">
                        <div class="text-sm">' . $day['day'] . '</div>';
                    
                    if ($day['appointments'] > 0) {
                        echo '<div class="text-xs text-teal-600 font-medium">' . $day['appointments'] . ' appt</div>';
                    }
                    
                    echo $appointmentDot . '</div>';
                }
                ?>
            </div>
        </div>
        <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Today's Appointments</h3>
            <div class="space-y-4">
                <div class="p-3 bg-teal-50 rounded-md border border-teal-100">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Jane Cooper</p>
                            <p class="text-xs text-gray-500">Teeth Cleaning</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-teal-600">09:30 AM</p>
                            <p class="text-xs text-gray-500">30 min</p>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-teal-50 rounded-md border border-teal-100">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Michael Johnson</p>
                            <p class="text-xs text-gray-500">Root Canal</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-teal-600">11:00 AM</p>
                            <p class="text-xs text-gray-500">60 min</p>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-yellow-50 rounded-md border border-yellow-100">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Robert Brown</p>
                            <p class="text-xs text-gray-500">Consultation</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-yellow-600">01:30 PM</p>
                            <p class="text-xs text-gray-500">45 min</p>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-teal-50 rounded-md border border-teal-100">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Emily Davis</p>
                            <p class="text-xs text-gray-500">Teeth Whitening</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-teal-600">03:00 PM</p>
                            <p class="text-xs text-gray-500">60 min</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <button class="w-full bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md">
                    Add New Appointment
                </button>
            </div>
        </div>
    </div>
</div>
