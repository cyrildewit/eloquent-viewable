<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class ViewerCookieRepository
{
    /**
     * The viewer cookie key.
     *
     * @var string
     */
    protected $key;

    /**
     * Create a new viewer cookie repository instance.
     *
     * @return void
     */
    public function __construct(ConfigRepository $config)
    {
        $this->key = $config['eloquent-viewable']['visitor_cookie_key'];
    }

    /**
     * Get the viewer's unique id.
     *
     * @return string
     */
    public function get()
    {
        if (! Cookie::has($this->key)) {
            $uniqueString = $this->generateUniqueString();

            Cookie::queue($this->key, $uniqueString, $this->expirationInMinutes());

            return $uniqueString;
        }

        return Cookie::get($this->key);
    }

    /**
     * Generate a unique viewer id.
     *
     * @return string
     */
    protected function generateUniqueString(): string
    {
        return Str::random(80);
    }

    /**
     * Get the expiration in minutes.
     *
     * @return int
     */
    protected function expirationInMinutes(): int
    {
        return 2628000; // aka 5 years
    }
}
