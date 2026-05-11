<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationController extends Controller
{
    /**
     * Get list of all campaigns (F28)
     */
    public function campaigns(Request $request)
    {
        $campaigns = Campaign::where('status', 'open')
            ->with('coordinator')
            ->withCount('donations')
            ->withSum(['donations' => function ($query) {
                $query->where('status', 'confirmed');
            }], 'amount')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Campaigns retrieved successfully',
            'data' => $campaigns,
        ], 200);
    }

    /**
     * Create a donation (F29)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'donor_name' => 'required|string|max:100',
            'donor_email' => 'required|email|max:150',
            'amount' => 'required|numeric|min:1|max:999999999',
            'method' => 'required|in:bank_transfer,cash,in_kind',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $donation = DB::transaction(function () use ($validated, $user) {
                $donation = Donation::create([
                    'user_id' => $user->id,
                    'campaign_id' => $validated['campaign_id'] ?? null,
                    'donor_name' => $validated['donor_name'],
                    'donor_email' => $validated['donor_email'],
                    'amount' => $validated['amount'],
                    'method' => $validated['method'],
                    'note' => $validated['note'] ?? null,
                    'status' => 'pending',
                ]);

                if ($donation->campaign_id) {
                    $campaign = Campaign::lockForUpdate()->find($donation->campaign_id);

                    if ($campaign && in_array($validated['method'], ['cash', 'in_kind'], true)) {
                        $donation->update(['status' => 'confirmed']);
                        $campaign->increment('current_amount', $validated['amount']);
                    }
                }

                return $donation;
            });

            return response()->json([
                'message' => 'Donation created successfully',
                'data' => $donation->load('campaign', 'paymentTransactions'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create donation: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get citizen's donation history (F30)
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $donations = Donation::where('user_id', $user->id)
            ->with('campaign', 'paymentTransactions')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Donation history retrieved successfully',
            'data' => $donations,
        ], 200);
    }

    /**
     * Get campaign details with donation info (F28)
     */
    public function showCampaign(Request $request, $id)
    {
        $campaign = Campaign::with('coordinator')
            ->withCount('donations')
            ->withSum(['donations' => function ($query) {
                $query->where('status', 'confirmed');
            }], 'amount')
            ->find($id);

        if (!$campaign) {
            return response()->json([
                'message' => 'Campaign not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Campaign retrieved successfully',
            'data' => $campaign,
        ], 200);
    }

    /**
     * Get donation details
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $donation = Donation::where('id', $id)
            ->where('user_id', $user->id)
            ->with('campaign', 'paymentTransactions')
            ->first();

        if (!$donation) {
            return response()->json([
                'message' => 'Donation not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Donation retrieved successfully',
            'data' => $donation,
        ], 200);
    }

    /**
     * Get donation statistics (F31)
     */
    public function statistics(Request $request)
    {
        $user = $request->user();

        // User's donation stats
        $userDonations = Donation::where('user_id', $user->id)->get();
        $confirmedDonations = $userDonations->where('status', 'confirmed');

        $stats = [
            'total_donated' => (float) $confirmedDonations->sum('amount'),
            'confirmed_donations' => $confirmedDonations->count(),
            'pending_donations' => $userDonations->where('status', 'pending')->count(),
            'failed_donations' => $userDonations->where('status', 'failed')->count(),
            'total_campaigns' => $confirmedDonations->pluck('campaign_id')->filter()->unique()->count(),
            'donations_by_method' => $confirmedDonations->groupBy('method')
                ->map(function ($group) {
                    return $group->count();
                })
                ->toArray(),
        ];

        return response()->json([
            'message' => 'Donation statistics retrieved successfully',
            'data' => $stats,
        ], 200);
    }

    /**
     * Generate VietQR payload for bank transfer donations.
     */
    public function generateQR(Request $request, $donationId)
    {
        $user = $request->user();

        $donation = Donation::where('id', $donationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$donation) {
            return response()->json([
                'message' => 'Donation not found',
            ], 404);
        }

        if ($donation->method !== 'bank_transfer') {
            return response()->json([
                'message' => 'QR is only available for bank transfer donations',
            ], 400);
        }

        if ($donation->status === 'confirmed') {
            return response()->json([
                'message' => 'Donation already confirmed',
            ], 400);
        }

        $transferContent = 'UNGHO-' . $donation->id;

        $transaction = PaymentTransaction::firstOrCreate(
            ['donation_id' => $donation->id],
            [
                'bank_transaction_id' => 'PENDING-' . $donation->id . '-' . Str::upper(Str::random(8)),
                'payment_gateway' => 'bank_transfer',
                'actual_amount' => $donation->amount,
                'status' => 'pending',
                'transfer_content' => $transferContent,
                'transacted_at' => now(),
            ]
        );

        $bankBin = env('VIETQR_BANK_BIN', '970422');
        $accountNo = env('VIETQR_ACCOUNT_NO', '0000000000');
        $accountName = env('VIETQR_ACCOUNT_NAME', 'DISASTER RELIEF FUND');
        $template = env('VIETQR_TEMPLATE', 'compact2');

        $qrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-%s.png?amount=%s&addInfo=%s&accountName=%s',
            $bankBin,
            $accountNo,
            $template,
            (int) $donation->amount,
            urlencode($transaction->transfer_content),
            urlencode($accountName)
        );

        return response()->json([
            'message' => 'VietQR generated successfully',
            'data' => [
                'donation_id' => $donation->id,
                'amount' => $donation->amount,
                'transfer_content' => $transaction->transfer_content,
                'bank' => [
                    'bank_bin' => $bankBin,
                    'account_no' => $accountNo,
                    'account_name' => $accountName,
                ],
                'qr_url' => $qrUrl,
            ],
        ], 200);
    }

    /**
     * Handle SePay webhook callback to auto-confirm bank transfers.
     */
    public function webhook(Request $request)
    {
        $secret = env('SEPAY_WEBHOOK_TOKEN');
        $authorization = $request->header('Authorization');
        $receivedSecret = null;

        if ($authorization && preg_match('/Apikey\s+(.+)/i', $authorization, $matches)) {
            $receivedSecret = trim($matches[1]);
        }

        if (!$receivedSecret) {
            return response()->json([
                'message' => 'Missing Authorization Apikey token',
            ], 401);
        }

        if ($secret && $receivedSecret !== $secret) {
            return response()->json([
                'message' => 'Invalid webhook token',
            ], 401);
        }

        $validated = $request->validate([
            'bank_transaction_id' => 'required|string|max:100',
            'amount' => 'required|numeric|min:1',
            'transfer_content' => 'required|string|max:500',
            'status' => 'nullable|string',
        ]);

        if (isset($validated['status']) && strtolower($validated['status']) !== 'success') {
            return response()->json([
                'message' => 'Webhook ignored: transaction is not successful',
            ], 200);
        }

        try {
            $result = DB::transaction(function () use ($validated) {
                $existing = PaymentTransaction::where('bank_transaction_id', $validated['bank_transaction_id'])->first();
                if ($existing && $existing->status === 'success') {
                    return ['already_processed' => true, 'transaction' => $existing];
                }

                $transaction = PaymentTransaction::query()
                    ->where('status', 'pending')
                    ->where('transfer_content', $validated['transfer_content'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (!$transaction) {
                    return ['not_found' => true];
                }

                $donation = Donation::lockForUpdate()->find($transaction->donation_id);
                if (!$donation) {
                    return ['not_found' => true];
                }

                if ((float) $validated['amount'] !== (float) $donation->amount) {
                    return ['amount_mismatch' => true];
                }

                $transaction->update([
                    'bank_transaction_id' => $validated['bank_transaction_id'],
                    'actual_amount' => $validated['amount'],
                    'status' => 'success',
                    'confirmed_at' => now(),
                ]);

                if ($donation->status !== 'confirmed') {
                    $donation->update(['status' => 'confirmed']);
                    if ($donation->campaign_id) {
                        Campaign::where('id', $donation->campaign_id)
                            ->lockForUpdate()
                            ->first()?->increment('current_amount', $validated['amount']);
                    }
                }

                return ['transaction' => $transaction];
            });

            if (isset($result['not_found'])) {
                return response()->json([
                    'message' => 'No pending transaction matched this webhook',
                ], 404);
            }

            if (isset($result['amount_mismatch'])) {
                return response()->json([
                    'message' => 'Webhook amount does not match donation amount',
                ], 400);
            }

            if (isset($result['already_processed'])) {
                return response()->json([
                    'message' => 'Webhook already processed',
                    'data' => $result['transaction'],
                ], 200);
            }

            return response()->json([
                'message' => 'Webhook processed successfully',
                'data' => $result['transaction'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process webhook: ' . $e->getMessage(),
            ], 400);
        }
    }
}
