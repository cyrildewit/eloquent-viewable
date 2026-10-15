<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Cooldowns\Stores;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use DateTimeInterface;
use Illuminate\Contracts\Session\Session;

final readonly class SessionStore implements CooldownStore
{
    public function __construct(
        private Session $session,
        private string $key,
    ) {}

    public function has(string $key): bool
    {
        return isset($this->running()[$key]);
    }

    public function put(string $key, DateTimeInterface $expiresAt): void
    {
        $this->session->put($this->key, [...$this->running(), $key => $expiresAt->getTimestamp()]);
    }

    /**
     * Drops the expired cooldowns from the session on the way.
     *
     * @return array<string, int>
     */
    private function running(): array
    {
        $stored = $this->session->get($this->key, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        $now = Carbon::now()->getTimestamp();

        /** @var array<string, int> $running */
        $running = array_filter($stored, fn (mixed $expiresAt): bool => is_int($expiresAt) && $expiresAt > $now);

        if (count($running) !== count($stored)) {
            $this->session->put($this->key, $running);
        }

        return $running;
    }
}
