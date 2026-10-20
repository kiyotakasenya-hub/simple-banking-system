<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class TransactionController extends Controller
{
    /**
     * Display a listing of the transactions.
     */
    public function index()
    {
        $transactions = Transaction::with('account')->latest()->paginate(10);
        return view('transactions.index', compact('transactions'));
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create()
    {
        $accounts = Account::all();
        return view('transactions.create', compact('accounts'));
    }

    /**
     * Store a newly created transaction in storage.
     */
    public function store(Request $request)
    {
        // 1. Standard Validation Rules
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:1',
        ]);

        $account = Account::findOrFail($request->account_id);

        // 2. Custom Validation (The Transfer/Withdrawal Scenario)
        // Check if the account has sufficient funds before saving
        if ($request->type === 'withdrawal') {
            $projectedBalance = $account->balance - $request->amount;
            
            if ($projectedBalance < $account->minimum_balance || $projectedBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient funds: This withdrawal violates the minimum balance requirement.'],
                ]);
            }
        }

        try {
            // 3. Create the Transaction 
            // (This automatically fires our TransactionObserver and DB::transaction)
            Transaction::create([
                'account_id' => $request->account_id,
                'type' => $request->type,
                'amount' => $request->amount,
                'reference_number' => $request->reference_number ?? 'REF-' . strtoupper(uniqid()),
                'notes' => $request->notes,
            ]);

            return redirect()->route('transactions.index')
                             ->with('success', 'Transaction processed successfully!');

        } catch (Exception $e) {
            // Catch observer errors or database rollbacks and return with error
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }
    }
}