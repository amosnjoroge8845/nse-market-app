
<?php
require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../purchases.php");
    exit;
}

$stockId = filter_input(INPUT_POST, "stock_id", FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);
$price = filter_input(INPUT_POST, "price", FILTER_VALIDATE_FLOAT);
$date = $_POST["transaction_date"] ?? "";

if (
    !$stockId ||
    !$quantity || $quantity < 1 ||
    $price === false || $price === null || $price <= 0
) {
    exit("Please enter a valid stock, quantity and price.");
}

$dateObject = DateTime::createFromFormat("!Y-m-d", $date);

if (!$dateObject || $dateObject->format("Y-m-d") !== $date) {
    exit("Please enter a valid purchase date.");
}

$stockCheck = $conn->prepare(
    "SELECT id FROM stocks WHERE id = ?"
);
$stockCheck->bind_param("i", $stockId);
$stockCheck->execute();

if ($stockCheck->get_result()->num_rows !== 1) {
    exit("The selected stock does not exist.");
}

$type = "BUY";

$stmt = $conn->prepare(
    "INSERT INTO transactions
    (stock_id, transaction_type, quantity, price, transaction_date)
    VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "isids",
    $stockId,
    $type,
    $quantity,
    $price,
    $date
);

if ($stmt->execute()) {
    header("Location: ../purchases.php");
    exit;
}

exit("Purchase could not be saved. Please check your database.");
?>
