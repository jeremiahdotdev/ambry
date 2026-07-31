<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sources:load-split {path=../ai-pipelines/data/processed/catholic-sources/structured/manifest.json}', function (): int {
    $argument = (string) $this->argument('path');
    $path = str_starts_with($argument, DIRECTORY_SEPARATOR)
        ? $argument
        : realpath(base_path($argument));

    if ($path === false || ! is_file($path)) {
        $this->error("Manifest not found: {$argument}");

        return self::FAILURE;
    }

    $manifest = json_decode((string) file_get_contents($path), true);

    if (! is_array($manifest) || ! is_array($manifest['tables'] ?? null)) {
        $this->error('Manifest must include a tables object.');

        return self::FAILURE;
    }

    $baseDir = dirname($path);
    $loadRows = function (string $table) use ($manifest, $baseDir): array {
        $entry = $manifest['tables'][$table] ?? null;

        if (! is_array($entry) || ! is_string($entry['path'] ?? null)) {
            throw new RuntimeException("Manifest is missing {$table}.path");
        }

        $payload = json_decode((string) file_get_contents($baseDir.DIRECTORY_SEPARATOR.$entry['path']), true);

        if (! is_array($payload) || ! is_array($payload['rows'] ?? null)) {
            throw new RuntimeException("{$entry['path']} must include a rows array");
        }

        return $payload['rows'];
    };
    $now = now();
    $encodeJson = fn ($value): ?string => $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE);
    $upsert = function (string $table, array $rows, array $uniqueBy, array $updateColumns): void {
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
        }
    };

    $sources = array_map(fn (array $row): array => [
        'id' => $row['id'] ?? (string) Str::uuid(),
        'name' => $row['name'],
        'slug' => $row['slug'],
        'type' => $row['type'] ?? null,
        'license' => $row['license'] ?? null,
        'attribution' => $row['attribution'] ?? null,
        'canonical_url' => $row['canonical_url'] ?? null,
        'reliability_notes' => $row['reliability_notes'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ], $loadRows('sources'));

    $sourceDocuments = array_map(fn (array $row): array => [
        'id' => $row['id'] ?? (string) Str::uuid(),
        'source_id' => $row['source_id'],
        'title' => $row['title'],
        'slug' => $row['slug'] ?? null,
        'author' => $row['author'] ?? null,
        'edition' => $row['edition'] ?? null,
        'language' => $row['language'] ?? 'en',
        'url' => $row['url'] ?? null,
        'raw_text' => $row['raw_text'] ?? null,
        'checksum' => $row['checksum'] ?? null,
        'metadata' => $encodeJson($row['metadata'] ?? null),
        'created_at' => $now,
        'updated_at' => $now,
    ], $loadRows('source_documents'));

    $citations = array_map(fn (array $row): array => [
        'id' => $row['id'] ?? (string) Str::uuid(),
        'source_id' => $row['source_id'] ?? null,
        'title' => $row['title'] ?? null,
        'locator' => $row['locator'] ?? null,
        'url' => $row['url'] ?? null,
        'excerpt' => $row['excerpt'] ?? null,
        'accessed_at' => $row['accessed_at'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ], $loadRows('citations'));

    $upsert('sources', $sources, ['id'], ['name', 'slug', 'type', 'license', 'attribution', 'canonical_url', 'reliability_notes', 'updated_at']);
    $upsert('source_documents', $sourceDocuments, ['id'], ['source_id', 'title', 'slug', 'author', 'edition', 'language', 'url', 'raw_text', 'checksum', 'metadata', 'updated_at']);
    $upsert('citations', $citations, ['id'], ['source_id', 'title', 'locator', 'url', 'excerpt', 'accessed_at', 'updated_at']);

    $this->info(sprintf(
        'Loaded %s sources, %s source documents, %s citations.',
        number_format(count($sources)),
        number_format(count($sourceDocuments)),
        number_format(count($citations)),
    ));

    return self::SUCCESS;
})->purpose('Load split DB-ready source documents and citations into the active database connection.');

