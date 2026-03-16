<?php

declare(strict_types=1);

namespace Larastan\Larastan\Properties;

use PHPStan\File\FileHelper;
use RuntimeException;
use SplFileInfo;

use function count;
use function database_path;
use function file_exists;
use function ksort;

final class DatabaseExtractionHelper
{
    /** @param  string[] $schemaPaths */
    public function __construct(
        private array $schemaPaths,
        private FileHelper $fileHelper,
        private bool $enableDatabaseExtractionScan,
    ) {
    }

    /** @return SplFileInfo[] */
    public function getSchemaFiles(): array
    {
        /** @var SplFileInfo[] $schemaFiles */
        $schemaFiles = [];

        $schemaPaths = $this->schemaPaths;

        if (empty($schemaPaths)) {
            $schemaPaths = [database_path('schema.php')];
        }

        foreach ($schemaPaths as $schemaPath) {
            $absolutePath = $this->fileHelper->absolutizePath($schemaPath);

            if (! file_exists($absolutePath)) {
                continue;
            }

            $schemaFiles[] = new SplFileInfo($absolutePath);
        }

        return $schemaFiles;
    }

    /**
     * @param array<string, SchemaTable> $tables
     *
     * @return array<string, SchemaTable>
     *
     * @throws RuntimeException
     */
    public function initializeTables(array $tables): array
    {
        if (! $this->enableDatabaseExtractionScan) {
            return $tables;
        }

        $filesArray = $this->getSchemaFiles();

        if (empty($filesArray)) {
            return [];
        }

        ksort($filesArray);

        $allExtractedTables = [];

        foreach ($filesArray as $file) {
            $path = $file->getPathname();

            if (! file_exists($path)) {
                throw new RuntimeException('Schema file ' . $path . ' does not exist. Please generate the database extraction schema file.');
            }

            // Include the file to get the $tables variable
            /**
             * @var array<string, array{
             *    name: string,
             *    readableType: string,
             *    nullable: bool,
             *    options: array<int, string>,
             * }[]> $extractedTables
             */
            $extractedTables = require $path;

            foreach ($extractedTables as $tableName => $columns) {
                $schemaColumns = [];

                foreach ($columns as $column) {
                    $schemaColumns[$column['name']] = new SchemaColumn(
                        name: $column['name'],
                        readableType: $column['readableType'],
                        nullable: $column['nullable'],
                        options: count($column['options']) === 0 ? null : $column['options'],
                    );
                }

                $table          = new SchemaTable($tableName);
                $table->columns = $schemaColumns;

                $tables[$tableName] = $table;
            }
        }

        return $tables;
    }
}
