<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Notifications\LowBalanceNotification;
use Illuminate\Support\Facades\DB;
use Exception;

class TransactionObserver
{
    /**
     * Handle the Transaction "creating" event.
     */
    public function creating(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            // Lock the account row to prevent race conditions during concurrent requests
            $account = $transaction->account()->lockForUpdate()->first();

            if (!$account) {
                throw new Exception("Associated account not found.");
            }

            $amount = $transaction->amount;
            $newBalance = $account->balance;

            // Calculate new balance based on transaction type
            if ($transaction->type === 'deposit') {
                $newBalance += $amount;
            } elseif ($transaction->type === 'withdrawal' || $transaction->type === 'transfer') {
                $newBalance -= $amount;

                // Critical Rule: Check if balance falls below minimum_balance or goes negative
                if ($newBalance < $account->minimum_balance || $newBalance < 0) {
                    throw new Exception("Transaction denied: Insufficient funds or balance falls below minimum threshold.");
                }
            }

            // Update and save the account balance
            $account->balance = $newBalance;
            $account->save();

            // Trigger low balance notification if withdrawal/transfer drops balance below or equal to threshold
            if ($transaction->type === 'withdrawal' || $transaction->type === 'transfer') {
                if ($newBalance <= $account->minimum_balance) {
                    if ($account->user) {
                        $account->user->notify(new LowBalanceNotification($account));
                    }
                }
            }
        });
    }
}