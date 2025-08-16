<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use WebSocket\Client;
use WebSocket\ConnectionException;
use App\Events\MarketTick;
use App\Events\MarketPriceUpdate;

class StreamMarketTicks extends Command
{
    protected $signature = 'market:stream
                           {--symbol=* : Market symbols to subscribe to (e.g. NASDAQ:AAPL)}';

    protected $description = 'Connect to InsightSentry WebSocket and broadcast live market ticks';

    public function handle()
    {

        $symbols      = $this->option('symbol') ?: ['NASDAQ:AAPL'];
        $wsApiKey     = config('services.insightsentry.wskey');
        $wsUrl        = 'wss://realtime.insightsentry.com/live';

        // Prepare subscriptions
        $subscriptions = array_map(function ($sym) {
            return ['code' => $sym, 'type' => 'quote'];
        }, $symbols);

        $attempts = 0;
        set_time_limit(0);
        while (true) {
            $client = null;

            try {
                // DNS check
                $host     = parse_url($wsUrl, PHP_URL_HOST);
                $resolved = gethostbyname($host);
                if ($resolved === $host) {
                    $this->error("DNS resolution failed for {$host}");
                    sleep(5);
                    continue;
                }
                $this->info("Resolved {$host} → {$resolved}");

                // SSL + timeout
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer'      => true,
                        'verify_peer_name' => true,
                        // 'allow_self_signed' => true, // for testing only
                    ],
                ]);
                $client = new Client($wsUrl, [
                    'timeout' => 10,
                    'context' => $context,
                ]);

                // Subscribe
                $client->send(json_encode([
                    'api_key'       => $wsApiKey,
                    'subscriptions' => $subscriptions,
                ]));
                $this->info("Subscribed to: " . implode(', ', $symbols));
                $client->setTimeout(0);
                $lastPing = time();

                // ————— Streaming loop —————
                while (true) {
                    // send a real WebSocket ping control frame every 15s
                    if (time() - $lastPing >= 15) {
                        $client->send('', 'ping');
                        $lastPing = time();
                    }

                    try {
                        $message = $client->receive();
                    } catch (\Exception $e) {
                        // any receive error triggers a reconnect
                        $this->error("Receive error: " . $e->getMessage());
                        break;
                    }

                    // skip pong or empty
                    if ($message === null || $message === '') {
                        continue;
                    }

                    // $this->info("Raw: {$message}");
                    $data = json_decode($message, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->warn("Invalid JSON: {$message}");
                        continue;
                    }

                    // Dispatch events…
                    if (isset($data['code'], $data['ask'], $data['bid'])) {
                        event(new MarketTick(
                            $data['code'],
                            (float) $data['ask'],
                            (float) $data['bid'],
                            (float) ($data['ask_size'] ?? 0),
                            (float) ($data['bid_size'] ?? 0)
                        ));
                        // $this->line("Quote: {$data['code']} - Bid: {$data['bid']} Ask: {$data['ask']}");
                    } elseif (isset($data['code'], $data['last_price'])) {
                        event(new MarketPriceUpdate(
                            $data['code'],
                            (float) $data['last_price'],
                            (float) ($data['volume'] ?? 0),
                            (float) ($data['change'] ?? 0),
                            (float) ($data['change_percent'] ?? 0)
                        ));
                        // $this->line("Price Update: {$data['code']} - Last Price: {$data['last_price']}");
                    } elseif (isset($data['series'])) {
                        $this->info("Series data for {$data['code']}");
                    } elseif (isset($data['message'])) {
                        $this->line("Info: {$data['message']}");
                    }
                    // server_time heartbeat and others are ignored
                }

                // clean close
                if ($client) {
                    try {
                        $client->close();
                        $this->info("Connection closed cleanly.");
                    } catch (\Exception $e) {
                        $this->warn("Error closing socket: " . $e->getMessage());
                    }
                }

                // **Reset attempts on a clean end-of-loop** (i.e. not an exception)
                $attempts = 0;
                // then immediately restart without any back‑off
                continue;
            } catch (ConnectionException $e) {
                $this->error("WebSocket ConnectionException: " . $e->getMessage());
            } catch (\Exception $e) {
                $this->error("Connection error: " . $e->getMessage());
            }

            // — Only back‑off after a real exception ——
            $attempts++;
            if ($attempts >= 5) {
                $this->error("Max reconnect attempts reached. Exiting.");
                return;
            }
            $wait = min(1 + ($attempts - 1) * 2, 10);
            $this->warn("Reconnecting in {$wait} seconds…");
            sleep($wait);
        }
    }
}