<?php
// app/Http/Controllers/Api/FavoriteController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Favorite;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index()
    {
        $favorites = Favorite::where('user_id', Auth::id())->get();
        return response()->json(['favorites' => $favorites]);
    }

    public function store(Request $request)
    {
        $request->validate(['symbol' => 'required|string']);
        $favorite = Favorite::firstOrCreate([
            'user_id' => Auth::id(),
            'symbol' => $request->symbol,
        ]);
        return response()->json(['favorite' => $favorite], 201);
    }

    public function destroy($symbol)
    {
        $deleted = Favorite::where('user_id', Auth::id())
            ->where('symbol', $symbol)
            ->delete();

        return response()->json(['deleted' => (bool)$deleted]);
    }
}