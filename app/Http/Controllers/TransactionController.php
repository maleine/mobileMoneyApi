<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TransactionController extends Controller
{
    public function deposit(Request $request)
    {
        $request->validate([
            'manager_phone' => 'required|string',
            'manager_pin' => 'required|string|min:4|max:6',
            'client_phone' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Vérification du manager
            $manager = User::where('phone_number', $request->manager_phone)->first();
            if (!$manager || !Hash::check($request->manager_pin, $manager->pin_code) || $manager->user_type !== 'manager') {
                return response()->json(['error' => 'Manager non valide ou code PIN incorrect.'], 401);
            }

            // Vérification du client
            $client = User::where('phone_number', $request->client_phone)->first();
            if (!$client || $client->user_type !== 'client') {
                return response()->json(['error' => 'Client non valide.'], 404);
            }

            // Vérifier si un dépôt similaire a été effectué dans les 5 dernières minutes
            $lastTransaction = Transaction::where('client_id', $client->id)
                ->where('manager_id', $manager->id)
                ->where('type', 'deposit')
                ->where('amount', $request->amount)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if ($lastTransaction) {
                return response()->json(['error' => 'Une transaction identique a été effectuée il y a moins de 5 minutes.'], 429);
            }

            // Enregistrement de la transaction de dépôt
            $transaction = new Transaction([
                'client_id' => $client->id,
                'manager_id' => $manager->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'balance_after' => $client->balance + $request->amount,
            ]);

            // Mise à jour du solde du client
            $client->balance += $request->amount;
            $client->save();

            // Sauvegarde de la transaction
            $transaction->save();

            DB::commit();
            return response()->json([
                'message' => 'Dépôt réussi',
                'transaction' => $transaction
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors du dépôt : ' . $e->getMessage()], 500);
        }
    }

    public function initiateWithdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'manager_phone' => 'required|string',
            'client_phone' => 'required|string',
        ]);

        $client = User::where('phone_number', $request->client_phone)->first();
        $amount = $request->input('amount');

        if ($amount > $client->balance) {
            return response()->json(['error' => 'Solde insuffisant'], 400);
        }

        $manager = User::where('phone_number', $request->manager_phone)->first();
        if (!$manager || $manager->user_type !== 'manager') {
            return response()->json(['error' => 'Informations du manager invalides'], 400);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'client_id' => $client->id,
                'manager_id' => $manager->id,
                'type' => 'withdraw',
                'amount' => $amount,
                'status' => 'en attente',
                'balance_after' => $client->balance,
                'expires_at' => now()->addMinutes(15),
            ]);

            DB::commit();
            return response()->json(['message' => 'Retrait initié. En attente de validation du code PIN.', 'transaction_id' => $transaction->id], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de l\'initiation du retrait : ' . $e->getMessage()], 500);
        }
    }

    public function confirmWithdraw(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer',
            'client_pin' => 'required|string|min:4|max:6',
        ]);

        $transaction = Transaction::find($request->transaction_id);

        if (!$transaction || $transaction->status !== 'en attente') {
            return response()->json(['error' => 'Transaction non trouvée ou déjà validée.'], 404);
        }

        if ($transaction->expires_at < now()) {
            return response()->json(['error' => 'Transaction expirée.'], 400);
        }

        $client = User::find($transaction->client_id);

        if (!Hash::check($request->client_pin, $client->pin_code)) {
            return response()->json(['error' => 'Code PIN incorrect.'], 400);
        }

        DB::beginTransaction();
        try {
            $clientBalance = $client->balance - $transaction->amount;
            User::where('id', $client->id)->update(['balance' => $clientBalance]);

            $transaction->update([
                'status' => 'effectué',
                'balance_after' => $clientBalance,
            ]);

            User::where('id', $transaction->manager_id)->increment('balance', $transaction->amount);

            DB::commit();
            return response()->json(['message' => 'Retrait validé avec succès.', 'transaction' => $transaction], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la validation du retrait : ' . $e->getMessage()], 500);
        }
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'sender_phone' => 'required|string',
            'receiver_phone' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'sender_pin' => 'required|string|min:4|max:6',
        ]);

        DB::beginTransaction();
        try {
            $sender = User::where('phone_number', $request->sender_phone)->first();
            if (!$sender) {
                return response()->json(['error' => 'Expéditeur non trouvé.'], 404);
            }

            if ($sender->user_type === 'manager') {
                return response()->json(['error' => 'Vous n\'êtes pas autorisé à effectuer des transferts.'], 403);
            }

            if (!Hash::check($request->sender_pin, $sender->pin_code)) {
                return response()->json(['error' => 'Code PIN incorrect.'], 401);
            }

            $receiver = User::where('phone_number', $request->receiver_phone)->first();
            if (!$receiver) {
                return response()->json(['error' => 'Destinataire non trouvé.'], 404);
            }

            if ($sender->balance < $request->amount) {
                return response()->json(['error' => 'Solde insuffisant.'], 400);
            }

            $lastTransaction = Transaction::where('sender_id', $sender->id)
                ->where('receiver_id', $receiver->id)
                ->where('amount', $request->amount)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if ($lastTransaction) {
                return response()->json(['error' => 'Vous devez attendre 5 minutes avant de répéter cette transaction.'], 400);
            }

            $sender->balance -= $request->amount;
            $receiver->balance += $request->amount;

            $senderTransaction = new Transaction([
                'type' => 'transfer',
                'amount' => $request->amount,
                'balance_after' => $sender->balance,
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
            ]);
            $senderTransaction->save();

            $sender->save();
            $receiver->save();

            DB::commit();

            $transactionDetails = [
                'sender_id' => $sender->id,
                'sender_phone' => $sender->phone_number,
                'receiver_id' => $receiver->id,
                'receiver_phone' => $receiver->phone_number,
                'amount' => $request->amount,
                'balance_after_sender' => $sender->balance,
                'balance_after_receiver' => $receiver->balance,
                'transaction_type' => 'transfer',
                'timestamp' => now(),
            ];

            return response()->json(['message' => 'Transfert réussi', 'transaction' => $transactionDetails], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors du transfert: ' . $e->getMessage()], 500);
        }
    }

    public function getClientTransactions(Request $request)
    {
        $request->validate([
            'client_phone' => 'required|string',
        ]);

        $client = User::where('phone_number', $request->client_phone)->first();

        if (!$client) {
            return response()->json(['error' => 'Client non trouvé.'], 404);
        }

        $transactions = Transaction::where('client_id', $client->id)
            ->orWhere('sender_id', $client->id)
            ->orWhere('receiver_id', $client->id)
            ->get();

        return response()->json(['transactions' => $transactions], 200);
    }
}
