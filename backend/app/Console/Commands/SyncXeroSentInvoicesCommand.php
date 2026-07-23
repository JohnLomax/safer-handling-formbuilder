<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use App\Services\EnquiryProcessRetry;
use Illuminate\Console\Command;
use Throwable;

class SyncXeroSentInvoicesCommand extends Command
{
    protected $signature = 'xero:sync-sent-invoices
                            {--enquiry= : Only check a single enquiry ID}';

    protected $description = 'Detect Xero invoices marked as sent and progress Monday (Quote Won + Client Booking Form)';

    public function handle(EnquiryProcessRetry $retry): int
    {
        $enquiryOpt = $this->option('enquiry');
        $onlyId = $enquiryOpt !== null && $enquiryOpt !== '' ? (int) $enquiryOpt : null;

        $stats = [
            'checked' => 0,
            'processed' => 0,
            'already_sent' => 0,
            'still_draft' => 0,
            'failed' => 0,
        ];

        try {
            // Ensure form helpers are loaded (via constructor boot).
            unset($retry);

            if (! xeroEnabled()) {
                $this->warn('Xero is disabled — nothing to sync.');

                return self::SUCCESS;
            }

            $query = Enquiry::query()
                ->whereNotNull('xero_invoice_id')
                ->where('xero_invoice_id', '!=', '');

            if ($onlyId !== null) {
                $query->whereKey($onlyId);
            } else {
                $query->whereNull('xero_invoice_sent_at');
            }

            $ids = $query->orderBy('id')->limit(100)->pluck('id');

            foreach ($ids as $id) {
                $enquiryId = (int) $id;
                $stats['checked']++;

                try {
                    $result = xeroMaybeProcessInvoiceSent($enquiryId);
                    if ($result === null) {
                        continue;
                    }
                    if (! empty($result['already_sent'])) {
                        $stats['already_sent']++;
                    } elseif (! empty($result['processed'])) {
                        $stats['processed']++;
                    } elseif (empty($result['sent'])) {
                        $stats['still_draft']++;
                    }
                } catch (Throwable $e) {
                    $stats['failed']++;
                    if (function_exists('enquiryLoggerEvent')) {
                        enquiryLoggerEvent(
                            $enquiryId,
                            'xero_invoice_sent_check_failed',
                            'Could not check whether the Xero invoice has been sent.',
                            ['error' => $e->getMessage()]
                        );
                    }
                    $this->error("Enquiry {$enquiryId}: ".$e->getMessage());
                }
            }
        } catch (Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Checked %d · processed %d · already sent %d · still draft %d · failed %d',
            $stats['checked'],
            $stats['processed'],
            $stats['already_sent'],
            $stats['still_draft'],
            $stats['failed']
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
