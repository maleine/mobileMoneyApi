<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'phone_number' => 'required|string|unique:users',
            'pin_code' => 'required|string|min:4|max:6',
            'user_type' => 'required|in:client,manager',
            'balance' => 'required|numeric|min:0',
        ]);

        // Hacher le code PIN avant de créer l'utilisateur
        $userData = $request->all();
        $userData['pin_code'] = Hash::make($request->pin_code);

        $user = User::create($userData);

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }



}
