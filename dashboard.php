<div id="dashboard" class="space-y-6">
    <h2 class="text-xl font-medium text-gray-800">Dashboard</h2>
    
    <!-- Time Period Selector -->
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
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-md border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 rounded-md bg-primary-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-xs text-gray-500">Total Patients</h3>
                    <div class="flex items-baseline">
                        <p class="text-lg font-medium text-gray-800" id="totalPatients">1,248</p>
                        <span class="ml-2 text-xs text-green-600" id="patientGrowth">+12%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-4 rounded-md border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 rounded-md bg-blue-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-xs text-gray-500">Appointments</h3>
                    <div class="flex items-baseline">
                        <p class="text-lg font-medium text-gray-800" id="totalAppointments">24</p>
                        <span class="ml-2 text-xs text-green-600" id="appointmentGrowth">+8%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-4 rounded-md border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 rounded-md bg-amber-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-xs text-gray-500">Pending</h3>
                    <div class="flex items-baseline">
                        <p class="text-lg font-medium text-gray-800" id="pendingAppointments">12</p>
                        <span class="ml-2 text-xs text-red-600" id="pendingGrowth">+5%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-4 rounded-md border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 rounded-md bg-green-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-xs text-gray-500">Revenue</h3>
                    <div class="flex items-baseline">
                        <p class="text-lg font-medium text-gray-800" id="totalRevenue">$24,500</p>
                        <span class="ml-2 text-xs text-green-600" id="revenueGrowth">+15%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-4 rounded-md border border-gray-100">
            <h3 class="text-sm font-medium text-gray-800 mb-4">Patient Statistics</h3>
            <div class="h-64">
                <canvas id="patientsChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white p-4 rounded-md border border-gray-100">
            <h3 class="text-sm font-medium text-gray-800 mb-4">Revenue Overview</h3>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Appointments -->
    <div class="bg-white p-4 rounded-md border border-gray-100">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-sm font-medium text-gray-800">Recent Appointments</h3>
            <a href="index.php?page=schedule" class="text-xs text-primary-600 hover:text-primary-800">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Treatment</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <img class="h-8 w-8 rounded-full object-cover" src="https://randomuser.me/api/portraits/women/2.jpg" alt="">
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">Jane Cooper</div>
                                    <div class="text-xs text-gray-500">jane.cooper@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">May 11, 2025</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">09:30 AM</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Teeth Cleaning</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-medium rounded-full bg-green-100 text-green-800">Confirmed</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <img class="h-8 w-8 rounded-full object-cover" src="https://randomuser.me/api/portraits/men/3.jpg" alt="">
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">Michael Johnson</div>
                                    <div class="text-xs text-gray-500">michael.j@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">May 11, 2025</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">11:00 AM</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Root Canal</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-medium rounded-full bg-amber-100 text-amber-800">Pending</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <img class="h-8 w-8 rounded-full object-cover" src="https://randomuser.me/api/portraits/women/4.jpg" alt="">
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">Sarah Williams</div>
                                    <div class="text-xs text-gray-500">sarah.w@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">May 12, 2025</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">10:15 AM</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Dental Implant</div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-medium rounded-full bg-green-100 text-green-800">Confirmed</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Initialize Dashboard Charts -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart data for different time periods
        const chartData = {
            daily: {
                patients: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    newPatients: [8, 12, 6, 9, 14, 10, 7],
                    returningPatients: [15, 18, 12, 14, 20, 16, 10]
                },
                revenue: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    data: [1200, 1800, 1500, 2200, 2500, 1900, 1400]
                },
                stats: {
                    totalPatients: 85,
                    patientGrowth: '+12%',
                    totalAppointments: 24,
                    appointmentGrowth: '+8%',
                    pendingAppointments: 12,
                    pendingGrowth: '+5%',
                    totalRevenue: '$12,500',
                    revenueGrowth: '+15%'
                }
            },
            monthly: {
                patients: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    newPatients: [65, 59, 80, 81, 56, 55],
                    returningPatients: [28, 48, 40, 19, 86, 27]
                },
                revenue: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    data: [12500, 19200, 15000, 18000, 22000, 24500]
                },
                stats: {
                    totalPatients: 396,
                    patientGrowth: '+8%',
                    totalAppointments: 145,
                    appointmentGrowth: '+12%',
                    pendingAppointments: 32,
                    pendingGrowth: '+3%',
                    totalRevenue: '$111,200',
                    revenueGrowth: '+10%'
                }
            },
            yearly: {
                patients: {
                    labels: ['2020', '2021', '2022', '2023', '2024', '2025'],
                    newPatients: [320, 450, 520, 590, 670, 720],
                    returningPatients: [280, 350, 390, 470, 520, 580]
                },
                revenue: {
                    labels: ['2020', '2021', '2022', '2023', '2024', '2025'],
                    data: [150000, 180000, 210000, 250000, 290000, 320000]
                },
                stats: {
                    totalPatients: 1248,
                    patientGrowth: '+7%',
                    totalAppointments: 580,
                    appointmentGrowth: '+9%',
                    pendingAppointments: 45,
                    pendingGrowth: '+2%',
                    totalRevenue: '$320,000',
                    revenueGrowth: '+11%'
                }
            }
        };
        
        // Initialize charts
        let patientsChart, revenueChart;
        
        function initCharts(period) {
            // Update stats
            document.getElementById('totalPatients').textContent = chartData[period].stats.totalPatients;
            document.getElementById('patientGrowth').textContent = chartData[period].stats.patientGrowth;
            document.getElementById('totalAppointments').textContent = chartData[period].stats.totalAppointments;
            document.getElementById('appointmentGrowth').textContent = chartData[period].stats.appointmentGrowth;
            document.getElementById('pendingAppointments').textContent = chartData[period].stats.pendingAppointments;
            document.getElementById('pendingGrowth').textContent = chartData[period].stats.pendingGrowth;
            document.getElementById('totalRevenue').textContent = chartData[period].stats.totalRevenue;
            document.getElementById('revenueGrowth').textContent = chartData[period].stats.revenueGrowth;
            
            // Destroy existing charts if they exist
            if (patientsChart) patientsChart.destroy();
            if (revenueChart) revenueChart.destroy();
            
            // Initialize patients chart
            const patientsCtx = document.getElementById('patientsChart');
            if (patientsCtx) {
                patientsChart = new Chart(patientsCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData[period].patients.labels,
                        datasets: [{
                            label: 'New Patients',
                            data: chartData[period].patients.newPatients,
                            backgroundColor: 'rgba(20, 184, 166, 0.2)',
                            borderColor: 'rgb(20, 184, 166)',
                            borderWidth: 1,
                            borderRadius: 2
                        }, {
                            label: 'Returning Patients',
                            data: chartData[period].patients.returningPatients,
                            backgroundColor: 'rgba(14, 116, 144, 0.2)',
                            borderColor: 'rgb(14, 116, 144)',
                            borderWidth: 1,
                            borderRadius: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    boxWidth: 12,
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    drawBorder: false,
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Initialize revenue chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                revenueChart = new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: chartData[period].revenue.labels,
                        datasets: [{
                            label: 'Revenue',
                            data: chartData[period].revenue.data,
                            fill: {
                                target: 'origin',
                                above: 'rgba(20, 184, 166, 0.05)'
                            },
                            borderColor: 'rgb(20, 184, 166)',
                            tension: 0.3,
                            pointBackgroundColor: 'rgb(20, 184, 166)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1,
                            pointRadius: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    boxWidth: 12,
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    drawBorder: false,
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    },
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Initialize with monthly data by default
        initCharts('monthly');
        
        // Set up period buttons
        const periodBtns = document.querySelectorAll('.period-btn');
        periodBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                periodBtns.forEach(b => {
                    b.classList.remove('bg-primary-600', 'text-white');
                    b.classList.add('bg-white', 'text-gray-700');
                });
                
                // Add active class to clicked button
                this.classList.remove('bg-white', 'text-gray-700');
                this.classList.add('bg-primary-600', 'text-white');
                
                // Update charts based on period
                let period;
                if (this.id === 'dailyBtn') {
                    period = 'daily';
                } else if (this.id === 'monthlyBtn') {
                    period = 'monthly';
                } else if (this.id === 'yearlyBtn') {
                    period = 'yearly';
                }
                
                initCharts(period);
            });
        });
        
        // Set monthly as active by default
        document.getElementById('monthlyBtn').click();
    });
</script>
