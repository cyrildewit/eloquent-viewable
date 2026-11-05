<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

/**
 * A guard that refuses a view because the visitor was counted already, such
 * as a cooldown or the throttle. The visitor is still looking, so presence
 * is kept up to date when only guards like this refuse, and a heartbeat
 * never asks them.
 */
interface LimitsRepeats {}
