<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\TrendingArticles;

class ShowArticle
{
    /**
     * The view is recorded by the `views` middleware on the route, once this
     * has returned a successful response.
     */
    public function __invoke(Article $article): Article
    {
        return $article;
    }
}
