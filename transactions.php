
<?php
$pageTitle = "Transactions";

require_once __DIR__ . "/config/database.php";

$sql = "
    SELECT
        t.id,
        t.transaction_type,
        t.quantity,
        t.price,
        t.transaction_date,
        s.symbol,
        s.company_name,
        (t.quantity * t.price) AS total_value
    FROM transactions t
    INNER JOIN stocks s ON s.id = t.stock_id
    ORDER BY t.transaction_date DESC, t.id DESC
";

$result = $conn->query($sql);

$summary = $conn->query("
    SELECT
        COUNT(*) AS total_transactions,
        COALESCE(SUM(
            CASE WHEN transaction_type = 'BUY'
                 THEN quantity * price ELSE 0 END
        ), 0) AS total_purchases,
        COALESCE(SUM(
            CASE WHEN transaction_type = 'SELL'
                 THEN quantity * price ELSE 0 END
        ), 0) AS total_sales
    FROM transactions
")->fetch_assoc();

$totalPurchases = (float)$summary['total_purchases'];
$totalSales = (float)$summary['total_sales'];
$netCashFlow = $totalSales - $totalPurchases;

include __DIR__ . "/includes/header.php";
?>

<section class="page-heading">
    <div>
        <h1>Transactions</h1>
        <p>A complete record of purchases and sales.</p>
    </div>
    <span class="status-badge">Transaction ledger</span>
</section>

<section class="stats-grid">
    <div class="stat-card">
        <span>Total Transactions</span>
        <strong><?= (int)$summary['total_transactions'] ?></strong>
    </div>

    <div class="stat-card">
        <span>Total Purchases</span>
        <strong>KES <?= number_format($totalPurchases, 2) ?></strong>
    </div>

    <div class="stat-card">
        <span>Total Sales</span>
        <strong>KES <?= number_format($totalSales, 2) ?></strong>
    </div>

    <div class="stat-card">
        <span>Net Transaction Cash Flow</span>
        <strong class="<?= $netCashFlow >= 0 ? 'positive' : 'negative' ?>">
            KES <?= number_format($netCashFlow, 2) ?>
        </strong>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Transaction Ledger</h2>
            <p>All recorded stock transactions, newest first.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Symbol</th>
                    <th>Company</th>
                    <th>Quantity</th>
                    <th>Price per Share</th>
                    <th>Total Value</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($transaction = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($transaction['transaction_date']) ?></td>

                        <td>
                            <span class="status-badge">
                                <?= htmlspecialchars($transaction['transaction_type']) ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= htmlspecialchars($transaction['symbol']) ?></strong>
                        </td>

                        <td><?= htmlspecialchars($transaction['company_name']) ?></td>

                        <td><?= number_format((int)$transaction['quantity']) ?></td>

                        <td>
                            KES <?= number_format((float)$transaction['price'], 2) ?>
                        </td>

                        <td>
                            <strong>
                                KES <?= number_format((float)$transaction['total_value'], 2) ?>
                            </strong>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No transactions recorded yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
