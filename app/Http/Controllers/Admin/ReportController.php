<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\ReliefRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Summary for downloadable reports.
     */
    public function summary(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $summary = [
            'range' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ],
            'relief_requests' => [
                'total' => ReliefRequest::whereBetween('created_at', [$from, $to])->count(),
                'completed' => ReliefRequest::whereBetween('created_at', [$from, $to])->where('status', 'completed')->count(),
            ],
            'donations' => [
                'confirmed_count' => Donation::whereBetween('created_at', [$from, $to])->where('status', 'confirmed')->count(),
                'confirmed_amount' => (float) Donation::whereBetween('created_at', [$from, $to])->where('status', 'confirmed')->sum('amount'),
            ],
            'distributions' => [
                'delivered_count' => Distribution::whereBetween('created_at', [$from, $to])->where('status', 'delivered')->count(),
                'delivered_value' => (float) Distribution::whereBetween('created_at', [$from, $to])->where('status', 'delivered')->sum('total_value'),
            ],
            'campaigns' => [
                'created_count' => Campaign::whereBetween('created_at', [$from, $to])->count(),
            ],
        ];

        return response()->json([
            'message' => 'Report summary retrieved successfully',
            'data' => $summary,
        ], 200);
    }

    /**
     * Export CSV report by type.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:donations,relief_requests,distributions,campaigns',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'format' => 'nullable|in:csv,excel,pdf',
            'region' => 'nullable|string|max:120',
            'disaster_type' => 'nullable|in:lu_lut,sat_lo,bao,chay,khac',
            'status' => 'nullable|string|max:50',
        ]);

        [$from, $to] = $this->resolveDateRange($request);

        $filters = [
            'region' => $validated['region'] ?? null,
            'disaster_type' => $validated['disaster_type'] ?? null,
            'status' => $validated['status'] ?? null,
        ];

        $format = $validated['format'] ?? 'csv';

        [$filename, $headers, $rows] = match ($validated['type']) {
            'donations' => $this->buildDonationsReport($from, $to, $filters),
            'relief_requests' => $this->buildReliefRequestsReport($from, $to, $filters),
            'distributions' => $this->buildDistributionsReport($from, $to, $filters),
            default => $this->buildCampaignsReport($from, $to, $filters),
        };

        if ($format === 'pdf') {
            $pdfName = preg_replace('/\.csv$/', '.pdf', $filename);
            return $this->exportPdf($pdfName, $headers, $rows, $validated['type']);
        }

        if ($format === 'excel') {
            $filename = preg_replace('/\.csv$/', '.xlsx', $filename);
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => $format === 'excel'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildDonationsReport(Carbon $from, Carbon $to, array $filters): array
    {
        $query = Donation::whereBetween('created_at', [$from, $to]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $rows = $items->map(function (Donation $item) {
            return [
                $item->id,
                $item->user_id,
                $item->campaign_id,
                $item->donor_name,
                $item->donor_email,
                $item->amount,
                $item->method,
                $item->status,
                optional($item->created_at)->toDateTimeString(),
            ];
        })->all();

        return [
            'donations_' . now()->format('Ymd_His') . '.csv',
            ['id', 'user_id', 'campaign_id', 'donor_name', 'donor_email', 'amount', 'method', 'status', 'created_at'],
            $rows,
        ];
    }

    private function buildReliefRequestsReport(Carbon $from, Carbon $to, array $filters): array
    {
        $query = ReliefRequest::whereBetween('created_at', [$from, $to]);

        if (!empty($filters['disaster_type'])) {
            $query->where('disaster_type', $filters['disaster_type']);
        }

        if (!empty($filters['region'])) {
            $query->where('address', 'like', '%' . $filters['region'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $rows = $items->map(function (ReliefRequest $item) {
            return [
                $item->id,
                $item->citizen_id,
                $item->coordinator_id,
                $item->title,
                $item->disaster_type,
                $item->urgency_level,
                $item->people_count,
                $item->status,
                optional($item->created_at)->toDateTimeString(),
            ];
        })->all();

        return [
            'relief_requests_' . now()->format('Ymd_His') . '.csv',
            ['id', 'citizen_id', 'coordinator_id', 'title', 'disaster_type', 'urgency_level', 'people_count', 'status', 'created_at'],
            $rows,
        ];
    }

    private function buildDistributionsReport(Carbon $from, Carbon $to, array $filters): array
    {
        $query = Distribution::whereBetween('created_at', [$from, $to]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['region']) || !empty($filters['disaster_type'])) {
            $query->whereHas('request', function (Builder $builder) use ($filters) {
                if (!empty($filters['region'])) {
                    $builder->where('address', 'like', '%' . $filters['region'] . '%');
                }
                if (!empty($filters['disaster_type'])) {
                    $builder->where('disaster_type', $filters['disaster_type']);
                }
            });
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $rows = $items->map(function (Distribution $item) {
            return [
                $item->id,
                $item->warehouse_id,
                $item->request_id,
                $item->rescue_team_id,
                $item->coordinator_id,
                $item->total_value,
                $item->status,
                optional($item->created_at)->toDateTimeString(),
            ];
        })->all();

        return [
            'distributions_' . now()->format('Ymd_His') . '.csv',
            ['id', 'warehouse_id', 'request_id', 'rescue_team_id', 'coordinator_id', 'total_value', 'status', 'created_at'],
            $rows,
        ];
    }

    private function buildCampaignsReport(Carbon $from, Carbon $to, array $filters): array
    {
        $query = Campaign::whereBetween('created_at', [$from, $to]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $rows = $items->map(function (Campaign $item) {
            return [
                $item->id,
                $item->coordinator_id,
                $item->title,
                $item->target_amount,
                $item->current_amount,
                $item->status,
                optional($item->start_date)->toDateString(),
                optional($item->end_date)->toDateString(),
                optional($item->created_at)->toDateTimeString(),
            ];
        })->all();

        return [
            'campaigns_' . now()->format('Ymd_His') . '.csv',
            ['id', 'coordinator_id', 'title', 'target_amount', 'current_amount', 'status', 'start_date', 'end_date', 'created_at'],
            $rows,
        ];
    }

    private function resolveDateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$from, $to];
    }

    private function exportPdf(string $filename, array $headers, array $rows, string $type)
    {
        $dompdfClass = '\\Dompdf\\Dompdf';
        if (!class_exists($dompdfClass)) {
            return response()->json([
                'message' => 'PDF export requires dompdf/dompdf. Please install the package first.',
            ], 501);
        }

        $dompdf = new $dompdfClass();
        $html = view('reports.simple', [
            'title' => strtoupper($type) . ' REPORT',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toDateTimeString(),
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
