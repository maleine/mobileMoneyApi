<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // ID auto-incrémenté
            $table->string('name'); // Nom de l'utilisateur
            $table->string('phone_number')->unique(); // Numéro de téléphone unique
            $table->string('pin_code'); // Code PIN
            $table->enum('user_type', ['client', 'manager']); // Type d'utilisateur
            $table->decimal('balance', 10, 2)->default(0.00); // Solde, seulement pour les clients
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
