<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\ParamProviders;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use SplFileInfo;
use Traversable;

/**
 * Finds the benchmark classes and their variants the way phpbench does, from
 * the same attributes, so a report keyed on them lines up with phpbench's
 * dump. The results repository matches runs across releases on the class,
 * the subject and the parameter set name, which makes that naming a contract;
 * `tests/Unit/Benchmarks/VariantsTest.php` pins it.
 *
 * phpbench names a parameter set after the keys the providers yield, joined
 * with a comma and no space, and builds the cartesian product with the first
 * provider as the innermost loop: `hot article,all time`, `cold article,all
 * time`, `all articles,all time`, `hot article,past year` and so on.
 *
 * Nothing here touches the database. The providers are called on a fresh
 * instance of each class, which is why they must not need `setUp`.
 */
final readonly class Variants
{
    /**
     * The autoload rule for the directory, from composer.json.
     */
    private const string Namespace = 'CyrildeWit\\EloquentViewable\\Benchmarks\\';

    /**
     * phpbench's `runner.file_pattern` and `runner.subject_pattern`.
     */
    private const string FilePattern = '/Bench\.php$/';

    private const string SubjectPattern = '/^bench/';

    /**
     * @param  list<Benchmark>  $benchmarks
     */
    private function __construct(private array $benchmarks) {}

    /**
     * Discovers the benchmarks under the given directory, `benchmarks/` by
     * default, in path order.
     */
    public static function discover(?string $directory = null): self
    {
        $directory ??= Application::projectPath('benchmarks');
        $benchmarks = [];

        foreach (self::files($directory) as $file) {
            $class = self::classFor($directory, $file);
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $benchmarks[] = self::describe($reflection);
        }

        return new self($benchmarks);
    }

    /**
     * @return list<Benchmark>
     */
    public function all(): array
    {
        return $this->benchmarks;
    }

    /**
     * The benchmarks whose class carries the given group.
     *
     * @return list<Benchmark>
     */
    public function inGroup(string $group): array
    {
        return array_values(array_filter(
            $this->benchmarks,
            static fn (Benchmark $benchmark): bool => $benchmark->inGroup($group),
        ));
    }

    /**
     * @return list<string>
     */
    private static function files(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match(self::FilePattern, $file->getFilename()) === 1) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return class-string
     */
    private static function classFor(string $directory, string $file): string
    {
        $relative = substr($file, strlen(rtrim($directory, '/')) + 1, -strlen('.php'));
        $class = self::Namespace.str_replace('/', '\\', $relative);

        if (! class_exists($class)) {
            throw new RuntimeException("The file {$file} does not define the class {$class} its path promises.");
        }

        return $class;
    }

    /**
     * @param  ReflectionClass<object>  $class
     */
    private static function describe(ReflectionClass $class): Benchmark
    {
        $instance = $class->newInstance();
        $variants = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || preg_match(self::SubjectPattern, $method->getName()) !== 1) {
                continue;
            }

            $providers = $method->getAttributes(ParamProviders::class);
            $providers = $providers === [] ? [] : $providers[0]->newInstance()->providers;

            array_push($variants, ...self::variants($instance, $class->getName(), $method->getName(), $providers));
        }

        $groups = $class->getAttributes(Groups::class);
        $beforeMethods = $class->getAttributes(BeforeMethods::class);

        return new Benchmark(
            class: $class->getName(),
            groups: $groups === [] ? [] : array_values($groups[0]->newInstance()->groups),
            beforeMethods: $beforeMethods === [] ? [] : array_values($beforeMethods[0]->newInstance()->methods),
            variants: $variants,
        );
    }

    /**
     * The cartesian product of the providers' parameter sets, first provider
     * innermost, as phpbench's `CartesianParameterIterator` builds it.
     *
     * @param  class-string  $class
     * @param  list<string>  $providers
     * @return list<Variant>
     */
    private static function variants(object $instance, string $class, string $subject, array $providers): array
    {
        /** @var list<list<array{string, array<string, mixed>}>> $combinations */
        $combinations = [[]];

        foreach ($providers as $provider) {
            $next = [];

            foreach (self::parameterSets($instance, $provider) as $key => $params) {
                foreach ($combinations as $combination) {
                    $next[] = [...$combination, [(string) $key, $params]];
                }
            }

            $combinations = $next;
        }

        return array_map(static fn (array $combination): Variant => new Variant(
            class: $class,
            subject: $subject,
            keys: array_column($combination, 0),
            params: array_merge([], ...array_column($combination, 1)),
        ), $combinations);
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    private static function parameterSets(object $instance, string $provider): array
    {
        if (! method_exists($instance, $provider)) {
            throw new RuntimeException(sprintf('Unknown parameter provider %s::%s().', $instance::class, $provider));
        }

        /** @var array<array-key, array<string, mixed>>|Traversable<array-key, array<string, mixed>> $sets */
        $sets = $instance->{$provider}();

        return $sets instanceof Traversable ? iterator_to_array($sets) : $sets;
    }
}
