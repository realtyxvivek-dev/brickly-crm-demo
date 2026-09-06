<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostSaleDemand extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['percentage' => 'decimal:3', 'amount' => 'decimal:2', 'due_date' => 'date', 'reminder_log' => 'array'];
    public function postSaleCase() { return $this->belongsTo(PostSaleCase::class); }
    public function transactions() { return $this->hasMany(PostSaleTransaction::class, 'demand_id'); }
}
