<?php

namespace Webkul\Core\Support;

final class BusinessUnit
{
    public const VARBEL = 'varbel';

    public const VARTECH = 'vartech';

    public const CAPTURE_IT = 'capture_it';

    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function options(): array
    {
        return [
            self::VARBEL => 'Varbel - EO',
            self::VARTECH => 'Vartech - Event Tech',
            self::CAPTURE_IT => 'Capture It - Photobooth',
        ];
    }

    public static function label(?string $value): string
    {
        return self::options()[$value] ?? '-';
    }
}
