<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone_number', 'balance','manager_id','pin_code'];

    // Dans le modèle Client
public function manager() {
    return $this->belongsTo(Manager::class);
}
}
