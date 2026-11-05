<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\AuthorDigest;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SendWeeklyDigests
{
    /** The hour on Monday morning, on the author's clock, the digest goes out. */
    private const int SendAt = 8;

    private const int Chunk = 100;

    public function __construct(private readonly AuthorDigests $digests) {}

    /**
     * Runs every hour and sends each author the digest of the last week that
     * has ended on their clock, once it is Monday morning where they are. An
     * author whose week has nothing to report is skipped but marked as done.
     *
     * @return int the number of digests sent
     */
    public function __invoke(): int
    {
        $sent = 0;

        foreach (Author::query()->distinct()->pluck('timezone') as $timezone) {
            $week = $this->lastWeek($timezone);

            Author::query()
                ->where('timezone', $timezone)
                ->where(fn ($query) => $query
                    ->whereNull('digested_week')
                    ->orWhere('digested_week', '<', $week->toDateString()))
                ->eachById(function (Author $author) use ($week, &$sent): void {
                    $digest = $this->digests->for($author, $week);

                    if ($digest instanceof WeeklyDigest) {
                        $author->notify(new ViewsDigest($digest));
                        $sent++;
                    }

                    $author->update(['digested_week' => $week->toDateString()]);
                }, self::Chunk);
        }

        return $sent;
    }

    /**
     * The Monday at midnight that starts the last whole week in the timezone.
     * Until Monday's send time has passed, that is the week before.
     */
    private function lastWeek(string $timezone): CarbonImmutable
    {
        $now = CarbonImmutable::now($timezone);
        $week = $now->startOfWeek(CarbonInterface::MONDAY)->subWeek();

        return $now->lt($week->addWeek()->setTime(self::SendAt, 0)) ? $week->subWeek() : $week;
    }
}
