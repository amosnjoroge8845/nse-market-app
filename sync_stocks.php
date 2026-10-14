
<?php
require_once __DIR__ . '/config/rapidapi.php';
require_once __DIR__ . '/config/database.php';



// Request stock data from RapidAPI
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://' . RAPIDAPI_HOST . '/stocks',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'X-RapidAPI-Host: ' . RAPIDAPI_HOST,
        'X-RapidAPI-Key: ' . RAPIDAPI_KEY
    ],
]);

$response = curl_exec($curl);
$error = curl_error($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($error) {
    exit("API connection failed: " . $error);
}

if ($status !== 200) {
    exit("API returned HTTP status: " . $status);
}

$result = json_decode($response, true);

if (
    !is_array($result) ||
    ($result['success'] ?? false) !== true ||
    !isset($result['data']) ||
    !is_array($result['data'])
) {
    exit("The API response was not in the expected format.");
}

// Prepare an insert/update query.
// Existing stock IDs are preserved when their symbols match.
$sql = "INSERT INTO stocks
        (symbol, company_name, sector, current_price,
         previous_close, trading_volume)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            company_name = VALUES(company_name),
            sector = VALUES(sector),
            current_price = VALUES(current_price),
            previous_close = VALUES(previous_close),
            trading_volume = VALUES(trading_volume)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit("Database query preparation failed.");
}

$imported = 0;
$skipped = 0;

foreach ($result['data'] as $stock) {
    $symbol = trim($stock['ticker'] ?? '');
    $name = trim($stock['name'] ?? '');
    $sector = trim($stock['sector'] ?? '');
    $price = $stock['price'] ?? null;
    $change = $stock['change'] ?? null;
    $volume = $stock['volume'] ?? null;

    if (
        $symbol === '' ||
        $name === '' ||
        !is_numeric($price) ||
        !is_numeric($change) ||
        !is_numeric($volume)
    ) {
        $skipped++;
        continue;
    }

    $price = (float) $price;
    $changePercent = (float) $change;
    $volume = (int) $volume;

    // API change is a percentage. Estimate previous close.
    $previousClose = abs(100 + $changePercent) > 0.000001
        ? $price / (1 + ($changePercent / 100))
        : $price;

    $stmt->bind_param(
        "sssddi",
        $symbol,
        $name,
        $sector,
        $price,
        $previousClose,
        $volume
    );

    if (!$stmt->execute()) {
        $stmt->close();
        exit("Database import failed. Check your stocks table and indexes.");
    }

    $imported++;
}

$stmt->close();

echo "Stock sync completed.\n";
echo "Records processed: " . $imported . "\n";
echo "Records skipped: " . $skipped . "\n";
echo "API last updated: " .
    ($result['meta']['lastUpdated'] ?? 'Not provided') . "\n";
echo "\nNote: Previous closing prices are estimated from the API's percentage change.";
