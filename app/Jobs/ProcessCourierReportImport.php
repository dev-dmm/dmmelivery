<?php

namespace App\Jobs;

use App\Models\CourierReportImport;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCourierReportImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $import;

    /**
     * Create a new job instance.
     */
    public function __construct(CourierReportImport $import)
    {
        $this->import = $import;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->import->start();
            
            $filePath = Storage::disk('public')->path($this->import->file_path);
            
            Log::info('Processing courier report file', [
                'import_id' => $this->import->id,
                'file_path' => $this->import->file_path,
                'full_path' => $filePath,
                'file_exists' => file_exists($filePath),
                'storage_disk' => config('filesystems.default'),
            ]);
            
            if (!file_exists($filePath)) {
                throw new \Exception('File not found: ' . $filePath);
            }

            $results = $this->processCsvFile($filePath);
            
            $this->import->update([
                'results_summary' => $results['summary'],
                'errors' => $results['errors'],
                'warnings' => $results['warnings'],
            ]);

            $this->import->complete();

            Log::info('Courier report import completed', [
                'import_id' => $this->import->id,
                'file_name' => $this->import->file_name,
                'total_rows' => $this->import->total_rows,
                'matched_rows' => $this->import->matched_rows,
                'unmatched_rows' => $this->import->unmatched_rows,
                'price_mismatch_rows' => $this->import->price_mismatch_rows,
            ]);

        } catch (\Exception $e) {
            Log::error('Courier report import failed', [
                'import_id' => $this->import->id,
                'file_name' => $this->import->file_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->import->fail($e->getMessage());
        }
    }

    /**
     * Process the CSV file and match with orders/shipments
     */
    private function processCsvFile(string $filePath): array
    {
        $results = [
            'summary' => [],
            'errors' => [],
            'warnings' => [],
            'matches' => [],
            'unmatched' => [],
            'price_mismatches' => [],
        ];

        $processed = 0;
        $matched = 0;
        $unmatched = 0;
        $priceMismatch = 0;
        $successful = 0;
        $failed = 0;

        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ';'); // Skip header - use semicolon delimiter
            
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                $processed++;
                
                try {
                    $rowResult = $this->processRow($data, $header, $processed);
                    
                    if ($rowResult['status'] === 'matched') {
                        $matched++;
                        $results['matches'][] = $rowResult;
                        
                        if ($rowResult['price_match']) {
                            $successful++;
                        } else {
                            $priceMismatch++;
                            $results['price_mismatches'][] = $rowResult;
                        }
                    } else {
                        $unmatched++;
                        $results['unmatched'][] = $rowResult;
                    }
                    
                } catch (\Exception $e) {
                    $failed++;
                    $results['errors'][] = [
                        'row' => $processed,
                        'message' => $e->getMessage(),
                        'data' => $data,
                    ];
                }

                // Update progress every 10 rows
                if ($processed % 10 === 0) {
                    $this->import->updateProgress($processed, $matched, $unmatched, $priceMismatch, $successful, $failed);
                }
            }
            
            fclose($handle);
        }

        // Final progress update
        $this->import->updateProgress($processed, $matched, $unmatched, $priceMismatch, $successful, $failed);

        // Build summary
        $results['summary'] = [
            'total_rows' => $processed,
            'matched_rows' => $matched,
            'unmatched_rows' => $unmatched,
            'price_mismatch_rows' => $priceMismatch,
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'match_rate' => $processed > 0 ? round(($matched / $processed) * 100, 2) : 0,
            'price_match_rate' => $matched > 0 ? round((($matched - $priceMismatch) / $matched) * 100, 2) : 0,
        ];

        return $results;
    }

    /**
     * Process a single row from the CSV
     */
    private function processRow(array $data, array $header, int $rowNumber): array
    {
        // Map CSV columns to our expected format
        $row = $this->mapCsvRow($data, $header);
        
        $trackingNumber = trim($row['tracking_number']);
        $price = floatval($row['price']);
        $date = $row['date'];
        $customerName = trim($row['customer_name']);
        $customerId = trim($row['customer_id']);

        // Find matching order or shipment by tracking number
        $order = Order::forTenant($this->import->tenant_id)
            ->where('external_order_id', $trackingNumber)
            ->orWhereHas('shipments', function ($query) use ($trackingNumber) {
                $query->where('tracking_number', $trackingNumber)
                      ->orWhere('courier_tracking_id', $trackingNumber);
            })
            ->first();

        if (!$order) {
            // Try to find by shipment tracking number
            $shipment = Shipment::forTenant($this->import->tenant_id)
                ->where('tracking_number', $trackingNumber)
                ->orWhere('courier_tracking_id', $trackingNumber)
                ->first();

            if ($shipment) {
                $order = $shipment->order;
            }
        }

        if (!$order) {
            return [
                'status' => 'unmatched',
                'row' => $rowNumber,
                'tracking_number' => $trackingNumber,
                'price' => $price,
                'date' => $date,
                'customer_name' => $customerName,
                'customer_id' => $customerId,
                'reason' => 'No matching order or shipment found',
                'order_id' => null,
                'order_total' => null,
                'price_match' => false,
            ];
        }

        // Check if price matches
        $orderTotal = floatval($order->total_amount);
        $priceMatch = abs($price - $orderTotal) < 0.01; // Allow for small floating point differences

        return [
            'status' => 'matched',
            'row' => $rowNumber,
            'tracking_number' => $trackingNumber,
            'price' => $price,
            'date' => $date,
            'customer_name' => $customerName,
            'customer_id' => $customerId,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_total' => $orderTotal,
            'price_match' => $priceMatch,
            'price_difference' => $price - $orderTotal,
            'order_status' => $order->status,
            'order_customer_name' => $order->customer_name,
        ];
    }

    /**
     * Map CSV row data to expected format
     */
    private function mapCsvRow(array $data, array $header): array
    {
        $mapped = [];
        
        // Expected columns: Tracking Number, Price, Date, Customer Name, Customer ID
        for ($i = 0; $i < count($header); $i++) {
            $column = trim($header[$i]);
            $value = isset($data[$i]) ? trim($data[$i], ' "') : '';
            
            // Map common column variations
            if (stripos($column, 'tracking') !== false || stripos($column, 'number') !== false) {
                $mapped['tracking_number'] = $value;
            } elseif (stripos($column, 'price') !== false || stripos($column, 'amount') !== false || stripos($column, 'total') !== false) {
                $mapped['price'] = $value;
            } elseif (stripos($column, 'date') !== false) {
                $mapped['date'] = $value;
            } elseif (stripos($column, 'customer') !== false && stripos($column, 'name') !== false) {
                $mapped['customer_name'] = $value;
            } elseif (stripos($column, 'customer') !== false && (stripos($column, 'id') !== false || stripos($column, 'code') !== false)) {
                $mapped['customer_id'] = $value;
            }
        }
        
        // If we couldn't map by column names, assume standard order
        if (empty($mapped['tracking_number']) && count($data) >= 1) {
            $mapped['tracking_number'] = $data[0];
        }
        if (empty($mapped['price']) && count($data) >= 2) {
            $mapped['price'] = $data[1];
        }
        if (empty($mapped['date']) && count($data) >= 3) {
            $mapped['date'] = $data[2];
        }
        if (empty($mapped['customer_name']) && count($data) >= 4) {
            $mapped['customer_name'] = $data[3];
        }
        if (empty($mapped['customer_id']) && count($data) >= 5) {
            $mapped['customer_id'] = $data[4];
        }
        
        return $mapped;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Courier report import job failed', [
            'import_id' => $this->import->id,
            'file_name' => $this->import->file_name,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->import->fail($exception->getMessage());
    }
}