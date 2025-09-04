<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    /**
     * POST /support/tickets
     * Create a new support ticket (with optional attachments)
     */
    public function store(Request $request)
    {
        $user = $request->user(); // nullable if you expose this publicly

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'email'      => ['required', 'email', 'max:255'],
            'phone_code' => ['required', 'string', 'max:10'],
            'phone'      => ['required', 'string', 'max:30'],
            'subject'    => ['nullable', 'string', 'max:150'],
            'message'    => ['required', 'string', 'max:5000'],
            'source'     => ['nullable', 'string', 'max:50'], // e.g. 'web'
            // allow multiple files with the "attachments[]" field
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'], // 10MB each
        ]);

        // Generate a human-readable ticket number
        $ticketNo = 'T' . date('ymd') . '-' . Str::upper(Str::random(6));

        // Save attachments (if any)
        $paths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store("support/{$ticketNo}", 'public'); // storage/app/public/...
            }
        }

        // Create ticket
        $ticket = SupportTicket::create([
            'user_id'        => $user?->id,
            'ticket_no'      => $ticketNo,
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone_code'     => $data['phone_code'],
            'phone'          => $data['phone'],
            'subject'        => $data['subject'] ?? null,
            'message'        => $data['message'],
            'attachments'    => $paths, // casted to array on the model
            'source'         => $data['source'] ?? 'web',
            'status'         => 'open', // open | pending | resolved | closed
        ]);

        // (Optional) Email notify support
        try {
            $to = config('support.email') ?: env('SUPPORT_EMAIL', 'support@yourdomain.com');
            if ($to) {
                $body =
                    "New support ticket: {$ticket->ticket_no}\n\n" .
                    "From: {$ticket->name} <{$ticket->email}>\n" .
                    "Phone: {$ticket->phone_code} {$ticket->phone}\n" .
                    "Subject: " . ($ticket->subject ?? '(none)') . "\n\n" .
                    "Message:\n{$ticket->message}\n";
                Mail::raw($body, function ($m) use ($to, $ticket) {
                    $m->to($to)->subject("[Support] {$ticket->ticket_no} " . ($ticket->subject ?? 'New ticket'));
                });
            }
        } catch (\Throwable $e) {
            // swallow mail failure — ticket creation should not fail because of email
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'ticket'  => $this->present($ticket),
        ], 201);
    }

    /**
     * GET /support/tickets/my
     * List tickets created by the current user
     */
    public function my(Request $request)
    {
        $user = $request->user();

        $rows = SupportTicket::query()
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user, fn($q) => $q->whereNull('user_id')) // if you ever allow guest tickets
            ->orderByDesc('id')
            ->get()
            ->map(fn($t) => $this->present($t))
            ->values();

        return response()->json($rows);
    }

    /**
     * PATCH /support/tickets/{id}/status   (admin-only – wire a policy/middleware)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:open,pending,resolved,closed'],
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $request->input('status');
        $ticket->save();

        return response()->json([
            'success' => true,
            'ticket'  => $this->present($ticket),
        ]);
    }

    /** Helper: format a ticket payload for API responses */
    private function present(SupportTicket $t): array
    {
        $files = [];
        foreach ($t->attachments ?? [] as $p) {
            $files[] = [
                'path' => $p,
                // use Storage::url for intelephense-friendly public URLs
                'url'  => Storage::url($p),
            ];
        }

        return [
            'id'         => $t->id,
            'ticket_no'  => $t->ticket_no,
            'status'     => $t->status,
            'name'       => $t->name,
            'email'      => $t->email,
            'phone_code' => $t->phone_code,
            'phone'      => $t->phone,
            'subject'    => $t->subject,
            'message'    => $t->message,
            'attachments' => $files,
            'source'     => $t->source,
            'created_at' => optional($t->created_at)->toIso8601String(),
            'updated_at' => optional($t->updated_at)->toIso8601String(),
        ];
    }
}
