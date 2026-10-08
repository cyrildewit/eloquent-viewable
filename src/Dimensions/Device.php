<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;

/**
 * The kind of device: `bot` when the crawler detector says so, the same
 * detector the `IgnoreCrawlers` guard asks, otherwise `tablet`, `mobile` or
 * `desktop` from the user agent. Bots only show up when that guard is off.
 * Each user agent is judged once per request.
 */
final class Device extends Dimension
{
    public const string Bot = 'bot';

    public const string Tablet = 'tablet';

    public const string Mobile = 'mobile';

    public const string Desktop = 'desktop';

    private const int Remembered = 64;

    private const string TabletPattern = '/iPad|Tablet|PlayBook|Silk|Kindle|Nexus (?:7|9|10)\b|SM-T\d/i';

    private const string MobilePattern = '/Mobi|iPhone|iPod|Windows Phone|IEMobile|BlackBerry|BB10|Opera Mini|webOS/i';

    /** @var array<string, string> */
    private array $judged = [];

    public function __construct(
        private readonly CrawlerDetector $crawlers,
        bool $personal = false,
        ?int $maxValues = self::MaxValues,
        ?string $json = null,
    ) {
        parent::__construct($personal, $maxValues, $json);
    }

    public function resolve(DimensionInput $input): ?string
    {
        $userAgent = $input->visitor->userAgent();

        if ($userAgent === null) {
            return null;
        }

        if (isset($this->judged[$userAgent])) {
            return $this->judged[$userAgent];
        }

        if (count($this->judged) >= self::Remembered) {
            $this->judged = [];
        }

        return $this->judged[$userAgent] = $this->judge($userAgent);
    }

    private function judge(string $userAgent): string
    {
        if ($this->crawlers->isCrawler($userAgent)) {
            return self::Bot;
        }

        if (preg_match(self::TabletPattern, $userAgent) === 1) {
            return self::Tablet;
        }

        if (preg_match(self::MobilePattern, $userAgent) === 1) {
            return self::Mobile;
        }

        // A phone on Android says `Mobile`, so an Android device that does
        // not is a tablet.
        if (stripos($userAgent, 'Android') !== false) {
            return self::Tablet;
        }

        return self::Desktop;
    }
}
