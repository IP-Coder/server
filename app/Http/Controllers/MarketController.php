<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\MarketService;
use App\Models\Symbol;

class MarketController extends Controller
{
    public function __construct(protected MarketService $service) {}

    /**
     * GET /api/v1/markets
     */
    public function index(Request $request): JsonResponse
    {
        $data = Symbol::all(); // ✅ Return all records from the symbols table
        return response()->json([
            'status' => 'success',
            'symbols' => $data,

        ]);
    }

    /**
     * GET /api/v1/markets/{symbol}
     */
    public function show(string $symbol): JsonResponse
    {
        $data = $this->service->fetchOne($symbol);
        return response()->json($data);
    }
}