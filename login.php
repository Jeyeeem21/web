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
                        }
                    },
                    animation: {
                        'slide-up': 'slideUp 0.3s ease-out forwards',
                        'fade-in': 'fadeIn 0.3s ease-out forwards'
                    }
                }
            }
        }
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
            height: 450px;
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
    </style>
</head>
<body class="bg-gray-50 font-sans flex items-center justify-center min-h-screen p-4 sm:p-6 lg:p-8">
    <!-- Loading Overlay -->
    <div class="fixed inset-0 bg-white z-[9999] flex items-center justify-center transition-opacity duration-400 loading-overlay">
        <div class="w-12 h-12 border-4 border-primary-100 border-t-primary-500 rounded-full animate-spin"></div>
    </div>

    <!-- Main Content -->
    <div class="w-full max-w-md">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-800 rounded-lg animate-fade-in">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-200 text-green-800 rounded-lg animate-fade-in">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl border-2 border-primary-500/20 overflow-hidden">
            <!-- Form Container -->
            <div id="form-container" class="relative h-[800px] transform-style-preserve-3d">
                <!-- Login Form -->
                <div id="login-form" class="backface-hidden">
                    <div class="p-8">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-heading font-bold text-neutral-dark mb-2">Welcome Back</h2>
                            <p class="text-secondary">Please sign in to your account</p>
                        </div>
                        <form action="process_login.php" method="POST" class="space-y-6">
                            <div>
                                <label for="username" class="block text-sm font-medium text-neutral-dark mb-2">Username</label>
                                <input type="text" id="username" name="username" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your username">
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-neutral-dark mb-2">Password</label>
                                <input type="password" id="password" name="password" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your password">
                            </div>
                            <button type="submit"
                                class="w-full bg-primary-500 text-white px-6 py-3 rounded-lg text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300">
                                Sign In
                            </button>
                        </form>
                        <div class="mt-6 text-center">
                            <p class="text-secondary">Don't have an account? 
                                <button onclick="flipForm()" class="text-primary-500 font-semibold hover:text-primary-600">Register</button>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Register Form -->
                <div id="register-form" class="backface-hidden rotate-y-180">
                    <div class="p-8 h-full overflow-y-auto">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-heading font-bold text-neutral-dark mb-2">Create Account</h2>
                            <p class="text-secondary">Please fill in your details</p>
                        </div>
                        <form action="process_register.php" method="POST" class="space-y-6" enctype="multipart/form-data">
                            <div>
                                <label for="reg_name" class="block text-sm font-medium text-neutral-dark mb-2">Full Name</label>
                                <input type="text" id="reg_name" name="name" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your full name">
                            </div>
                            <div>
                                <label for="reg_email" class="block text-sm font-medium text-neutral-dark mb-2">Email</label>
                                <input type="email" id="reg_email" name="email" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your email">
                            </div>
                            <div>
                                <label for="reg_phone" class="block text-sm font-medium text-neutral-dark mb-2">Phone</label>
                                <input type="tel" id="reg_phone" name="phone" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your phone number">
                            </div>
                            <div>
                                <label for="reg_address" class="block text-sm font-medium text-neutral-dark mb-2">Address</label>
                                <textarea id="reg_address" name="address" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium"
                                    placeholder="Enter your address" rows="2"></textarea>
                            </div>
                            <div>
                                <label for="reg_birthdate" class="block text-sm font-medium text-neutral-dark mb-2">Birthdate</label>
                                <input type="date" id="reg_birthdate" name="birthdate" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium">
                            </div>
                            <div>
                                <label for="reg_gender" class="block text-sm font-medium text-neutral-dark mb-2">Gender</label>
                                <select id="reg_gender" name="gender" required
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="reg_photo" class="block text-sm font-medium text-neutral-dark mb-2">Photo</label>
                                <input type="file" id="reg_photo" name="photo" accept="image/*"
                                    class="w-full px-4 py-3 rounded-lg border border-primary-500/50 focus:ring-2 focus:ring-primary-500 focus:border-transparent text-base font-medium">
                            </div>
                            <div class="flex space-x-4">
                                <button type="button" onclick="flipForm()"
                                    class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg text-base font-bold hover:bg-gray-300 hover:scale-105 hover:shadow-lg transition-all duration-300">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="flex-1 bg-primary-500 text-white px-6 py-3 rounded-lg text-base font-bold hover:bg-primary-600 hover:scale-105 hover:shadow-lg transition-all duration-300">
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
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

        // Hide loading overlay when page is loaded
        window.addEventListener('load', function() {
            document.querySelector('.loading-overlay').style.opacity = '0';
            setTimeout(() => {
                document.querySelector('.loading-overlay').style.display = 'none';
            }, 400);
        });
    </script>
</body>
</html>