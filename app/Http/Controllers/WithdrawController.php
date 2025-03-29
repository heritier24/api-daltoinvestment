<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WithdrawController extends Controller
{
    public function withdrawals(Request $request)
    {
        // $user = $request->user();
        $perPage = $request->query('limit', 5);
        $page = $request->query('page', 1);
        $search = $request->query('search', '');

        $query = Withdrawal::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('network', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%"); // Using ID as a proxy for reference number
            });
        }

        $withdrawals = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'withdrawals' => $withdrawals->map(function ($withdrawal) {
                    return [
                        'id' => $withdrawal->id,
                        'date' => $withdrawal->created_at->format('d/m/Y'),
                        'reference_number' => 'WDR-' . $withdrawal->id, // Generate a reference number
                        'network' => $withdrawal->network,
                        'networkaddress' => $withdrawal->user->networkaddress,
                        'amount' => number_format($withdrawal->amount, 2, '.', '') . ' USDT',
                        'status' => $withdrawal->status,
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'total_pages' => $withdrawals->lastPage(),
                    'total_items' => $withdrawals->total(),
                    'limit' => $withdrawals->perPage(),
                ],
            ],
        ]);
    }
}
