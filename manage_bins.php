<?php
session_start();

/* -------------------------------------------------------
   ROLE CHECK — Allow only Admin & Operator (case-insensitive)
------------------------------------------------------- */
if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !in_array(strtolower($_SESSION['role']), ['admin', 'operator'])
) {
    header("Location: login_selection.php");
    exit;
}

/* -------------------------------------------------------
   DB CONNECTION
------------------------------------------------------- */
$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");
if (!$conn) die("Database Error: " . mysqli_connect_error());

$message = "";
$type = "";

/* -------------------------------------------------------
   ADD BIN
------------------------------------------------------- */
if (isset($_POST['action']) && $_POST['action'] === "add_bin") {

    $bin_id   = mysqli_real_escape_string($conn, $_POST['bin_id']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $capacity = intval($_POST['capacity_percent']);
    $lat      = mysqli_real_escape_string($conn, $_POST['latitude']);
    $lng      = mysqli_real_escape_string($conn, $_POST['longitude']);
    $status   = mysqli_real_escape_string($conn, $_POST['status']);

    if ($lat === "" || $lng === "") {
        $message = "Please pin a location on the map.";
        $type = "error";
    } else {
        $sql = "INSERT INTO bins (bin_id, location, capacity_percent, status, latitude, longitude)
                VALUES ('$bin_id', '$location', $capacity, '$status', '$lat', '$lng')";

        if (mysqli_query($conn, $sql)) {
            $message = "Bin <strong>$bin_id</strong> added successfully!";
            $type = "success";
        } else {
            $message = "DB Error: " . mysqli_error($conn);
            $type = "error";
        }
    }
}

/* -------------------------------------------------------
   SERVICE BIN
------------------------------------------------------- */
if (isset($_POST['action']) && $_POST['action'] === "service_bin") {

    $bin_id = mysqli_real_escape_string($conn, $_POST['bin_id']);

    $sql = "UPDATE bins SET
                capacity_percent = 0,
                last_capacity_percent = 0,
                co2 = 450.00,
                ammonia = 0.05,
                hygiene_status = 'Normal',
                time_to_fullness_hrs = 999,
                status = 'Normal',
                last_updated = NOW()
            WHERE bin_id = '$bin_id'";

    if (mysqli_query($conn, $sql)) {
        $message = "Bin <strong>$bin_id</strong> serviced successfully!";
        $type = "success";
    } else {
        $message = "Service Error: " . mysqli_error($conn);
        $type = "error";
    }
}

/* -------------------------------------------------------
   DELETE BIN — Admin ONLY
------------------------------------------------------- */
if (
    isset($_POST['action']) &&
    $_POST['action'] === "delete_bin" &&
    strtolower($_SESSION['role']) === 'admin'
) {

    $bin_id = mysqli_real_escape_string($conn, $_POST['bin_id']);

    $sql = "DELETE FROM bins WHERE bin_id = '$bin_id'";

    if (mysqli_query($conn, $sql)) {
        $message = "Bin <strong>$bin_id</strong> deleted successfully!";
        $type = "success";
    } else {
        $message = "Delete Error: " . mysqli_error($conn);
        $type = "error";
    }
}

/* -------------------------------------------------------
   FETCH BINS
------------------------------------------------------- */
$bins = mysqli_query($conn, "SELECT * FROM bins ORDER BY bin_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Manage Bins</title>

<!-- TAILWIND + ICONS + MAP -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        lucide.createIcons();
    });
</script>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

<style>
    body { font-family: 'Inter', sans-serif; }
    #map { height: 300px; border-radius: 10px; }
</style>
</head>

