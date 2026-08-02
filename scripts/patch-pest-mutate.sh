#!/usr/bin/env bash
#
# TEMPORARY patch for pest-plugin-mutate v5.0.0 so `pest --mutate` works with
# phpunit/php-code-coverage 14 (bundled with PHPUnit 13).
#
#   Bug:  https://github.com/pestphp/pest/issues/1790
#   Fix:  https://github.com/pestphp/pest-plugin-mutate/pull/38
#
# Delete this script once a pest-plugin-mutate > 5.0.0 release ships the fix
# (then a plain `composer update` restores the untouched, working vendor file).
#
# Usage:
#   scripts/patch-pest-mutate.sh          # apply the patch
#   scripts/patch-pest-mutate.sh revert   # undo the patch
#
# The patch targets a vendored file, so re-run this after every composer install.
set -euo pipefail

cd "$(dirname "$0")/.."

TARGET="vendor/pestphp/pest-plugin-mutate/src/Tester/MutationTestRunner.php"
MODE="${1:-apply}"

if [ ! -f "$TARGET" ]; then
    echo "error: $TARGET not found — run 'composer install' first." >&2
    exit 1
fi

read -r -d '' PATCH <<'PATCH_EOF' || true
diff --git a/src/Tester/MutationTestRunner.php b/src/Tester/MutationTestRunner.php
index e2d1aea..01898b7 100644
--- a/src/Tester/MutationTestRunner.php
+++ b/src/Tester/MutationTestRunner.php
@@ -20,6 +20,7 @@
 use Pest\Support\Coverage;
 use Psr\SimpleCache\CacheInterface;
 use SebastianBergmann\CodeCoverage\CodeCoverage;
+use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;

 class MutationTestRunner implements MutationTestRunnerContract
 {
@@ -109,11 +110,27 @@ public function run(): int

         Facade::instance()->emitter()->startMutationGeneration($mutationSuite);

-        /** @var CodeCoverage $codeCoverage */
-        $codeCoverage = require $reportPath;
+        /** @var CodeCoverage|array{basePath: string, codeCoverage: ProcessedCodeCoverageData} $loadedCoverage */
+        $loadedCoverage = require $reportPath;

         unlink($reportPath);
-        $coveredLines = array_map(fn (array $lines): array => array_filter($lines, fn (?array $tests): bool => $tests !== [] && $tests !== null), $codeCoverage->getData()->lineCoverage());
+
+        if ($loadedCoverage instanceof CodeCoverage) {
+            $coverageData = $loadedCoverage->getData();
+        } else {
+            // since phpunit/php-code-coverage 14, `--coverage-php` writes an array instead of a
+            // serialized CodeCoverage, and its file keys are relative to `basePath`
+            $coverageData = $loadedCoverage['codeCoverage'];
+            $basePath = $loadedCoverage['basePath'];
+
+            if ($basePath !== '') {
+                foreach ($coverageData->coveredFiles() as $relativePath) {
+                    $coverageData->renameFile($relativePath, $basePath.DIRECTORY_SEPARATOR.$relativePath);
+                }
+            }
+        }
+
+        $coveredLines = array_map(fn (array $lines): array => array_filter($lines, fn (?array $tests): bool => $tests !== [] && $tests !== null), $coverageData->lineCoverage());
         $coveredLines = array_filter($coveredLines, fn (array $lines): bool => $lines !== []);

         $files = FileFinder::files($this->getConfiguration()->paths, $this->getConfiguration()->pathsToIgnore);
PATCH_EOF

if [ "$MODE" = "revert" ]; then
    if ! grep -q 'ProcessedCodeCoverageData' "$TARGET"; then
        echo "Patch not applied — nothing to revert."
        exit 0
    fi
    printf '%s\n' "$PATCH" | git apply -R -p1 --directory=vendor/pestphp/pest-plugin-mutate
    echo "Reverted pest-plugin-mutate patch."
    exit 0
fi

if grep -q 'ProcessedCodeCoverageData' "$TARGET"; then
    echo "Patch already applied — nothing to do."
    exit 0
fi

printf '%s\n' "$PATCH" | git apply -p1 --directory=vendor/pestphp/pest-plugin-mutate
echo "Applied pest-plugin-mutate patch (PR #38) to $TARGET."
