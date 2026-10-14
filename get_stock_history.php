
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
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

let selectedStockId = null;
let priceChart = null;
let volumeChart = null;
let searchTimer = null;
let requestNumber = 0;

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
                    backgroundColor: "rgba(56,189,248,0.12)",
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: "index"
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: "KES"
                        }
                    }
                }
            }
        }
    );

    volumeChart = new Chart(
        document.getElementById("volumeChart"),
        {
            type: "bar",
            data: {
                labels: [],
                datasets: [{
                    label: "Shares traded",
                    data: [],
                    backgroundColor: "rgba(34,197,94,0.65)",
                    borderColor: "#22c55e",
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value =>
                                Number(value).toLocaleString()
                        }
                    }
                }
            }
        }
    );
}

function showSearchResults(query) {
    searchResults.replaceChildren();

    const normalizedQuery = query.trim().toLowerCase();

    if (!normalizedQuery) {
        searchResults.hidden = true;
        return;
    }

    const matches = stocksForSearch.filter(stock =>
        stock.symbol.toLowerCase().includes(normalizedQuery) ||
        stock.company_name.toLowerCase().includes(normalizedQuery)
    ).slice(0, 10);

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
        button.textContent =
            stock.symbol + " — " + stock.company_name;

        button.addEventListener("click", () => {
            searchInput.value =
                stock.symbol + " — " + stock.company_name;

            searchResults.hidden = true;
            loadStock(stock.id);
        });

        searchResults.appendChild(button);
    });

    searchResults.hidden = false;
}

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
            throw new Error(data.error || "Unable to load stock.");
        }

        // Ignore an older response if a newer selection was made.
        if (thisRequest !== requestNumber) return;

        selectedStockId = data.stock.id;

        selectedName.textContent =
            data.stock.symbol + " — " + data.stock.company;

        selectedPrice.textContent =
            "Current recorded price: KES " +
            data.stock.currentPrice.toLocaleString(
                "en-KE",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

        const history = data.history;

        priceChart.data.labels = history.map(point => point.date);
        priceChart.data.datasets[0].data =
            history.map(point => point.price);
        priceChart.update();

        volumeChart.data.labels = history.map(point => point.date);
        volumeChart.data.datasets[0].data =
            history.map(point => point.volume);
        volumeChart.update();

        chartStatus.textContent = history.length
            ? history.length + " historical records loaded."
            : "No historical records exist for this stock yet.";

    } catch (error) {
        if (thisRequest !== requestNumber) return;
        chartStatus.textContent = error.message;
    }
}

searchInput.addEventListener("input", () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        showSearchResults(searchInput.value);
    }, 150);
});

document.addEventListener("click", event => {
    if (!event.target.closest(".stock-explorer")) {
        searchResults.hidden = true;
    }
});

createCharts();

// Automatically display the first available stock.
if (stocksForSearch.length > 0) {
    loadStock(stocksForSearch[0].id);
} else {
    chartStatus.textContent = "No stocks are registered yet.";
}
</script>
