<?php
session_start();

// SECURITY CHECK — only admin allowed
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

// DB CONNECTION
$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");
if (!$conn) {
    die("DB Connection Failed: " . mysqli_connect_error());
}

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users & Operators - Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }

        .sidebar-collapsed { width: 4.5rem !important; }
        .sidebar-collapsed .sidebar-text { display: none; }
        .sidebar-collapsed .sidebar-logo-text { display: none; }
        .sidebar-collapsed .sidebar-item { justify-content: center; }
        .sidebar-collapsed .sidebar-item i { margin-right: 0; }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">

<div class="flex min-h-screen">

    <!-- ==================== SIDEBAR (same as admin_home.php) ==================== -->
    <aside id="sidebar"
           class="bg-slate-900 text-slate-100 w-64 transition-all duration-300 flex flex-col">

        <!-- Logo Section -->
        <div class="flex items-center justify-between px-4 py-4 border-b border-slate-800">
            <div class="flex items-center">
                <div class="bg-violet-600 rounded-lg p-2 mr-2 flex items-center justify-center">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <div class="sidebar-logo-text">
                    <p class="text-xs uppercase tracking-widest text-slate-400">Smart Hygiene</p>
                    <p class="text-sm font-semibold">Admin Console</p>
                </div>
            </div>
            <button id="sidebarToggle"
                    class="p-2 rounded-lg hover:bg-slate-800 focus:ring-2 focus:ring-violet-500">
                <i data-lucide="panel-left-close" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Menu -->
        <nav class="flex-1 px-2 py-4 space-y-1 text-sm">

            <a href="admin_home.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Dashboard Overview</span>
            </a>

            <a href="manage_bins.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Manage Bins</span>
            </a>

            <a href="admin_reports.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reports & Escalations</span>
            </a>

            <a href="admin_users.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg bg-slate-800 text-violet-300 font-semibold">
                <i data-lucide="users" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Users & Operators</span>
            </a>

            <a href="admin_rewards.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="award" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Reward Analytics</span>
            </a>

            <a href="admin_prediction.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="line-chart" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">Prediction & Insights</span>
            </a>

            <a href="admin_map.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="map" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">City Bin Map</span>
            </a>

            <a href="admin_logs.php"
               class="sidebar-item flex items-center px-3 py-2 rounded-lg hover:bg-slate-800">
                <i data-lucide="activity" class="w-4 h-4 mr-2"></i>
                <span class="sidebar-text">System Logs</span>
            </a>
        </nav>

        <!-- Footer -->
        <div class="px-3 py-3 border-t border-slate-800 flex items-center justify-between text-xs">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center mr-2">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </div>
                <div class="sidebar-text">
                    <p class="font-semibold text-slate-100 text-xs">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </p>
                    <p class="text-slate-400 text-[11px] uppercase tracking-wide">Administrator</p>
                </div>
            </div>
            <a href="logout.php" class="p-2 rounded-lg hover:bg-slate-800 text-slate-300">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </aside>



    <!-- ==================== MAIN CONTENT ==================== -->
    <main class="flex-1 p-8">

        <div class="max-w-5xl mx-auto">

            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-extrabold text-slate-800">Users & Operators</h1>

                <button onclick="openAddModal()"
                    class="bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700 flex items-center gap-2">
                    <i data-lucide="user-plus"></i> Add User
                </button>
            </div>

            <!-- TABLE BOX -->
            <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-x-auto">

                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="p-3 text-left">ID</th>
                            <th class="p-3 text-left">Username</th>
                            <th class="p-3 text-left">Role</th>
                            <th class="p-3 text-left">Points</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($u = mysqli_fetch_assoc($users)) { ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3 font-semibold">#<?php echo $u['id']; ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($u['username']); ?></td>

                            <td class="p-3">
                                <?php
                                $roleColor = [
                                    "admin"    => "bg-purple-100 text-purple-800",
                                    "operator" => "bg-blue-100 text-blue-800",
                                    "citizen"  => "bg-green-100 text-green-800",
                                ][strtolower($u['role'])];
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $roleColor; ?>">
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </td>

                            <td class="p-3"><?php echo $u['points']; ?></td>

                            <td class="p-3 text-right">
                                <button 
                                    onclick="openEditModal('<?php echo $u['id']; ?>', '<?php echo $u['username']; ?>', '<?php echo $u['role']; ?>')"
                                    class="text-blue-600 hover:underline mr-4">
                                    Edit
                                </button>

                                <a href="delete_user.php?id=<?php echo $u['id']; ?>"
                                   onclick="return confirm('Delete this user?');"
                                   class="text-red-600 hover:underline">Delete</a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>

                </table>
            </div>

        </div>
    </main>
</div>


<!-- ==================== ADD USER MODAL ==================== -->
<div id="addModal"
     class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">

    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Add New User</h2>

        <form action="add_user.php" method="POST" class="space-y-3">

            <div>
                <label class="text-sm font-semibold">Username</label>
                <input name="username" required class="w-full p-2 border rounded-md">
            </div>

            <div>
                <label class="text-sm font-semibold">Password</label>
                <input name="password" type="password" required class="w-full p-2 border rounded-md">
            </div>

            <div>
                <label class="text-sm font-semibold">Role</label>
                <select name="role" class="w-full p-2 border rounded-md">
                    <option value="citizen">Citizen</option>
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 bg-slate-200 rounded-md">Cancel</button>

                <button
                    class="px-4 py-2 bg-violet-600 text-white rounded-md hover:bg-violet-700">
                    Create
                </button>
            </div>

        </form>
    </div>
</div>


<!-- ==================== EDIT USER MODAL ==================== -->
<div id="editModal"
     class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">

    <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Edit User</h2>

        <form action="edit_user.php" method="POST" class="space-y-3">

            <input type="hidden" id="edit_id" name="id">

            <div>
                <label class="text-sm font-semibold">Username</label>
                <input id="edit_username" name="username" required class="w-full p-2 border rounded-md">
            </div>

            <div>
                <label class="text-sm font-semibold">Role</label>
                <select id="edit_role" name="role" class="w-full p-2 border rounded-md">
                    <option value="citizen">Citizen</option>
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 bg-slate-200 rounded-md">Cancel</button>

                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Update
                </button>
            </div>

        </form>
    </div>
</div>


<script>
lucide.createIcons();

// Sidebar collapse
document.getElementById("sidebarToggle").addEventListener("click", () => {
    document.getElementById("sidebar").classList.toggle("sidebar-collapsed");
});

// ADD USER MODAL
function openAddModal() {
    const modal = document.getElementById("addModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}
function closeAddModal() {
    const modal = document.getElementById("addModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

// EDIT USER MODAL
function openEditModal(id, username, role) {
    document.getElementById("edit_id").value = id;
    document.getElementById("edit_username").value = username;
    document.getElementById("edit_role").value = role;

    const modal = document.getElementById("editModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}
function closeEditModal() {
    const modal = document.getElementById("editModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}
</script>

</body>
</html>