<body class="bg-slate-100">
<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <?php include __DIR__ . "/admin_sidebar.php"; ?>

    <!-- MAIN AREA -->
    <main class="flex-1 p-6">

        <h1 class="text-2xl font-bold mb-4">Manage Bins</h1>

        <!-- MESSAGE -->
        <?php if ($message): ?>
            <div class="p-4 mb-4 rounded-lg <?= $type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- ADD BIN -->
        <div class="bg-white p-5 rounded-xl shadow mb-6">
            <h2 class="font-semibold mb-3">Add New Bin</h2>

            <form method="POST" class="grid grid-cols-2 gap-4">
                <input type="hidden" name="action" value="add_bin">

                <input type="text" name="bin_id" placeholder="BIN005" required class="p-2 border rounded">
                <input type="text" name="location" placeholder="Descriptive Location" required class="p-2 border rounded">

                <input type="number" name="capacity_percent" value="0" min="0" max="100" class="p-2 border rounded">

                <select name="status" class="p-2 border rounded">
                    <option value="Normal">Normal</option>
                    <option value="Alert">Alert</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Offline">Offline</option>
                </select>

                <input type="text" name="latitude" id="lat" readonly placeholder="Latitude (click map)" class="p-2 border rounded">
                <input type="text" name="longitude" id="lng" readonly placeholder="Longitude (click map)" class="p-2 border rounded">

                <div class="col-span-2">
                    <div id="map"></div>
                </div>

                <button class="col-span-2 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded">
                    Save Bin
                </button>
            </form>
        </div>

        <!-- TABLE -->
        <div class="bg-white p-5 rounded-xl shadow">
            <h2 class="font-semibold mb-3">All Bins</h2>

            <table class="min-w-full text-sm border-collapse">
    <thead>
        <tr class="bg-slate-100 border-b">
            <th class="py-3 px-4 text-left font-semibold text-slate-700">Bin ID</th>
            <th class="py-3 px-4 text-left font-semibold text-slate-700">Location</th>
            <th class="py-3 px-4 text-center font-semibold text-slate-700">Status</th>
            <th class="py-3 px-4 text-center font-semibold text-slate-700">Fill %</th>
            <th class="py-3 px-4 text-center font-semibold text-slate-700">Coordinates</th>
            <th class="py-3 px-4 text-center font-semibold text-slate-700">Actions</th>
        </tr>
    </thead>

    <tbody class="divide-y divide-slate-200">
        <?php while ($b = mysqli_fetch_assoc($bins)): ?>
        <tr class="hover:bg-slate-50 transition">
            
            <td class="py-3 px-4 font-medium text-slate-800"><?= $b['bin_id'] ?></td>

            <td class="py-3 px-4 text-slate-600"><?= $b['location'] ?></td>

            <td class="py-3 px-4 text-center">
                <span class="px-3 py-1 text-xs font-semibold rounded-full 
                    <?= $b['status']=='Normal' ? 'bg-green-100 text-green-700' : 
                    ($b['status']=='Alert' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                    <?= $b['status'] ?>
                </span>
            </td>

            <td class="py-3 px-4 text-center font-semibold 
                <?= $b['capacity_percent'] > 80 ? 'text-red-600' : 
                ($b['capacity_percent'] > 50 ? 'text-orange-500' : 'text-green-600'); ?>">
                <?= $b['capacity_percent'] ?>%
            </td>

            <td class="py-3 px-4 text-center text-slate-600">
                <?= $b['latitude'] ?> , <?= $b['longitude'] ?>
            </td>

            <td class="py-3 px-4 text-center space-x-2">

                <!-- Service Button -->
                <form method="POST" class="inline-block">
                    <input type="hidden" name="action" value="service_bin">
                    <input type="hidden" name="bin_id" value="<?= $b['bin_id'] ?>">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-md text-xs shadow">
                        Service
                    </button>
                </form>

                <!-- Delete Button -->
                <?php if ($_SESSION['role'] === 'Admin'): ?>
                <form method="POST" class="inline-block">
                    <input type="hidden" name="action" value="delete_bin">
                    <input type="hidden" name="bin_id" value="<?= $b['bin_id'] ?>">
                    <button class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md text-xs shadow">
                        Delete
                    </button>
                </form>
                <?php endif; ?>

            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

        </div>

    </main>
</div>

<!-- MAP SCRIPT -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
let map = L.map("map").setView([12.9716, 77.5946], 12);
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png").addTo(map);

let marker;

map.on("click", function(e) {
    let lat = e.latlng.lat.toFixed(8);
    let lng = e.latlng.lng.toFixed(8);

    document.getElementById("lat").value = lat;
    document.getElementById("lng").value = lng;

    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng]).addTo(map);
});
</script>

</body>
</html>
