<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        return response()->json([
            'total_accounts' => Account::count(),
            'total_money' => Account::sum('balance'),
            'total_transactions_today' => Transaction::whereDate('created_at', Carbon::today())->count(),
        ]);
    }
}