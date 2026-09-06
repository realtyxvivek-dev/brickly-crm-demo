<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_number',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'resolved_at',
        'closed_at',
        'admin_note',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at'   => 'datetime',
    ];

    // Notification types
    public const TYPE_SUPPORT_TICKET = 'support_ticket';
    public const TYPE_SUPPORT_REPLY  = 'support_reply';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id')->orderBy('created_at');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id')->orderBy('created_at');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public static function generateTicketNumber(): string
    {
        $last = self::latest('id')->value('id') ?? 0;
        return 'TKT-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'open'        => '#3b82f6',
            'in_progress' => '#f59e0b',
            'resolved'    => '#10b981',
            'closed'      => '#6b7280',
            default       => '#6b7280',
        };
    }

    public function getStatusBgAttribute(): string
    {
        return match($this->status) {
            'open'        => '#dbeafe',
            'in_progress' => '#fef3c7',
            'resolved'    => '#d1fae5',
            'closed'      => '#f3f4f6',
            default       => '#f3f4f6',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low'    => '#10b981',
            'medium' => '#3b82f6',
            'high'   => '#f59e0b',
            'urgent' => '#ef4444',
            default  => '#6b7280',
        };
    }

    public function getPriorityBgAttribute(): string
    {
        return match($this->priority) {
            'low'    => '#d1fae5',
            'medium' => '#dbeafe',
            'high'   => '#fef3c7',
            'urgent' => '#fee2e2',
            default  => '#f3f4f6',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'resolved'    => 'Resolved',
            'closed'      => 'Closed',
            default       => ucfirst($this->status),
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'bug'             => 'Bug',
            'feature_request' => 'Feature Request',
            'question'        => 'Question',
            'account'         => 'Account',
            'other'           => 'Other',
            default           => ucfirst($this->category),
        };
    }
}
