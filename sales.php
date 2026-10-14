
<?php
$pageTitle = "Sales";

require_once __DIR__ . "/config/database.php";

$stocks = $conn->query(
    "SELECT id, symbol, company_name
     FROM stocks
     ORDER BY symbol ASC"
);

$sales = $conn->query(
    "SELECT t.id, s.symbol, s.company_name,
            t.quantity, t.price, t.transaction_date,
            (t.quantity * t.price) AS total_value
     FROM transactions t
     INNER JOIN stocks s ON s.id = t.stock_id
     WHERE t.transaction_type = 'SELL'
     ORDER BY t.transaction_date DESC, t.id DESC"
);

$summary = $conn->query(
    "SELECT COUNT(*) AS total_transactions,
            COALESCE(SUM(quantity), 0) AS total_shares,
            COALESCE(SUM(quantity * price), 0) AS total_received
     FROM transactions
     WHERE transaction_type = 'SELL'"
)->fetch_assoc();

include __DIR__ . "/includes/header.php";
?>

<section class="page-heading">
    <div>
        <h1>Sales</h1>
        <p>Record and review shares sold by the investor.</p>
    </div>
    <span class="status-badge">Demo market data</span>
</section>

<section class="content-grid">
    <div class="panel">
        <div class="panel-heading">
            <div>
                <h2>Record a Sale</h2>
                <p>Enter the selling transaction details.</p>
            </div>
        </div>

        <form action="actions/add_sale.php" method="POST"
              class="form-stack">

            <label for="stock_id">Stock Counter</label>
            <select name="stock_id" id="stock_id" required>
                <option value="">Select a company</option>

                <?php if ($stocks): ?>
                    <?php while ($stock = $stocks->fetch_assoc()): ?>
                        <option value="<?= (int)$stock['id'] ?>">
                            <?= htmlspecialchars($stock['symbol']) ?>
                            — <?= htmlspecialchars($stock['company_name']) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>

            <label for="quantity">Number of Shares Sold</label>
            <input type="number" name="quantity" id="quantity"
                   min="1" step="1" required>

            <label for="price">Selling Price per Share (KES)</label>
            <input type="number" name="price" id="price"
                   min="0.01" step="0.01" required>

            <label for="transaction_date">Sale Date</label>
            <input type="date" name="transaction_date"
                   id="transaction_date"
                   value="<?= date('Y-m-d') ?>" required>

            <button type="submit">Record Sale</button>
        </form>
    </div>

    <div class="panel">
        <h2>Sales Summary</h2>
        <p class="muted">
            Total received is the number of shares sold multiplied by
            the selling price per share.
        </p>

        <div class="stat-card">
            <span>Total Sale Transactions</span>
            <strong><?= (int)$summary['total_transactions'] ?></strong>
        </div>

        <div class="stat-card">
            <span>Total Shares Sold</span>
            <strong><?= number_format((int)$summary['total_shares']) ?></strong>
        </div>

        <div class="stat-card">
            <span>Total Sales Value</span>
            <strong>
                KES <?= number_format((float)$summary['total_received'], 2) ?>
            </strong>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Sales History</h2>
            <p>Previously recorded sales transactions.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Symbol</th>
                    <th>Company</th>
                    <th>Quantity Sold</th>
                    <th>Selling Price</th>
                    <th>Total Received</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($sales && $sales->num_rows > 0): ?>
                <?php while ($sale = $sales->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($sale['transaction_date']) ?></td>
                        <td><strong><?= htmlspecialchars($sale['symbol']) ?></strong></td>
                        <td><?= htmlspecialchars($sale['company_name']) ?></td>
                        <td><?= number_format((int)$sale['quantity']) ?></td>
                        <td>KES <?= number_format((float)$sale['price'], 2) ?></td>
                        <td><strong>KES <?= number_format((float)$sale['total_value'], 2) ?></strong></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No sales transactions recorded yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
