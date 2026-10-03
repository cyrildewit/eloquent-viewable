<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\TrendingArticles;

class ShowArticle
{
    /**
     * A reader who refreshes the page or comes back within the cooldown is
     * counted once, so a single visitor cannot push an article up the list.
     */
    private const int CooldownMinutes = 30;

    public function __invoke(Article $article): Article
    {
        views($article)
            ->cooldown(self::CooldownMinutes)
            ->record();

        return $article;
    }
}
