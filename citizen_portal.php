<?php
session_start();

// ------------------------------------------------------------------
// *** FIX FOR REDIRECT LOOP ***
// If a citizen is ALREADY logged in, send them straight to the dashboard.
// This prevents the citizen_dashboard.php redirecting here, and this page
// immediately redirecting back, causing the "too many redirects" error.
// ------------------------------------------------------------------
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && ($_SESSION['role'] === 'citizen' || $_SESSION['role'] === 'Citizen')) {
    header("Location: citizen_dashboard.php");
    exit;
}
// ------------------------------------------------------------------


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = ''; // success or error

// Handle Registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $user_input = $conn->real_escape_string($_POST['reg_username']);
    $pass_input = $_POST['reg_password'];
    $pass_hash = password_hash($pass_input, PASSWORD_DEFAULT);
    $role = 'citizen'; // Default role for portal users

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "Username already exists. Please choose a different one.";
        $message_type = 'error';
    } else {
        // Insert new user
        $stmt->close();
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user_input, $pass_hash, $role);
        
        if ($stmt->execute()) {
            $message = "Registration successful! You can now log in.";
            $message_type = 'success';
        } else {
            $message = "Registration failed. Please try again. Error: " . $stmt->error;
            $message_type = 'error';
        }
    }
    $stmt->close();
}


// Handle Login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $input_username = $conn->real_escape_string($_POST['login_username']);
    $input_password = $_POST['login_password'];

    // Select all user data including ID for session use
    $sql = "SELECT id, password_hash, role FROM users WHERE username = '$input_username'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $hashed_password = $row['password_hash'];
        $user_role = $row['role'];
        $user_id = $row['id']; // Get the user ID

        // Verify password and ensure role is 'citizen'
        if (password_verify($input_password, $hashed_password) && ($user_role === 'citizen' || $user_role === 'Citizen')) {
            // Login successful
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $input_username;
            $_SESSION['role'] = 'citizen'; // Standardize the role to lowercase for consistency
            $_SESSION['user_id'] = $user_id; // Store user ID

            // Redirect to the citizen dashboard
            header("Location: citizen_dashboard.php");
            exit;
        } else {
            // Either password failed, or the user is not a citizen (e.g., they tried to log in as admin here)
            $message = "Invalid username or password, or you do not have citizen access.";
            $message_type = 'error';
        }
    } else {
        $message = "Invalid username or password.";
        $message_type = 'error';
    }
}
$conn->close();
session_write_close();

// Determine the active tab based on message or default to Login
$active_tab = (isset($_POST['register']) || (isset($_POST['login']) && $message_type == 'error')) ? 'register' : 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Portal - Login/Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        .tab-button.active { background-color: #0d9488; color: white; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white p-8 rounded-xl shadow-2xl">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-extrabold text-teal-700">Smart Hygiene Citizen Portal</h1>
            <p class="text-gray-500 mt-2">Report bins and earn rewards!</p>
        </div>

        <!-- Alert Message Area -->
        <?php if (!empty($message)): ?>
            <div class="p-4 rounded-lg mb-4 
                <?php echo $message_type === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700'; ?>">
                <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Tab Controls -->
        <div class="flex border-b border-gray-200 mb-6">
            <button id="login-tab" onclick="switchTab('login')" class="tab-button flex-1 py-3 px-4 text-center text-sm font-semibold transition duration-150 rounded-t-lg <?php echo $active_tab == 'login' ? 'active' : 'text-gray-600 hover:bg-gray-50'; ?>">
                Citizen Login
            </button>
            <button id="register-tab" onclick="switchTab('register')" class="tab-button flex-1 py-3 px-4 text-center text-sm font-semibold transition duration-150 rounded-t-lg <?php echo $active_tab == 'register' ? 'active' : 'text-gray-600 hover:bg-gray-50'; ?>">
                New Citizen Registration
            </button>
        </div>

        <!-- Login Form -->
        <div id="login-form" class="space-y-5 <?php echo $active_tab == 'login' ? 'block' : 'hidden'; ?>">
            <form action="citizen_portal.php" method="POST" class="space-y-6">
                <input type="hidden" name="login" value="1">
                <div>
                    <label for="login_username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="login_username" name="login_username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150" placeholder="Your Username">
                </div>
                <div>
                    <label for="login_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="login_password" name="login_password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150" placeholder="••••••••">
                </div>
                
                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-base font-semibold text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition duration-300 transform hover:scale-[1.01]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3v-5m6-10v1a3 3 0 01-3 3H6a3 3 0 00-3 3v5"></path></svg>
                    Sign In to Dashboard
                </button>
            </form>
        </div>

        <!-- Registration Form -->
        <div id="register-form" class="space-y-5 <?php echo $active_tab == 'register' ? 'block' : 'hidden'; ?>">
            <form action="citizen_portal.php" method="POST" class="space-y-6">
                <input type="hidden" name="register" value="1">
                <div>
                    <label for="reg_username" class="block text-sm font-medium text-gray-700 mb-1">Choose Username</label>
                    <input type="text" id="reg_username" name="reg_username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150" placeholder="A unique username">
                </div>
                <div>
                    <label for="reg_password" class="block text-sm font-medium text-gray-700 mb-1">Choose Password</label>
                    <input type="password" id="reg_password" name="reg_password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150" placeholder="Secure Password">
                </div>
                
                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-base font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-300 transform hover:scale-[1.01]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-3a4 4 0 11-8 0 4 4 0 018 0zm-8 10h10a2 2 0 002-2v-4a2 2 0 00-2-2H6a2 2 0 00-2 2v4a2 2 0 002 2z"></path></svg>
                    Register and Join
                </button>
            </form>
        </div>

        <p class="mt-8 text-center text-sm text-gray-500">
            For Admin/Operator login, please use the <a href="login.php" class="text-indigo-600 hover:underline font-medium">standard login page</a>.
        </p>
    </div>
    
    <script>
        // Function to switch between login and registration tabs
        function switchTab(tab) {
            document.getElementById('login-form').classList.add('hidden');
            document.getElementById('register-form').classList.add('hidden');
            document.getElementById('login-tab').classList.remove('active');
            document.getElementById('register-tab').classList.remove('active');

            document.getElementById(tab + '-form').classList.remove('hidden');
            document.getElementById(tab + '-tab').classList.add('active');
        }

        // Set initial active tab based on PHP logic
        switchTab('<?php echo $active_tab; ?>');
    </script>
</body>
</html>