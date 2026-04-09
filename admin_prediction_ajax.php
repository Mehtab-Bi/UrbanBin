<?php
header('Content-Type: application/json');

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$type = $_GET['type'] ?? 'trends';

/* ============================================================
   FETCH DATA FOR TRENDS CHARTS
============================================================ */
if ($type === "trends") {

    /* ----------------------------
       1. Fill Level Trend
    ---------------------------- */
    $fill_result = $conn->query("SELECT bin_id, capacity_percent FROM bins ORDER BY bin_id ASC");
    $fill_trend = [];
    while ($row = $fill_result->fetch_assoc()) {
        $fill_trend[] = $row;
    }

    /* ----------------------------
       2. Gas Trend (CO2 & NH3)
    ---------------------------- */
    $gas_result = $conn->query("SELECT bin_id, co2, ammonia FROM bins ORDER BY bin_id ASC");
    $gas_trend = [];
    while ($row = $gas_result->fetch_assoc()) {
        $gas_trend[] = $row;
    }

    /* ----------------------------
       3. Daily Report Counts
    ---------------------------- */
    $daily_rep_res = $conn->query("
        SELECT DATE(reported_at) AS d, COUNT(*) AS c
        FROM reports
        GROUP BY DATE(reported_at)
        ORDER BY d ASC
    ");

    $daily_reports = [];
    while ($row = $daily_rep_res->fetch_assoc()) {
        $daily_reports[] = $row;
    }

    /* ----------------------------
       SEND JSON RESPONSE
    ---------------------------- */
    echo json_encode([
        "fill_trend"      => $fill_trend,
        "gas_trend"       => $gas_trend,
        "daily_reports"   => $daily_reports
    ]);

    exit;
}
/* ============================================================
   PREDICTION ENGINE – For Prediction Tab
============================================================ */
if ($type === "prediction") {

    // Fetch all bins
    $bins_res = $conn->query("
        SELECT bin_id, location, capacity_percent, last_capacity_percent, co2, ammonia
        FROM bins ORDER BY bin_id ASC
    ");

    $predictions = [];
    $risk_low = 0;
    $risk_med = 0;
    $risk_high = 0;

    while ($b = $bins_res->fetch_assoc()) {

        $bin_id   = $b['bin_id'];
        $loc      = $b['location'];
        $cur      = (int)$b['capacity_percent'];
        $last     = (int)$b['last_capacity_percent'];
        $co2      = (float)$b['co2'];
        $nh3      = (float)$b['ammonia'];

        /* ------------------------------------------------------------
           TREND SLOPE (Recent change)
        ------------------------------------------------------------ */
        $slope = $cur - $last;  
        // positive = rising fill level
        // negative = being emptied

        /* ------------------------------------------------------------
           GAS INFLUENCE FACTOR (affects citizen usage)
        ------------------------------------------------------------ */
        $gas_factor = 1.0;

        // Increase usage probability when hygiene is bad  
        if ($nh3 > 3.0 || $co2 > 2500) {
            $gas_factor = 1.30;    // 30% faster fill-up
        } elseif ($nh3 > 1.5 || $co2 > 1500) {
            $gas_factor = 1.20;
        } elseif ($co2 > 800) {
            $gas_factor = 1.10;
        }

        /* ------------------------------------------------------------
           SHORT-TERM PREDICTION (6 hours)
           Weighted Hybrid Model:
           - 65% based on recent slope
           - 25% based on current fullness trend
           - 10% gas influence
        ------------------------------------------------------------ */

        // slope * hours * influence
        $pred_6h = $cur + (($slope * 6) * 0.65) * $gas_factor;

        // extra fullness influence
        $pred_6h += ($cur * 0.25 * 0.06) * 6;

        // gas weight
        $pred_6h += ($gas_factor - 1) * 5;  

        // limit range
        $pred_6h = max(0, min(100, round($pred_6h)));


        /* ------------------------------------------------------------
           MID-TERM PREDICTION (12 hours)
        ------------------------------------------------------------ */
        $pred_12h = $cur + (($slope * 12) * 0.65) * $gas_factor;
        $pred_12h += ($cur * 0.25 * 0.06) * 12;
        $pred_12h += ($gas_factor - 1) * 10;

        $pred_12h = max(0, min(100, round($pred_12h)));


        /* ------------------------------------------------------------
           RISK CLASSIFICATION
        ------------------------------------------------------------ */
        $risk = "Low";

        if ($pred_12h >= 90 || $nh3 > 3 || $co2 > 2500) {
            $risk = "High";
            $risk_high++;
        }
        elseif ($pred_12h >= 75 || $nh3 > 1.5 || $co2 > 1500) {
            $risk = "Medium";
            $risk_med++;
        }
        else {
            $risk = "Low";
            $risk_low++;
        }


        /* ------------------------------------------------------------
           ADD TO OUTPUT LIST
        ------------------------------------------------------------ */
        $predictions[] = [
            "bin_id"   => $bin_id,
            "location" => $loc,
            "current"  => $cur,
            "pred_6h"  => $pred_6h,
            "pred_12h" => $pred_12h,
            "risk"     => $risk
        ];
    }

    echo json_encode([
        "predictions" => $predictions,
        "risk_counts" => [
            "low"    => $risk_low,
            "medium" => $risk_med,
            "high"   => $risk_high
        ]
    ]);

    exit;
}
/* ============================
      HEATMAP DATA
   ============================ */
/* ============================
      HEATMAP DATA (FIXED)
   ============================ */
if ($type === "heatmap") {

    $heatmapData = [];

    $res = $conn->query("
        SELECT 
            bin_id,
            latitude AS lat,
            longitude AS lng,
            hygiene_status,
            capacity_percent
        FROM bins
        WHERE 
            latitude IS NOT NULL 
            AND longitude IS NOT NULL
    ");

    while ($row = $res->fetch_assoc()) {

        $sev = 1;

        if ($row['hygiene_status'] === 'Service Soon' ||
            $row['hygiene_status'] === 'Ventilation Suggested') {
            $sev = 2;
        } 
        else if ($row['hygiene_status'] === 'Fullness Alert' ||
                 $row['hygiene_status'] === 'Hygiene Service Recommended' ||
                 $row['hygiene_status'] === 'Immediate Hygiene Alert') {
            $sev = 3;
        }

        $heatmapData[] = [
            "bin_id"   => $row["bin_id"],
            "lat"      => floatval($row["lat"]),
            "lng"      => floatval($row["lng"]),
            "severity" => $sev,
            "capacity" => $row["capacity_percent"],
            "status"   => $row["hygiene_status"]
        ];
    }

    echo json_encode(["heatmap" => $heatmapData]);
    exit;
}

/* ============================
         ALERTS DATA
   ============================ */
if ($type === "alerts") {

    $alertList = [];

    $res = $conn->query("
        SELECT bin_id, hygiene_status, capacity_percent, co2, ammonia, last_updated 
        FROM bins
        WHERE hygiene_status NOT IN ('Normal')
        ORDER BY last_updated DESC
    ");

    while ($row = $res->fetch_assoc()) {

        $severity = "Medium";

        if ($row["hygiene_status"] === "Fullness Alert" ||
            $row["hygiene_status"] === "Hygiene Service Recommended") {
            $severity = "High";
        }

        if ($row["hygiene_status"] === "Immediate Hygiene Alert") {
            $severity = "Critical";
        }

        $alertList[] = [
            "bin_id"   => $row["bin_id"],
            "status"   => $row["hygiene_status"],
            "severity" => $severity,
            "capacity" => $row["capacity_percent"],
            "co2"      => $row["co2"],
            "ammonia"  => $row["ammonia"],
            "time"     => $row["last_updated"]
        ];
    }

    echo json_encode(["alerts" => $alertList]);
    exit;
}

echo json_encode(["error" => "Invalid request"]);
$conn->close();
?>
