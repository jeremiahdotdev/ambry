<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('db:copy-sqlite-to-pgsql {--source=database/database.sqlite} {--fresh : Drop and recreate the target schema before copying} {--dry-run : Show what would be copied without writing}', function (): int {
    $source = base_path((string) $this->option('source'));
    $dryRun = (bool) $this->option('dry-run');

    if (! is_file($source)) {
        $this->error("SQLite database not found: {$source}");

        return self::FAILURE;
    }

    if (! $dryRun && config('database.connections.pgsql.url') === null && ! env('DB_HOST')) {
        $this->error('Postgres target is not configured. Set DB_URL or DATABASE_URL to your Neon connection string.');

        return self::FAILURE;
    }

    if ($dryRun) {
        $this->warn('Dry run: target Postgres database will not be changed.');
    } elseif ($this->option('fresh')) {
        $this->call('migrate:fresh', [
            '--database' => 'pgsql',
            '--force' => true,
        ]);
    } else {
        $this->call('migrate', [
            '--database' => 'pgsql',
            '--force' => true,
        ]);
    }

    $sqlite = new PDO('sqlite:'.$source);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = collect($sqlite->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name")->fetchAll(PDO::FETCH_COLUMN))
        ->reject(fn (string $table): bool => $table === 'migrations')
        ->values()
        ->all();

    $copyOrder = [
        'users',
        'password_reset_tokens',
        'sources',
        'citations',
        'saints',
        'saint_aliases',
        'feast_days',
        'patronages',
        'patronage_saint',
        'religious_orders',
        'religious_order_saint',
        'countries',
        'relationship_types',
        'relationships',
        'search_documents',
        'bookmarks',
        'source_documents',
        'import_batches',
        'imported_facts',
        'research_leads',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
    ];
    $tables = array_values(array_unique(array_merge(
        array_values(array_intersect($copyOrder, $tables)),
        array_values(array_diff($tables, $copyOrder)),
    )));

    $counts = [];

    if (! $dryRun) {
        $pgsql = DB::connection('pgsql');
        $quotePgIdentifier = fn (string $identifier): string => '"'.str_replace('"', '""', $identifier).'"';
        $resetPgSequence = function (PDO $pdo, string $table, string $column) use ($quotePgIdentifier): void {
            $statement = $pdo->prepare('select pg_get_serial_sequence(?, ?)');
            $statement->execute([$table, $column]);
            $sequence = $statement->fetchColumn();

            if (! $sequence) {
                return;
            }

            $pdo->exec(
                'select setval('
                .$pdo->quote((string) $sequence)
                .', coalesce((select max('.$quotePgIdentifier($column).') from '.$quotePgIdentifier($table).'), 1), true)'
            );
        };
        $copyTables = function () use ($sqlite, $pgsql, $quotePgIdentifier, $resetPgSequence, &$counts, &$tables): void {
            $pdo = $pgsql->getPdo();

            foreach (array_reverse($tables) as $table) {
                $pdo->exec('truncate table '.$quotePgIdentifier($table).' restart identity cascade');
            }

            foreach ($tables as $table) {
                $rows = $sqlite->query('select * from "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
                $counts[$table] = count($rows);

                if ($rows === []) {
                    continue;
                }

                $columnTypes = collect($pdo->query(
                    "select column_name, data_type from information_schema.columns where table_schema = 'public' and table_name = ".$pdo->quote($table)
                )->fetchAll(PDO::FETCH_ASSOC))->pluck('data_type', 'column_name')->all();
                $columns = array_keys($rows[0]);
                $columnList = implode(', ', array_map($quotePgIdentifier, $columns));
                $placeholders = implode(', ', array_map(fn (string $column): string => ':'.$column, $columns));
                $statement = $pdo->prepare('insert into '.$quotePgIdentifier($table)." ({$columnList}) values ({$placeholders})");

                foreach ($rows as $row) {
                    foreach ($row as $column => $value) {
                        $type = $columnTypes[$column] ?? null;

                        if ($type === 'boolean') {
                            $row[$column] = $value === null || $value === '' ? 'false' : ((bool) $value ? 'true' : 'false');
                            continue;
                        }

                        if ($value === '' && ! in_array($type, ['character varying', 'text', 'character'], true)) {
                            $row[$column] = null;
                        }
                    }

                    try {
                        $statement->execute($row);
                    } catch (PDOException $exception) {
                        $sample = collect($row)
                            ->map(fn ($value, string $column): string => $column.'='.json_encode($value))
                            ->implode(', ');

                        throw new RuntimeException("Failed copying {$table}: {$sample}", previous: $exception);
                    }
                }

                if (array_key_exists('id', $rows[0]) && is_numeric($rows[0]['id'])) {
                    $resetPgSequence($pdo, $table, 'id');
                }
            }
        };
        $pgsql->transaction($copyTables);
    } else {
        foreach ($tables as $table) {
            $counts[$table] = (int) $sqlite->query('select count(*) from "'.$table.'"')->fetchColumn();
        }
    }

    foreach ($counts as $table => $count) {
        $this->line(sprintf('%s: %s', $table, number_format($count)));
    }

    $this->info('SQLite to Postgres copy '.($dryRun ? 'dry run complete.' : 'complete.'));

    return self::SUCCESS;
})->purpose('Copy the local SQLite database into the configured pgsql database.');
