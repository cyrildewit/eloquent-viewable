#!/bin/sh
#
# Fixes the code with Rector and Pint, then runs PHPStan, the type coverage and
# the tests side by side. Runs in the composer container through `make ready`.
#
# Rector and Pint rewrite files, so they run first and one after the other. The
# three checks only read the code. Each one writes to its own log, which is
# printed once all three are done, so their output does not interleave.

set -u

composer rector || exit $?
composer lint || exit $?

logs=$(mktemp -d)
trap 'rm -rf "$logs"' EXIT
trap 'kill 0; exit 130' INT TERM

# The checks write to a file, so they turn their colours off unless told.
if [ -t 1 ]; then
    ansi=--ansi
    colors=--colors=always
else
    ansi=--no-ansi
    colors=--colors=never
fi

check() {
    script=$1
    shift
    composer "$script" -- "$@" > "$logs/$script.log" 2>&1
    echo $? > "$logs/$script.status"
}

check test:types --no-progress "$ansi" &
check test:type-coverage "$colors" &
check test "$colors" &
wait

failed=''

for script in test:types test:type-coverage test; do
    printf '\n> composer %s\n' "$script"
    cat "$logs/$script.log"

    if [ "$(cat "$logs/$script.status")" != 0 ]; then
        failed="$failed $script"
    fi
done

if [ -n "$failed" ]; then
    printf '\nFailed:%s\n' "$failed"
    exit 1
fi
