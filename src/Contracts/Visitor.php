<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

interface Visitor
{
    /**
     * Get the unique ID that represent's the visitor.
     */
    public function id(): string;

    /**
     * Get the visitor IP address.
     */
    public function ip(): ?string;

    /**
     * Determine if the visitor has a "Do Not Track" header.
     */
    public function hasDoNotTrackHeader(): bool;

    /**
     * Determine if the visitor is a crawler.
     */
    public function isCrawler(): bool;
}
