<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use Illuminate\Console\Command;
use Throwable;

class SendQuoteSentRemindersCommand extends Command
{
    protected $signature = 'quote:send-reminders
                            {--enquiry= : Only process a single enquiry ID}
                            {--dry-run : List matching enquiries without sending}';

    protected $description = 'Send daily Quote Sent reminders at 9am for preferred dates within 30 days';

    public function handle(): int
    {
        $enquiryOpt = $this->option('enquiry');
        $onlyId = $enquiryOpt !== null && $enquiryOpt !== '' ? (int) $enquiryOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'candidates' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        try {
            require_once dirname(base_path()).'/config.php';
            require_once dirname(base_path()).'/enquiry_logger.php';
            require_once dirname(base_path()).'/brevo_email.php';

            if (brevoApiKey() === '') {
                $this->warn('Brevo API key is not configured — nothing to send.');

                return self::SUCCESS;
            }

            $tz = new \DateTimeZone('Europe/London');
            $today = new \DateTimeImmutable('today', $tz);
            $horizon = $today->modify('+30 days')->format('Y-m-d');
            $todayStr = $today->format('Y-m-d');

            $query = Enquiry::query()
                ->whereNotNull('quote_email_sent_at')
                ->whereNull('booking_submitted_at')
                ->whereNotIn('status', ['quote_accepted', 'quote_won'])
                ->where(function ($q): void {
                    $q->where('date_not_sure', false)
                        ->orWhereNull('date_not_sure')
                        ->orWhere('date_not_sure', 0);
                })
                ->whereNotNull('preferred_date_time')
                ->where('preferred_date_time', '!=', '')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                // Rough SQL window on the date prefix; exact 0–30 day check happens per row.
                ->whereRaw('SUBSTR(preferred_date_time, 1, 10) >= ?', [$todayStr])
                ->whereRaw('SUBSTR(preferred_date_time, 1, 10) <= ?', [$horizon]);

            if ($onlyId !== null) {
                $query->whereKey($onlyId);
            }

            /** @var \Illuminate\Support\Collection<int, Enquiry> $enquiries */
            $enquiries = $query->orderBy('id')->limit(500)->get();
            $stats['candidates'] = $enquiries->count();

            foreach ($enquiries as $enquiry) {
                $enquiryId = (int) $enquiry->id;
                $preferredDate = enquiryPreferredDateOnly((string) $enquiry->preferred_date_time);
                $daysUntil = enquiryPreferredDateDaysUntil($preferredDate);

                if ($preferredDate === '' || $daysUntil === null || $daysUntil < 0 || $daysUntil > 30) {
                    $stats['skipped']++;
                    continue;
                }

                if (enquiryLoggerQuoteReminderSentToday($enquiryId)) {
                    $stats['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("Would remind enquiry {$enquiryId} ({$enquiry->email}) preferred={$preferredDate} days_until={$daysUntil}");
                    $stats['sent']++;
                    continue;
                }

                try {
                    $sent = maybeSendQuoteSentReminderEmail(
                        $enquiryId,
                        (string) $enquiry->name,
                        (string) $enquiry->email
                    );
                    if ($sent) {
                        $stats['sent']++;
                        $this->info("Sent quote reminder for enquiry {$enquiryId}");
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Throwable $e) {
                    $stats['failed']++;
                    enquiryLoggerEvent(
                        $enquiryId,
                        'quote_reminder_failed',
                        'Quote Sent reminder could not be sent.',
                        ['error' => $e->getMessage(), 'preferred_date' => $preferredDate]
                    );
                    $this->error("Enquiry {$enquiryId}: ".$e->getMessage());
                }
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Quote reminders: candidates=%d sent=%d skipped=%d failed=%d%s',
            $stats['candidates'],
            $stats['sent'],
            $stats['skipped'],
            $stats['failed'],
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
