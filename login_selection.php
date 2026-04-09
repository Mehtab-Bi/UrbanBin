<?php
session_start();

// Redirect user if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin_home.php");
            exit;
        case 'operator':
            header("Location: operator_dashboard.php");
            exit;
        case 'citizen':
            header("Location: citizen_dashboard.php");
            exit;
    }
}

// Admin Login Logic (Hardcoded Check)
$admin_message = '';
$admin_message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_login'])) {
    $user_input = $_POST['username'] ?? '';
    $pass_input = $_POST['password'] ?? '';

    // Hardcoded credentials check for demonstration
    $correct_username = 'admin';
    $correct_password = 'password123'; 

    if ($user_input === $correct_username && $pass_input === $correct_password) {
        // Successful Admin Login
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = 0; // Placeholder for Admin (not from users table)
        $_SESSION['username'] = $correct_username;
        $_SESSION['role'] = 'admin'; // IMPORTANT: lowercase "admin"

        header("Location: admin_home.php");
        exit;
    } else {
        $admin_message = "Invalid Admin username or password.";
        $admin_message_type = 'error';
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Hygiene System - Role Selection</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .role-card {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .role-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="p-4 flex justify-center items-center min-h-screen">
    <div class="max-w-4xl mx-auto w-full text-center">
        
        <h1 class="text-4xl font-extrabold text-gray-800 mb-2">Smart Urban Hygiene System</h1>
        <p class="text-xl text-gray-500 mb-12">Select your role to proceed</p>

        <?php if ($admin_message): ?>
            <div class="fixed top-5 left-1/2 -translate-x-1/2 w-full max-w-md p-4 rounded-lg shadow-xl mb-6
                <?php echo $admin_message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <p class="font-semibold"><?php echo htmlspecialchars($admin_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Role Selection Grid -->
        <div class="grid md:grid-cols-3 gap-8">
            
            <!-- Admin Card -->
            <a href="#" id="admin-card" class="role-card bg-white p-8 rounded-xl shadow-lg border-t-8 border-violet-500 block">
                <i data-lucide="shield-check" class="w-12 h-12 mx-auto mb-4 text-violet-600"></i>
                <h2 class="text-2xl font-bold text-violet-700 mb-2">Administrator</h2>
                <p class="text-gray-500">System Configuration & Oversight</p>
                <span class="mt-4 inline-block text-sm font-semibold text-violet-600 border-b border-violet-600">Login Only</span>
            </a>

            <!-- Operator Card -->
            <a href="operator_portal.php" class="role-card bg-white p-8 rounded-xl shadow-lg border-t-8 border-amber-500 block">
                <i data-lucide="truck" class="w-12 h-12 mx-auto mb-4 text-amber-600"></i>
                <h2 class="text-2xl font-bold text-amber-700 mb-2">Field Operator</h2>
                <p class="text-gray-500">Alert Resolution & Bin Management</p>
                <span class="mt-4 inline-block text-sm font-semibold text-amber-600 border-b border-amber-600">Register & Login</span>
            </a>

            <!-- Citizen Card -->
            <a href="citizen_portal.php" class="role-card bg-white p-8 rounded-xl shadow-lg border-t-8 border-green-500 block">
                <i data-lucide="users" class="w-12 h-12 mx-auto mb-4 text-green-600"></i>
                <h2 class="text-2xl font-bold text-green-700 mb-2">Citizen Reporter</h2>
                <p class="text-gray-500">Issue Reporting & Rewards</p>
                <span class="mt-4 inline-block text-sm font-semibold text-green-600 border-b border-green-600">Register & Login</span>
            </a>
        </div>

        <!-- Admin Login Form (Hidden by Default) -->
        <div id="admin-login-area" class="mt-12 w-full max-w-sm mx-auto bg-white p-8 rounded-xl shadow-2xl border-2 border-violet-200 hidden">
            <h3 class="text-2xl font-bold text-violet-700 mb-6">Admin Login</h3>
            
            <form method="POST" action="login_selection.php" class="space-y-4">
                <input type="hidden" name="admin_login" value="1">
                <div>
                    <input type="text" name="username" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-violet-500 focus:border-violet-500 transition duration-150" 
                           placeholder="Username (e.g., admin)" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-violet-500 focus:border-violet-500 transition duration-150" 
                           placeholder="Password (e.g., password123)">
                </div>
                
                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-base font-semibold text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition duration-300 transform hover:scale-[1.01]">
                    Log in as Admin
                </button>
            </form>
            <button onclick="document.getElementById('admin-login-area').classList.add('hidden')" 
                    class="mt-4 text-sm text-gray-500 hover:text-gray-700 transition duration-150">
                Cancel
            </button>
        </div>
        
    </div>
    
    <script>
        lucide.createIcons();

        // Show/Hide Admin Login Form
        document.getElementById('admin-card').addEventListener('click', function(e) {
            e.preventDefault();
            const adminArea = document.getElementById('admin-login-area');
            adminArea.classList.toggle('hidden');
        });
        
        // If there was an error message from a failed login attempt, keep the form visible
        <?php if ($admin_message_type === 'error'): ?>
            document.getElementById('admin-login-area').classList.remove('hidden');
        <?php endif; ?>

    </script>
</body>
</html>
