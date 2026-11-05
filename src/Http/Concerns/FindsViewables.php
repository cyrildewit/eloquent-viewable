<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http\Concerns;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

trait FindsViewables
{
    /**
     * The model a signed URL names, or the status to answer with when the
     * URL was changed or the model is gone.
     */
    protected function resolve(Request $request, string $type, string $key): Viewable|int
    {
        if (! $this->urls->hasValidSignature($request, absolute: false)) {
            return Response::HTTP_FORBIDDEN;
        }

        return $this->find($type, $key) ?? Response::HTTP_NOT_FOUND;
    }

    /**
     * `no-store` keeps a proxy or the browser from caching the answer and
     * replaying it, so every beacon and heartbeat reaches the app.
     */
    protected function respond(int $status): Response
    {
        return new Response(status: $status, headers: ['Cache-Control' => 'no-store']);
    }

    /**
     * Call this only after the signature is checked. `$type` comes from the
     * URL and picks the class to query, so an unchecked type would let a
     * visitor look up any model class.
     */
    protected function find(string $type, string $key): ?Viewable
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class)) {
            return null;
        }

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        $model = $class::query()->whereKey($key)->first();

        if (! $model instanceof Viewable) {
            return null;
        }

        return $model;
    }
}
