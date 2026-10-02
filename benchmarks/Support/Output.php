<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

/**
 * Console output for the seed, index and explain scripts.
 */
final class Output
{
    public static function line(string $text = ''): void
    {
        fwrite(STDOUT, $text.PHP_EOL);
    }

    public static function heading(string $text): void
    {
        self::line();
        self::line($text);
        self::line(str_repeat('-', mb_strlen($text)));
    }

    public static function elapsed(float $startedAt): string
    {
        $seconds = microtime(true) - $startedAt;

        return $seconds < 1
            ? sprintf('%d ms', (int) round($seconds * 1000))
            : sprintf('%.1f s', $seconds);
    }
}
