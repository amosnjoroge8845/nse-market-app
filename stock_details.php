
<?php
require_once __DIR__ . "/config/database.php";

$stockId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$stockId || $stockId < 1) {
    http_response_code(400);
    exit("Invalid stock ID.");
}

$stmt = $conn->prepare("
    SELECT id, symbol, company_name, sector,
           current_price, previous_close, trading_volume
    FROM stocks
    WHERE id = ?
");

$stmt->bind_param("i", $stockId);
$stmt->execute();
$result = $stmt->get_result();
$stock = $result->fetch_assoc();

if (!$stock) {
    http_response_code(404);
    exit("Stock counter not found.");
}

$change = (float) $stock["current_price"]
    - (float) $stock["previous_close"];

$percent = (float) $stock["previous_close"] > 0
    ? ($change / (float) $stock["previous_close"]) * 100
    : 0;

$history = $conn->prepare("
    SELECT price_date, closing_price, trading_volume
    FROM stock_prices
    WHERE stock_id = ?
    ORDER BY price_date ASC
");

$history->bind_param("i", $stockId);
$history->execute();
$historyResult = $history->get_result();

$historyData = [];

while ($point = $historyResult->fetch_assoc()) {
    $historyData[] = [
        "date" => $point["price_date"],
        "price" => (float) $point["closing_price"],
        "volume" => (int) $point["trading_volume"]
    ];
}

function kes($amount) {
    return "KES " . number_format((float) $amount, 2);
}

$pageTitle = $stock["symbol"] . " — Stock Details";
include __DIR__ . "/includes/header.php";
?>

<div class="panel">
    <p class="muted">NSE MARKET / STOCK DETAILS</p>

    <h2>
        <?= htmlspecialchars($stock["company_name"]) ?>
        (<?= htmlspecialchars($stock["symbol"]) ?>)
    </h2>

    <p class="muted">
        Sector: <?= htmlspecialchars($stock["sector"]) ?>
    </p>

    <div class="grid">
        <div class="metric">
            <p>Current recorded price</p>
            <h2><?= kes($stock["current_price"]) ?></h2>
        </div>

        <div class="metric">
            <p>Daily price change</p>
            <h2 class="<?= $change >= 0 ? 'positive' : 'negative' ?>">
                <?= $change >= 0 ? '+' : '' ?>
                <?= number_format($change, 2) ?>
            </h2>
            <span class="muted">
                (<?= number_format($percent, 2) ?>%)
            </span>
        </div>

        <div class="metric">
            <p>Trading volume</p>
            <h2>
                <?= number_format((int) $stock["trading_volume"]) ?>
            </h2>
            <span class="muted">Recorded shares traded</span>
        </div>
    </div>
</div>

<div class="panel">
    <h2>Historical Closing Prices</h2>

    <?php if (count($historyData) > 0): ?>
        <div style="height:320px; position:relative;">
            <canvas id="detailsChart"></canvas>
        </div>
    <?php else: ?>
        <p class="muted">
            No historical price records are available for this counter yet.
        </p>
    <?php endif; ?>
</div>

<p>
    <a class="btn" href="index.php">Back to Market Dashboard</a>
</p>

<?php if (count($historyData) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const historyData = <?= json_encode(
    $historyData,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

new Chart(document.getElementById("detailsChart"), {
    type: "line",
    data: {
        labels: historyData.map(item => item.date),
        datasets: [{
            label: "Closing Price (KES)",
            data: historyData.map(item => item.price),
            borderWidth: 2,
            tension: 0.25,
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: false,
                title: {
                    display: true,
                    text: "Price (KES)"
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . "/includes/footer.php"; ?>
