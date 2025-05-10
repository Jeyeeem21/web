<div id="records">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Patient Records</h2>
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="mb-4">
            <div class="relative">
                <input type="text" placeholder="Search patients..." class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                <div class="absolute left-3 top-2.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Visit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    // This would normally be populated from a database
                    $patients = [
                        ['id' => 'PT-1001', 'name' => 'Jane Cooper', 'email' => 'jane.cooper@example.com', 'phone' => '(555) 123-4567', 'last_visit' => 'Apr 23, 2025'],
                        ['id' => 'PT-1002', 'name' => 'Michael Johnson', 'email' => 'michael.j@example.com', 'phone' => '(555) 234-5678', 'last_visit' => 'Apr 18, 2025'],
                        ['id' => 'PT-1003', 'name' => 'Sarah Williams', 'email' => 'sarah.w@example.com', 'phone' => '(555) 345-6789', 'last_visit' => 'May 2, 2025'],
                        ['id' => 'PT-1004', 'name' => 'Robert Brown', 'email' => 'robert.b@example.com', 'phone' => '(555) 456-7890', 'last_visit' => 'Apr 30, 2025'],
                        ['id' => 'PT-1005', 'name' => 'Emily Davis', 'email' => 'emily.d@example.com', 'phone' => '(555) 567-8901', 'last_visit' => 'May 5, 2025'],
                    ];
                    
                    // For demo purposes, we'll just output HTML directly
                    foreach ($patients as $patient) {
                        echo '<tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . $patient['id'] . '</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">' . $patient['name'] . '</div>
                                <div class="text-sm text-gray-500">' . $patient['email'] . '</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . $patient['phone'] . '</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . $patient['last_visit'] . '</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="#" class="text-teal-600 hover:text-teal-900 mr-3">View</a>
                                <a href="#" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                <a href="#" class="text-red-600 hover:text-red-900">Delete</a>
                            </td>
                        </tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex justify-end">
            <button class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md">
                Add New Patient
            </button>
        </div>
    </div>
</div>
