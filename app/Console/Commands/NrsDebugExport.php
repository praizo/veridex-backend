<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NrsDebugExport extends Command
{
    protected $signature = 'nrs:debug-export {submission_id? : NRS submission ID to find} {--invoice= : Invoice ID to find all matching exports}';

    protected $description = 'Find development-only raw NRS debug export files by submission or invoice ID.';

    public function handle(): int
    {
        $submissionId = $this->argument('submission_id');
        $invoiceId = $this->option('invoice');

        if (! $submissionId && ! $invoiceId) {
            $this->error('Provide a submission_id or --invoice={invoice_id}.');

            return self::FAILURE;
        }

        $diskName = config('audit.nrs_raw_debug_disk', 'local');
        $basePath = trim(config('audit.nrs_raw_debug_path', 'nrs-debug'), '/');
        $disk = Storage::disk($diskName);
        $matches = [];

        foreach ($disk->allFiles($basePath) as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }

            $artifact = json_decode($disk->get($file), true);
            if (! is_array($artifact)) {
                continue;
            }

            $context = $artifact['context'] ?? [];
            $matchesSubmission = $submissionId && (string) ($context['submission_id'] ?? '') === (string) $submissionId;
            $matchesInvoice = $invoiceId && (string) ($context['invoice_id'] ?? '') === (string) $invoiceId;

            if ($matchesSubmission || $matchesInvoice) {
                $matches[] = [
                    'file' => method_exists($disk, 'path') ? $disk->path($file) : $file,
                    'submission_id' => $context['submission_id'] ?? null,
                    'invoice_id' => $context['invoice_id'] ?? null,
                    'action' => $context['action'] ?? null,
                    'endpoint' => $context['endpoint'] ?? null,
                    'exported_at' => $artifact['exported_at'] ?? null,
                ];
            }
        }

        if ($matches === []) {
            $this->warn('No matching raw NRS debug exports found.');

            return self::SUCCESS;
        }

        $this->table(['File', 'Submission', 'Invoice', 'Action', 'Endpoint', 'Exported At'], array_map(fn ($match) => [
            $match['file'],
            $match['submission_id'],
            $match['invoice_id'],
            $match['action'],
            $match['endpoint'],
            $match['exported_at'],
        ], $matches));

        return self::SUCCESS;
    }
}
