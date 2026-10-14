
<?php
require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../sales.php");
    exit;
}

$stockId = (int)($_POST["stock_id"] ?? 0);
$quantity = (int)($_POST["quantity"] ?? 0);
$price = (float)($_POST["price"] ?? 0);
$date = $_POST["transaction_date"] ?? "";

if ($stockId < 1 || $quantity < 1 || $price <= 0) {
    exit("Please enter a valid stock, quantity and price.");
}

$dateObject = DateTime::createFromFormat("!Y-m-d", $date);

if (!$dateObject || $dateObject->format("Y-m-d") !== $date) {
    exit("Please enter a valid transaction date.");
}

$check = $conn->prepare("SELECT id FROM stocks WHERE id = ?");
$check->bind_param("i", $stockId);
$check->execute();

if ($check->get_result()->num_rows !== 1) {
    exit("The selected stock does not exist.");
}

$balance = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN transaction_type = 'BUY'
                          THEN quantity ELSE 0 END), 0)
        -
        COALESCE(SUM(CASE WHEN transaction_type = 'SELL'
                          THEN quantity ELSE 0 END), 0)
        AS available_shares
    FROM transactions
    WHERE stock_id = ?
");

$balance->bind_param("i", $stockId);
$balance->execute();
$row = $balance->get_result()->fetch_assoc();

$available = (int)$row["available_shares"];

if ($quantity > $available) {
    exit("Not enough shares. You currently have " . $available .
         " shares available to sell.");
}

$type = "SELL";

$insert = $conn->prepare("
    INSERT INTO transactions
    (stock_id, transaction_type, quantity, price, transaction_date)
    VALUES (?, ?, ?, ?, ?)
");

$insert->bind_param(
    "isids",
    $stockId,
    $type,
    $quantity,
    $price,
    $date
);

if ($insert->execute()) {
    header("Location: ../sales.php");
    exit;
}

exit("Unable to record the sale. Please check your database.");
?>
