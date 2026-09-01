<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class CrmBackupCommand extends Command
{
    protected $signature =
        'crm:backup {--database-only}';

    protected $description =
        'Create a private CRM database + storage backup archive.';

    public function handle(): int
    {
        if (
            ! class_exists(ZipArchive::class)
        ) {
            $this->error('PHP Zip extension belum aktif.');
            return self::FAILURE;
        }

        $backupDir =
            config(
                'crm-hardening.backup.directory',
                storage_path('app/private/crm-backups')
            );

        File::ensureDirectoryExists($backupDir);

        $stamp = now()->format('Ymd-His');
        $workingDir =
            $backupDir
            .DIRECTORY_SEPARATOR
            .'tmp-'.$stamp;

        File::ensureDirectoryExists($workingDir);

        $sqlPath =
            $workingDir
            .DIRECTORY_SEPARATOR
            .'database.sql';

        try {
            $this->dumpDatabase($sqlPath);

            $metadata = [
                'created_at' => now()->toIso8601String(),
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
                'database' => config('database.connections.mysql.database'),
                'database_only' => (bool) $this->option('database-only'),
            ];

            file_put_contents(
                $workingDir
                .DIRECTORY_SEPARATOR
                .'metadata.json',
                json_encode(
                    $metadata,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                )
            );

            $archive =
                $backupDir
                .DIRECTORY_SEPARATOR
                .'crm-backup-'
                .$stamp
                .'.zip';

            $zip = new ZipArchive();

            if (
                $zip->open(
                    $archive,
                    ZipArchive::CREATE
                    | ZipArchive::OVERWRITE
                ) !== true
            ) {
                throw new \RuntimeException(
                    'Gagal membuat archive backup.'
                );
            }

            $zip->addFile(
                $sqlPath,
                'database.sql'
            );

            $zip->addFile(
                $workingDir
                .DIRECTORY_SEPARATOR
                .'metadata.json',
                'metadata.json'
            );

            if (! $this->option('database-only')) {
                $this->addStorageToZip($zip);
            }

            $zip->close();

            File::deleteDirectory($workingDir);

            $this->purgeOldBackups($backupDir);

            $this->info('Backup PASS');
            $this->line($archive);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            File::deleteDirectory($workingDir);

            $this->error(
                'Backup FAIL: '
                .$exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function dumpDatabase(
        string $path
    ): void {
        $pdo =
            DB::connection()
                ->getPdo();

        $handle =
            fopen(
                $path,
                'wb'
            );

        if ($handle === false) {
            throw new \RuntimeException(
                'Tidak dapat membuat database.sql.'
            );
        }

        fwrite(
            $handle,
            "SET FOREIGN_KEY_CHECKS=0;\n"
        );

        $database =
            DB::getDatabaseName();

        $tables =
            DB::select(
                'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'
            );

        foreach ($tables as $tableRow) {
            $values = array_values(
                (array) $tableRow
            );

            $table =
                (string) $values[0];

            $quotedTable =
                '`'
                .str_replace(
                    '`',
                    '``',
                    $table
                )
                .'`';

            $create =
                DB::selectOne(
                    'SHOW CREATE TABLE '
                    .$quotedTable
                );

            $createValues =
                array_values(
                    (array) $create
                );

            fwrite(
                $handle,
                "\nDROP TABLE IF EXISTS "
                .$quotedTable
                .";\n"
            );

            fwrite(
                $handle,
                $createValues[1]
                .";\n"
            );

            $statement =
                $pdo->query(
                    'SELECT * FROM '
                    .$quotedTable
                );

            while (
                $row =
                    $statement->fetch(
                        \PDO::FETCH_ASSOC
                    )
            ) {
                $columns =
                    array_map(
                        fn ($column) =>
                            '`'
                            .str_replace(
                                '`',
                                '``',
                                $column
                            )
                            .'`',
                        array_keys($row)
                    );

                $values = array_map(
                    function ($value) use ($pdo) {
                        if ($value === null) {
                            return 'NULL';
                        }

                        return $pdo->quote(
                            (string) $value
                        );
                    },
                    array_values($row)
                );

                fwrite(
                    $handle,
                    'INSERT INTO '
                    .$quotedTable
                    .' ('
                    .implode(',', $columns)
                    .') VALUES ('
                    .implode(',', $values)
                    .");\n"
                );
            }
        }

        fwrite(
            $handle,
            "\nSET FOREIGN_KEY_CHECKS=1;\n"
        );

        fclose($handle);
    }

    private function addStorageToZip(
        ZipArchive $zip
    ): void {
        $root =
            storage_path('app');

        if (! is_dir($root)) {
            return;
        }

        $excluded =
            collect(
                config(
                    'crm-hardening.backup.exclude_relative_paths',
                    []
                )
            )
                ->map(
                    fn ($value) =>
                        str_replace(
                            '\\',
                            '/',
                            trim(
                                (string) $value,
                                '/\\'
                            )
                        )
                );

        $iterator =
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS
                )
            );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute =
                $file->getPathname();

            $relative =
                str_replace(
                    '\\',
                    '/',
                    substr(
                        $absolute,
                        strlen($root) + 1
                    )
                );

            $skip =
                $excluded->contains(
                    fn ($prefix) =>
                        $relative === $prefix
                        || str_starts_with(
                            $relative,
                            $prefix.'/'
                        )
                );

            if ($skip) {
                continue;
            }

            $zip->addFile(
                $absolute,
                'storage-app/'.$relative
            );
        }
    }

    private function purgeOldBackups(
        string $directory
    ): void {
        $days =
            max(
                1,
                (int) config(
                    'crm-hardening.backup.retention_days',
                    14
                )
            );

        $cutoff =
            now()->subDays(
                $days
            )->timestamp;

        foreach (
            glob(
                $directory
                .DIRECTORY_SEPARATOR
                .'crm-backup-*.zip'
            )
            ?: []
            as $file
        ) {
            if (
                filemtime($file)
                < $cutoff
            ) {
                @unlink($file);
            }
        }
    }
}
