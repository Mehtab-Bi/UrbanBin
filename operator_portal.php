<?php
session_start();
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

/* --------------------------------------------------
   1. HANDLE REGISTRATION
-----------------------------------------------------*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $user_input = $_POST['reg_username'];
    $pass_input = $_POST['reg_password'];
    $pass_hash = password_hash($pass_input, PASSWORD_DEFAULT);

    // FIX: Always store role in lowercase
    $role = 'operator';

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "Username already exists. Please choose a different one.";
        $message_type = 'error';
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user_input, $pass_hash, $role);

        if ($stmt->execute()) {
            $message = "Operator registration successful! You can now log in.";
            $message_type = 'success';
        } else {
            $message = "Registration failed. Please try again.";
            $message_type = 'error';
        }
    }
    $stmt->close();
}

/* --------------------------------------------------
   2. HANDLE LOGIN
-----------------------------------------------------*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $user_input = $_POST['login_username'];
    $pass_input = $_POST['login_password'];

    // enforce lowercase role
    $role = 'operator';

    $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? AND role = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $user_input, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();

            if (password_verify($pass_input, trim($row['password_hash']))) {
                // FIX: Unify session naming
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $row['id'];          // FIXED
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];

                $stmt->close();
                $conn->close();
                header("Location: operator_dashboard.php");
                exit;
            } else {
                $message = "Invalid username or password.";
                $message_type = 'error';
            }
        } else {
            $message = "Invalid username or password.";
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "Database error during login.";
        $message_type = 'error';
    }
}

$conn->close();

/* --------------------------------------------------
   3. REDIRECT IF ALREADY LOGGED IN
-----------------------------------------------------*/
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['role'] === 'operator') {
    header("Location: operator_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Portal - Smart Hygiene</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f0f9ff; }</style>
</head>
<body class="p-4 flex justify-center items-center min-h-screen">
    <div class="w-full max-w-4xl grid md:grid-cols-2 gap-8">

        <?php if ($message): ?>
            <div class="md:col-span-2 fixed top-5 left-1/2 -translate-x-1/2 w-full max-w-md p-4 rounded-lg shadow-xl 
                <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <p class="font-semibold"><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <!-- 1. Registration Card -->
        <div class="bg-white p-8 rounded-xl shadow-2xl transition-all duration-300 hover:shadow-3xl border-t-4 border-amber-500">
            <h2 class="text-3xl font-extrabold text-amber-700 mb-6 border-b pb-2">
                Operator Registration
            </h2>
            <p class="text-gray-600 mb-6">Register to manage critical alerts and collection tasks.</p>
            
            <form method="POST" action="operator_portal.php" class="space-y-6">
                <input type="hidden" name="register" value="1">
                <div>
                    <label for="reg_username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="reg_username" name="reg_username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500 transition duration-150" placeholder="e.g., TaskForce01">
                </div>
                <div>
                    <label for="reg_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="reg_password" name="reg_password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-amber-500 focus:border-amber-500 transition duration-150" placeholder="••••••••">
                </div>
                
                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-base font-semibold text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition duration-300 transform hover:scale-[1.01]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Register for Field Operations
                </button>
            </form>
        </div>

        <!-- 2. Login Card -->
        <div class="bg-white p-8 rounded-xl shadow-2xl transition-all duration-300 hover:shadow-3xl border-t-4 border-blue-500">
            <h2 class="text-3xl font-extrabold text-blue-700 mb-6 border-b pb-2">
                Operator Login
            </h2>
            <p class="text-gray-600 mb-6">Access your assigned tasks dashboard.</p>
            
            <form method="POST" action="operator_portal.php" class="space-y-6">
                <input type="hidden" name="login" value="1">
                <div>
                    <label for="login_username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="login_username" name="login_username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150" placeholder="Your Username">
                </div>
                <div>
                    <label for="login_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="login_password" name="login_password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150" placeholder="••••••••">
                </div>
                
                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300 transform hover:scale-[1.01]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    Log In
                </button>
            </form>
        </div>
    </div>
</body>
</html>