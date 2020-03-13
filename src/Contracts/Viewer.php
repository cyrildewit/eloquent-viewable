<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

interface Viewer
{
    /**
     * Get the unique ID that represent's the viewer.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the viewer's IP address.
     *
     * @return string|null
     */
    public function ip(): string;

    /**
     * Determine if the viewer has a "Do Not Track" header.
     *
     * @return bool
     */
    public function hasDoNotTrackHeader(): bool;

    /**
     * Determine if the viewer is a crawler.
     *
     * @return bool
     */
    public function isCrawler(): bool;
}
