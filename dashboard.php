<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');
?>

<div id="dashboard" class="space-y-6">
    <h2 class="text-xl font-medium text-gray-800">Dashboard</h2>
    
    <!-- Time Period Selector -->
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center mb-6">
        <div class="inline-flex rounded-md shadow-sm" role="group">
            <button type="button" id="dailyBtn" class="period-btn px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-l-md hover:bg-gray-50 focus:z-10 focus:ring-1 focus:ring-primary-500">
                Daily
            </button>
            <button type="button" id="monthlyBtn" class="period-btn px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border-t border-b border-gray-200 hover:bg-gray-50 focus:z-10 focus:ring-1 focus:ring-primary-500">
                Monthly
            </button>
            <button type="button" id="yearlyBtn" class="period-btn px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-r-md hover:bg-gray-50 focus:z-10 focus:ring-1 focus:ring-primary-500">
                Yearly
            </button>
        </div>
        
        <!-- Date Selectors -->
        <div id="dailyDateSelector" class="date-selector">
            <input type="date" id="dailyDate" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
        </div>
        <div id="monthlyDateSelector" class="date-selector hidden">
            <input type="month" id="monthlyDate" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
        </div>
        <div id="yearlyDateSelector" class="date-selector hidden">
            <select id="yearlyDate" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                <?php
                $currentYear = date('Y');
                for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                    echo "<option value='$year'>$year</option>";
                }
                ?>
            </select>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-primary-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Total Patients</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-2xl font-semibold text-gray-800" id="totalPatients">0</p>
                        <span class="ml-2 text-sm font-medium text-green-600" id="patientGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-blue-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Appointments</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-2xl font-semibold text-gray-800" id="totalAppointments">0</p>
                        <span class="ml-2 text-sm font-medium text-green-600" id="appointmentGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-amber-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Pending</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-2xl font-semibold text-gray-800" id="pendingAppointments">0</p>
                        <span class="ml-2 text-sm font-medium text-red-600" id="pendingGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-green-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Revenue</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-2xl font-semibold text-gray-800" id="totalRevenue">₱0.00</p>
                        <span class="ml-2 text-sm font-medium text-green-600" id="revenueGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm">
            <h3 class="text-sm font-medium text-gray-800 mb-6">Patient Statistics</h3>
            <div class="h-[250px] sm:h-[350px] lg:h-[400px]">
                <canvas id="patientsChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm">
            <h3 class="text-sm font-medium text-gray-800 mb-6">Revenue Overview</h3>
            <div class="h-[250px] sm:h-[350px] lg:h-[400px]">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Appointments -->
    <div class="bg-white p-6 rounded-lg border border-gray-100 shadow-sm mt-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-sm font-medium text-gray-800">Recent Appointments</h3>
            <a href="index.php?page=schedule" class="text-sm text-primary-600 hover:text-primary-800 font-medium">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table id="appointmentsTable" class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Treatment</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- Data will be loaded dynamically -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DataTables Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<!-- DataTables Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<!-- JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set Philippine timezone for date handling
        const philippineTime = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: 'numeric',
            day: 'numeric'
        });
        
        // Get current Philippine date
        const now = new Date();
        const phDate = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
        
        // Format dates without using toISOString to avoid UTC conversion
        const year = phDate.getFullYear();
        const month = String(phDate.getMonth() + 1).padStart(2, '0');
        const day = String(phDate.getDate()).padStart(2, '0');
        
        const today = `${year}-${month}-${day}`; // Format: YYYY-MM-DD
        const currentMonth = `${year}-${month}`; // Format: YYYY-MM
        const currentYear = year.toString();

        // Debug log
        console.log('Current Philippine Date:', phDate);
        console.log('Today:', today);
        console.log('Current Month:', currentMonth);
        console.log('Current Year:', currentYear);

        // Define variables at the top
        let currentPeriod = 'daily';
        let currentDate = today;

        // Initialize date inputs with current Philippine date
        document.getElementById('dailyDate').value = today;
        document.getElementById('monthlyDate').value = currentMonth;
        document.getElementById('yearlyDate').value = currentYear;

        // Initialize DataTable with export buttons
        const appointmentsTable = $('#appointmentsTable').DataTable({
            pageLength: 10,
            order: [[1, 'desc'], [2, 'desc']],
            dom: 'Bfrtip',
            buttons: ['csv', 'excel', 'pdf'],
            responsive: true,
            ajax: {
                url: 'get_appointments.php',
                data: function(d) {
                    d.period = currentPeriod;
                    d.date = currentDate;
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTables AJAX error:', error, thrown);
                    console.log('Response text:', xhr.responseText); // Log full response
                    alert('Failed to load appointments. Check console for details.');
                }
            },
            columns: [
                { 
                    data: 'patient',
                    render: function(data, type, row) {
                        return `
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <img class="h-8 w-8 rounded-full object-cover" src="${row.patient_image || 'https://randomuser.me/api/portraits/lego/1.jpg'}" alt="">
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">${row.patient_name}</div>
                                    <div class="text-xs text-gray-500">${row.patient_email}</div>
                                </div>
                            </div>
                        `;
                    }
                },
                { data: 'appointment_date' },
                { data: 'appointment_time' },
                { data: 'treatment' },
                { 
                    data: 'status',
                    render: function(data, type, row) {
                        const statusClasses = {
                            'Completed': 'bg-green-100 text-green-800',
                            'Pending': 'bg-amber-100 text-amber-800',
                            'Cancelled': 'bg-red-100 text-red-800'
                        };
                        return `<span class="px-2 inline-flex text-xs leading-5 font-medium rounded-full ${statusClasses[data]}">${data}</span>`;
                    }
                }
            ]
        });

        // Update chart options for more aesthetic design
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'center',
                    labels: {
                        color: '#4B5563',
                        font: {
                            size: 12,
                            family: "'Inter', sans-serif",
                            weight: '500'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        boxWidth: 6,
                        boxHeight: 6
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.98)',
                    titleColor: '#1F2937',
                    bodyColor: '#4B5563',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#6B7280',
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        padding: 10,
                        maxRotation: 0
                    },
                    border: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#F3F4F6',
                        drawBorder: false,
                        lineWidth: 1
                    },
                    ticks: {
                        color: '#6B7280',
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        padding: 10,
                        callback: function(value) {
                            return Math.round(value);
                        }
                    },
                    border: {
                        display: false
                    }
                }
            }
        };

        // Update patients chart
        const patientsChart = new Chart(document.getElementById('patientsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Completed Appointments',
                        data: [],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#10B981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Cancelled Appointments',
                        data: [],
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.05)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#EF4444',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: chartOptions
        });

        // Update revenue chart
        const revenueChart = new Chart(document.getElementById('revenueChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Revenue',
                        data: [],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                ...chartOptions,
                scales: {
                    ...chartOptions.scales,
                    y: {
                        ...chartOptions.scales.y,
                        ticks: {
                            ...chartOptions.scales.y.ticks,
                            callback: function(value) {
                                return '₱' + Math.round(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Function to update date selectors visibility
        function updateDateSelectors(period) {
            document.querySelectorAll('.date-selector').forEach(selector => selector.classList.add('hidden'));
            document.getElementById(`${period}DateSelector`).classList.remove('hidden');
        }

        // Function to update dashboard data
        function updateDashboardData(period, date) {
            console.log('Fetching dashboard data with period:', period, 'date:', date);
            
            fetch(`get_dashboard_data.php?period=${period}&date=${date}`)
                .then(response => {
                    // Log response for debugging
                    console.log('Dashboard data response status:', response.status);
                    console.log('Response headers:', Object.fromEntries(response.headers.entries()));
                    
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Error response text:', text);
                            throw new Error(`HTTP ${response.status}: ${text}`);
                        });
                    }
                    return response.text(); // Get raw text first
                })
                .then(text => {
                    // Log raw response
                    console.log('Dashboard data raw response:', text);
                    
                    // Try to parse JSON and log any parsing errors
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Raw text that failed to parse:', text);
                        throw new Error('Failed to parse JSON response: ' + e.message);
                    }
                })
                .then(data => {
                    console.log('Parsed dashboard data:', data);
                    
                    // Update stats
                    document.getElementById('totalPatients').textContent = data.stats.totalPatients;
                    document.getElementById('patientGrowth').textContent = data.stats.patientGrowth;
                    document.getElementById('totalAppointments').textContent = data.stats.totalAppointments;
                    document.getElementById('appointmentGrowth').textContent = data.stats.appointmentGrowth;
                    document.getElementById('pendingAppointments').textContent = data.stats.pendingAppointments;
                    document.getElementById('pendingGrowth').textContent = data.stats.pendingGrowth;
                    document.getElementById('totalRevenue').textContent = `₱${data.stats.totalRevenue}`;
                    document.getElementById('revenueGrowth').textContent = data.stats.revenueGrowth;

                    // Update charts
                    patientsChart.data.labels = data.charts.patients.labels;
                    patientsChart.data.datasets[0].data = data.charts.patients.completed;
                    patientsChart.data.datasets[1].data = data.charts.patients.cancelled;
                    patientsChart.update();

                    revenueChart.data.labels = data.charts.revenue.labels;
                    revenueChart.data.datasets[0].data = data.charts.revenue.amounts;
                    revenueChart.update();

                    // Refresh appointments table
                    appointmentsTable.ajax.reload();
                })
                .catch(error => {
                    console.error('Error fetching dashboard data:', error);
                    console.error('Error stack:', error.stack);
                    console.error('Full error details:', error.message);
                    alert('Failed to load dashboard data. Check browser console (F12) for details.');
                });
        }

        // Set up period buttons
        const periodBtns = document.querySelectorAll('.period-btn');
        periodBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                periodBtns.forEach(b => {
                    b.classList.remove('bg-primary-600', 'text-white');
                    b.classList.add('bg-white', 'text-gray-700');
                });
                this.classList.remove('bg-white', 'text-gray-700');
                this.classList.add('bg-primary-600', 'text-white');
                currentPeriod = this.id.replace('Btn', '');
                updateDateSelectors(currentPeriod);
                const dateInput = document.getElementById(`${currentPeriod}Date`);
                currentDate = dateInput.value;
                updateDashboardData(currentPeriod, currentDate);
            });
        });

        // Set up date change listeners
        document.getElementById('dailyDate').addEventListener('change', function() {
            currentDate = this.value;
            updateDashboardData(currentPeriod, currentDate);
        });

        document.getElementById('monthlyDate').addEventListener('change', function() {
            currentDate = this.value;
            updateDashboardData(currentPeriod, currentDate);
        });

        document.getElementById('yearlyDate').addEventListener('change', function() {
            currentDate = this.value;
            updateDashboardData(currentPeriod, currentDate);
        });

        // Set daily as active by default
        document.getElementById('dailyBtn').click();
    });
</script>

<style>
    /* Custom scrollbar for better aesthetics */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Smooth transitions */
    .transition-all {
        transition: all 0.3s ease;
    }

    /* Card hover effects */
    .hover-lift {
        transition: transform 0.2s ease;
    }
    
    .hover-lift:hover {
        transform: translateY(-2px);
    }
</style>