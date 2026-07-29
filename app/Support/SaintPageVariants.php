<?php

namespace App\Support;

class SaintPageVariants
{
    public static function all(string $slug): array
    {
        $defaultScheme = self::defaultScheme($slug);

        return [
            'default' => [
                'randomizeScheme' => true,
                'scheme' => $defaultScheme,
                'circles' => self::circles('verdant', true),
            ],
            'classic-gold' => [
                'scheme' => self::scheme(
                    '#e3d1a4',
                    '#f8f1e6',
                    '#fffaf0',
                    '#fff6e0',
                    '#ded3c1',
                    '#102b2d',
                    '#ad761e',
                ),
                'circles' => self::circles('gold'),
            ],
            'celtic-green' => [
                'scheme' => self::scheme(
                    '#d9e5df',
                    '#f1f4ec',
                    '#fbfaf4',
                    '#f7f8ee',
                    '#c9d4ca',
                    '#143433',
                    '#4e7b68',
                ),
                'circles' => self::circles('verdant', true),
            ],
            'marian-blue' => [
                'scheme' => self::scheme(
                    '#dfe6ef',
                    '#f3f6f8',
                    '#fbfbf7',
                    '#f8f9f2',
                    '#c9d2dc',
                    '#183348',
                    '#56738f',
                ),
                'circles' => self::circles('blue'),
            ],
            'martyr-crimson' => [
                'scheme' => self::scheme(
                    '#eadbd8',
                    '#f8efec',
                    '#fbfaf6',
                    '#fbf6ec',
                    '#dcc8c1',
                    '#342525',
                    '#9b4a43',
                ),
                'circles' => self::circles('crimson', true),
            ],
            'monastic-olive' => [
                'scheme' => self::scheme(
                    '#dde5d1',
                    '#f1f5e9',
                    '#fbfaf3',
                    '#f7f8ed',
                    '#cbd8bf',
                    '#243829',
                    '#6f805c',
                ),
                'circles' => self::circles('olive'),
            ],
            'desert-rose' => [
                'scheme' => self::scheme(
                    '#e8d8d1',
                    '#f6eee8',
                    '#fbf7f0',
                    '#fff6ea',
                    '#decbbf',
                    '#3a2c29',
                    '#b26b55',
                ),
                'circles' => self::circles('rose', true),
            ],
            'bishop-plum' => [
                'scheme' => self::scheme(
                    '#ead6d2',
                    '#f7ebe7',
                    '#fff8ee',
                    '#fff3df',
                    '#dfc4b9',
                    '#33211f',
                    '#7f3438',
                ),
                'circles' => self::circles('plum', true),
            ],
            'doctor-indigo' => [
                'scheme' => self::scheme(
                    '#dce2eb',
                    '#f0f3f7',
                    '#fbfaf5',
                    '#f7f8ef',
                    '#c8d0db',
                    '#1d3048',
                    '#455f88',
                ),
                'circles' => self::circles('indigo'),
            ],
            'virgin-ivory' => [
                'scheme' => self::scheme(
                    '#e8e3d9',
                    '#f6f2e9',
                    '#fffaf0',
                    '#fff8e8',
                    '#d9cfbf',
                    '#2c302f',
                    '#b08b45',
                ),
                'circles' => self::circles('ivory'),
            ],
            'mission-teal' => [
                'scheme' => self::scheme(
                    '#d7e4e2',
                    '#edf4f1',
                    '#fbfaf3',
                    '#f7f8ee',
                    '#c5d7d4',
                    '#14383c',
                    '#3f7f7b',
                ),
                'circles' => self::circles('teal', true),
            ],
            'papal-cream' => [
                'scheme' => self::scheme(
                    '#eadfca',
                    '#f8f2e5',
                    '#fffaf0',
                    '#fff8e6',
                    '#ded0b9',
                    '#2f2b24',
                    '#9d7734',
                ),
                'circles' => self::circles('cream'),
            ],
            'ascetic-stone' => [
                'scheme' => self::scheme(
                    '#d8ddd7',
                    '#edf0eb',
                    '#fbfaf4',
                    '#f6f6ee',
                    '#c8cec6',
                    '#2a302d',
                    '#697467',
                ),
                'circles' => self::circles('stone'),
            ],
            'dominican-charcoal' => [
                'scheme' => self::scheme(
                    '#d9d9d4',
                    '#efeee9',
                    '#fbfaf4',
                    '#f7f6ee',
                    '#c9c8bf',
                    '#202322',
                    '#4b5350',
                ),
                'circles' => self::circles('charcoal'),
            ],
            'royal-red-gold' => [
                'scheme' => self::scheme(
                    '#ead8cf',
                    '#f8eee5',
                    '#fff8ea',
                    '#fff5df',
                    '#dec7b6',
                    '#34221f',
                    '#9f4635',
                ),
                'circles' => self::circles('royal', true),
            ],
            'byzantine-jewel' => [
                'scheme' => self::scheme(
                    '#d8dee7',
                    '#f0edf2',
                    '#fbf7ec',
                    '#faf3e2',
                    '#d1c1a8',
                    '#1f2938',
                    '#315f79',
                ),
                'circles' => self::circles('jewel', true),
            ],
            'floral-rose' => [
                'scheme' => self::scheme(
                    '#ead9dd',
                    '#f8eef1',
                    '#fff8f0',
                    '#fff6ea',
                    '#dec8cd',
                    '#38282e',
                    '#b55f78',
                ),
                'circles' => self::circles('floral', true),
            ],
            'sea-aqua' => [
                'scheme' => self::scheme(
                    '#d5e5e7',
                    '#edf5f5',
                    '#fbfaf0',
                    '#f6f8ee',
                    '#c4d8da',
                    '#14363f',
                    '#438a92',
                ),
                'circles' => self::circles('aqua'),
            ],
        ];
    }

