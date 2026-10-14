
<?php
$pageTitle = "Purchases";

require_once __DIR__ . "/config/database.php";

$stocks = $conn->query(
    "SELECT id, symbol, company_name
     FROM stocks
     ORDER BY symbol ASC"
);

$purchases = $conn->query(
    "SELECT t.id, s.symbol, s.company_name,
            t.quantity, t.price, t.transaction_date,
            (t.quantity * t.price) AS total_cost
     FROM transactions t
     INNER JOIN stocks s ON s.id = t.stock_id
     WHERE t.transaction_type = 'BUY'
     ORDER BY t.transaction_date DESC, t.id DESC"
);

$summary = $conn->query(
    "SELECT COUNT(*) AS total_transactions,
            COALESCE(SUM(quantity * price), 0) AS total_spent,
            COALESCE(SUM(quantity), 0) AS total_shares
     FROM transactions
     WHERE transaction_type = 'BUY'"
)->fetch_assoc();

include __DIR__ . "/includes/header.php";
?>

<section class="page-heading">
    <div>
        <h1>Purchases</h1>
        <p>Record and review shares purchased by the investor.</p>
    </div>
    <span class="status-badge">Demo market data</span>
</section>

<section class="content-grid">
    <div class="panel">
        <h2>Record a Purchase</h2>
        <p class="muted">Enter the purchase details below.</p>

        <form action="actions/add_purchase.php" method="POST"
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

            <label for="quantity">Number of Shares</label>
            <input type="number" name="quantity" id="quantity"
                   min="1" step="1" required>

            <label for="price">Purchase Price per Share (KES)</label>
            <input type="number" name="price" id="price"
                   min="0.01" step="0.01" required>

            <label for="transaction_date">Purchase Date</label>
            <input type="date" name="transaction_date"
                   id="transaction_date"
                   value="<?= date('Y-m-d') ?>" required>

            <button type="submit">Record Purchase</button>
        </form>
    </div>

    <div class="panel">
        <h2>Purchase Summary</h2>

        <div class="stat-card">
            <span>Total Purchase Transactions</span>
            <strong><?= (int)$summary['total_transactions'] ?></strong>
        </div>

        <div class="stat-card">
            <span>Total Shares Purchased</span>
            <strong><?= number_format((int)$summary['total_shares']) ?></strong>
        </div>

        <div class="stat-card">
            <span>Total Purchase Value</span>
            <strong>KES <?= number_format((float)$summary['total_spent'], 2) ?></strong>
        </div>
    </div>
</section>

<section class="panel">
    <h2>Purchase History</h2>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Symbol</th>
                    <th>Company</th>
                    <th>Quantity</th>
                    <th>Price per Share</th>
                    <th>Total Cost</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($purchases && $purchases->num_rows > 0): ?>
                <?php while ($purchase = $purchases->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($purchase['transaction_date']) ?></td>
                        <td><strong><?= htmlspecialchars($purchase['symbol']) ?></strong></td>
                        <td><?= htmlspecialchars($purchase['company_name']) ?></td>
                        <td><?= number_format((int)$purchase['quantity']) ?></td>
                        <td>KES <?= number_format((float)$purchase['price'], 2) ?></td>
                        <td>KES <?= number_format((float)$purchase['total_cost'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No purchases recorded yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>

