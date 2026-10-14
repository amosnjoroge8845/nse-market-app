
<?php
require_once __DIR__ . "/config/database.php";

$result = $conn->query("
    SELECT symbol, current_price, previous_close,
           ((current_price - previous_close) / previous_close) * 100 AS change_percent
    FROM stocks
    WHERE previous_close > 0
      AND current_price < previous_close
    ORDER BY change_percent ASC
    LIMIT 5
");

if (!$result) {
    die("Query failed: " . $conn->error);
}

echo "<h2>Top Losers Test</h2>";
echo "<p>Rows returned: " . $result->num_rows . "</p>";

while ($stock = $result->fetch_assoc()) {
    echo htmlspecialchars($stock["symbol"]) . " — KES "
       . number_format((float) $stock["current_price"], 2)
       . " — " . number_format((float) $stock["change_percent"], 2)
       . "%<br>";
}
