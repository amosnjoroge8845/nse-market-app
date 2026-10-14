
<?php
$pageTitle = "Stock Counters";

require_once __DIR__ . "/config/database.php";
include __DIR__ . "/includes/header.php";

$sql = "SELECT id, symbol, company_name, sector,
               current_price, previous_close, trading_volume
        FROM stocks
        ORDER BY company_name ASC";

$result = $conn->query($sql);

function formatKes($amount) {
    return "KES " . number_format((float)$amount, 2);
}
?>

<section class="page-heading">
    <div>
        <h1>Stock Counters</h1>
        <p>Monitor registered companies and their market prices.</p>
    </div>
    <span class="status-badge">Demo market data</span>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Listed Counters</h2>
            <p>Companies currently registered in the application.</p>
        </div>
        <span class="muted">
            <?= $result ? $result->num_rows : 0 ?> counters
        </span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Company</th>
                    <th>Sector</th>
                    <th>Current Price</th>
                    <th>Previous Close</th>
                    <th>Daily Change</th>
                    <th>Trading Volume</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($stock = $result->fetch_assoc()): ?>
                    <?php
                    $change = (float)$stock["current_price"]
                            - (float)$stock["previous_close"];

                    $changePercent = (float)$stock["previous_close"] != 0
                        ? ($change / (float)$stock["previous_close"]) * 100
                        : 0;
                    ?>

                    <tr>
                        <td><strong>
                            <?= htmlspecialchars($stock["symbol"]) ?>
                        </strong></td>

                        <td>
                            <?= htmlspecialchars($stock["company_name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($stock["sector"] ?? "N/A") ?>
                        </td>

                        <td>
                            <?= formatKes($stock["current_price"]) ?>
                        </td>

                        <td>
                            <?= formatKes($stock["previous_close"]) ?>
                        </td>

                        <td class="<?= $change > 0 ? 'positive' : ($change < 0 ? 'negative' : '') ?>">
                            <?= $change > 0 ? '+' : '' ?>
                            <?= number_format($change, 2) ?>
                            (<?= number_format($changePercent, 2) ?>%)
                        </td>

                        <td>
                            <?= number_format((int)$stock["trading_volume"]) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        No stock counters found. Check your database records.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
