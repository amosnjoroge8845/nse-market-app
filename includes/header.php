
<?php
if (!isset($pageTitle)) {
    $pageTitle = "NSE Market Terminal";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | NSE Terminal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
    <?php include __DIR__ . "/sidebar.php"; ?> 
    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">NAIROBI SECURITIES EXCHANGE</p>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            <span class="demo-badge">DEMO DATA</span>
        </header>
        <div class="content">
