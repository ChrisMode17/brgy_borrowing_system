<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filtered($request);
        $logs = $query->orderByDesc('datetime')->paginate(10)->withQueryString();

        // Gap-free display sequence numbers, oldest = #1 (mirrors getAuditDisplaySeqMap())
        $allIds = AuditLog::orderBy('datetime')->orderBy('id')->pluck('id');
        $seqMap = array_flip($allIds->toArray());
        foreach ($seqMap as $id => $idx) {
            $seqMap[$id] = $idx + 1;
        }

        if ($request->header('X-Live-Filter') === 'true') {
            return view('audit.partials.results', compact('logs', 'seqMap'));
        }

        return view('audit.index', [
            'logs' => $logs,
            'seqMap' => $seqMap,
            'search' => $request->query('search', ''),
            'dateFilter' => $request->query('dateFilter', ''),
            'typeFilter' => $request->query('typeFilter', ''),
        ]);
    }

    protected function filtered(Request $request)
    {
        $query = AuditLog::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('actor', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('information', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($type = trim((string) $request->query('typeFilter', ''))) {
            $query->where('type', $type);
        }

        if ($date = $request->query('dateFilter', '')) {
            $since = match ($date) {
                '1hour' => Carbon::now()->subHour(),
                '1day' => Carbon::now()->subDay(),
                '1week' => Carbon::now()->subWeek(),
                default => null,
            };
            if ($since) {
                $query->where('datetime', '>=', $since);
            }
        }

        return $query;
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])],
        ]);

        $format = $validated['format'] ?? 'csv';
        $logs = $this->filtered($request)->orderByDesc('datetime')->get();

        $filename = 'audit_trail_'.now()->format('Ymd_His');

        if ($format === 'xlsx') {
            return $this->exportExcel($logs, $filename);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('audit.pdf', ['logs' => $logs])->setPaper('a4', 'landscape');
            AuditLogger::log('Export Report', 'System', "Exported audit trail report (PDF, {$logs->count()} records)");

            return $pdf->download("{$filename}.pdf");
        }

        $filename .= '.csv';
        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Actor', 'Role', 'Action', 'Type', 'Information', 'Date/Time']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $this->spreadsheetSafe($log->actor),
                    $this->spreadsheetSafe($log->role),
                    $this->spreadsheetSafe($log->action),
                    $this->spreadsheetSafe($log->type),
                    $this->spreadsheetSafe($log->information),
                    $log->datetime,
                ]);
            }
            fclose($handle);
        };

        AuditLogger::log('Export Report', 'System', "Exported audit trail report (CSV, {$logs->count()} records)");

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     */
    private function exportExcel(Collection $logs, string $filename)
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'audit-trail-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary file for the audit export.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($temporaryPath);
            $writer->addRow(Row::fromValues(['ID', 'Actor', 'Role', 'Action', 'Type', 'Information', 'Date/Time']));

            foreach ($logs as $log) {
                $writer->addRow(Row::fromValues([
                    $log->id,
                    $this->spreadsheetSafe($log->actor),
                    $this->spreadsheetSafe($log->role),
                    $this->spreadsheetSafe($log->action),
                    $this->spreadsheetSafe($log->type),
                    $this->spreadsheetSafe($log->information),
                    $log->datetime?->format('Y-m-d H:i:s'),
                ]));
            }

            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } finally {
                unlink($temporaryPath);
            }

            throw $exception;
        }

        AuditLogger::log('Export Report', 'System', "Exported audit trail report (Excel, {$logs->count()} records)");

        return response()->download(
            $temporaryPath,
            "{$filename}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function spreadsheetSafe(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
