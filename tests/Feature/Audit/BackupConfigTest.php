<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * Verifies the backup configuration audit guarantees.
 *
 * 2026-05-06 audit findings:
 *
 *   1. .env.example was missing BACKUP_ARCHIVE_PASSWORD entirely. A
 *      cp .env.example .env on a fresh deploy left
 *      \$BACKUP_ARCHIVE_PASSWORD unset, which made
 *      env('BACKUP_ARCHIVE_PASSWORD') return null, which Spatie\Backup
 *      treats as "no encryption". Backups landed on disk as
 *      cleartext zips containing the entire DB dump plus uploaded
 *      files. Anyone with read access to the backup directory had
 *      everything.
 *
 *   2. config/backup.php had 'to' => 'your@example.com' hardcoded
 *      for notifications. Spatie sends:
 *        - BackupHasFailedNotification
 *        - UnhealthyBackupWasFoundNotification
 *        - CleanupHasFailedNotification
 *      Without a real address, none of these reach the operator;
 *      backups fail silently for weeks until someone tries to
 *      restore.
 *
 * Fixes:
 *   - Added BACKUP_ARCHIVE_PASSWORD and BACKUP_NOTIFY_EMAIL to
 *     .env.example with explanatory comments.
 *   - Switched config/backup.php 'to' to env('BACKUP_NOTIFY_EMAIL')
 *     with a fallback to env('MAIL_FROM_ADDRESS').
 *
 * Out of scope (TODO): backup destination is still 'disks => [local]'.
 * Best practice is to ALSO ship to S3/Wasabi for offsite redundancy.
 * Requires verifying the AWS/Wasabi disk config first; flagged for
 * follow-up.
 */
class BackupConfigTest extends TestCase
{
    public function test_env_example_documents_backup_archive_password(): void
    {
        $body = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString(
            'BACKUP_ARCHIVE_PASSWORD',
            $body,
            ".env.example must declare BACKUP_ARCHIVE_PASSWORD so deployers " .
            "see it as a required-to-set variable. Without it, Spatie\\Backup " .
            "writes unencrypted zip files containing the full DB dump."
        );
    }

    public function test_env_example_documents_backup_notify_email(): void
    {
        $body = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString(
            'BACKUP_NOTIFY_EMAIL',
            $body,
            ".env.example must declare BACKUP_NOTIFY_EMAIL — without it, the " .
            "config falls back through env() chain but operators have no " .
            "explicit reminder that backup failure alerts need a real address."
        );
    }

    public function test_backup_notification_email_is_not_placeholder(): void
    {
        // Validate the resolved config value, not just the source — this
        // catches a future maintainer who reverts the env() lookup.
        $to = config('backup.notifications.mail.to');

        $this->assertNotSame('your@example.com', $to,
            'config/backup.php notifications.mail.to is the placeholder default — backup-failure alerts will silently disappear in prod');

        // Also verify the source uses env() so different envs can override
        // without code changes.
        $src = file_get_contents(config_path('backup.php'));
        $this->assertStringContainsString(
            "env('BACKUP_NOTIFY_EMAIL'",
            $src,
            "config/backup.php should resolve 'to' from env('BACKUP_NOTIFY_EMAIL') so per-env override is possible without code change"
        );
    }

    public function test_backup_archive_encryption_is_default(): void
    {
        $enc = config('backup.backup.destination.encryption')
            ?? config('backup.backup.encryption'); // some Spatie versions

        // Spatie accepts 'default' (which uses ZipArchive::EM_AES_256
        // when available) or false/null to disable. We want it set to
        // anything that isn't 'false' / 'null' / null.
        $this->assertNotEquals(false, $enc,
            "backup.encryption must NOT be disabled — switch to 'default' (AES-256)");
        $this->assertNotEquals('false', $enc);
    }
}
