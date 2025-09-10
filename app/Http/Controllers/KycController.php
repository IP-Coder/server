<?php

namespace App\Http\Controllers;

use App\Models\KycSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    public function my(Request $req)
    {
        $latest = KycSubmission::where('user_id', $req->user()->id)
            ->latest('id')->first();

        if (!$latest) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'status' => $latest->status,
            'aadhaar_last4' => $latest->aadhaar_last4,
            'submitted_at' => $latest->created_at,
            'review_notes' => $latest->review_notes,
            'document_url' => $latest->document_path ? Storage::url($latest->document_path) : null,
            'selfie_url'   => $latest->selfie_path ? Storage::url($latest->selfie_path) : null,
        ]);
    }

    public function submit(Request $req)
    {
        $req->validate([
            'aadhaar_number' => ['required', 'regex:/^\d{12}$/'],
            'document' => ['required', 'file', 'image', 'max:8192'], // 8 MB
            'selfie'   => ['required', 'file', 'image', 'max:8192'],
        ]);

        $user = $req->user();

        $dir = "kyc/{$user->id}";
        $docPath = $req->file('document')->storePublicly($dir, 'public');
        $selfiePath = $req->file('selfie')->storePublicly($dir, 'public');

        $aadhaar = $req->input('aadhaar_number');
        $sub = KycSubmission::create([
            'user_id' => $user->id,
            'aadhaar_hash' => hash('sha256', $aadhaar),
            'aadhaar_last4' => substr($aadhaar, -4),
            'document_path' => $docPath,
            'selfie_path'   => $selfiePath,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'KYC submitted successfully.',
            'id' => $sub->id,
        ], 201);
    }

    // Optional: admin review endpoint(s) can be added later
}
