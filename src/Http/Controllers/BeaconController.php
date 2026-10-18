<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http\Controllers;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use CyrildeWit\EloquentViewable\Http\Concerns\FindsViewables;
use CyrildeWit\EloquentViewable\Http\RecordingOptions;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\UrlGenerator;

/**
 * Records a view of the model a signed beacon URL names.
 *
 * The CSRF check of the `web` group refuses a post from another site before
 * it gets here. A view a guard skips still gets a 204, so the response does
 * not tell a visitor what the guards decided.
 */
final readonly class BeaconController
{
    use FindsViewables;

    /**
     * How long the referrer and the landing query the script posts may be. The
     * limit bounds what a visitor can make the app store. A longer value is
     * cut to this length rather than refused, because a truncated referrer or
     * landing query only shortens the dimension read from it.
     */
    private const int MaxPostedLength = 2_048;

    public function __construct(
        private Container $container,
        private UrlGenerator $urls,
    ) {}

    /**
     * The model was found by its key, so it has one and attempt() never throws
     * RecordingFailed here. The tag is kept because attempt() declares it.
     *
     * @throws RecordingFailed
     */
    public function __invoke(Request $request, string $type, string $key): Response
    {
        $viewable = $this->resolve($request, $type, $key);

        if (! $viewable instanceof Viewable) {
            return $this->respond($viewable);
        }

        $this->views($request)->forViewable($viewable)->attempt();

        return $this->respond(Response::HTTP_NO_CONTENT);
    }

    /**
     * The options were signed along with the model, so they are as the page
     * printed them. An option the URL leaves out is not set at all, so the
     * config still decides it. The referrer and the landing page come from
     * the body, never from the signed query, so the signature stays the same
     * for every visitor.
     */
    private function views(Request $request): Views
    {
        return RecordingOptions::fromQuery($request->query)
            ->applyTo($this->container->make(Views::class))
            ->arrivedFrom(Arrival::fromUrls($this->posted($request, 'referrer'), $this->posted($request, 'landing')));
    }

    /**
     * Read through `all()`, because asking the input bag for a value a visitor
     * posted as an array throws.
     */
    private function posted(Request $request, string $key): ?string
    {
        $value = $request->request->all()[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        return substr($value, 0, self::MaxPostedLength);
    }
}
