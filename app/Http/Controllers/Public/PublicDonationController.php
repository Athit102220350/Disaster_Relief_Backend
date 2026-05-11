<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicDonationController extends Controller
{
    /**
     * Create a public bank transfer donation (no auth required).
     */
    public function storeBankTransfer(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'donor_name' => 'required|string|max:100',
            'donor_email' => 'required|email|max:150',
            'amount' => 'required|numeric|min:1|max:999999999',
            'note' => 'nullable|string|max:500',
        ]);

        if (!empty($validated['campaign_id'])) {
            $campaign = Campaign::where('status', 'open')->find($validated['campaign_id']);
            if (!$campaign) {
                return response()->json([
                    'message' => 'Campaign not found or closed',
                ], 400);
            }
        }

        try {
            $donation = DB::transaction(function () use ($validated) {
                return Donation::create([
                    'user_id' => null,
                    'campaign_id' => $validated['campaign_id'] ?? null,
                    'donor_name' => $validated['donor_name'],
                    'donor_email' => $validated['donor_email'],
                    'amount' => $validated['amount'],
                    'method' => 'bank_transfer',
                    'note' => $validated['note'] ?? null,
                    'status' => 'pending',
                ]);
            });

            return response()->json([
                'message' => 'Donation created successfully',
                'data' => $donation->load('campaign'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create donation: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate VietQR payload for public bank transfer donations.
     */
    public function generateQR(Request $request, $donationId)
    {
        $donation = Donation::where('id', $donationId)->first();

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
}
