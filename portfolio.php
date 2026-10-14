
<?php
$pageTitle = "Portfolio";

require_once __DIR__ . "/config/database.php";

$stocksResult = $conn->query("
    SELECT id, symbol, company_name, current_price
    FROM stocks
    ORDER BY symbol ASC
");

$portfolio = [];
$totalHoldingsValue = 0;
$totalCostOfHoldings = 0;
$totalRealizedProfitLoss = 0;

if (!$stocksResult) {
    exit("Unable to load stocks. Please check your database.");
}

// Initialize each stock's portfolio calculations.
while ($stock = $stocksResult->fetch_assoc()) {
    $id = (int)$stock["id"];

    $stock["shares_held"] = 0;
    $stock["average_cost"] = 0;
    $stock["holding_cost"] = 0;
    $stock["market_value"] = 0;
    $stock["unrealized_pl"] = 0;
    $stock["realized_pl"] = 0;

    $portfolio[$id] = $stock;
}

// Process transactions in chronological order.
$transactionsResult = $conn->query("
    SELECT stock_id, transaction_type, quantity, price,
           transaction_date, id
    FROM transactions
    ORDER BY transaction_date ASC, id ASC
");

if (!$transactionsResult) {
    exit("Unable to load transactions. Please check your database.");
}

$warnings = [];

while ($transaction = $transactionsResult->fetch_assoc()) {
    $stockId = (int)$transaction["stock_id"];

    if (!isset($portfolio[$stockId])) {
        continue;
    }

    $quantity = (int)$transaction["quantity"];
    $price = (float)$transaction["price"];
    $type = $transaction["transaction_type"];

    $held = $portfolio[$stockId]["shares_held"];
    $cost = $portfolio[$stockId]["holding_cost"];

    if ($type === "BUY") {
        // Add the purchase to the existing investment cost.
        $portfolio[$stockId]["shares_held"] += $quantity;
        $portfolio[$stockId]["holding_cost"] += $quantity * $price;

    } elseif ($type === "SELL") {
        // A sale must follow enough purchases in date order.
        if ($quantity > $held) {
            $warnings[] =
                $portfolio[$stockId]["symbol"] .
                " has a sale dated before sufficient shares were available.";
            continue;
        }

        // Calculate the average cost immediately before the sale.
        $averageCost = $held > 0 ? $cost / $held : 0;

        // Realized profit/loss on the shares sold.
        $portfolio[$stockId]["realized_pl"] +=
            ($quantity * $price) - ($quantity * $averageCost);

        // Remove the cost basis of the shares sold.
        $portfolio[$stockId]["shares_held"] -= $quantity;
        $portfolio[$stockId]["holding_cost"] -=
            $quantity * $averageCost;

        // Avoid tiny floating-point rounding leftovers.
        if ($portfolio[$stockId]["shares_held"] === 0) {
            $portfolio[$stockId]["holding_cost"] = 0;
        }
    }
}

// Calculate current market value and unrealized P/L.
foreach ($portfolio as &$stock) {
    $held = $stock["shares_held"];
    $currentPrice = (float)$stock["current_price"];

    $stock["average_cost"] = $held > 0
        ? $stock["holding_cost"] / $held
        : 0;

    $stock["market_value"] = $held * $currentPrice;

    $stock["unrealized_pl"] =
        $stock["market_value"] - $stock["holding_cost"];

    $totalHoldingsValue += $stock["market_value"];
    $totalCostOfHoldings += $stock["holding_cost"];
    $totalRealizedProfitLoss += $stock["realized_pl"];
}
unset($stock);

include __DIR__ . "/includes/header.php";
?>

<section class="page-heading">
    <div>
        <h1>Portfolio</h1>
        <p>Review shareholdings and estimated investment performance.</p>
    </div>
    <span class="status-badge">Demo market data</span>
</section>

<?php if (!empty($warnings)): ?>
<section class="panel">
    <h2>Transaction review required</h2>
    <?php foreach ($warnings as $warning): ?>
        <p class="negative"><?= htmlspecialchars($warning) ?></p>
    <?php endforeach; ?>
    <p class="muted">
        Check transaction dates and quantities before relying on these figures.
    </p>
</section>
<?php endif; ?>

<section class="stats-grid">
    <div class="stat-card">
        <span>Current Holdings Value</span>
        <strong>KES <?= number_format($totalHoldingsValue, 2) ?></strong>
    </div>

    <div class="stat-card">
        <span>Remaining Investment Cost</span>
        <strong>KES <?= number_format($totalCostOfHoldings, 2) ?></strong>
    </div>

    <div class="stat-card">
        <span>Unrealized Profit / Loss</span>
        <strong class="<?= ($totalHoldingsValue - $totalCostOfHoldings) >= 0 ? 'positive' : 'negative' ?>">
            KES <?= number_format(
                $totalHoldingsValue - $totalCostOfHoldings, 2
            ) ?>
        </strong>
    </div>

    <div class="stat-card">
        <span>Realized Profit / Loss</span>
        <strong class="<?= $totalRealizedProfitLoss >= 0 ? 'positive' : 'negative' ?>">
            KES <?= number_format($totalRealizedProfitLoss, 2) ?>
        </strong>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>My Holdings</h2>
            <p>Shares remaining after recorded purchases and sales.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Company</th>
                    <th>Shares Held</th>
                    <th>Average Purchase Cost</th>
                    <th>Market Price</th>
                    <th>Market Value</th>
                    <th>Unrealized P/L</th>
                    <th>Realized P/L</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($portfolio as $stock): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($stock["symbol"]) ?></strong></td>
                    <td><?= htmlspecialchars($stock["company_name"]) ?></td>
                    <td><?= number_format($stock["shares_held"]) ?></td>
                    <td>KES <?= number_format($stock["average_cost"], 2) ?></td>
                    <td>KES <?= number_format((float)$stock["current_price"], 2) ?></td>
                    <td>KES <?= number_format($stock["market_value"], 2) ?></td>
                    <td class="<?= $stock["unrealized_pl"] >= 0 ? 'positive' : 'negative' ?>">
                        KES <?= number_format($stock["unrealized_pl"], 2) ?>
                    </td>
                    <td class="<?= $stock["realized_pl"] >= 0 ? 'positive' : 'negative' ?>">
                        KES <?= number_format($stock["realized_pl"], 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($portfolio)): ?>
                <tr>
                    <td colspan="8">No registered stock counters found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="muted">
        Profit/loss uses the weighted-average cost method and excludes
        brokerage fees, taxes and other charges. Market prices are
        database values and may be illustrative rather than live NSE prices.
    </p>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
