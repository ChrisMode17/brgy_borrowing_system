<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Services\AuditLogger;
use App\Services\EquipmentService;
use App\Services\InventoryAvailabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\Exception\NoSheetsFoundException;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class ManageEquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'in:Equipment,Tool,Unit'],
            'status' => ['nullable', 'string', 'in:available,under_maintenance,broken,lost,borrowed'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $query = Inventory::with('category')->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if (isset($filters['category'])) {
            $category = $filters['category'];
            $query->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('category_name', $category));
        }

        $statusGroups = [
            'available' => ['available'],
            'under_maintenance' => ['under_maintenance', 'undermaintenance', 'under maintenanced', 'maintenance'],
            'broken' => ['broken'],
            'lost' => ['lost'],
        ];

        if (isset($filters['status'])) {
            $status = $filters['status'];
            $previousStatuses = [];

            foreach ($statusGroups as $statusKey => $statusValues) {
                if ($statusKey === $status) {
                    $this->whereStatusRows($query, $statusValues);
                    if ($previousStatuses !== []) {
                        $this->whereStatusRows($query, $previousStatuses, false);
                    }

                    break;
                }

                $previousStatuses = array_merge($previousStatuses, $statusValues);
            }

            if ($status === 'borrowed') {
                $this->whereStatusRows($query, array_merge(...array_values($statusGroups)), false);
            }
        }

        $items = $query->paginate(10)->withQueryString()->through(function ($item) {
            $snapshot = InventoryAvailabilityService::snapshot($item->equipment_id);

            return (object) array_merge($item->toArray(), ['snapshot' => $snapshot, 'category_name' => $item->category?->category_name]);
        });
        $categories = EquipmentService::VALID_CATEGORIES;

        $filters = [
            'search' => $search,
            'category' => $filters['category'] ?? '',
            'status' => $filters['status'] ?? '',
        ];

        if ($request->header('X-Live-Filter') === 'true') {
            return view('equipment.partials.results', compact('items'));
        }

        return view('equipment.index', compact('items', 'categories', 'filters'));
    }

    /**
     * @param  list<string>  $statuses
     */
    private function whereStatusRows(Builder $query, array $statuses, bool $shouldExist = true): void
    {
        $filter = static function (Builder $statusQuery) use ($statuses): void {
            $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
            $statusQuery->whereRaw("LOWER(TRIM(status)) IN ({$placeholders})", $statuses)
                ->where('quantity', '>', 0);
        };

        if ($shouldExist) {
            $query->whereHas('statuses', $filter);

            return;
        }

        $query->whereDoesntHave('statuses', $filter);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'in:Equipment,Tool,Unit'],
            'totalQuantity' => ['required', 'integer', 'min:1'],
        ]);

        $result = EquipmentService::insert($validated['name'], $validated['category'], (int) $validated['totalQuantity']);

        if ($result['success']) {
            AuditLogger::log('Added new equipment', 'Equipment', 'Added new equipment');
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'extensions:csv,xlsx', 'max:10240'],
        ]);

        $file = $validated['file'];
        $rows = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->readExcelRows($file->getRealPath())
            : $this->readCsvRows($file->getRealPath());

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'The file does not contain any equipment rows.',
            ]);
        }

        $added = 0;
        $errors = [];
        foreach ($rows as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $category = trim((string) ($item['category'] ?? ''));
            $qty = (int) ($item['totalQuantity'] ?? 0);

            $result = EquipmentService::insert($name, $category, $qty);
            if ($result['success']) {
                $added++;
            } else {
                $errors[] = 'Row '.$item['row'].': '.$result['message'];
            }
        }

        if ($added > 0) {
            AuditLogger::log('Bulk added equipment', 'Equipment', "Bulk added {$added} equipment item(s)");
        }

        $message = $added > 0
            ? "{$added} equipment item(s) added successfully.".(count($errors) ? ' '.count($errors).' row(s) failed.' : '')
            : 'No equipment was added. Please fix the errors in your file.';

        return back()->with($added > 0 ? 'success' : 'error', $message);
    }

    public function downloadBulkCsvTemplate()
    {
        $callback = static function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'category', 'totalQuantity']);
            fputcsv($handle, ['Sample Equipment', 'Equipment', '5']);
            fputcsv($handle, ['Sample Tool', 'Tool', '10']);
            fclose($handle);
        };

        return response()->streamDownload($callback, 'equipment_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadBulkExcelTemplate()
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'equipment-template-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary file for the equipment template.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($temporaryPath);
            $writer->addRow(Row::fromValues(['name', 'category', 'totalQuantity']));
            $writer->addRow(Row::fromValues(['Sample Equipment', 'Equipment', 5]));
            $writer->addRow(Row::fromValues(['Sample Tool', 'Tool', 10]));
            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } finally {
                unlink($temporaryPath);
            }

            throw $exception;
        }

        return response()->download(
            $temporaryPath,
            'equipment_template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /**
     * @return list<array{name: string, category: string, totalQuantity: string, row: int}>
     */
    private function readCsvRows(string|false $path): array
    {
        if ($path === false || ($handle = fopen($path, 'r')) === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV file could not be read.']);
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if ($headers === false) {
                throw ValidationException::withMessages(['file' => 'The CSV file must include a header row.']);
            }

            $indexes = $this->columnIndexes($headers);
            $rows = [];
            $rowNumber = 1;

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                $row = $this->mapSpreadsheetRow($values, $indexes, $rowNumber);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<array{name: string, category: string, totalQuantity: string, row: int}>
     */
    private function readExcelRows(string|false $path): array
    {
        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded Excel file could not be read.']);
        }

        $reader = new Reader;

        try {
            $reader->open($path);
            $sheets = $reader->getSheetIterator();
            if (! $sheets->valid()) {
                throw ValidationException::withMessages(['file' => 'The Excel file must include a worksheet.']);
            }

            $rowIterator = $sheets->current()->getRowIterator();
            $indexes = null;
            $rows = [];
            $rowNumber = 0;

            foreach ($rowIterator as $row) {
                $rowNumber++;
                if ($indexes === null) {
                    $indexes = $this->columnIndexes($row->toArray());

                    continue;
                }

                $mappedRow = $this->mapSpreadsheetRow($row->toArray(), $indexes, $rowNumber);
                if ($mappedRow !== null) {
                    $rows[] = $mappedRow;
                }
            }

            if ($indexes === null) {
                throw ValidationException::withMessages(['file' => 'The Excel worksheet must include a header row.']);
            }

            return $rows;
        } catch (IOException|NoSheetsFoundException $exception) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded Excel file is invalid or could not be read.',
            ]);
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array{name: int, category: int, totalquantity: int}
     */
    private function columnIndexes(array $headers): array
    {
        $indexes = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $header) ?? '');
            if (in_array($normalized, ['name', 'category', 'totalquantity'], true)) {
                $indexes[$normalized] = $index;
            }
        }

        if (! isset($indexes['name'], $indexes['category'], $indexes['totalquantity'])) {
            throw ValidationException::withMessages([
                'file' => 'The file must include name, category, and totalQuantity columns.',
            ]);
        }

        return $indexes;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array{name: int, category: int, totalquantity: int}  $indexes
     * @return array{name: string, category: string, totalQuantity: string, row: int}|null
     */
    private function mapSpreadsheetRow(array $values, array $indexes, int $rowNumber): ?array
    {
        $name = trim((string) ($values[$indexes['name']] ?? ''));
        $category = trim((string) ($values[$indexes['category']] ?? ''));
        $totalQuantity = trim((string) ($values[$indexes['totalquantity']] ?? ''));

        if ($name === '' && $category === '' && $totalQuantity === '') {
            return null;
        }

        return compact('name', 'category', 'totalQuantity') + ['row' => $rowNumber];
    }

    public function update(Request $request, Inventory $inventory)
    {
        if (! Auth::user()->canEditEquipment()) {
            abort(403, 'You do not have permission to edit equipment');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string'],
            'totalQuantity' => ['required', 'integer', 'min:1'],
            'availableQuantity' => ['required', 'integer', 'min:0'],
        ]);

        $result = EquipmentService::update(
            $inventory->equipment_id,
            $validated['name'],
            $validated['category'],
            (int) $validated['totalQuantity'],
            (int) $validated['availableQuantity']
        );

        if ($result['success']) {
            AuditLogger::log('Edited information of equipment', 'Equipment', 'Edited information of equipment');
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Inventory $inventory)
    {
        if (! Auth::user()->canDeleteEquipment()) {
            abort(403, 'You do not have permission to delete equipment');
        }

        $result = EquipmentService::delete($inventory->equipment_id);

        if ($result['success']) {
            AuditLogger::log('Deleted equipment', 'Equipment', 'Equipment removed');
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function fix(Request $request, Inventory $inventory)
    {
        if (! Auth::user()->canEditEquipment()) {
            abort(403, 'You do not have permission to fix equipment');
        }

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        $result = EquipmentService::fixMaintenance($inventory->equipment_id, (int) $validated['quantity']);

        if ($result['success']) {
            AuditLogger::log('Fixed equipment', 'Equipment', $result['message']);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
