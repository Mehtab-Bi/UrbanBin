<?php
session_start();

// ---------------------------------------------
// SECURITY → Only citizens allowed
// ---------------------------------------------
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'citizen') {
    header("Location: citizen_portal.php");
    exit;
}

// ---------------------------------------------
// DB Connection
// ---------------------------------------------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);

$db_connected = true;
$user_id = $_SESSION['user_id'];
$current_points = 0;
$leaderboard = [];
$transaction_history = [];
$system_message = '';
$message_type = '';

if ($conn->connect_error) {
    $db_connected = false;
    $system_message = "Database connection failed: " . $conn->connect_error;
}

// ---------------------------------------------
// Fetch Rewards, Leaderboard, History
// ---------------------------------------------
if ($db_connected) {

    // USER POINTS
    $sql = "SELECT SUM(points_change) AS total FROM point_transactions WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $current_points = (int)($row['total'] ?? 0);
    $stmt->close();

    // LEADERBOARD
    $sql = "
        SELECT u.username, SUM(t.points_change) AS total
        FROM point_transactions t
        JOIN users u ON t.user_id = u.id
        GROUP BY t.user_id
        ORDER BY total DESC
        LIMIT 5
    ";
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) $leaderboard[] = $r;

    // TRANSACTION HISTORY
    $sql = "SELECT reason, points_change, transaction_date FROM point_transactions WHERE user_id = ? ORDER BY transaction_date DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $transaction_history[] = $r;
    $stmt->close();

    $conn->close();
}

// ---------------------------------------------
// HANDLE SESSION MESSAGES
// ---------------------------------------------
if (isset($_SESSION['message'])) {
    $system_message = $_SESSION['message'];
    $message_type   = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Default View → Rewards
$view = $_GET['view'] ?? "rewards";

// Helper for nav highlight
function tab($name, $current) {
    return $name === $current
        ? "bg-indigo-600 text-white shadow"
        : "bg-gray-100 text-gray-700 hover:bg-gray-200";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Citizen Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
    body { font-family: 'Inter', sans-serif; background: #f4f7f9; }
    .card { transition: 0.2s; }
    .card:hover { transform: translateY(-3px); }
</style>
</head>

<body class="p-4 md:p-8">
<header class="max-w-4xl mx-auto mb-8">
    <nav class="flex justify-between bg-white p-4 rounded-xl shadow-lg">
        <h1 class="hidden sm:block text-indigo-600 font-bold text-xl">Citizen Portal</h1>

        <div class="flex space-x-2 sm:space-x-4">

            <a href="citizen_dashboard.php?view=rewards"
               class="px-3 py-2 rounded-lg text-sm font-medium <?= tab('rewards', $view) ?>">
               <i data-lucide="star" class="w-4 h-4 inline mr-1"></i> Rewards
            </a>

            <a href="citizen_dashboard.php?view=report"
               class="px-3 py-2 rounded-lg text-sm font-medium <?= tab('report', $view) ?>">
               <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i> Report Issue
            </a>

            <a href="awareness.php"
               class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-medium">
               <i data-lucide="book-open" class="w-4 h-4 inline mr-1"></i> Awareness
            </a>

            <a href="logout.php"
               class="px-3 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600 text-sm font-medium">
               <i data-lucide="log-out" class="w-4 h-4 inline mr-1"></i> Logout
            </a>

        </div>
    </nav>

    <?php if ($system_message): ?>
        <div class="mt-4 p-3 border-l-4 rounded-lg shadow 
             <?= $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700' ?>">
            <?= htmlspecialchars($system_message) ?>
        </div>
    <?php endif; ?>
</header>

<div class="max-w-4xl mx-auto bg-white p-6 md:p-10 rounded-xl shadow-xl">

<?php if ($view === "rewards"): ?>

    <h2 class="text-3xl font-extrabold mb-6">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>

    <!-- POINTS CARD -->
    <div class="card bg-indigo-500 text-white p-6 rounded-xl shadow-lg mb-8">
        <p class="text-sm opacity-80">Total Reward Points</p>
        <p class="text-5xl font-extrabold mt-2"><?= $current_points ?></p>
    </div>

    <!-- LEADERBOARD -->
    <h3 class="text-xl font-bold mb-4 flex items-center">
        <i data-lucide="trophy" class="w-5 h-5 text-yellow-500 mr-2"></i> Community Leaderboard (Top 5)
    </h3>

    <ul class="space-y-2 mb-10">
        <?php foreach ($leaderboard as $i => $u): ?>
            <li class="flex justify-between p-3 rounded-lg bg-gray-50">
                <span class="font-bold"><?= $i + 1 ?>.</span>
                <span><?= htmlspecialchars($u['username']) ?></span>
                <span class="font-bold text-indigo-600"><?= $u['total'] ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- TRANSACTION HISTORY -->
    <h3 class="text-xl font-bold mb-4 flex items-center">
        <i data-lucide="history" class="w-5 h-5 text-indigo-600 mr-2"></i> Point History
    </h3>

    <table class="w-full mt-4 border">
        <tr class="bg-gray-100">
            <th class="p-2">Date</th>
            <th class="p-2">Activity</th>
            <th class="p-2 text-right">Points</th>
        </tr>
        <?php foreach ($transaction_history as $t): ?>
        <tr class="border-b">
            <td class="p-2"><?= $t['transaction_date'] ?></td>
            <td class="p-2"><?= htmlspecialchars($t['reason']) ?></td>
            <td class="p-2 text-right font-bold"><?= $t['points_change'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

<?php elseif ($view === "report"): ?>

    <h2 class="text-3xl font-extrabold mb-6">Report a Bin Issue</h2>

    <form action="api_submit_report.php" method="POST" class="space-y-6">

        <div>
            <label class="font-medium">Bin ID / Location *</label>
            <input type="text" name="bin_id" required
                   class="w-full mt-1 p-3 border rounded-lg">
        </div>

        <div>
            <label class="font-medium">Issue Type *</label>
            <select name="report_type" required
                    class="w-full mt-1 p-3 border rounded-lg">
                <option value="">Select Issue</option>
                <option value="Overflow">Overflow</option>
                <option value="Damaged">Damaged</option>
                <option value="Misplaced">Misplaced</option>
                <option value="Blockage">Sensor Blockage</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div>
            <label class="font-medium">Additional Details</label>
            <textarea name="details" rows="4" class="w-full mt-1 p-3 border rounded-lg"></textarea>
        </div>

        <button class="w-full bg-green-600 text-white p-3 rounded-lg font-bold hover:bg-green-700">
            Submit Report & Earn +10 Points
        </button>
    </form>

<?php endif; ?>

</div>

<script>
lucide.createIcons();
</script>

</body>
</html>
