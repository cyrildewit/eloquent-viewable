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
use CyrildeWit\EloquentViewable\Support\MorphLoader;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewableSet;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
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
        private PresenceConfig $config,
        private ViewableLoader $loader,
        private ?Viewable $viewable = null,
        private ?ViewableSet $viewables = null,
        private ?string $collection = null,
        private ?int $within = null,
    ) {}

    /**
     * The same reader, over a model, a type, a set of models or the whole
     * site, within a collection or every one.
     *
     * @internal
     */
    public function scoped(?Viewable $viewable, ?ViewableSet $viewables, ?string $collection): self
    {
        return new self($this->store, $this->config, $this->loader, $viewable, $viewables, $collection, $this->within);
    }

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
        $window = $this->config->window();

        if ($seconds < 1 || $seconds > $window) {
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
     * The number of active visitors of each viewable in the set, keyed by the
     * key of the viewable.
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

        $candidates = $this->store->active($type, $since, $this->config->maxCandidates());

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
        if (! $this->config->tracksViewers()) {
            throw InvalidConfiguration::presenceViewersDisabled();
        }

        if ($limit < 1) {
            throw InvalidLimit::belowOne($limit, 'live()->viewers()');
        }

        $references = $this->store->viewers(Scope::of($this->viewable, $this->collection), $this->since(), $limit);
        $models = MorphLoader::load(array_map(static fn (Reference $reference): array => ['type' => $reference->type, 'id' => $reference->id], $references));
        $viewers = new EloquentCollection;

        foreach ($references as $reference) {
            $model = $models[$reference->type][(string) $reference->id] ?? null;

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
        if (! $this->config->enabled()) {
            throw InvalidConfiguration::presenceDisabled();
        }

        return Carbon::now()->subSeconds($this->within ?? $this->config->window());
    }
}
