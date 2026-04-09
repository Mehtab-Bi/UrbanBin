<?php
// Start the session to manage user authentication state
session_start();

// Check if the user is logged in, if not, redirect to the login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Get the user's role, defaulting to 'Citizen' if somehow not set
$user_role = $_SESSION['role'] ?? 'Citizen'; 
$is_privileged_user = in_array($user_role, ['Admin', 'Operator']); // Check if user can manage bins

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

/**
 * Helper function to format hours into "Xh Ym" or handle alerts/no data.
 */
function formatTime($hours, $capacity) {
    
    // --- STEP 1: Handle IMMEDIATE ALERT (CRITICAL CAPACITY) ---
    if ($capacity >= 95) {
        return 'IMMEDIATE ALERT';
    }

    // --- STEP 2: Handle No Rate Data/Invalid Time ---\
    // Interprets 999.0 (the temporary placeholder) as No Rate Data
    if ($hours >= 999.0 || $hours <= 0) {
        return 'No Rate Data';
    }
    
    // --- STEP 3: Handle Near-Zero Time ---
    if ($hours <= 0.1) {
        return 'Less than 6m';
    }
    
    // --- STEP 4: Convert Hours to 'Xh Ym' Format ---
    $total_minutes = round($hours * 60);
    $h = floor($total_minutes / 60);
    $m = $total_minutes % 60;
    
    $time_string = "";
    if ($h > 0) {
        $time_string .= "{$h}h ";
    }
    if ($m > 0 || $h == 0) { // Show minutes if hours is 0 or minutes > 0
        $time_string .= "{$m}m";
    }
    
    return trim($time_string);
}

// Data fetching setup
$bins = [];
$map_markers = [];
$message = '';
$message_type = ''; // success or error

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

if (!$conn->connect_error) {
    // FIX: The column 'time_to_fill_hours' is missing in the database structure.
    // We temporarily alias it to 999.0 to prevent the crash and display 'No Rate Data' 
    // until the column is correctly added to the database schema.
    $sql = "SELECT bin_id, location, latitude, longitude, capacity_percent, last_updated, hygiene_status, 999.0 AS time_to_fill_hours FROM bins ORDER BY bin_id ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Apply the helper function to format the time/status
            $row['time_to_fill_formatted'] = formatTime((float)$row['time_to_fill_hours'], (int)$row['capacity_percent']);
            $bins[] = $row;
            
            // Prepare map marker data for JavaScript
            $map_markers[] = [
                'id' => $row['bin_id'],
                'lat' => (float)$row['latitude'],
                'lng' => (float)$row['longitude'],
                'location' => $row['location'],
                'capacity' => (int)$row['capacity_percent'],
                'status' => $row['hygiene_status'],
                'time_to_fill' => $row['time_to_fill_formatted']
            ];
        }
    }
    
    $conn->close();
} else {
    $message = "Database connection failed: " . $conn->connect_error . ". Bin data cannot be loaded.";
    $message_type = 'error';
}

