<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // Get logged in user details
    public function me()
    {
        return response()->json(Auth::user());
    }

    // Update profile
    public function update(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'mobile' => 'nullable|string|max:15',
        ]);

        $user->update($data);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }
}
