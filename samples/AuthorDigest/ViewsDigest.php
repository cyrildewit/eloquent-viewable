<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\AuthorDigest;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViewsDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly WeeklyDigest $digest) {}

    /** @return list<string> */
    public function via(Author $author): array
    {
        return ['mail'];
    }

    public function toMail(Author $author): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your week on the blog: {$this->views($this->digest->views->current)}")
            ->greeting("Hi {$author->name},")
            ->line("Your essays got {$this->views($this->digest->views->current)} in the week of {$this->digest->week->format('j F')}, {$this->trend()}.");

        foreach ($this->digest->top as $rank => $essay) {
            $mail->line(($rank + 1).". {$essay['title']}: {$this->views($essay['views'])}");
        }

        return $mail;
    }

    private function views(int $count): string
    {
        return number_format($count).($count === 1 ? ' view' : ' views');
    }

    private function trend(): string
    {
        $comparison = $this->digest->views;

        // Growth from nothing has no percentage.
        if ($comparison->percent === null) {
            return 'and none the week before';
        }

        return match (true) {
            $comparison->delta > 0 => "up {$comparison->percent}% on the week before",
            $comparison->delta < 0 => 'down '.abs($comparison->percent).'% on the week before',
            default => 'the same as the week before',
        };
    }
}
