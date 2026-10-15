<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\BreakingNews;

class ShowStory
{
    /**
     * A reader who refreshes a developing story is counted once per
     * quarter of an hour.
     */
    private const int CooldownMinutes = 15;

    public function __invoke(Story $story): Story
    {
        // The guards run here, in the request, as with any store. With the
        // `redis` store the view itself is one XADD; the insert happens when
        // the buffer is flushed.
        views($story)
            ->cooldown(self::CooldownMinutes)
            ->record();

        return $story;
    }
}
