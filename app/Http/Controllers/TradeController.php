<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TradeController extends Controller
{
    /**
     * GET /api/v1/trades
     * List all trades for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $trades = $request->user()
            ->orders()            // get user's orders
            ->with('trades')      // eager-load related trades
            ->get()
            ->flatMap->trades;    // flatten into a single collection

        return response()->json($trades);
    }
}