Artisan::command('profile-enrichment:sync {path=../ai-pipelines/data/processed/catholic-sources/raw/profile-enrichment} {--slug=* : Only sync one or more slugs} {--dry-run : Show what would be updated without writing}', function (): int {
    $argument = (string) $this->argument('path');
    $path = str_starts_with($argument, DIRECTORY_SEPARATOR)
        ? $argument
        : base_path($argument);
    $dryRun = (bool) $this->option('dry-run');
    $slugs = collect((array) $this->option('slug'))
        ->filter()
        ->values();

    if (! file_exists($path)) {
        $this->error("Profile enrichment path not found: {$argument}");

        return self::FAILURE;
    }

    $files = is_file($path)
        ? collect([$path])
        : collect(glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'response.output.json') ?: []);

    if ($slugs->isNotEmpty()) {
        $files = $files->filter(function (string $file) use ($slugs): bool {
            $slug = basename(dirname($file));

            return $slugs->contains($slug);
        })->values();
    }

    if ($files->isEmpty()) {
        $this->warn('No profile enrichment response.output.json files found.');

        return self::SUCCESS;
    }

    $encodeJson = fn ($value): ?string => $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE);
    $updated = 0;
    $missing = 0;
    $failed = 0;

    foreach ($files as $file) {
        $payload = json_decode((string) file_get_contents($file), true);

        if (! is_array($payload)) {
            $failed++;
            $this->error("Invalid JSON: {$file}");

            continue;
        }

        $slug = (string) ($payload['slug'] ?? basename(dirname($file)));
        $requestPath = dirname($file).DIRECTORY_SEPARATOR.'request.json';
        $request = is_file($requestPath)
            ? json_decode((string) file_get_contents($requestPath), true)
            : [];

        if (! DB::table('saints')->where('slug', $slug)->exists()) {
            $missing++;
            $this->warn("No saint found for {$slug}");

            continue;
        }

        $values = [
            'profile_enrichment' => $encodeJson($payload),
            'profile_summary' => $payload['summary'] ?? null,
            'profile_life_span' => $encodeJson($payload['life_span'] ?? null),
            'profile_patronages' => $encodeJson($payload['patronages'] ?? null),
            'profile_temperaments' => $encodeJson($payload['temperaments'] ?? null),
            'profile_key_struggles' => $encodeJson($payload['key_struggles'] ?? null),
            'profile_key_virtues' => $encodeJson($payload['key_virtues'] ?? null),
            'profile_church_roles' => $encodeJson($payload['church_roles'] ?? null),
            'profile_feast_days' => $encodeJson($payload['feast_days'] ?? null),
            'profile_related_saints' => $encodeJson($payload['related_saints'] ?? null),
            'profile_works' => $encodeJson($payload['works'] ?? null),
            'profile_sources' => $encodeJson($payload['sources'] ?? null),
            'profile_source_block' => $encodeJson($payload['source_block'] ?? null),
            'profile_research_notes' => $encodeJson($payload['research_notes'] ?? null),
            'profile_enrichment_model' => is_array($request) ? ($request['model'] ?? null) : null,
            'profile_enriched_at' => now(),
            'updated_at' => now(),
        ];

        $this->line(($dryRun ? 'Would sync ' : 'Syncing ').$slug);

        if (! $dryRun) {
            DB::table('saints')->where('slug', $slug)->update($values);
        }

        $updated++;
    }

    $this->info(sprintf(
        '%s %s profile enrichment rows. Missing: %s. Failed: %s.',
        $dryRun ? 'Would sync' : 'Synced',
        number_format($updated),
        number_format($missing),
        number_format($failed),
    ));

    return $failed > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Sync saved AI profile enrichment JSON into new saints.profile_* columns only.');

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
