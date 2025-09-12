<?php
// File: /var/www/html/scripts/cron_update_quotes.php
// Purpose: Pull minimal quotes (last_price, bid, ask) for ANY number of codes.
//          Batches requests to 10 codes per call (RapidAPI limit).

date_default_timezone_set('Asia/Kolkata');

// ====== CONFIG ======
$codes = [
    // Apni full list yahan daalo (EXCHANGE:SYMBOL). 10 se zyada allowed—script chunk karega.
    "OANDA:AUDCAD",
    "OANDA:AUDJPY",
    "OANDA:AUDNZD",
    "OANDA:AUDSGD",
    "OANDA:AUDUSD",
    "OANDA:CADJPY",
    "OANDA:CHFJPY",
    "OANDA:EURAUD",
    "OANDA:EURCHF",
    "OANDA:EURCZK",
    "OANDA:EURGBP",
    "OANDA:EURJPY",
    "OANDA:EURNOK",
    "OANDA:EURNZD",
    "OANDA:EURPLN",
    "OANDA:EURSEK",
    "OANDA:EURTRY",
    "OANDA:EURUSD",
    "OANDA:GBPAUD",
    "OANDA:GBPCAD",
    "OANDA:GBPCHF",
    "OANDA:GBPJPY",
    "OANDA:GBPSGD",
    "OANDA:GBPUSD",
    "OANDA:NZDCAD",
    "OANDA:NZDJPY",
    "OANDA:NZDUSD",
    "OANDA:USDCAD",
    "OANDA:USDCNH",
    "OANDA:USDDKK",
    "OANDA:USDHUF",
    "OANDA:USDJPY",
    "OANDA:USDMXN",
    "OANDA:USDNOK",
    "OANDA:USDSEK",
    "OANDA:USDZAR",
    "BINANCE:BTCUSDT",
    "BINANCE:ETHUSDT",
    "OANDA:XAUUSD",
    "OANDA:XAGUSD",
    "OANDA:XCUUSD",
    "OANDA:BCOUSD",
    "OANDA:NATGASUSD",

];
$RAPID_HOST = 'insightsentry.p.rapidapi.com';
$RAPID_KEY  = getenv('RAPIDAPI_KEY') ?: '408e348f18msh307e2c939cc6e59p1b7474jsnb50d80ee193a'; // key env me rakho
$CHUNK_SIZE = 10;          // RapidAPI hard limit
$SLEEP_BETWEEN_CHUNKS = 1; // seconds (rate-limit ke liye)

// ====== DB CREDS ======
$dbHost = 'localhost';
$dbName = 'broker_db';
$dbUser = 'root';
$dbPass = '';

// ====== DB CONNECT ======
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Throwable $e) {
    file_put_contents('php://stderr', "DB connect error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// UPSERT stmt (sirf teen fields)
$up = $pdo->prepare("
INSERT INTO symbols (symbol, last_price, bid, ask, last_quote_at)
VALUES (:symbol, :last_price, :bid, :ask, NOW())
ON DUPLICATE KEY UPDATE
  last_price = VALUES(last_price),
  bid        = VALUES(bid),
  ask        = VALUES(ask),
  last_quote_at = NOW()
");

// ====== Helpers ======
function fetch_quotes_chunk(array $chunk, string $host, string $key)
{
    $codesParam = rawurlencode(implode(',', $chunk));
    $url = "https://{$host}/v2/symbols/quotes?codes={$codesParam}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            "x-rapidapi-host: {$host}",
            "x-rapidapi-key: {$key}",
        ],
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error: {$err}");
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Helpful log on 4xx/5xx
    if ($httpCode >= 400) {
        file_put_contents('php://stderr', "HTTP {$httpCode}: {$resp}\n");
        // still try to decode to surface API message
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        throw new RuntimeException("Invalid JSON: " . $resp);
    }
    // API sometimes wraps data
    $items = $json['data'] ?? $json['quotes'] ?? (is_array($json) ? $json : []);
    if (!is_array($items)) $items = [];

    return $items;
}

// ====== MAIN: batch over chunks of 10 ======
$allCodes = array_values(array_unique(array_filter($codes)));
$chunks = array_chunk($allCodes, $CHUNK_SIZE);

foreach ($chunks as $i => $chunk) {
    try {
        $items = fetch_quotes_chunk($chunk, $RAPID_HOST, $RAPID_KEY);

        foreach ($items as $it) {
            $codeFull = $it['code'] ?? null;             // e.g., "NASDAQ:AAPL"
            if (!$codeFull) continue;

            // DB 'symbols.symbol' ke saath map: "EXCHANGE:SYMBOL" -> "SYMBOL"
            $symbol = preg_replace('/^[^:]+:/', '', $codeFull);
            echo "Updating {$symbol}...\n";

            $bind = [
                ':symbol'     => $symbol,
                ':last_price' => isset($it['last_price']) ? (float)$it['last_price'] : null,
                ':bid'        => isset($it['bid'])        ? (float)$it['bid']        : null,
                ':ask'        => isset($it['ask'])        ? (float)$it['ask']        : null,
            ];
            $up->execute($bind);
        }
    } catch (Throwable $e) {
        file_put_contents('php://stderr', "[chunk " . ($i + 1) . "/" . count($chunks) . "] " . $e->getMessage() . PHP_EOL);
        // continue to next chunk
    }

    // Rate-limit friendliness
    if ($i < count($chunks) - 1 && $SLEEP_BETWEEN_CHUNKS > 0) {
        sleep($SLEEP_BETWEEN_CHUNKS);
    }
}
