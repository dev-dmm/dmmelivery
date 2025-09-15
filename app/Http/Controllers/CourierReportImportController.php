<?php

namespace App\Http\Controllers;

use App\Models\CourierReportImport;
use App\Models\Order;
use App\Models\Shipment;
use App\Jobs\ProcessCourierReportImport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CourierReportImportController extends Controller
{
    /**
     * Display the courier report import interface
     */
    public function index(): Response
    {
        $tenant = Auth::user()->currentTenant();
        
        // Get recent imports
        $recentImports = CourierReportImport::forTenant($tenant->id)
            ->recent()
            ->limit(10)
            ->get()
            ->map(function ($import) {
                return [
                    'id' => $import->id,
                    'uuid' => $import->uuid,
                    'file_name' => $import->file_name,
                    'status' => $import->status,
                    'progress' => $import->getProgressPercentage(),
                    'match_rate' => $import->getMatchRate(),
                    'price_match_rate' => $import->getPriceMatchRate(),
                    'total_rows' => $import->total_rows,
                    'matched_rows' => $import->matched_rows,
                    'unmatched_rows' => $import->unmatched_rows,
                    'price_mismatch_rows' => $import->price_mismatch_rows,
                    'has_errors' => !empty($import->errors),
                    'created_at' => $import->created_at->format('M d, Y H:i'),
                    'processing_time' => $import->processing_time_seconds ? 
                        $this->formatProcessingTime($import->processing_time_seconds) : null,
                    'status_icon' => $import->getStatusIcon(),
                    'status_color' => $import->getStatusColor(),
                ];
            });

        // Get import statistics
        $stats = [
            'total_imports' => CourierReportImport::forTenant($tenant->id)->count(),
            'completed_imports' => CourierReportImport::forTenant($tenant->id)->where('status', 'completed')->count(),
            'failed_imports' => CourierReportImport::forTenant($tenant->id)->where('status', 'failed')->count(),
            'pending_imports' => CourierReportImport::forTenant($tenant->id)->whereIn('status', ['pending', 'processing'])->count(),
        ];

        return Inertia::render('CourierReports/Import/Index', [
            'recentImports' => $recentImports,
            'stats' => $stats,
            'supportedFormats' => ['csv', 'xlsx', 'xls'],
            'maxFileSize' => config('app.max_file_size', 10240), // 10MB default
        ]);
    }

    /**
     * Handle file upload for courier report import
     */
    public function uploadFile(Request $request): JsonResponse
    {
        \Log::info('Courier report upload request received', [
            'has_file' => $request->hasFile('file'),
            'all_data' => $request->all(),
            'files' => $request->files->all(),
            'file_input' => $request->file('file'),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
        ]);
        
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|min:1|max:10240', // 10MB max, accept any file type for now
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            \Log::error('Courier report upload validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all(),
                'files' => $request->files->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = Auth::user()->currentTenant();
            $file = $request->file('file');
            
            // Additional validation for file extension
            if ($file && !in_array(strtolower($file->getClientOriginalExtension()), ['csv'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only CSV files are allowed',
                    'errors' => ['file' => ['Only CSV files are allowed']],
                ], 422);
            }
            
            \Log::info('Courier report upload attempt', [
                'file_name' => $file ? $file->getClientOriginalName() : 'no file',
                'file_size' => $file ? $file->getSize() : 'no file',
                'file_mime' => $file ? $file->getMimeType() : 'no file',
                'file_extension' => $file ? $file->getClientOriginalExtension() : 'no file',
            ]);
            
            $fileName = $file->getClientOriginalName();
            $fileHash = hash_file('sha256', $file->path());
            
            // Check if file already exists
            $existingImport = CourierReportImport::forTenant($tenant->id)
                ->where('file_hash', $fileHash)
                ->where('status', '!=', 'failed')
                ->first();
                
            if ($existingImport) {
                return response()->json([
                    'success' => false,
                    'message' => 'This file has already been imported',
                    'existing_import_id' => $existingImport->id,
                ], 409);
            }

            // Store file with original extension
            $extension = $file->getClientOriginalExtension();
            
            \Log::info('Attempting to store file', [
                'original_name' => $fileName,
                'extension' => $extension,
                'file_hash' => $fileHash,
                'target_filename' => $fileHash . '.' . $extension,
                'file_size' => $file->getSize(),
                'file_path' => $file->path(),
            ]);
            
            $storagePath = $file->storeAs('courier-reports', $fileHash . '.' . $extension, 'public');
            
            \Log::info('File storage result', [
                'storage_path' => $storagePath,
                'success' => $storagePath !== false,
            ]);
            
            // Check if file was actually stored
            $fullPath = Storage::disk('public')->path($storagePath);
            $fileExists = file_exists($fullPath);
            
            \Log::info('File stored successfully', [
                'original_name' => $fileName,
                'extension' => $extension,
                'storage_path' => $storagePath,
                'full_path' => $fullPath,
                'file_exists' => $fileExists,
                'actual_file_size' => $fileExists ? filesize($fullPath) : 0,
            ]);
            
            if (!$fileExists) {
                throw new \Exception('File was not stored successfully. Storage path: ' . $storagePath);
            }
            
            // Count rows in CSV
            $totalRows = $this->countCsvRows(Storage::disk('public')->path($storagePath));

            // Create import log
            $importLog = CourierReportImport::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::id(),
                'file_name' => $fileName,
                'file_path' => $storagePath,
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'mime_type' => $file->getMimeType(),
                'status' => 'pending',
                'total_rows' => $totalRows,
                'notes' => $request->input('notes'),
            ]);
            
            \Log::info('Courier report import created', [
                'import_id' => $importLog->id,
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
                'file_path' => $storagePath,
                'total_rows' => $totalRows,
            ]);

            // Dispatch job for processing
            ProcessCourierReportImport::dispatch($importLog);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and queued for processing',
                'import_id' => $importLog->id,
                'uuid' => $importLog->uuid,
                'status' => 'pending',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get import status
     */
    public function getStatus(string $uuid): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $import = CourierReportImport::forTenant($tenant->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'id' => $import->id,
            'uuid' => $import->uuid,
            'status' => $import->status,
            'progress' => $import->getProgressPercentage(),
            'match_rate' => $import->getMatchRate(),
            'price_match_rate' => $import->getPriceMatchRate(),
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'matched_rows' => $import->matched_rows,
            'unmatched_rows' => $import->unmatched_rows,
            'price_mismatch_rows' => $import->price_mismatch_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->failed_rows,
            'has_errors' => !empty($import->errors),
            'errors' => $import->errors,
            'warnings' => $import->warnings,
            'processing_time' => $import->processing_time_seconds ? 
                $this->formatProcessingTime($import->processing_time_seconds) : null,
            'created_at' => $import->created_at->format('M d, Y H:i'),
            'completed_at' => $import->completed_at?->format('M d, Y H:i'),
        ]);
    }

    /**
     * Get detailed results of import
     */
    public function getDetails(string $uuid): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $import = CourierReportImport::forTenant($tenant->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'import' => [
                'id' => $import->id,
                'uuid' => $import->uuid,
                'file_name' => $import->file_name,
                'status' => $import->status,
                'progress' => $import->getProgressPercentage(),
                'match_rate' => $import->getMatchRate(),
                'price_match_rate' => $import->getPriceMatchRate(),
                'total_rows' => $import->total_rows,
                'processed_rows' => $import->processed_rows,
                'matched_rows' => $import->matched_rows,
                'unmatched_rows' => $import->unmatched_rows,
                'price_mismatch_rows' => $import->price_mismatch_rows,
                'successful_rows' => $import->successful_rows,
                'failed_rows' => $import->failed_rows,
                'results_summary' => $import->results_summary,
                'errors' => $import->errors,
                'warnings' => $import->warnings,
                'error_log' => $import->error_log,
                'notes' => $import->notes,
                'created_at' => $import->created_at->format('M d, Y H:i'),
                'completed_at' => $import->completed_at?->format('M d, Y H:i'),
                'processing_time' => $import->processing_time_seconds ? 
                    $this->formatProcessingTime($import->processing_time_seconds) : null,
            ]
        ]);
    }

    /**
     * Cancel an import
     */
    public function cancel(string $uuid): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $import = CourierReportImport::forTenant($tenant->id)
            ->where('uuid', $uuid)
            ->whereIn('status', ['pending', 'processing'])
            ->firstOrFail();

        $import->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Import cancelled successfully',
        ]);
    }

    /**
     * Delete an import
     */
    public function delete(string $uuid): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        
        $import = CourierReportImport::forTenant($tenant->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Delete file if exists
        if ($import->file_path && Storage::exists($import->file_path)) {
            Storage::delete($import->file_path);
        }

        $import->delete();

        return response()->json([
            'success' => true,
            'message' => 'Import deleted successfully',
        ]);
    }

    /**
     * Download template file
     */
    public function downloadTemplate(string $format): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $templateData = [
            ['Tracking Number', 'Price', 'Date', 'Customer Name', 'Customer ID'],
            ['HF880039185GR', '18.90', '26/08/2025', 'Φωτεινή Γκικα', '137988'],
            ['HF880039662GR', '27.90', '29/08/2025', 'tzeni kreyzioy', '138412'],
        ];

        $filename = 'courier_report_template.' . $format;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($templateData) {
                $handle = fopen('php://output', 'w');
                foreach ($templateData as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // For other formats, you might want to use a library like PhpSpreadsheet
        return response()->json(['message' => 'Template format not supported'], 400);
    }

    /**
     * Count rows in CSV file
     */
    private function countCsvRows(string $filePath): int
    {
        if (!file_exists($filePath)) {
            \Log::error('CSV file not found for row counting', ['file_path' => $filePath]);
            return 0;
        }
        
        $count = 0;
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                $count++;
            }
            fclose($handle);
        }
        return max(0, $count - 1); // Subtract header row, ensure non-negative
    }

    /**
     * Format processing time
     */
    private function formatProcessingTime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . ' minutes';
        } else {
            return round($seconds / 3600, 1) . ' hours';
        }
    }
}