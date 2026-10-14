
<?php
require_once __DIR__ . '/config/rapidapi.php';

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
    exit('Connection error: ' . htmlspecialchars($error));
}

echo '<h2>API HTTP Status: ' . $status . '</h2>';

$data = json_decode($response, true);

if (isset($data['success']) && $data['success'] === true) {
    echo '<p>Success! Stock data received.</p>';
    echo '<pre>';
    echo htmlspecialchars(
        json_encode($data['data'][0] ?? [], JSON_PRETTY_PRINT)
    );
    echo '</pre>';
} else {
    echo '<p>The API did not return the expected stock data.</p>';
    echo '<pre>' . htmlspecialchars($response) . '</pre>';
}
