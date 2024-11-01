<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'type',
        'amount',
        'balance_after',
        'client_id',
        'manager_id',
        'receiver_id',
        'sender_id',
        'status',
        'expires_at'

    ];

    // Relation avec le compte
    public function account()
    {
        return $this->belongsTo(Account::class);
    }


    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
