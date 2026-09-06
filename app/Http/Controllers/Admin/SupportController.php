<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }

    public function index(Request $request)
    {
        $query = SupportTicket::with(['user.role'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $tickets = $query->paginate(20);

        $stats = [
            'total'       => SupportTicket::count(),
            'open'        => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved'    => SupportTicket::where('status', 'resolved')->count(),
            'urgent'      => SupportTicket::where('priority', 'urgent')->whereNotIn('status', ['resolved', 'closed'])->count(),
        ];

        return view('admin.support.index', compact('tickets', 'stats'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user.role', 'attachments', 'replies.user']);
        return view('admin.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'message'    => 'required|string|max:5000',
            'status'     => 'nullable|in:open,in_progress,resolved,closed',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $admin = $request->user();

        SupportTicketReply::create([
            'ticket_id'      => $ticket->id,
            'user_id'        => $admin->id,
            'message'        => $request->message,
            'is_admin_reply' => true,
        ]);

        $updates = [];
        if ($request->filled('status')) {
            $updates['status'] = $request->status;
            if ($request->status === 'resolved') $updates['resolved_at'] = now();
            if ($request->status === 'closed')   $updates['closed_at']   = now();
        }
        if ($request->filled('admin_note')) {
            $updates['admin_note'] = $request->admin_note;
        }
        if (!empty($updates)) {
            $ticket->update($updates);
        }

        // Notify ticket owner
        AppNotification::create([
            'user_id'    => $ticket->user_id,
            'type'       => SupportTicket::TYPE_SUPPORT_REPLY,
            'title'      => 'Support Ticket Updated',
            'message'    => 'Admin replied to your ticket: ' . $ticket->ticket_number,
            'action_url' => route('support.show', $ticket),
        ]);

        return redirect()->route('admin.support.show', $ticket)
            ->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate(['status' => 'required|in:open,in_progress,resolved,closed']);

        $updates = ['status' => $request->status];
        if ($request->status === 'resolved') $updates['resolved_at'] = now();
        if ($request->status === 'closed')   $updates['closed_at']   = now();

        $ticket->update($updates);

        // Notify ticket owner
        AppNotification::create([
            'user_id'    => $ticket->user_id,
            'type'       => SupportTicket::TYPE_SUPPORT_REPLY,
            'title'      => 'Ticket Status Updated',
            'message'    => 'Your ticket ' . $ticket->ticket_number . ' is now ' . ucfirst(str_replace('_', ' ', $request->status)),
            'action_url' => route('support.show', $ticket),
        ]);

        return redirect()->route('admin.support.show', $ticket)
            ->with('success', 'Status updated to ' . ucfirst(str_replace('_', ' ', $request->status)) . '.');
    }

    public function destroy(SupportTicket $ticket)
    {
        $ticket->delete();

        return redirect()->route('admin.support.index')
            ->with('success', 'Ticket ' . $ticket->ticket_number . ' deleted.');
    }
}
