<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http\Controllers;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Http\Concerns\FindsViewables;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\UrlGenerator;

/**
 * Keeps a visitor active while the page is open, and lets them go when it
 * closes. The heartbeat passes the same guards as a view, except the ones
 * that limit repeats, and records nothing.
 *
 * With `presence.expose_count` on, a heartbeat answers with the number of
 * active visitors, whatever the guards decided, so the answer does not tell
 * a visitor whether they were counted.
 */
final readonly class PresenceController
{
    use FindsViewables;

    public function __construct(
        private Container $container,
        private UrlGenerator $urls,
        private Config $config,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     * @throws RecordingFailed
     */
    public function store(Request $request, string $type, string $key): Response|JsonResponse
    {
        $views = $this->views($request, $type, $key);

        if (! $views instanceof Views) {
            return $this->respond($views);
        }

        $views->heartbeat();

        if (! $this->config->presenceExposesCount()) {
            return $this->respond(Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(
            data: ['active' => $views->live()->count()],
            headers: ['Cache-Control' => 'no-store'],
        );
    }

    /** @throws RecordingFailed */
    public function destroy(Request $request, string $type, string $key): Response
    {
        $views = $this->views($request, $type, $key);

        if (! $views instanceof Views) {
            return $this->respond($views);
        }

        $views->leave();

        return $this->respond(Response::HTTP_NO_CONTENT);
    }

    /**
     * The builder for the model the signed URL names, or the status to answer
     * with when the URL was changed or the model is gone.
     */
    private function views(Request $request, string $type, string $key): Views|int
    {
        if (! $this->urls->hasValidSignature($request, absolute: false)) {
            return Response::HTTP_FORBIDDEN;
        }

        $viewable = $this->find($type, $key);

        if (! $viewable instanceof Viewable) {
            return Response::HTTP_NOT_FOUND;
        }

        $views = $this->container->make(Views::class)->forViewable($viewable);

        if ($request->query->has('collection')) {
            $views->collection($request->query->getString('collection'));
        }

        return $views;
    }

    private function respond(int $status): Response
    {
        return new Response(status: $status, headers: ['Cache-Control' => 'no-store']);
    }
}
