<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Container\Attributes\Log;

class MarketService
{
    protected string $baseUrl;
    protected array  $headers;

    public function __construct()
    {
        $this->baseUrl = 'https://' . config('services.insightsentry.host');
        $this->headers = [
            'X-RapidAPI-Host' => config('services.insightsentry.host'),
            'X-RapidAPI-Key'  => config('services.insightsentry.key'),
        ];
    }

    /**
     * Fetch list of all markets.
     *
     * @param  array  $query
     * @return array
     * @throws RequestException
     */
    public function fetchAll(array $query = []): array
    {
        return Http::withHeaders($this->headers)
            ->retry(3, 100)
            ->get("{$this->baseUrl}/markets", $query)
            ->throw()
            ->json();
    }

    /**
     * Fetch details for a single market symbol.
     *
     * @param  string  $symbol
     * @return array
     * @throws RequestException
     */
    public function fetchOne(string $symbol): array
    {
        Log:
        info("{$this->baseUrl}/v2/symbols/quotes?codes=OANDA:{$symbol}");
        return Http::withHeaders($this->headers)
            ->retry(3, 100)
            ->get("{$this->baseUrl}/v2/symbols/quotes?codes=OANDA:{$symbol}")
            ->throw()
            ->json();
    }
    public function fetchOHLC(string $symbol, int $interval, int $periods): array
    {
        return Http::withHeaders($this->headers)
            ->retry(3, 100)
            ->get("{$this->baseUrl}/ohlc", [
                'symbol'   => $symbol,
                'interval' => $interval,
                'periods'  => $periods,
            ])
            ->throw()
            ->json();
    }
}