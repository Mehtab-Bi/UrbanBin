<?php
session_start();

// ----------------------------------------------------
// 1. SECURITY CHECK (Operator Only)
// ----------------------------------------------------
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'operator') {
    header("Location: operator_portal.php");
    exit;
}

$operator_id = $_SESSION['id'];

// ----------------------------------------------------
// DB CONNECTION
// ----------------------------------------------------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ----------------------------------------------------
// 2. VALIDATE REPORT ID
// ----------------------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Report ID");
}

$report_id = intval($_GET['id']);

// ----------------------------------------------------
// 3. FETCH REPORT DETAILS
// ----------------------------------------------------
$sql = "
    SELECT r.*, u.username AS citizen_name
    FROM reports r
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    die("Report not found.");
}

$stmt->close();

// ----------------------------------------------------
// 4. HANDLE UPDATE FORM SUBMISSION
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $status = $_POST['report_status'] ?? 'Pending';
    $notes = $_POST['operator_notes'] ?? '';

    // Auto-set resolution date if resolved
    $resolution_date = ($status === "Resolved") ? date("Y-m-d H:i:s") : null;

    $sql_update = "
        UPDATE reports 
        SET 
            report_status = ?, 
            operator_notes = ?, 
            resolved_by_user_id = ?, 
            resolution_date = ?
        WHERE id = ?
    ";

    $stmt2 = $conn->prepare($sql_update);
    $stmt2->bind_param(
        "ssisi",
        $status,
        $notes,
        $operator_id,
        $resolution_date,
        $report_id
    );

    if ($stmt2->execute()) {
        $_SESSION['message'] = "Report updated successfully.";
        $_SESSION['message_type'] = "success";
        header("Location: operator_dashboard.php");
        exit;
    } else {
        $_SESSION['message'] = "Update failed: " . $stmt2->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt2->close();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Report #<?php echo $report_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-8">

    <div class="max-w-5xl mx-auto bg-white p-8 rounded-xl shadow-xl">

        <h1 class="text-3xl font-bold text-indigo-700 mb-6">
            Update Report #<?php echo $report_id; ?>
        </h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            <!-- LEFT PANEL: DETAILS -->
            <div class="bg-gray-50 p-6 rounded-lg border">
                <h2 class="text-xl font-semibold mb-4 text-gray-700">Report Details</h2>

                <div class="space-y-3 text-gray-700">
                    <p><strong>Citizen Name:</strong> <?php echo htmlspecialchars($report['citizen_name']); ?></p>
                    <p><strong>Bin ID:</strong> <?php echo htmlspecialchars($report['bin_id']); ?></p>
                    <p><strong>Issue Type:</strong> <?php echo htmlspecialchars($report['issue_type']); ?></p>

                    <p>
                        <strong>Description:</strong><br>
                        <span class="text-gray-600"><?php echo nl2br(htmlspecialchars($report['details'])); ?></span>
                    </p>

                    <p><strong>Reported At:</strong> <?php echo $report['reported_at']; ?></p>

                    <?php if (!empty($report['resolution_date'])): ?>
                        <p><strong>Resolved On:</strong> <?php echo $report['resolution_date']; ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT PANEL: UPDATE FORM -->
            <div>
                <form method="POST" class="space-y-6">

                    <!-- STATUS -->
                    <div>
                        <label class="font-semibold">Update Status</label>
                        <select name="report_status" class="w-full p-3 rounded-lg border focus:ring-indigo-500">
                            <option <?php if ($report['report_status']=="Pending") echo "selected"; ?>>Pending</option>
                            <option <?php if ($report['report_status']=="In Progress") echo "selected"; ?>>In Progress</option>
                            <option <?php if ($report['report_status']=="Resolved") echo "selected"; ?>>Resolved</option>
                            <option <?php if ($report['report_status']=="Rejected") echo "selected"; ?>>Rejected</option>
                        </select>
                    </div>

                    <!-- NOTES -->
                    <div>
                        <label class="font-semibold">Operator Notes</label>
                        <textarea 
                            name="operator_notes" 
                            rows="3" 
                            class="w-full p-3 rounded-lg border focus:ring-indigo-500"
                        ><?php echo htmlspecialchars($report['operator_notes']); ?></textarea>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <button 
                        type="submit"
                        class="w-full bg-indigo-600 text-white py-3 rounded-lg shadow-lg hover:bg-indigo-700 transition"
                    >
                        Save Changes
                    </button>

                </form>
            </div>

        </div>
    </div>

</body>
</html>
