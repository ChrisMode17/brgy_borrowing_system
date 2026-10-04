<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

class SystemBackupService
{
    private const FORMAT_VERSION = 1;

    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
    ];

    private const FILE_DIRECTORIES = [
        'backgrounds',
        'branding',
        'profile-photos',
    ];

    private const IMAGE_EXTENSIONS = ['bmp', 'gif', 'jpeg', 'jpg', 'png', 'webp'];

    public function create(): string
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'system-backup-');
        if ($archivePath === false) {
            throw new RuntimeException('Unable to create a temporary system backup.');
        }

        $archive = new ZipArchive;
        if ($archive->open($archivePath, ZipArchive::OVERWRITE) !== true) {
            unlink($archivePath);

            throw new RuntimeException('Unable to open a temporary system backup archive.');
        }

        try {
            $tables = [];
            foreach ($this->applicationTables() as $table) {
                $columns = Schema::getColumnListing($table);
                $records = DB::table($table)->get()->map(
                    static fn (object $record): array => (array) $record
                )->all();

                $tables[$table] = $columns;
                if (! $archive->addFromString(
                    "database/{$table}.json",
                    json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                )) {
                    throw new RuntimeException("Unable to add {$table} data to the system backup.");
                }
            }

            if (! $archive->addFromString('manifest.json', json_encode([
                'format_version' => self::FORMAT_VERSION,
                'created_at' => now()->toIso8601String(),
                'tables' => $tables,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
                throw new RuntimeException('Unable to add the manifest to the system backup.');
            }

            $disk = Storage::disk('public');
            foreach (self::FILE_DIRECTORIES as $directory) {
                foreach ($disk->allFiles($directory) as $relativePath) {
                    $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                    if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                        continue;
                    }

                    if (! $archive->addFile($disk->path($relativePath), "files/{$relativePath}")) {
                        throw new RuntimeException("Unable to add {$relativePath} to the system backup.");
                    }
                }
            }
        } catch (Throwable $exception) {
            $archive->close();
            unlink($archivePath);

            throw $exception;
        }

        if (! $archive->close()) {
            unlink($archivePath);

            throw new RuntimeException('Unable to finish the system backup archive.');
        }

        return $archivePath;
    }

    public function restore(string $archivePath): void
    {
        $workspace = storage_path('app/system-backups/'.Str::uuid());
        File::ensureDirectoryExists($workspace);

        $assetSwapStarted = false;
        $foreignKeysDisabled = false;

        try {
            $archive = new ZipArchive;
            if ($archive->open($archivePath) !== true) {
                throw new InvalidArgumentException('The uploaded backup is not a valid ZIP archive.');
            }

            try {
                $this->validateArchiveLimits($archive);
                $manifest = $this->readManifest($archive);
                $tables = $this->readTableData($archive, $manifest);
                $this->extractImages($archive, "{$workspace}/restored-files");
            } finally {
                $archive->close();
            }

            $this->preserveCurrentImages("{$workspace}/previous-files");

            Schema::disableForeignKeyConstraints();
            $foreignKeysDisabled = true;

            DB::transaction(function () use ($tables, $workspace, &$assetSwapStarted): void {
                foreach (array_reverse($this->applicationTables()) as $table) {
                    DB::table($table)->delete();
                }

                foreach ($this->applicationTables() as $table) {
                    foreach (array_chunk($tables[$table], 500) as $records) {
                        if ($records !== []) {
                            DB::table($table)->insert($records);
                        }
                    }
                }

                $assetSwapStarted = true;
                $this->installImages("{$workspace}/restored-files");
            });
        } catch (Throwable $exception) {
            if ($assetSwapStarted) {
                $this->restorePreservedImages("{$workspace}/previous-files");
            }

            throw $exception;
        } finally {
            if ($foreignKeysDisabled) {
                Schema::enableForeignKeyConstraints();
            }

            File::deleteDirectory($workspace);
        }
    }

    /**
     * @return list<string>
     */
    private function applicationTables(): array
    {
        $tables = array_values(array_filter(
            Schema::getTableListing(schemaQualified: false),
            static fn (string $table): bool => ! in_array($table, self::EXCLUDED_TABLES, true)
        ));

        sort($tables);

        return $tables;
    }

    private function validateArchiveLimits(ZipArchive $archive): void
    {
        if ($archive->numFiles > 10000) {
            throw new InvalidArgumentException('The backup contains too many files.');
        }

        $totalSize = 0;
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->statIndex($index);
            if ($entry === false) {
                throw new InvalidArgumentException('The backup contains an unreadable archive entry.');
            }

            $totalSize += $entry['size'];
            if ($totalSize > 536870912) {
                throw new InvalidArgumentException('The uncompressed backup exceeds the 512 MB limit.');
            }
        }
    }

    /**
     * @return array{format_version: int, tables: array<string, array<int, string>>}
     */
    private function readManifest(ZipArchive $archive): array
    {
        $contents = $archive->getFromName('manifest.json');
        if ($contents === false) {
            throw new InvalidArgumentException('The backup is missing its manifest.');
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('The backup manifest is invalid.', previous: $exception);
        }

        if (
            ! is_array($manifest)
            || ($manifest['format_version'] ?? null) !== self::FORMAT_VERSION
            || ! is_array($manifest['tables'] ?? null)
        ) {
            throw new InvalidArgumentException('The backup format is not supported.');
        }

        $backupTables = array_keys($manifest['tables']);
        $currentTables = $this->applicationTables();
        sort($backupTables);

        if ($backupTables !== $currentTables) {
            throw new InvalidArgumentException('The backup database tables do not match this system version.');
        }

        foreach ($currentTables as $table) {
            $backupColumns = $manifest['tables'][$table] ?? null;
            $currentColumns = Schema::getColumnListing($table);

            if (! is_array($backupColumns) || $backupColumns !== $currentColumns) {
                throw new InvalidArgumentException("The backup schema for {$table} does not match this system.");
            }
        }

        return $manifest;
    }

    /**
     * @param  array{tables: array<string, array<int, string>>}  $manifest
     * @return array<string, list<array<string, mixed>>>
     */
    private function readTableData(ZipArchive $archive, array $manifest): array
    {
        $tables = [];

        foreach ($manifest['tables'] as $table => $columns) {
            $contents = $archive->getFromName("database/{$table}.json");
            if ($contents === false) {
                throw new InvalidArgumentException("The backup is missing data for {$table}.");
            }

            try {
                $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new InvalidArgumentException("The backup data for {$table} is invalid.", previous: $exception);
            }

            if (! is_array($records) || ! array_is_list($records)) {
                throw new InvalidArgumentException("The backup data for {$table} has an invalid structure.");
            }

            foreach ($records as $record) {
                if (! is_array($record) || array_diff(array_keys($record), $columns) !== []) {
                    throw new InvalidArgumentException("The backup data for {$table} contains invalid columns.");
                }
            }

            $tables[$table] = $records;
        }

        return $tables;
    }

    private function extractImages(ZipArchive $archive, string $destination): void
    {
        File::ensureDirectoryExists($destination);
        $seenPaths = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->statIndex($index);
            $entryName = $entry['name'] ?? '';

            if (! str_starts_with($entryName, 'files/')) {
                continue;
            }

            $relativePath = substr($entryName, strlen('files/'));
            $segments = explode('/', $relativePath);
            $root = $segments[0] ?? '';
            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

            if (
                $relativePath === ''
                || str_contains($relativePath, '\\')
                || preg_match('/[^a-zA-Z0-9_.\/ -]/', $relativePath) === 1
                || in_array('..', $segments, true)
                || in_array('.', $segments, true)
                || str_starts_with($relativePath, '/')
                || ! in_array($root, self::FILE_DIRECTORIES, true)
                || ! in_array($extension, self::IMAGE_EXTENSIONS, true)
            ) {
                throw new InvalidArgumentException('The backup contains an invalid uploaded file path.');
            }

            if (isset($seenPaths[$relativePath])) {
                throw new InvalidArgumentException('The backup contains duplicate uploaded file paths.');
            }
            $seenPaths[$relativePath] = true;

            if (($entry['size'] ?? 0) > 20971520) {
                throw new InvalidArgumentException('A backed-up image exceeds the 20 MB limit.');
            }

            $contents = $archive->getFromIndex($index);
            if ($contents === false || getimagesizefromstring($contents) === false) {
                throw new InvalidArgumentException('The backup contains an invalid image file.');
            }

            $targetPath = "{$destination}/{$relativePath}";
            File::ensureDirectoryExists(dirname($targetPath));
            if (file_put_contents($targetPath, $contents) === false) {
                throw new RuntimeException('Unable to stage an image from the backup.');
            }
        }
    }

    private function preserveCurrentImages(string $destination): void
    {
        $publicPath = Storage::disk('public')->path('');

        foreach (self::FILE_DIRECTORIES as $directory) {
            $source = "{$publicPath}/{$directory}";
            if (File::isDirectory($source) && ! File::copyDirectory($source, "{$destination}/{$directory}")) {
                throw new RuntimeException('Unable to preserve the current uploaded images before restoring.');
            }
        }
    }

    private function installImages(string $sourcePath): void
    {
        $publicPath = Storage::disk('public')->path('');

        foreach (self::FILE_DIRECTORIES as $directory) {
            $destination = "{$publicPath}/{$directory}";
            if (File::isDirectory($destination) && ! File::deleteDirectory($destination)) {
                throw new RuntimeException("Unable to replace the current {$directory} images.");
            }

            $source = "{$sourcePath}/{$directory}";
            if (File::isDirectory($source) && ! File::copyDirectory($source, $destination)) {
                throw new RuntimeException('Unable to restore the uploaded images.');
            }
        }
    }

    private function restorePreservedImages(string $sourcePath): void
    {
        $publicPath = Storage::disk('public')->path('');

        foreach (self::FILE_DIRECTORIES as $directory) {
            $destination = "{$publicPath}/{$directory}";
            if (File::isDirectory($destination) && ! File::deleteDirectory($destination)) {
                throw new RuntimeException("Unable to roll back the {$directory} images.");
            }

            $source = "{$sourcePath}/{$directory}";
            if (File::isDirectory($source) && ! File::copyDirectory($source, $destination)) {
                throw new RuntimeException("Unable to restore the previous {$directory} images.");
            }
        }
    }
}
