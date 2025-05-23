<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get current date and time in Asia/Manila (UTC+8)
$currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
$today = $currentDateTime->format('Y-m-d'); // e.g., 2025-05-24
$currentMonth = $currentDateTime->format('Y-m'); // e.g., 2025-05
$currentYear = $currentDateTime->format('Y'); // e.g., 2025
?>

<div id="dashboard" class="space-y-8 bg-neutral-light p-6 md:p-8 animate-fade-in">
    <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Dashboard</h2>
    
    <!-- Time Period Selector -->
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center mb-8">
        <div class="inline-flex rounded-lg shadow-sm" role="group">
            <button type="button" id="dailyBtn" class="period-btn px-4 py-2 text-sm font-medium text-neutral-dark bg-white border border-primary-100 rounded-l-lg hover:bg-gradient-to-r hover:from-primary-500 hover:to-accent-300 hover:text-white hover:scale-105 focus:z-10 focus:ring-2 focus:ring-primary-500 transition-all duration-200">
                Daily
            </button>
            <button type="button" id="monthlyBtn" class="period-btn px-4 py-2 text-sm font-medium text-neutral-dark bg-white border-t border-b border-primary-100 hover:bg-gradient-to-r hover:from-primary-500 hover:to-accent-300 hover:text-white hover:scale-105 focus:z-10 focus:ring-2 focus:ring-primary-500 transition-all duration-200">
                Monthly
            </button>
            <button type="button" id="yearlyBtn" class="period-btn px-4 py-2 text-sm font-medium text-neutral-dark bg-white border border-primary-100 rounded-r-lg hover:bg-gradient-to-r hover:from-primary-500 hover:to-accent-300 hover:text-white hover:scale-105 focus:z-10 focus:ring-2 focus:ring-primary-500 transition-all duration-200">
                Yearly
            </button>
        </div>
        
        <!-- Date Selectors -->
        <div id="dailyDateSelector" class="date-selector">
            <input type="date" id="dailyDate" value="<?php echo $today; ?>" class="block w-full rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm text-neutral-dark focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-all">
        </div>
        <div id="monthlyDateSelector" class="date-selector hidden">
            <input type="month" id="monthlyDate" value="<?php echo $currentMonth; ?>" class="block w-full rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm text-neutral-dark focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-all">
        </div>
        <div id="yearlyDateSelector" class="date-selector hidden">
            <select id="yearlyDate" class="block w-full rounded-lg border border-primary-100 bg-white px-3 py-2 text-sm text-neutral-dark focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-all">
                <?php
                $startYear = $currentYear - 10;
                $endYear = $currentYear + 10;
                for ($year = $endYear; $year >= $startYear; $year--) {
                    $selected = ($year == $currentYear) ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 animate-slide-up">
        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-6 rounded-xl shadow-sm hover:shadow-lg transition-all duration-200 hover-lift">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-primary-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-neutral-dark">Total Patients</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-3xl font-semibold text-neutral-dark" id="totalPatients">0</p>
                        <span class="ml-2 text-sm font-medium" id="patientGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-6 rounded-xl shadow-sm hover:shadow- lg transition-all duration-200 hover-lift">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-primary-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-neutral-dark">Appointments</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-3xl font-semibold text-neutral-dark" id="totalAppointments">0</p>
                        <span class="ml-2 text-sm font-medium" id="appointmentGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-6 rounded-xl shadow-sm hover:shadow-lg transition-all duration-200 hover-lift">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-primary-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-neutral-dark">Pending</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-3xl font-semibold text-neutral-dark" id="pendingAppointments">0</p>
                        <span class="ml-2 text-sm font-medium" id="pendingGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-primary-50 to-accent-100 p-6 rounded-xl shadow-sm hover:shadow-lg transition-all duration-200 hover-lift">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-primary-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-neutral-dark">Revenue</h3>
                    <div class="flex items-baseline mt-1">
                        <p class="text-3xl font-semibold text-neutral-dark" id="totalRevenue">₱0.00</p>
                        <span class="ml-2 text-sm font-medium" id="revenueGrowth">+0%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8 animate-slide-up">
        <div class="bg-white p-6 rounded-xl border border-primary-100 shadow-sm hover:shadow-md transition-all duration-200">
            <h3 class="text-base font-medium text-neutral-dark mb-6">Patient Statistics</h3>
            <div class="h-[250px] sm:h-[350px] lg:h-[400px]">
                <canvas id="patientsChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl border border-primary-100 shadow-sm hover:shadow-md transition-all duration-200">
            <h3 class="text-base font-medium text-neutral-dark mb-6">Revenue Overview</h3>
            <div class="h-[250px] sm:h-[350px] lg:h-[400px]">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Appointments -->
    <div class="bg-white p-6 rounded-xl border border-primary-100 shadow-sm mt-8 animate-slide-up">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-base font-medium text-neutral-dark">Recent Appointments</h3>
            <div class="flex gap-3">
                <button id="printTableBtn" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 hover:scale-105 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
                <a href="index.php?page=schedule" class="py-2 text-sm text-primary-500 hover:text-primary-700 font-medium transition-all duration-200">View All</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table id="appointmentsTable" class="min-w-full divide-y divide-primary-100 mobile-card-view">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Treatment</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-primary-100">
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
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

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

        // Initialize date inputs with current Philippine date if not set by PHP
        if (!document.getElementById('dailyDate').value) document.getElementById('dailyDate').value = today;
        if (!document.getElementById('monthlyDate').value) document.getElementById('monthlyDate').value = currentMonth;
        if (!document.getElementById('yearlyDate').value) document.getElementById('yearlyDate').value = currentYear;

        // Initialize DataTable with export buttons and mobile card view
        const appointmentsTable = $('#appointmentsTable').DataTable({
            pageLength: 10,
            order: [[1, 'desc'], [2, 'desc']],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'csv',
                    text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H3a2 2 0 01-2-2V3a2 2 0 012-2h18a2 2 0 012 2v16a2 2 0 01-2 2z" /></svg> CSV',
                    className: 'bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 hover:scale-105 transition-all duration-200'
                },
                {
                    extend: 'excel',
                    text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg> Excel',
                    className: 'bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 hover:scale-105 transition-all duration-200'
                },
                {
                    extend: 'pdf',
                    text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H3a2 2 0 01-2-2V3a2 2 0 012-2h18a2 2 0 012 2v16a2 2 0 01-2 2z" /></svg> PDF',
                    className: 'bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 hover:scale-105 transition-all duration-200'
                }
            ],
            responsive: true,
            autoWidth: false,
            scrollX: false,
            columns: [
                { 
                    data: 'patient',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            return `
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <img class="h-8 w-8 rounded-full object-cover" src="${row.patient_image || 'https://randomuser.me/api/portraits/lego/1.jpg'}" alt="">
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-neutral-dark">${row.patient_name}</div>
                                        <div class="text-xs text-secondary">${row.patient_email}</div>
                                    </div>
                                </div>
                            `;
                        }
                        return data;
                    }
                },
                { 
                    data: 'appointment_date',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            return `<div>${data}</div>`;
                        }
                        return data;
                    }
                },
                { 
                    data: 'appointment_time',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            return `<div>${data}</div>`;
                        }
                        return data;
                    }
                },
                { 
                    data: 'treatment',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            return `<div>${data}</div>`;
                        }
                        return data;
                    }
                },
                { 
                    data: 'status',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            const statusClasses = {
                                'Completed': 'bg-success-light text-success',
                                'Pending': 'bg-accent-100 text-accent-500',
                                'Cancelled': 'bg-red-100 text-red-800'
                            };
                            return `<span class="px-2 py-1 inline-flex text-xs leading-5 font-medium rounded-full ${statusClasses[data]}">${data}</span>`;
                        }
                        return data;
                    }
                }
            ],
            createdRow: function(row, data, dataIndex) {
                // Add data-label attributes to each cell
                $(row).find('td').each(function(index) {
                    const labels = ['Patient', 'Date', 'Time', 'Treatment', 'Status'];
                    $(this).attr('data-label', labels[index]);
                });
            },
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
                { data: 'patient' },
                { data: 'appointment_date' },
                { data: 'appointment_time' },
                { data: 'treatment' },
                { 
                    data: 'status',
                    render: function(data, type, row) {
                        const statusClasses = {
                            'Completed': 'bg-success-light text-success',
                            'Pending': 'bg-accent-100 text-accent-500',
                            'Cancelled': 'bg-red-100 text-red-800'
                        };
                        return `<span class="px-2 py-1 inline-flex text-xs leading-5 font-medium rounded-full ${statusClasses[data]}">${data}</span>`;
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
                        color: '#1e293b',
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
                    titleColor: '#1e293b',
                    bodyColor: '#475569',
                    borderColor: '#ccfbf1',
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
                        color: '#475569',
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
                        color: '#f3f4f6',
                        drawBorder: false,
                        lineWidth: 1
                    },
                    ticks: {
                        color: '#475569',
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
                        borderColor: '#14b8a6',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#14b8a6',
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
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.1)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#d97706',
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
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        ...chartOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.y;
                                let label = context.dataset.label + ': ' + Math.round(value);
                                let percent = null;
                                if (context.dataset.label === 'Completed Appointments') {
                                    percent = getPercentChanges(context.dataset.data)[context.dataIndex];
                                } else if (context.dataset.label === 'Cancelled Appointments') {
                                    percent = getPercentChanges(context.dataset.data)[context.dataIndex];
                                }
                                if (percent !== null && context.dataIndex !== 0) {
                                    label += ` (${percent > 0 ? '+' : ''}${percent}%)`;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    ...chartOptions.scales,
                    y: {
                        ...chartOptions.scales.y,
                        ticks: {
                            ...chartOptions.scales.y.ticks,
                            callback: function(value) {
                                return Math.round(value); // No peso sign
                            }
                        }
                    }
                }
            }
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
                        borderColor: '#14b8a6',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#14b8a6',
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
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        ...chartOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.y;
                                let label = context.dataset.label + ': ₱' + Math.round(value).toLocaleString();
                                const percent = getPercentChanges(context.dataset.data)[context.dataIndex];
                                if (percent !== null && context.dataIndex !== 0) {
                                    label += ` (${percent > 0 ? '+' : ''}${percent}%)`;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    ...chartOptions.scales,
                    y: {
                        ...chartOptions.scales.y,
                        ticks: {
                            ...chartOptions.scales.y.ticks,
                            callback: function(value) {
                                return '₱' + Math.round(value).toLocaleString(); // Keep peso sign
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

        // Function to calculate percent changes
        function getPercentChanges(dataArray) {
            return dataArray.map((val, idx, arr) => {
                if (idx === 0) return null;
                const prev = arr[idx - 1];
                if (prev === 0) return null;
                return ((val - prev) / Math.abs(prev) * 100).toFixed(1); // 1 decimal place
            });
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
                    document.getElementById('patientGrowth').className = `ml-2 text-sm font-medium ${data.stats.patientGrowth.startsWith('+') ? 'text-success' : 'text-accent-500'}`;
                    document.getElementById('totalAppointments').textContent = data.stats.totalAppointments;
                    document.getElementById('appointmentGrowth').textContent = data.stats.appointmentGrowth;
                    document.getElementById('appointmentGrowth').className = `ml-2 text-sm font-medium ${data.stats.appointmentGrowth.startsWith('+') ? 'text-success' : 'text-accent-500'}`;
                    document.getElementById('pendingAppointments').textContent = data.stats.pendingAppointments;
                    document.getElementById('pendingGrowth').textContent = data.stats.pendingGrowth;
                    document.getElementById('pendingGrowth').className = `ml-2 text-sm font-medium ${data.stats.pendingGrowth.startsWith('+') ? 'text-success' : 'text-accent-500'}`;
                    document.getElementById('totalRevenue').textContent = `₱${data.stats.totalRevenue}`;
                    document.getElementById('revenueGrowth').textContent = data.stats.revenueGrowth;
                    document.getElementById('revenueGrowth').className = `ml-2 text-sm font-medium ${data.stats.revenueGrowth.startsWith('+') ? 'text-success' : 'text-accent-500'}`;

                    // --- Percent change arrays ---
                    const completed = data.charts.patients.completed;
                    const cancelled = data.charts.patients.cancelled;
                    const revenue = data.charts.revenue.amounts;
                    const completedPercent = getPercentChanges(completed);
                    const cancelledPercent = getPercentChanges(cancelled);
                    const revenuePercent = getPercentChanges(revenue);

                    // Update patients chart
                    patientsChart.data.labels = data.charts.patients.labels;
                    patientsChart.data.datasets[0].data = completed;
                    patientsChart.data.datasets[1].data = cancelled;
                    patientsChart.update();

                    // Update revenue chart
                    revenueChart.data.labels = data.charts.revenue.labels;
                    revenueChart.data.datasets[0].data = revenue;
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

        // Function to print only the DataTable content
        function printDataTable() {
            const printWindow = window.open('', '_blank');
            const tableContent = document.getElementById('appointmentsTable').outerHTML;
            const printStyles = `
                <style>
                    body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f8fafc; }
                    h2 { font-family: 'Poppins', sans-serif; color: #1e293b; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { 
                        border: 1px solid #ccfbf1; 
                        padding: 12px; 
                        text-align: left; 
                        font-size: 12px;
                        color: #1e293b;
                    }
                    th { 
                        background-color: #f8fafc; 
                        color: #14b8a6; 
                        text-transform: uppercase; 
                        font-weight: 500;
                    }
                    tr:nth-child(even) { background-color: #f8fafc; }
                    tr:hover { background-color: #ccfbf1; }
                    img { width: 32px; height: 32px; border-radius: 50%; margin-right: 8px; }
                    .flex { display: flex; align-items: center; }
                    .text-sm { font-size: 14px; }
                    .text-xs { font-size: 12px; }
                    .text-neutral-dark { color: #1e293b; }
                    .text-secondary { color: #475569; }
                    .bg-success-light { background-color: #d1fae5; }
                    .text-success { color: #10b981; }
                    .bg-accent-100 { background-color: #fef3c7; }
                    .text-accent-500 { color: #d97706; }
                    .bg-red-100 { background-color: #fee2e2; }
                    .text-red-800 { color: #991b1b; }
                    .px-2 { padding-left: 8px; padding-right: 8px; }
                    .py-1 { padding-top: 4px; padding-bottom: 4px; }
                    .inline-flex { display: inline-flex; }
                    .leading-5 { line-height: 1.25; }
                    .font-medium { font-weight: 500; }
                    .rounded-full { border-radius: 9999px; }
                </style>
            `;
            
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Appointments Table</title>
                        ${printStyles}
                    </head>
                    <body>
                        <h2 class="text-neutral-dark">Recent Appointments</h2>
                        ${tableContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Set up period buttons
        const periodBtns = document.querySelectorAll('.period-btn');
        periodBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                periodBtns.forEach(b => {
                    b.classList.remove('bg-gradient-to-r', 'from-primary-500', 'to-accent-300', 'text-white');
                    b.classList.add('bg-white', 'text-neutral-dark');
                });
                this.classList.remove('bg-white', 'text-neutral-dark');
                this.classList.add('bg-gradient-to-r', 'from-primary-500', 'to-accent-300', 'text-white');
                currentPeriod = this.id.replace('Btn', '');
                updateDateSelectors(currentPeriod);

                // Reset the date input to current date/month/year
                if (currentPeriod === 'daily') {
                    document.getElementById('dailyDate').value = today;
                    currentDate = today;
                } else if (currentPeriod === 'monthly') {
                    document.getElementById('monthlyDate').value = currentMonth;
                    currentDate = currentMonth;
                } else if (currentPeriod === 'yearly') {
                    document.getElementById('yearlyDate').value = currentYear;
                    currentDate = currentYear;
                }

                updateDashboardData(currentPeriod, currentDate);
            });
        });

        // Set up date change listeners
        document.getElementById('dailyDate').addEventListener('change', function() {
            currentDate = this.value;
            updateUrlWithDate();
        });

        document.getElementById('monthlyDate').addEventListener('change', function() {
            currentDate = this.value;
            updateUrlWithDate();
        });

        document.getElementById('yearlyDate').addEventListener('change', function() {
            currentDate = this.value;
            updateUrlWithDate();
        });

        // Set daily as active by default
        document.getElementById('dailyBtn').click();

        // Add custom print button functionality
        document.getElementById('printTableBtn').addEventListener('click', printDataTable);

        // Style DataTables buttons container
        $('.dt-buttons').addClass('flex gap-3 mb-4');
    });

    // Function to update URL with date parameters
    function updateUrlWithDate() {
        const dateInput = document.getElementById(`${currentPeriod}Date`);
        currentDate = dateInput.value;
        updateDashboardData(currentPeriod, currentDate);
        const url = new URL(window.location);
        url.searchParams.set('period', currentPeriod);
        url.searchParams.set('date', currentDate);
        window.history.pushState({}, '', url);
    }
</script>

<style>
    /* Custom scrollbar for better aesthetics */
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

    /* Card hover effects */
    .hover-lift {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .hover-lift:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* DataTable styles */
    .dataTables_wrapper {
        width: 100%;
    }

    #appointmentsTable {
        width: 100% !important;
    }

    #appointmentsTable th,
    #appointmentsTable td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 12px;
    }

    #appointmentsTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #appointmentsTable tbody tr:hover {
        background-color: #ccfbf1;
    }

    .dataTables_scrollBody {
        overflow-x: hidden !important;
    }

    /* Mobile card view */
    @media (max-width: 640px) {
        #dashboard {
            padding: 4px;
        }
        .period-btn {
            padding: 8px 12px;
            font-size: 0.75rem;
        }
        .date-selector input,
        .date-selector select {
            font-size: 0.875rem;
            padding: 8px;
        }
        .hover-lift:hover {
            transform: none;
        }
        
        /* Mobile card view for appointments table */
        #appointmentsTable.mobile-card-view thead {
            display: none;
        }
        
        #appointmentsTable.mobile-card-view tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #ccfbf1;
            border-radius: 0.5rem;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        #appointmentsTable.mobile-card-view tbody td {
            display: flex;
            padding: 0.75rem;
            border: none;
            align-items: center;
        }
        
        #appointmentsTable.mobile-card-view tbody td:before {
            content: attr(data-label);
            font-weight: 500;
            width: 40%;
            margin-right: 1rem;
            color: #475569;
        }
        
        #appointmentsTable.mobile-card-view tbody td .cell-content {
            flex: 1;
        }
        
        /* Adjust patient info layout */
        #appointmentsTable.mobile-card-view tbody td:first-child {
            padding-top: 1rem;
        }
        
        #appointmentsTable.mobile-card-view tbody td:last-child {
            padding-bottom: 1rem;
        }
        
        /* Status badge styling */
        #appointmentsTable.mobile-card-view tbody td:last-child span {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
    }

    /* Print-specific styles */
    @media print {
        /* Hide all elements except the table */
        body > *:not(#appointmentsTable):not(h2) {
            display: none !important;
        }
        
        /* Ensure table takes full width */
        #appointmentsTable {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Ensure table headers are visible */
        #appointmentsTable thead {
            display: table-header-group !important;
        }
        
        /* Ensure table rows are visible */
        #appointmentsTable tbody {
            display: table-row-group !important;
        }
        
        /* Ensure table cells are visible */
        #appointmentsTable td,
        #appointmentsTable th {
            display: table-cell !important;
            padding: 12px !important;
            border: 1px solid #ccfbf1 !important;
        }
        
        /* Remove background colors for better printing */
        #appointmentsTable tr {
            background-color: #f8fafc !important;
        }
        
        /* Ensure text is dark for better printing */
        #appointmentsTable * {
            color: #1e293b !important;
        }

        /* Style images in print */
        #appointmentsTable img {
            width: 32px !important;
            height: 32px !important;
            border-radius: 50% !important;
            margin-right: 8px !important;
        }
    }
</style>