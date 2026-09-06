<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SupportTicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = SupportTicket::where('user_id', $user->id)->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(15);

        return view('support.index', compact('tickets'));
    }

    public function create()
    {
        return view('support.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'category'    => 'required|in:bug,feature_request,question,account,other',
            'priority'    => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240',
            'voice_data'  => 'nullable|string',
            'video_data'  => 'nullable|string',
        ]);

        $user = $request->user();

        $ticket = SupportTicket::create([
            'user_id'       => $user->id,
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'title'         => $request->title,
            'description'   => $request->description,
            'category'      => $request->category,
            'priority'      => $request->priority,
            'status'        => 'open',
        ]);

        // Handle file/image attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("support/{$ticket->id}", 'public');
                $type = str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'document';
                SupportTicketAttachment::create([
                    'ticket_id'       => $ticket->id,
                    'uploaded_by'     => $user->id,
                    'file_path'       => $path,
                    'file_name'       => $file->getClientOriginalName(),
                    'file_size'       => $file->getSize(),
                    'mime_type'       => $file->getMimeType(),
                    'attachment_type' => $type,
                ]);
            }
        }

        // Handle voice recording
        if ($request->filled('voice_data')) {
            $this->saveMediaBlob($request->voice_data, $ticket->id, $user->id, 'voice', 'audio/webm', 'voice_' . time() . '.webm');
        }

        // Handle video recording
        if ($request->filled('video_data')) {
            $this->saveMediaBlob($request->video_data, $ticket->id, $user->id, 'video', 'video/webm', 'video_' . time() . '.webm');
        }

        // Notify all admins
        $this->notifyAdmins($ticket, $user);

        return redirect()->route('support.show', $ticket)
            ->with('success', 'Ticket ' . $ticket->ticket_number . ' submitted successfully.');
    }

    public function show(SupportTicket $ticket)
    {
        $user = request()->user();

        if ($ticket->user_id !== $user->id && !$user->isAdmin()) {
            abort(403);
        }

        $ticket->load(['attachments', 'replies.user']);

        return view('support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();

        if ($ticket->user_id !== $user->id) {
            abort(403);
        }

        $request->validate(['message' => 'required|string|max:5000']);

        SupportTicketReply::create([
            'ticket_id'      => $ticket->id,
            'user_id'        => $user->id,
            'message'        => $request->message,
            'is_admin_reply' => false,
        ]);

        // If ticket was closed/resolved by admin, reopen it
        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open']);
        }

        // Notify admins of user reply
        $this->notifyAdmins($ticket, $user, 'User replied to ticket: ' . $ticket->ticket_number);

        return redirect()->route('support.show', $ticket)
            ->with('success', 'Reply sent.');
    }

    private function saveMediaBlob(string $base64, int $ticketId, int $userId, string $type, string $mimeType, string $filename): void
    {
        try {
            $data = preg_replace('/^data:[^;]+;base64,/', '', $base64);
            $decoded = base64_decode($data, true);
            if ($decoded === false) return;

            $path = "support/{$ticketId}/{$filename}";
            Storage::disk('public')->put($path, $decoded);

            SupportTicketAttachment::create([
                'ticket_id'       => $ticketId,
                'uploaded_by'     => $userId,
                'file_path'       => $path,
                'file_name'       => $filename,
                'file_size'       => strlen($decoded),
                'mime_type'       => $mimeType,
                'attachment_type' => $type,
            ]);
        } catch (\Exception $e) {
            Log::warning("Support {$type} save failed: " . $e->getMessage());
        }
    }

    private function notifyAdmins(SupportTicket $ticket, User $fromUser, ?string $customMessage = null): void
    {
        $admins = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->get();
        foreach ($admins as $admin) {
            AppNotification::create([
                'user_id'    => $admin->id,
                'type'       => SupportTicket::TYPE_SUPPORT_TICKET,
                'title'      => 'New Support Ticket',
                'message'    => $customMessage ?? ($fromUser->name . ' submitted: ' . $ticket->title),
                'action_url' => route('admin.support.show', $ticket),
            ]);
        }
    }
}
