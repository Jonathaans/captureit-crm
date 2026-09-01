<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class CrmBackupVerifyCommand extends Command
{
    protected $signature =
        'crm:backup-verify';

    protected $description =
        'Verify the newest CRM backup archive.';

    public function handle(): int
    {
        $directory =
            config(
                'crm-hardening.backup.directory',
                storage_path('app/private/crm-backups')
            );

        $files =
            glob(
                $directory
                .DIRECTORY_SEPARATOR
                .'crm-backup-*.zip'
            )
            ?: [];

        if (! $files) {
            $this->error('Belum ada CRM backup.');
            return self::FAILURE;
        }

        usort(
            $files,
            fn ($a, $b) =>
                filemtime($b)
                <=> filemtime($a)
        );

        $latest = $files[0];

        $zip = new ZipArchive();

        if ($zip->open($latest) !== true) {
            $this->error('Archive tidak dapat dibuka.');
            return self::FAILURE;
        }

        $required = [
            'database.sql',
            'metadata.json',
        ];

        foreach ($required as $entry) {
            if ($zip->locateName($entry) === false) {
                $zip->close();
                $this->error(
                    'Missing backup entry: '
                    .$entry
                );
                return self::FAILURE;
            }
        }

        $zip->close();

        $ageHours =
            round(
                (time() - filemtime($latest))
                / 3600,
                1
            );

        $this->info('Backup verify PASS');
        $this->line('File : '.$latest);
        $this->line('Age  : '.$ageHours.' hours');
        $this->line(
            'Size : '
            .round(
                filesize($latest)
                / 1024
                / 1024,
                2
            )
            .' MB'
        );

        return self::SUCCESS;
    }
}
