<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Exceptions\InvalidWindow;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Ranking\ViewableLoader;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewableSet;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Reads who is looking right now. A viewable with a key reads that viewable,
 * one without a key, such as `new Post`, reads its type, and none reads the
 * whole site. A collection narrows each read to the visitors in it.
 */
final readonly class LiveViews
{
    public function __construct(
        private PresenceStore $store,
        private Config $config,
        private ViewableLoader $loader,
        private ?Viewable $viewable = null,
        private ?ViewableSet $viewables = null,
        private ?string $collection = null,
        private ?int $within = null,
    ) {}

    /**
     * Counts only the visitors seen in the last given seconds, at most the
     * configured `presence.window`.
     *
     * @throws InvalidConfiguration
     * @throws InvalidWindow
     */
    public function within(CarbonInterval|int $seconds): self
    {
        $seconds = $seconds instanceof CarbonInterval ? (int) $seconds->totalSeconds : $seconds;
        $window = $this->config->presenceWindow();

        if ($seconds < 1) {
            throw InvalidWindow::outOfRange($seconds, $window);
        }

        if ($seconds > $window) {
            throw InvalidWindow::outOfRange($seconds, $window);
        }

        return new self($this->store, $this->config, $this->loader, $this->viewable, $this->viewables, $this->collection, $seconds);
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function count(): int
    {
        if ($this->viewables instanceof ViewableSet) {
            throw InvalidViewable::setNeedsCounts();
        }

        return $this->store->countVisitors([Scope::of($this->viewable, $this->collection)], $this->since())[0] ?? 0;
    }

    /**
     * The active visitors of every viewable of the set, keyed by its key.
     *
     * @return Collection<int|string, int>
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function counts(): Collection
    {
        $viewables = $this->viewables ?? throw InvalidViewable::missingSet();
        $type = $viewables->type()?->getMorphClass();
        $keys = $viewables->keys();

        $scopes = array_map(fn (int|string $key): Scope => new Scope($type, $key, $this->collection), $keys);

        return new Collection(array_combine($keys, $this->store->countVisitors($scopes, $this->since())));
    }

    /**
     * What is being looked at right now, ranked by its active visitors. Only
     * the `presence.max_candidates` most recently seen viewables are ranked.
     *
     * @throws InvalidConfiguration
     * @throws InvalidLimit
     * @throws InvalidViewable
     */
    public function top(int $limit = 10): Ranking
    {
        if ($limit < 1) {
            throw InvalidLimit::belowOne($limit, 'live()->top()');
        }

        $type = $this->type();
        $since = $this->since();

        $candidates = $this->store->active($type, $since, $this->config->presenceMaxCandidates());

        if ($candidates === []) {
            return new Ranking(new Collection);
        }

        $counts = $this->store->countVisitors(
            array_map(fn (Reference $candidate): Scope => new Scope($candidate->type, $candidate->id, $this->collection), $candidates),
            $since,
        );

        $rows = [];

        foreach ($candidates as $index => $candidate) {
            $count = $counts[$index] ?? 0;

            if ($count > 0) {
                $rows[] = ['type' => $candidate->type, 'id' => $candidate->id, 'count' => $count];
            }
        }

        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $this->loader->load(array_slice($rows, 0, $limit));
    }

    /**
     * The signed-in viewers looking right now, the most recently seen first.
     * Needs `presence.viewers`.
     *
     * @return EloquentCollection<int, Model>
     *
     * @throws InvalidConfiguration
     * @throws InvalidLimit
     * @throws InvalidViewable
     * @throws InvalidViewer
     */
    public function viewers(int $limit = 100): EloquentCollection
    {
        if (! $this->config->presenceTracksViewers()) {
            throw InvalidConfiguration::presenceViewersDisabled();
        }

        if ($limit < 1) {
            throw InvalidLimit::belowOne($limit, 'live()->viewers()');
        }

        $references = $this->store->viewers(Scope::of($this->viewable, $this->collection), $this->since(), $limit);

        $models = [];

        foreach (new Collection($references)->groupBy('type') as $type => $group) {
            $class = Relation::getMorphedModel((string) $type) ?? (string) $type;

            if (! is_a($class, Model::class, true)) {
                continue;
            }

            foreach (new $class()->newQuery()->whereKey($group->pluck('id')->all())->get() as $model) {
                $key = ViewerKey::of($model);

                $models["{$type}|{$key}"] = $model;
            }
        }

        $viewers = new EloquentCollection;

        foreach ($references as $reference) {
            $model = $models["{$reference->type}|{$reference->id}"] ?? null;

            if ($model instanceof Model) {
                $viewers->push($model);
            }
        }

        return $viewers;
    }

    /** @throws InvalidViewable */
    private function type(): ?string
    {
        if (! $this->viewable instanceof Viewable) {
            return null;
        }

        if (ViewableKey::of($this->viewable) !== null) {
            throw InvalidViewable::cannotRankOne($this->viewable);
        }

        return $this->viewable->getMorphClass();
    }

    /** @throws InvalidConfiguration */
    private function since(): CarbonInterface
    {
        if (! $this->config->presenceEnabled()) {
            throw InvalidConfiguration::presenceDisabled();
        }

        return Carbon::now()->subSeconds($this->within ?? $this->config->presenceWindow());
    }
}
