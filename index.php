<?php
require_once __DIR__ . "/config/database.php";

$pageTitle = "Market Dashboard";

/* =========================
   DASHBOARD METRICS
========================= */

$stockCount = (int) $conn->query("
    SELECT COUNT(*) AS total FROM stocks
")->fetch_assoc()["total"];

$purchase = (float) $conn->query("
    SELECT COALESCE(SUM(quantity * price), 0) AS total
    FROM transactions
    WHERE transaction_type = 'BUY'
")->fetch_assoc()["total"];

$sales = (float) $conn->query("
    SELECT COALESCE(SUM(quantity * price), 0) AS total
    FROM transactions
    WHERE transaction_type = 'SELL'
")->fetch_assoc()["total"];

/* =========================
   MARKET WATCH DATA
========================= */

$stocks = $conn->query("
    SELECT id, symbol, company_name, sector,
           current_price, previous_close, trading_volume
    FROM stocks
    ORDER BY company_name
");

/* =========================
   TOP GAINERS
========================= */

$topGainers = $conn->query("
    SELECT id, symbol, company_name, current_price,
           previous_close, trading_volume,
           ((current_price - previous_close) / previous_close) * 100
               AS change_percent
    FROM stocks
    WHERE previous_close > 0
      AND current_price > previous_close
    ORDER BY change_percent DESC
    LIMIT 5
");

/* =========================
   TOP LOSERS
========================= */

$topLosers = $conn->query("
    SELECT id, symbol, company_name, current_price,
           previous_close, trading_volume,
           ((current_price - previous_close) / previous_close) * 100
               AS change_percent
    FROM stocks
    WHERE previous_close > 0
      AND current_price < previous_close
    ORDER BY change_percent ASC
    LIMIT 5
");

/* =========================
   SPARKLINE HISTORY
========================= */

$sparklineStocks = [];

foreach ([$topGainers, $topLosers] as $ranking) {
    if ($ranking) {
        while ($stock = $ranking->fetch_assoc()) {
            $sparklineStocks[(int) $stock["id"]] = true;
        }

        $ranking->data_seek(0);
    }
}

$sparklineData = [];

if (!empty($sparklineStocks)) {
    $historyQuery = $conn->prepare("
        SELECT price_date, closing_price
        FROM stock_prices
        WHERE stock_id = ?
        ORDER BY price_date DESC
        LIMIT 7
    ");

    foreach (array_keys($sparklineStocks) as $stockId) {
        $historyQuery->bind_param("i", $stockId);
        $historyQuery->execute();

        $historyResult = $historyQuery->get_result();
        $prices = [];

        while ($point = $historyResult->fetch_assoc()) {
            $prices[] = (float) $point["closing_price"];
        }

        $sparklineData[$stockId] = array_reverse($prices);
    }

    $historyQuery->close();
}

/* =========================
   PRICE FORMATTER
========================= */

function kes($amount) {
    return "KES " . number_format((float) $amount, 2);
}

/* =========================
   LOAD HEADER
========================= */

include __DIR__ . "/includes/header.php";
?>

<style>
/* ===== Stock autocomplete ===== */

.stock-explorer {
    position: relative;
    max-width: 620px;
    margin: 20px 0;
}

.stock-explorer label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.stock-explorer input {
    width: 100%;
    box-sizing: border-box;
    padding: 13px 15px;
    border: 1px solid #374151;
    border-radius: 9px;
    background: #111827;
    color: #f9fafb;
    font: inherit;
}

.stock-search-results {
    position: absolute;
    z-index: 100;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 9px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
}

.stock-search-results[hidden] {
    display: none !important;
}

.stock-search-results button {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 14px 16px;
    border: none;
    border-bottom: 1px solid #273244;
    background: transparent;
    color: #f9fafb;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.stock-search-results button:hover,
.stock-search-results button:focus {
    background: #263449;
    outline: none;
}

.stock-search-results button strong {
    min-width: 65px;
    color: #38bdf8;
}

.stock-search-results button span {
    opacity: 0.9;
}

/* ===== Selected stock information ===== */

.selected-stock-info {
    margin: 20px 0 10px;
}

.selected-stock-info h3 {
    margin-bottom: 6px;
}

/* ===== Main charts side by side ===== */

.market-chart-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    width: 100%;
    margin-top: 20px;
    align-items: stretch;
}

.chart-panel {
    min-width: 0;
    box-sizing: border-box;
    padding: 16px;
    border: 1px solid #273244;
    border-radius: 12px;
    background: rgba(17, 24, 39, 0.45);
}

.chart-panel h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
}

.chart-container {
    position: relative;
    width: 100%;
    height: 300px;
}

/* ===== Top gainers sparklines ===== */

.sparkline-cell {
    width: 100px;
    min-width: 80px;
}

.stock-sparkline {
    display: block;
    width: 90px;
    height: 35px;
}

/* ===== Trading volume rankings ===== */

.volume-ranking {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.volume-item-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.volume-rank {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #263449;
    font-weight: 700;
}

.volume-company {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.volume-number {
    font-size: 13px;
    white-space: nowrap;
}

.volume-track {
    height: 8px;
    overflow: hidden;
    border-radius: 20px;
    background: #273244;
}

.volume-fill {
    height: 100%;
    border-radius: 20px;
    background: #38bdf8;
}

/* ===== Market Watch search ===== */

.market-search {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin: 15px 0;
}

.market-search input {
    flex: 1;
    min-width: 220px;
    padding: 11px 12px;
    border: 1px solid #374151;
    border-radius: 8px;
    font: inherit;
}

/* ===== Responsive layout ===== */

@media (max-width: 760px) {
    .market-chart-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .chart-container {
        height: 260px;
    }

    .market-search {
        align-items: stretch;
        flex-direction: column;
    }

    .market-search input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
}
</style>

<!-- =========================
     DASHBOARD METRICS
========================= -->

<div class="alert">
    <strong>Demonstration data:</strong>
    Prices and historical records may be sample values, not live NSE quotations.
</div>

<div class="grid">
    <div class="metric">
        <p>Registered counters</p>
        <h2><?= $stockCount ?></h2>
        <span class="muted">Stocks in database</span>
    </div>

    <div class="metric">
        <p>Total purchases</p>
        <h2><?= kes($purchase) ?></h2>
        <span class="muted">Recorded buy transactions</span>
    </div>

    <div class="metric">
        <p>Total sales</p>
        <h2><?= kes($sales) ?></h2>
        <span class="muted">Recorded sell transactions</span>
    </div>

    <div class="metric">
        <p>Net transaction cash flow</p>
        <h2><?= kes($sales - $purchase) ?></h2>
        <span class="muted">Sales minus purchases</span>
    </div>
</div>

<!-- =========================
     MARKET EXPLORER
========================= -->

<section class="panel">
    <h2>Explore the Market</h2>

    <p class="muted">
        Search for a company or ticker to view its historical closing
        price and daily trading volume.
    </p>

    <div class="stock-explorer">
        <label for="stockSearchChart">Search stocks</label>

        <input
            type="search"
            id="stockSearchChart"
            placeholder="Type Safaricom, SCOM, KCB..."
            autocomplete="off"
        >

        <div
            id="stockSearchResults"
            class="stock-search-results"
            role="listbox"
            hidden
        ></div>
    </div>

    <div class="selected-stock-info">
        <h3 id="selectedStockName">Select a stock</h3>

        <p id="selectedStockPrice" class="muted">
            Search above to view stock information.
        </p>
    </div>

    <p id="chartStatus" class="muted" aria-live="polite">
        Type a stock symbol or company name to get started.
    </p>

    <!-- Both charts occupy the same row on desktop -->
    <div class="market-chart-grid">
        <div class="chart-panel">
            <h3>Historical Closing Price</h3>

            <div class="chart-container">
                <canvas id="priceChart"></canvas>
            </div>
        </div>

        <div class="chart-panel">
            <h3>Daily Trading Volume</h3>

            <div class="chart-container">
                <canvas id="volumeChart"></canvas>
            </div>
        </div>
    </div>
</section>

<!-- =========================
     GAINERS, LOSERS AND
     MOST ACTIVE COUNTERS
========================= -->

<div class="grid">

    <!-- TOP GAINERS -->

    <section class="panel top-gainers-card">
        <h2>Top Gainers</h2>
        <p class="muted">Largest positive price changes</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Price (KES)</th>
                        <th>Change</th>
                        <th>Trend</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($topGainers && $topGainers->num_rows > 0): ?>

                        <?php while ($stock = $topGainers->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <a
                                        class="stock-link"
                                        href="stock_details.php?id=<?= (int) $stock['id'] ?>"
                                    >
                                        <?= htmlspecialchars($stock['symbol']) ?>
                                    </a>
                                </td>

                                <td><?= kes($stock['current_price']) ?></td>

                                <td class="positive">
                                    +<?= number_format(
                                        (float) $stock['change_percent'],
                                        2
                                    ) ?>%
                                </td>

                                <td class="sparkline-cell">
                                    <?php
                                    $history =
                                        $sparklineData[(int) $stock['id']] ?? [];
                                    ?>

                                    <?php if (count($history) >= 2): ?>

                                        <canvas
                                            class="stock-sparkline gainers-sparkline"
                                            data-prices="<?= htmlspecialchars(
                                                json_encode($history),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            role="img"
                                            aria-label="Historical price trend for <?= htmlspecialchars($stock['symbol']) ?>"
                                        ></canvas>

                                    <?php else: ?>

                                        <span class="muted">No history</span>

                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4">No gainers recorded.</td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- TOP LOSERS -->

    <section class="panel">
        <h2>Top Losers</h2>
        <p class="muted">Largest negative price changes</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Price (KES)</th>
                        <th>Change</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($topLosers && $topLosers->num_rows > 0): ?>

                        <?php while ($stock = $topLosers->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <a
                                        class="stock-link"
                                        href="stock_details.php?id=<?= (int) $stock['id'] ?>"
                                    >
                                        <?= htmlspecialchars($stock['symbol']) ?>
                                    </a>
                                </td>

                                <td><?= kes($stock['current_price']) ?></td>

                                <td class="negative">
                                    <?= number_format(
                                        (float) $stock['change_percent'],
                                        2
                                    ) ?>%
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3">No losers recorded.</td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- MOST ACTIVELY TRADED -->

    <section class="panel">
        <h2>Most Actively Traded</h2>

        <p class="muted">
            Top five counters by recorded trading volume
        </p>

        <?php
        $activeStocks = $conn->query("
            SELECT id, symbol, company_name, trading_volume
            FROM stocks
            ORDER BY trading_volume DESC, company_name ASC
            LIMIT 5
        ");

        $activeRows = [];

        if ($activeStocks) {
            while ($stock = $activeStocks->fetch_assoc()) {
                $activeRows[] = $stock;
            }
        }

        $maxVolume = 0;

        foreach ($activeRows as $stock) {
            $maxVolume = max(
                $maxVolume,
                (int) $stock["trading_volume"]
            );
        }
        ?>

        <?php if (count($activeRows) > 0): ?>

            <div class="volume-ranking">

                <?php foreach ($activeRows as $index => $stock): ?>

                    <?php
                    $stockVolume = (int) $stock["trading_volume"];

                    $barWidth = $maxVolume > 0
                        ? ($stockVolume / $maxVolume) * 100
                        : 0;
                    ?>

                    <div class="volume-item">
                        <div class="volume-item-heading">

                            <span class="volume-rank">
                                <?= $index + 1 ?>
                            </span>

                            <div class="volume-company">
                                <a
                                    class="stock-link"
                                    href="stock_details.php?id=<?= (int) $stock['id'] ?>"
                                >
                                    <?= htmlspecialchars($stock["symbol"]) ?>
                                </a>

                                <span class="muted">
                                    <?= htmlspecialchars($stock["company_name"]) ?>
                                </span>
                            </div>

                            <strong class="volume-number">
                                <?= number_format($stockVolume) ?>
                            </strong>
                        </div>

                        <div
                            class="volume-track"
                            role="img"
                            aria-label="<?= htmlspecialchars($stock['symbol']) ?> trading volume: <?= number_format($stockVolume) ?> shares"
                        >
                            <div
                                class="volume-fill"
                                style="width: <?= number_format($barWidth, 2, '.', '') ?>%;"
                            ></div>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="muted">
                No trading-volume records are available.
            </p>

        <?php endif; ?>
    </section>

</div>

<!-- =========================
     MARKET WATCH
========================= -->

<section class="panel">
    <h2>Market Watch</h2>

    <p class="muted">
        Search and explore all registered stock counters.
    </p>

    <div class="market-search">
        <input
            type="search"
            id="stockSearch"
            placeholder="Search by symbol, company or sector..."
            autocomplete="off"
            aria-label="Search stock counters"
        >

        <span id="stockCountLabel" class="muted">
            Showing all counters
        </span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Company</th>
                    <th>Sector</th>
                    <th>Price</th>
                    <th>Change</th>
                    <th>Volume</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($stock = $stocks->fetch_assoc()): ?>

                    <?php
                    $change = (float) $stock["current_price"]
                        - (float) $stock["previous_close"];

                    $percent = (float) $stock["previous_close"] > 0
                        ? ($change / (float) $stock["previous_close"]) * 100
                        : 0;
                    ?>

                    <tr
                        class="stock-row"
                        data-search="<?= htmlspecialchars(
                            strtolower(
                                $stock["symbol"] . " " .
                                $stock["company_name"] . " " .
                                $stock["sector"]
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >
                        <td>
                            <strong>
                                <a
                                    class="stock-link"
                                    href="stock_details.php?id=<?= (int) $stock['id'] ?>"
                                >
                                    <?= htmlspecialchars($stock["symbol"]) ?>
                                </a>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($stock["company_name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($stock["sector"]) ?>
                        </td>

                        <td>
                            <?= kes($stock["current_price"]) ?>
                        </td>

                        <td class="<?= $change >= 0 ? 'positive' : 'negative' ?>">
                            <?= $change >= 0 ? '+' : '' ?>
                            <?= number_format($change, 2) ?>
                            (<?= number_format($percent, 2) ?>%)
                        </td>

                        <td>
                            <?= number_format((int) $stock["trading_volume"]) ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- =========================
     CHART.JS LIBRARY
========================= -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/* Get the available stocks from the database. */
const stocksForSearch = <?= json_encode(
    $conn->query("
        SELECT id, symbol, company_name
        FROM stocks
        ORDER BY symbol
    ")->fetch_all(MYSQLI_ASSOC),
    JSON_HEX_TAG | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const searchInput = document.getElementById("stockSearchChart");
const searchResults = document.getElementById("stockSearchResults");
const selectedName = document.getElementById("selectedStockName");
const selectedPrice = document.getElementById("selectedStockPrice");
const chartStatus = document.getElementById("chartStatus");

let priceChart = null;
let volumeChart = null;
let searchTimer = null;
let requestNumber = 0;

/* Create the closing-price chart. */
function createCharts() {
    priceChart = new Chart(
        document.getElementById("priceChart"),
        {
            type: "line",

            data: {
                labels: [],
                datasets: [{
                    label: "Closing price (KES)",
                    data: [],
                    borderColor: "#38bdf8",
                    backgroundColor: "rgba(56, 189, 248, 0.12)",
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHoverRadius: 5
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: "index"
                },

                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return "Closing price: KES " +
                                    Number(context.raw).toLocaleString(
                                        "en-KE",
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    );
                            }
                        }
                    }
                },

                scales: {
                    x: {
                        title: {
                            display: true,
                            text: "Trading date"
                        }
                    },

                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: "Price (KES)"
                        }
                    }
                }
            }
        }
    );

    /* Create the daily trading-volume chart. */
    volumeChart = new Chart(
        document.getElementById("volumeChart"),
        {
            type: "bar",

            data: {
                labels: [],
                datasets: [{
                    label: "Shares traded",
                    data: [],
                    backgroundColor: "rgba(34, 197, 94, 0.65)",
                    borderColor: "#22c55e",
                    borderWidth: 1
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: "index"
                },

                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return "Shares traded: " +
                                    Number(context.raw).toLocaleString();
                            }
                        }
                    }
                },

                scales: {
                    x: {
                        title: {
                            display: true,
                            text: "Trading date"
                        }
                    },

                    y: {
                        beginAtZero: true,

                        title: {
                            display: true,
                            text: "Shares traded"
                        },

                        ticks: {
                            callback: function(value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        }
    );
}

/* Display matching stock suggestions as the user types. */
function showSearchResults(query) {
    searchResults.replaceChildren();

    const normalizedQuery = query.trim().toLowerCase();

    if (!normalizedQuery) {
        searchResults.hidden = true;
        return;
    }

    const matches = stocksForSearch.filter(stock => {
        const symbol = String(stock.symbol || "").toLowerCase();
        const company = String(stock.company_name || "").toLowerCase();

        return symbol.includes(normalizedQuery) ||
               company.includes(normalizedQuery);
    }).slice(0, 10);

    if (matches.length === 0) {
        const empty = document.createElement("div");
        empty.textContent = "No matching stocks found.";
        empty.style.padding = "12px";

        searchResults.appendChild(empty);
        searchResults.hidden = false;
        return;
    }

    matches.forEach(stock => {
        const button = document.createElement("button");
        button.type = "button";
        button.setAttribute("role", "option");

        const symbol = document.createElement("strong");
        symbol.textContent = stock.symbol;

        const company = document.createElement("span");
        company.textContent = stock.company_name;

        button.appendChild(symbol);
        button.appendChild(company);

        button.addEventListener("click", () => {
            searchInput.value = stock.symbol;
            searchResults.hidden = true;
            loadStock(stock.id);
        });

        searchResults.appendChild(button);
    });

    searchResults.hidden = false;
}

/* Load historical prices and volume for the selected stock. */
async function loadStock(stockId) {
    const thisRequest = ++requestNumber;

    chartStatus.textContent = "Loading historical data...";

    try {
        const response = await fetch(
            "get_stock_history.php?stock_id=" +
            encodeURIComponent(stockId),
            {
                headers: {
                    "Accept": "application/json"
                }
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.error || "Unable to load this stock."
            );
        }

        /* Ignore an older request if another stock was selected. */
        if (thisRequest !== requestNumber) {
            return;
        }

        selectedName.textContent =
            data.stock.symbol + " — " + data.stock.company;

        selectedPrice.textContent =
            "Current recorded price: KES " +
            Number(data.stock.currentPrice).toLocaleString(
                "en-KE",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

        const history = data.history || [];

        /* Update closing-price graph. */
        priceChart.data.labels =
            history.map(point => point.date);

        priceChart.data.datasets[0].data =
            history.map(point => point.price);

        priceChart.update();

        /* Update trading-volume graph. */
        volumeChart.data.labels =
            history.map(point => point.date);

        volumeChart.data.datasets[0].data =
            history.map(point => point.volume);

        volumeChart.update();

        chartStatus.textContent = history.length
            ? history.length + " historical records loaded."
            : "No historical records exist for this stock yet.";

    } catch (error) {
        if (thisRequest !== requestNumber) {
            return;
        }

        chartStatus.textContent =
            error.message || "An error occurred while loading the stock.";
    }
}

/* Search as the user types. */
if (searchInput) {
    searchInput.addEventListener("input", () => {
        clearTimeout(searchTimer);

        searchTimer = setTimeout(() => {
            showSearchResults(searchInput.value);
        }, 150);
    });
}

/* Close suggestions when clicking outside the search area. */
document.addEventListener("click", event => {
    if (!event.target.closest(".stock-explorer")) {
        searchResults.hidden = true;
    }
});

/* Draw mini trend charts in the Top Gainers table. */
function createSparklines() {
    document.querySelectorAll(".gainers-sparkline").forEach(canvas => {
        let prices = [];

        try {
            prices = JSON.parse(canvas.dataset.prices || "[]");
        } catch (error) {
            return;
        }

        if (prices.length < 2) {
            return;
        }

        new Chart(canvas, {
            type: "line",

            data: {
                labels: prices.map((_, index) => index + 1),

                datasets: [{
                    data: prices,
                    borderColor: "#22c55e",
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.35,
                    fill: false
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: false
                    },

                    tooltip: {
                        enabled: false
                    }
                },

                scales: {
                    x: {
                        display: false
                    },

                    y: {
                        display: false
                    }
                },

                animation: false
            }
        });
    });
}

/* Filter rows in the Market Watch table. */
const stockSearch = document.getElementById("stockSearch");

const stockRows = Array.from(
    document.querySelectorAll(".stock-row")
);

const stockCountLabel = document.getElementById("stockCountLabel");

function filterStocks() {
    if (!stockSearch || !stockCountLabel) {
        return;
    }

    const query = stockSearch.value.trim().toLowerCase();
    let visible = 0;

    stockRows.forEach(row => {
        const matches = row.dataset.search.includes(query);

        row.hidden = !matches;

        if (matches) {
            visible++;
        }
    });

    stockCountLabel.textContent = query
        ? `Showing ${visible} of ${stockRows.length} counters`
        : `Showing all ${stockRows.length} counters`;
}

if (stockSearch && stockCountLabel) {
    stockSearch.addEventListener("input", filterStocks);
    filterStocks();
}

/* Initialise the dashboard. */
createCharts();
createSparklines();

if (stocksForSearch.length > 0) {
    loadStock(stocksForSearch[0].id);
} else {
    chartStatus.textContent = "No stocks are registered yet.";
}
</script>

<?php include __DIR__ . "/includes/footer.php"; ?>