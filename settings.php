<!-- settings.php -->
<?php
// Include database connection
require_once 'config/db.php';

// Initialize variables
$error = null;
$success = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];
        
        // Validate passwords
        if (strlen($newPassword) < 8) {
            $error = "New password must be at least 8 characters long";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match";
        } else {
            // Get user credentials
            $stmt = $pdo->prepare("SELECT u.*, s.* FROM users u 
                                 JOIN staff s ON u.staff_id = s.id 
                                 WHERE u.id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $userData = $stmt->fetch();
            
            if (password_verify($currentPassword, $userData['pass'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET pass = :password WHERE id = :id");
                $updateStmt->execute([
                    ':password' => $hashedPassword,
                    ':id' => $_SESSION['user_id']
                ]);
                $success = "Password updated successfully";
            } else {
                $error = "Current password is incorrect";
            }
        }
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT u.*, s.* FROM users u 
                      JOIN staff s ON u.staff_id = s.id 
                      WHERE u.id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<div id="settings" class="space-y-8 bg-neutral-light p-6 md:p-8 animate-fade-in">
    <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Settings</h2>
    
    <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
        <h3 class="text-xl font-semibold text-gray-900 mb-6">Account Settings</h3>
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200" readonly>
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200">
                </div>
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200">
                </div>
                <div>
                    <label for="gmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" id="gmail" name="gmail" value="<?php echo htmlspecialchars($user['gmail'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200">
                </div>
                <div>
                    <label for="contact" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input type="tel" id="contact" name="contact" value="<?php echo htmlspecialchars($user['contact'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200">
                </div>
            </div>

            <div class="mb-8">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Change Password</h4>
                <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600">Password Requirements:</p>
                    <ul class="mt-2 text-sm text-gray-600 list-disc list-inside">
                        <li>Must be at least 8 characters long</li>
                        <li>Should include a mix of letters, numbers, and special characters</li>
                    </ul>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" id="currentPassword" name="currentPassword" required minlength="8" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200">
                    </div>
                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" id="newPassword" name="newPassword" required minlength="8" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200">
                        </div>
                        <div>
                            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" required minlength="8" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition duration-200">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-4">
                <input type="hidden" name="action" value="change_password">
                <button type="submit" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-3 py-1.5 rounded-lg text-sm flex items-center hover:scale-105 transition-all duration-200">Save Changes</button>
            </div>
        </form>
    </div>
</div>