$current_page = 'dashboard';
// The JSON-encoded bin data for JavaScript consumption
$markers_json = json_encode($map_markers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Hygiene Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .nav-link { padding: 0.5rem 1rem; border-radius: 0.5rem; }
        .nav-link.active { background-color: #4f46e5; color: white; }
        .status-pill { padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .map-container { height: 400px; width: 100%; border-radius: 0.75rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    </style>
    <!-- Placeholder for Google Maps API key - MUST be replaced with a real key -->
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY_HERE&callback=initMap"></script>
</head>
<body class="min-h-screen">
    <!-- Navigation Bar -->
    <header class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <i data-lucide="droplets" class="w-6 h-6 text-indigo-600 mr-2"></i>
                    <span class="text-xl font-bold text-gray-900">HygieneNet</span>
                </div>
                <nav class="flex space-x-4">
                    <a href="index.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Dashboard</a>
                    <a href="customer_report.php" class="nav-link <?php echo $current_page == 'report' ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Report</a>
                    <a href="awareness.php" class="nav-link <?php echo $current_page == 'awareness' ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Awareness</a>
                    <?php if ($user_role === 'Citizen'): ?>
                        <a href="citizen_dashboard.php" class="nav-link <?php echo $current_page == 'citizen' ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">Citizen Portal</a>
                    <?php endif; ?>
                    <a href="contact.php" class="nav-link text-gray-600 hover:bg-gray-100">Contact</a>
                    <a href="logout.php" class="nav-link text-red-600 hover:bg-red-100 font-semibold">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-6">Smart Bin Management Dashboard</h1>

        <?php if (!empty($message)): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <span class="font-medium">Error:</span> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Map and Route Planning Section (Visible to Admin/Operator) -->
        <?php if ($is_privileged_user): ?>
            <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
                <h2 class="text-2xl font-bold text-indigo-700 mb-4 flex items-center">
                    <i data-lucide="map-pin" class="w-6 h-6 mr-2"></i> Real-Time Status Map & Routing
                </h2>
                
                <div class="map-container mb-4" id="map"></div>
                
                <div class="flex items-center space-x-4">
                    <button id="generate-route-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                        <i data-lucide="route" class="w-5 h-5 inline mr-2"></i> Generate Collection Route
                    </button>
                    
                    <div id="route-message-box" class="flex-grow">
                        <!-- Route summary message will be injected here -->
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Bin Status List -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                 <i data-lucide="list-checks" class="w-6 h-6 mr-2"></i> Bin Status Overview
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Fill (%)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Hygiene Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Est. Time to Fill</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php if (empty($bins)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500 italic">No smart bins currently registered in the system.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bins as $bin): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-indigo-600"><?php echo htmlspecialchars($bin['bin_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($bin['location']); ?></td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold <?php echo $bin['capacity_percent'] >= 95 ? 'text-red-600' : ($bin['capacity_percent'] >= 75 ? 'text-yellow-600' : 'text-green-600'); ?>">
                                        <?php echo htmlspecialchars($bin['capacity_percent']); ?>%
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php
                                            $hygiene_status = htmlspecialchars($bin['hygiene_status']);
                                            $pill_class = 'bg-gray-100 text-gray-800'; // Default
                                            if (strpos($hygiene_status, 'Alert') !== false) {
                                                $pill_class = 'bg-red-100 text-red-800';
                                            } elseif (strpos($hygiene_status, 'Recommended') !== false) {
                                                $pill_class = 'bg-yellow-100 text-yellow-800';
                                            } elseif (strpos($hygiene_status, 'Suggested') !== false) {
                                                $pill_class = 'bg-indigo-100 text-indigo-800';
                                            } elseif ($hygiene_status === 'Normal') {
                                                $pill_class = 'bg-green-100 text-green-800';
                                            }
                                        ?>
                                        <span class="status-pill <?php echo $pill_class; ?>">
                                            <?php echo $hygiene_status; ?>
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium text-gray-700">
                                        <?php echo htmlspecialchars($bin['time_to_fill_formatted']); ?>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        <?php echo htmlspecialchars($bin['last_updated']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
    
    <script>
        // Use PHP data in JavaScript
        const mapMarkersData = <?php echo $markers_json; ?>;
        const isPrivileged = <?php echo json_encode($is_privileged_user); ?>;
        
        // Default Depot/Starting Location (Central Point for Routing)
        const depotLocation = { lat: 12.9716, lng: 77.5946 }; // Example: Central location in Bengaluru

        let map;
        let markers = [];
        let directionsService;
        let directionsRenderer;

        // Initialize Google Map
        function initMap() {
            // Only initialize map components if the user is privileged (Admin/Operator)
            if (!isPrivileged) return;

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 12,
                center: depotLocation,
                mapId: 'DEMO_MAP_ID' // Use a simple map ID
            });
            
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({ map: map, suppressMarkers: true });

            // Add the Depot marker (start point for routes)
            new google.maps.Marker({
                position: depotLocation,
                map: map,
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="%234f46e5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.7C17.3 17 20 13 20 10A8 8 0 0 0 12 2a8 8 0 0 0-8 8c0 3 2.7 7 8 11.7z"/><circle cx="12" cy="10" r="3"/></svg>',
                    scaledSize: new google.maps.Size(30, 30),
                    anchor: new google.maps.Point(15, 30),
                },
                title: 'Vehicle Depot / Start Point',
            });

            // Add all bin markers
            mapMarkersData.forEach(data => {
                addBinMarker(data);
            });
            
            // Event listener for route generation
            document.getElementById('generate-route-btn').addEventListener('click', generateOptimizedRoute);
        }
        
        // Helper to add a single marker to the map
        function addBinMarker(data) {
            const isAlert = data.status.includes('Alert');
            const color = isAlert ? 'red' : (data.capacity >= 75 ? 'orange' : 'green');
            
            const marker = new google.maps.Marker({
                position: { lat: data.lat, lng: data.lng },
                map: map,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 10,
                    fillColor: color,
                    fillOpacity: 0.9,
                    strokeWeight: 2,
                    strokeColor: 'white'
                },
                title: `${data.location} (${data.capacity}%)`,
                binData: data // Attach the data object
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div class="p-2">
                        <h3 class="font-bold text-lg">${data.location} (ID: ${data.id})</h3>
                        <p><strong>Fill:</strong> ${data.capacity}%</p>
                        <p><strong>Hygiene:</strong> <span class="text-${color}-600">${data.status}</span></p>
                        <p><strong>Est. Fill:</strong> ${data.time_to_fill}</p>
                    </div>
                `
            });

            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });

            markers.push(marker);
        }

        // Function to generate the optimized route
        function generateOptimizedRoute() {
            // 1. Filter for bins that require collection/attention (Alert or Service Soon)
            const collectionBins = mapMarkersData.filter(data => 
                data.capacity >= 75 || data.status.includes('Alert') || data.status.includes('Recommended')
            );

            if (collectionBins.length === 0) {
                directionsRenderer.setDirections({ routes: [] }); // Clear previous route
                showRouteMessage(0, 0, "No bins require immediate attention or collection service.");
                return;
            }

            // 2. Prepare waypoints
            const waypoints = collectionBins.map(bin => ({
                location: new google.maps.LatLng(bin.lat, bin.lng),
                stopover: true 
            }));

            // Google Maps Directions API has a limit of 23 waypoints + origin + destination (25 total).
            // We slice the array to respect the limit and handle large datasets gracefully.
            const maxWaypoints = 23;
            const routeWaypoints = waypoints.slice(0, maxWaypoints);
            
            const request = {
                origin: depotLocation,
                destination: depotLocation, // End back at the depot for a full loop
                waypoints: routeWaypoints,
                travelMode: 'DRIVING',
                optimizeWaypoints: true // Crucial for getting the best route order
            };

            // 3. Request the route
            directionsService.route(request, (response, status) => {
                if (status === 'OK') {
                    directionsRenderer.setDirections(response);
                    
                    // Calculate total distance
                    let totalDistanceMeters = 0;
                    const route = response.routes[0];
                    for (let i = 0; i < route.legs.length; i++) {
                        totalDistanceMeters += route.legs[i].distance.value;
                    }
                    const totalDistanceKm = totalDistanceMeters / 1000;

                    showRouteMessage(collectionBins.length, totalDistanceKm.toFixed(2));
                } else if (status === 'ZERO_RESULTS') {
                    showRouteMessage(0, 0, "Could not find a driving route between the selected bins.");
                } else {
                    showRouteMessage(0, 0, `Route generation failed: ${status}.`);
                    console.error('Directions request failed due to ' + status);
                }
            });
        }

        // Function to display the route summary
        function showRouteMessage(count, distance, customMessage = null) {
            const messageContainer = document.getElementById('route-message-box');
            let content;

            if (customMessage) {
                 content = `
                    <div class="p-4 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 rounded-lg">
                        <p class="font-bold">Notice:</p>
                        <p class="text-sm">${customMessage}</p>
                    </div>
                `;
            } else {
                content = `
                    <div class="p-4 bg-indigo-50 border-l-4 border-indigo-500 text-indigo-800 rounded-lg">
                        <p class="font-bold">Route Generated Successfully!</p>
                        <p class="text-sm">
                            ${count} bins require collection. 
                            Total Estimated Distance: <strong class="text-indigo-900">${distance} km</strong>.
                        </p>
                    </div>
                `;
            }
            messageContainer.innerHTML = content;
        }

        // Initialize the map on load and icons
        window.onload = function() {
            initMap();
            // Re-run Lucide creation to ensure icons render
            lucide.createIcons(); 
        };

    </script>
</body>
</html>
