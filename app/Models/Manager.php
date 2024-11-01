<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manager extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone_number', 'balance','pin_code'];

    // Dans le modèle Manager
public function clients() {
    return $this->hasMany(Client::class);
}
}
