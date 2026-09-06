<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'user_id',
        'assigned_to',
        'status',
        'phone_number',
        'contact_name',
        'lead_id',
        'meta_waba_account_id',
        'last_inbound_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user that owns the conversation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the lead associated with this conversation (if phone matches)
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function metaWabaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaWabaAccount::class, 'meta_waba_account_id');
    }

    /**
     * Get all messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsAppConversationNote::class, 'conversation_id')->latest();
    }

    /**
     * Get the latest message in this conversation
     */
    public function getLatestMessage()
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount(): int
    {
        return $this->messages()
            ->where('direction', 'received')
            ->where('status', '!=', 'read')
            ->count();
    }

    /**
     * Mark all received messages as read
     */
    public function markAsRead(): void
    {
        $this->messages()
            ->where('direction', 'received')
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);
    }
}
