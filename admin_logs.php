<?php
session_start();

// SECURITY CHECK
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

// DB CONNECTION
$conn = new mysqli("localhost", "root", "", "smart_hygiene_db");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

// FILTER INPUTS
$search = $_GET['search'] ?? "";
$user_role = $_GET['user_role'] ?? "all";

// BUILD SECURE QUERY
$sql = "SELECT * FROM system_logs WHERE 1";
$params = [];
$types = "";

// Search filter
if (!empty($search)) {
    $sql .= " AND activity LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

// Role filter
if ($user_role !== "all") {
    $sql .= " AND user_role = ?";
    $params[] = $user_role;
    $types .= "s";
}

$sql .= " ORDER BY timestamp DESC";

// PREPARE STATEMENT
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Logs - Smart Hygiene</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100">

<div class="flex min-h-screen">

    <?php include "admin_sidebar.php"; ?>

    <main class="flex-1 p-6 md:p-8">

        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-slate-900">System Logs</h1>
            <p class="text-slate-600 mt-1">Track Admin, Operator, Citizen & System activities</p>
        </div>

        <!-- FILTERS -->
        <form method="GET" class="flex flex-wrap gap-3 mb-6">

            <input 
                type="text" 
                name="search" 
                placeholder="Search activity..." 
                value="<?php echo htmlspecialchars($search); ?>"
                class="px-4 py-2 rounded-lg border border-slate-300 w-72 bg-white shadow-sm"
            >

            <select name="user_role" class="px-4 py-2 rounded-lg border border-slate-300 bg-white shadow-sm">
                <option value="all">All Roles</option>
                <option value="admin" <?php if ($user_role == "admin") echo "selected"; ?>>Admin</option>
                <option value="operator" <?php if ($user_role == "operator") echo "selected"; ?>>Operator</option>
                <option value="citizen" <?php if ($user_role == "citizen") echo "selected"; ?>>Citizen</option>
                <option value="system" <?php if ($user_role == "system") echo "selected"; ?>>System</option>
            </select>

            <button class="bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 shadow">
                Filter
            </button>
        </form>

        <!-- LOG TABLE -->
        <div class="bg-white rounded-xl shadow border border-slate-200 overflow-x-auto">

            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="py-3 px-4 text-left">Role</th>
                        <th class="py-3 px-4 text-left">Bin ID</th>
                        <th class="py-3 px-4 text-left">Action</th>
                        <th class="py-3 px-4 text-left">Activity</th>
                        <th class="py-3 px-4 text-left">Module</th>
                        <th class="py-3 px-4 text-left">IP</th>
                        <th class="py-3 px-4 text-left">Time</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $role = $row['user_role'];
                        $color = "bg-gray-200 text-gray-800";
                        if ($role == "admin") $color = "bg-red-100 text-red-700";
                        if ($role == "operator") $color = "bg-blue-100 text-blue-700";
                        if ($role == "citizen") $color = "bg-green-100 text-green-700";
                        if ($role == "system") $color = "bg-purple-100 text-purple-700";
                    ?>
                    <tr class="border-b hover:bg-slate-50">

                        <td class="py-3 px-4">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                <?= strtoupper(htmlspecialchars($role)) ?>
                            </span>
                        </td>

                        <td class="py-3 px-4 text-slate-700">
                            <?= htmlspecialchars($row['bin_id'] ?? '-') ?>
                        </td>

                        <td class="py-3 px-4 font-medium text-slate-800">
                            <?= htmlspecialchars($row['action'] ?? 'GENERAL') ?>
                        </td>

                        <td class="py-3 px-4 text-slate-700">
                            <?= htmlspecialchars($row['activity']) ?>
                        </td>

                        <td class="py-3 px-4 text-slate-600">
                            <?= htmlspecialchars($row['module'] ?? 'SYSTEM') ?>
                        </td>

                        <td class="py-3 px-4 text-slate-500 text-xs">
                            <?= htmlspecialchars($row['ip_address'] ?? 'UNKNOWN') ?>
                        </td>

                        <td class="py-3 px-4 text-slate-500 text-xs">
                            <?= htmlspecialchars($row['timestamp']) ?>
                        </td>

                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-6 text-slate-500">
                            No logs found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

</body>
</html>
