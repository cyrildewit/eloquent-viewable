<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Throwable;

class Doctor
{
    public function __construct(
        protected Container $container,
        protected Config $config,
    ) {}

    /**
     * The key `--only` picks a check by: its class name in kebab case, without
     * the `Check` suffix.
     */
    public static function keyOf(Check $check): string
    {
        return Str::kebab(Str::beforeLast(class_basename($check), 'Check'));
    }

    /**
     * @param  list<string>  $only
     * @return list<Check>
     *
     * @throws InvalidConfiguration
     */
    public function checks(array $only = []): array
    {
        $checks = [];

        foreach ($this->config->doctorChecks() as $class) {
            $check = $this->container->make($class);

            if (! $check instanceof Check) {
                throw InvalidConfiguration::mustImplement('doctor.checks', Check::class, $class);
            }

            if ($only !== [] && ! in_array(self::keyOf($check), $only, true)) {
                continue;
            }

            $checks[] = $check;
        }

        return $checks;
    }

    /**
     * A check that throws, because a database or Redis cannot be reached, is
     * reported as a failure, so the checks after it still run.
     *
     * @return list<Finding>
     */
    public function examine(Check $check): array
    {
        try {
            $findings = [];

            foreach ($check->run() as $finding) {
                $findings[] = $finding;
            }
        } catch (Throwable $exception) {
            return [Finding::failure("The check could not run: {$exception->getMessage()}")];
        }

        return $findings;
    }
}
