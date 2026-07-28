<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use Illuminate\Console\Command;
use Throwable;

class SendBookingVenueRemindersCommand extends Command
{
    protected $signature = 'booking:send-venue-reminders
                            {--enquiry= : Only process a single enquiry ID}
                            {--dry-run : List matching enquiries without sending}';

    protected $description = 'Send Accept Quote / venue details reminders 24 hours before the preferred date when booking is incomplete';

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
            $tomorrow = $today->modify('+1 day')->format('Y-m-d');
            $todayStr = $today->format('Y-m-d');

            $query = Enquiry::query()
                ->whereNull('booking_submitted_at')
                ->whereNull('booking_reminder_sent_at')
                ->where(function ($q): void {
                    $q->where('date_not_sure', false)
                        ->orWhereNull('date_not_sure')
                        ->orWhere('date_not_sure', 0);
                })
                ->whereNotNull('preferred_date_time')
                ->where('preferred_date_time', '!=', '')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->where(function ($q) use ($tomorrow, $todayStr): void {
                    // Preferred date is tomorrow (24h window), or today if the reminder was missed.
                    $q->where('preferred_date_time', 'like', $tomorrow.'%')
                        ->orWhere('preferred_date_time', 'like', $todayStr.'%');
                });

            if ($onlyId !== null) {
                $query->whereKey($onlyId);
            }

            /** @var \Illuminate\Support\Collection<int, Enquiry> $enquiries */
            $enquiries = $query->orderBy('id')->limit(200)->get();
            $stats['candidates'] = $enquiries->count();

            foreach ($enquiries as $enquiry) {
                $enquiryId = (int) $enquiry->id;
                $preferredDate = enquiryPreferredDateOnly((string) $enquiry->preferred_date_time);
                $daysUntil = enquiryPreferredDateDaysUntil($preferredDate);

                if ($preferredDate === '' || $daysUntil === null || $daysUntil > 1 || $daysUntil < 0) {
                    $stats['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("Would remind enquiry {$enquiryId} ({$enquiry->email}) preferred={$preferredDate} days_until={$daysUntil}");
                    $stats['sent']++;
                    continue;
                }

                try {
                    $sent = maybeSendBookingVenueReminderEmail(
                        $enquiryId,
                        (string) $enquiry->name,
                        (string) $enquiry->email
                    );
                    if ($sent) {
                        $stats['sent']++;
                        $this->info("Sent venue reminder for enquiry {$enquiryId}");
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Throwable $e) {
                    $stats['failed']++;
                    enquiryLoggerEvent(
                        $enquiryId,
                        'booking_reminder_failed',
                        'Accept Quote / venue details reminder could not be sent.',
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
            'Venue reminders: candidates=%d sent=%d skipped=%d failed=%d%s',
            $stats['candidates'],
            $stats['sent'],
            $stats['skipped'],
            $stats['failed'],
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
