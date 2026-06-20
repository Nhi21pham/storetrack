<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class VerifyBackupContents extends Command
{
    protected $signature = 'backup:verify
        {--path= : Verify a specific backup zip on the backups disk instead of the latest}';

    protected $description = 'Verify the latest backup dump actually contains data for every table that has live rows.';

    public function handle(): int
    {
        $disk = Storage::disk('backups');

        $zipRelativePath = $this->option('path') ?: $this->latestBackupPath($disk);
        if ($zipRelativePath === null) {
            return $this->reportFailure('No backup zip found on the backups disk.');
        }

        $dumpSql = $this->readDumpFromZip($disk->path($zipRelativePath));
        if ($dumpSql === null) {
            return $this->reportFailure("No database dump found inside backup: {$zipRelativePath}");
        }

        $tablesInDump = $this->tablesWithDataInDump($dumpSql);
        $tablesWithLiveData = $this->tablesWithLiveData();

        $missing = array_values(array_diff($tablesWithLiveData, $tablesInDump));

        if (! empty($missing)) {
            $message = sprintf(
                'Backup "%s" is missing data for %d table(s) that have live rows: %s',
                $zipRelativePath,
                count($missing),
                implode(', ', $missing)
            );
            Log::error('[backup:verify] '.$message);

            return $this->fail($message);
        }

        $this->info(sprintf(
            'Backup "%s" verified: all %d table(s) with live data are present in the dump.',
            $zipRelativePath,
            count($tablesWithLiveData)
        ));

        return Command::SUCCESS;
    }

    private function latestBackupPath($disk): ?string
    {
        $zips = collect($disk->allFiles())
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->sort()
            ->values();

        return $zips->last();
    }

    private function readDumpFromZip(string $absolutePath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            return null;
        }

        $dumpSql = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_starts_with($entry, 'db-dumps/') && str_ends_with($entry, '.sql')) {
                $dumpSql = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        return $dumpSql === false ? null : $dumpSql;
    }

    private function tablesWithDataInDump(string $dumpSql): array
    {
        preg_match_all('/INSERT INTO `([^`]+)`/', $dumpSql, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function tablesWithLiveData(): array
    {
        $tablesWithData = [];

        foreach (DB::getSchemaBuilder()->getTableListing() as $qualifiedTable) {
            $table = str_contains($qualifiedTable, '.')
                ? substr($qualifiedTable, strrpos($qualifiedTable, '.') + 1)
                : $qualifiedTable;

            if (DB::table($table)->limit(1)->exists()) {
                $tablesWithData[] = $table;
            }
        }

        return $tablesWithData;
    }

    private function reportFailure(string $message): int
    {
        $this->error($message);

        return Command::FAILURE;
    }
}
