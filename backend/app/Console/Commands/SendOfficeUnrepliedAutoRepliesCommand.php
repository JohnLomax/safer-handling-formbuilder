<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class SendOfficeUnrepliedAutoRepliesCommand extends Command
{
    protected $signature = 'email:send-office-auto-replies
                            {--dry-run : List matching unreplied messages without sending}';

    protected $description = 'Send email.html auto-replies from office@ for IMAP inbox messages with no human reply after 8 hours';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            require_once dirname(base_path()).'/config.php';
            require_once dirname(base_path()).'/brevo_email.php';
            require_once dirname(base_path()).'/office_mailbox.php';

            if (! officeAutoReplyEnabled()) {
                $this->warn('Office unreplied auto-reply is disabled in Integration settings.');

                return self::SUCCESS;
            }

            if (brevoApiKey() === '') {
                $this->warn('Brevo API key is not configured — nothing to send.');

                return self::SUCCESS;
            }

            $stats = officeProcessUnrepliedAutoReplies($dryRun);

            $this->info(sprintf(
                'candidates=%d sent=%d skipped=%d failed=%d%s',
                $stats['candidates'],
                $stats['sent'],
                $stats['skipped'],
                $stats['failed'],
                $dryRun ? ' (dry-run)' : ''
            ));

            foreach ($stats['errors'] as $error) {
                $this->warn($error);
            }

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
