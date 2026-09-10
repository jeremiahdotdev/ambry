<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WordPrefixSearch
{
    public static function where(Builder $builder, string $column, string $text, string $boolean = 'and'): void
    {
        $connection = $builder->getConnection();
        $driver = $connection->getDriverName();
        $letters = [
            'a' => 'aàáâãäåāăą', 'c' => 'cçćč', 'd' => 'dďđ',
            'e' => 'eèéêëēĕėęě', 'g' => 'gğģ', 'i' => 'iìíîïĩīĭįı',
            'l' => 'lĺļľł', 'n' => 'nñńņň', 'o' => 'oòóôõöøōŏő',
            'r' => 'rŕř', 's' => 'sśşš', 't' => 'tţť',
            'u' => 'uùúûüũūŭůűų', 'y' => 'yýÿ', 'z' => 'zźżž',
        ];
        $pattern = '(^|[^[:alnum:]_])';
        foreach (mb_str_split(mb_strtolower($text)) as $character) {
            $base = Str::ascii($character);
            $pattern .= isset($letters[$base]) ? '['.$letters[$base].']' : preg_quote($character);
        }

        if ($driver === 'sqlite') {
            $connection->getPdo()->sqliteCreateFunction('regexp', static function ($pattern, $value): int {
                return preg_match('~'.str_replace('~', '\\~', $pattern).'~iu', (string) $value) === 1 ? 1 : 0;
            }, 2);
        }

        $operator = $driver === 'pgsql' ? '~*' : 'regexp';
        $builder->whereRaw("lower(coalesce({$column}, '')) {$operator} ?", [$pattern], $boolean);
    }
}
