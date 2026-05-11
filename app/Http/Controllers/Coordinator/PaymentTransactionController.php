<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentTransactionController extends Controller
{
    /**
     * Get all payment transactions for coordinator (F32)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PaymentTransaction::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment gateway
        if ($request->has('gateway')) {
            $query->where('payment_gateway', $request->gateway);
        }

        $transactions = $query->with('donation.campaign')
            ->orderBy('transacted_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Payment transactions retrieved successfully',
            'data' => $transactions,
        ], 200);
    }

    /**
     * Get transaction details
     */
    public function show(Request $request, $id)
    {
        $transaction = PaymentTransaction::with('donation.campaign', 'donation.user')
            ->find($id);

        if (!$transaction) {
            return response()->json([
                'message' => 'Payment transaction not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Payment transaction retrieved successfully',
            'data' => $transaction,
        ], 200);
    }

    /**
     * Create/record a payment transaction (F32)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'donation_id' => 'required|exists:donations,id',
            'bank_transaction_id' => 'required|string|max:100',
            'payment_gateway' => 'required|in:VNPay,Momo,bank_transfer',
            'actual_amount' => 'required|numeric|min:1',
            'transfer_content' => 'nullable|string|max:500',
        ]);

        try {
            $donation = Donation::find($validated['donation_id']);

            if (!$donation) {
                return response()->json([
                    'message' => 'Donation not found',
                ], 404);
            }

            // Check if transaction already exists
            if (PaymentTransaction::where('bank_transaction_id', $validated['bank_transaction_id'])->exists()) {
                return response()->json([
                    'message' => 'Transaction already recorded',
                ], 400);
            }

            $transaction = PaymentTransaction::create([
                'donation_id' => $validated['donation_id'],
                'bank_transaction_id' => $validated['bank_transaction_id'],
                'payment_gateway' => $validated['payment_gateway'],
                'actual_amount' => $validated['actual_amount'],
                'status' => 'pending',
                'transfer_content' => $validated['transfer_content'] ?? null,
                'transacted_at' => now(),
            ]);

            return response()->json([
                'message' => 'Payment transaction recorded successfully',
                'data' => $transaction->load('donation'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record transaction: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verify/confirm a payment transaction (F32)
     */
    public function confirm(Request $request, $id)
    {
        $validated = $request->validate([
            'confirmed_amount' => 'required|numeric|min:1',
        ]);

        try {
            $result = DB::transaction(function () use ($id, $validated) {
                $transaction = PaymentTransaction::lockForUpdate()->find($id);

                if (!$transaction) {
                    return ['error' => 'Payment transaction not found', 'status' => 404];
                }

                if ($transaction->status !== 'pending') {
                    return ['error' => 'Transaction is not in pending status', 'status' => 400];
                }

                $donation = Donation::lockForUpdate()->find($transaction->donation_id);
                if (!$donation) {
                    return ['error' => 'Donation not found', 'status' => 404];
                }

                if ((float) $validated['confirmed_amount'] !== (float) $donation->amount) {
                    return ['error' => 'Confirmed amount does not match donation amount', 'status' => 400];
                }

                $transaction->update([
                    'status' => 'success',
                    'confirmed_at' => now(),
                    'actual_amount' => $validated['confirmed_amount'],
                ]);

                if ($donation->status !== 'confirmed') {
                    $donation->update(['status' => 'confirmed']);

                    if ($donation->campaign_id) {
                        $donation->campaign()->lockForUpdate()->first()?->increment('current_amount', $validated['confirmed_amount']);
                    }
                }

                return ['transaction' => $transaction, 'status' => 200];
            });

            if (isset($result['error'])) {
                return response()->json([
                    'message' => $result['error'],
                ], $result['status']);
            }

            return response()->json([
                'message' => 'Payment confirmed successfully',
                'data' => $result['transaction']->load('donation.campaign'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to confirm payment: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject/fail a payment transaction (F32)
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $transaction = PaymentTransaction::find($id);

            if (!$transaction) {
                return response()->json([
                    'message' => 'Payment transaction not found',
                ], 404);
            }

            if ($transaction->status !== 'pending') {
                return response()->json([
                    'message' => 'Transaction is not in pending status',
                ], 400);
            }

            // Update transaction and donation status
            $transaction->update(['status' => 'failed']);
            $transaction->donation->update(['status' => 'failed']);

            return response()->json([
                'message' => 'Payment rejected successfully',
                'data' => $transaction->load('donation'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject payment: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment statistics (F32)
     */
    public function statistics(Request $request)
    {
        $transactions = PaymentTransaction::all();

        $stats = [
            'total_transactions' => $transactions->count(),
            'successful_transactions' => $transactions->where('status', 'success')->count(),
            'pending_transactions' => $transactions->where('status', 'pending')->count(),
            'failed_transactions' => $transactions->where('status', 'failed')->count(),
            'total_amount_received' => (float) $transactions->where('status', 'success')->sum('actual_amount'),
            'pending_amount' => (float) $transactions->where('status', 'pending')->sum('actual_amount'),
            'by_gateway' => $transactions->groupBy('payment_gateway')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_amount' => (float) $group->where('status', 'success')->sum('actual_amount'),
                    ];
                })
                ->toArray(),
        ];

        return response()->json([
            'message' => 'Payment statistics retrieved successfully',
            'data' => $stats,
        ], 200);
    }
}