    public static function names(): array
    {
        return [
            'classic-gold',
            'celtic-green',
            'marian-blue',
            'martyr-crimson',
            'monastic-olive',
            'desert-rose',
            'bishop-plum',
            'doctor-indigo',
            'virgin-ivory',
            'mission-teal',
            'papal-cream',
            'ascetic-stone',
            'dominican-charcoal',
            'royal-red-gold',
            'byzantine-jewel',
            'floral-rose',
            'sea-aqua',
        ];
    }

    public static function defaultForSlug(string $slug): string
    {
        return self::names()[abs(crc32($slug)) % count(self::names())];
    }

    private static function defaultScheme(string $slug): array
    {
        $schemes = [
            self::allScheme('celtic-green'),
            self::allScheme('marian-blue'),
            self::allScheme('martyr-crimson'),
            self::allScheme('monastic-olive'),
        ];

        return $schemes[abs(crc32($slug)) % count($schemes)];
    }

    private static function allScheme(string $name): array
    {
        return match ($name) {
            'marian-blue' => self::scheme('#dfe6ef', '#f3f6f8', '#fbfbf7', '#f8f9f2', '#c9d2dc', '#183348', '#56738f'),
            'martyr-crimson' => self::scheme('#eadbd8', '#f8efec', '#fbfaf6', '#fbf6ec', '#dcc8c1', '#342525', '#9b4a43'),
            'monastic-olive' => self::scheme('#dde5d1', '#f1f5e9', '#fbfaf3', '#f7f8ed', '#cbd8bf', '#243829', '#6f805c'),
            default => self::scheme('#d9e5df', '#f1f4ec', '#fbfaf4', '#f7f8ee', '#c9d4ca', '#143433', '#4e7b68'),
        };
    }

    private static function scheme(
        string $pageStart,
        string $pageMid,
        string $pageEnd,
        string $card,
        string $border,
        string $text,
        string $accent,
    ): array {
        return [
            'pageStart' => $pageStart,
            'pageMid' => $pageMid,
            'pageEnd' => $pageEnd,
            'card' => $card,
            'border' => $border,
            'text' => $text,
            'accent' => $accent,
            'backdrop' => 'rgb(255 253 248 / 68%)',
            'circles' => [$accent, $pageStart, $pageMid, $border, $pageEnd],
        ];
    }

    private static function circles(string $palette, bool $curved = false): array
    {
        [$first, $second, $third, $fourth] = match ($palette) {
            'blue' => ['#28465f', '#dfe6ef', '#7d8ea2', '#f3f6f8'],
            'crimson' => ['#8d3933', '#eadbd8', '#665350', '#f8efec'],
            'olive' => ['#4f6844', '#dde5d1', '#7c9070', '#f1f5e9'],
            'rose' => ['#a75f4b', '#e8d8d1', '#7e655a', '#f6eee8'],
            'plum' => ['#713238', '#ead6d2', '#b58236', '#fff1d7'],
            'indigo' => ['#344d75', '#dce2eb', '#7387a6', '#f0f3f7'],
            'ivory' => ['#b08b45', '#f6f2e9', '#c7ad72', '#fffaf0'],
            'teal' => ['#2f6f6d', '#d7e4e2', '#78a39e', '#edf4f1'],
            'cream' => ['#9d7734', '#eadfca', '#c8a85f', '#f8f2e5'],
            'stone' => ['#697467', '#d8ddd7', '#8d9788', '#edf0eb'],
            'charcoal' => ['#343a38', '#d9d9d4', '#6d746f', '#efeee9'],
            'royal' => ['#9f4635', '#ead8cf', '#c8a04a', '#fff1d2'],
            'jewel' => ['#214b68', '#d8dee7', '#7a2d44', '#d6b45c'],
            'floral' => ['#b55f78', '#ead9dd', '#d08aa0', '#fff2e8'],
            'aqua' => ['#2e7f8a', '#d5e5e7', '#79aeb4', '#edf5f5'],
            'gold' => ['#b9831f', '#efe1bd', '#113d2a', '#f3ead7'],
            default => ['#315a37', '#d9e5df', '#1f4c5f', '#f1f4ec'],
        };

        return [
            ['size' => 410, 'cssSize' => 'clamp(24rem, 44vmin, 48rem)', 'x' => '-150px', 'y' => '-160px', 'first' => $first, 'second' => $second, 'rotation' => -1, 'cut' => $curved ? 'curved' : 'straight'],
            ['size' => 210, 'cssSize' => 'clamp(14rem, 24vmin, 28rem)', 'x' => 'calc(100% - 86px)', 'y' => '-76px', 'first' => $first, 'second' => $fourth, 'rotation' => -18, 'opacity' => 0.42, 'cut' => $curved ? 'curved' : 'straight'],
            ['size' => 410, 'cssSize' => 'clamp(24rem, 44vmin, 48rem)', 'x' => 'calc(100% - 260px)', 'y' => 'calc(100% - 90px)', 'first' => $third, 'second' => $fourth, 'rotation' => 42, 'opacity' => 0.52, 'cut' => $curved ? 'curved' : 'straight'],
            ['size' => 350, 'cssSize' => 'clamp(21rem, 38vmin, 42rem)', 'x' => '-78px', 'y' => 'calc(100% - 120px)', 'first' => $third, 'second' => $first, 'rotation' => -22, 'opacity' => 0.48, 'cut' => $curved ? 'curved' : 'straight'],
        ];
    }
}
