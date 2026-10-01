#!/bin/sh
#
# Draws the dependencies between the layers in deptrac.yaml to build/deptrac.png.
# Runs in the composer container through `make deptrac-graph`.
#
# Deptrac writes a Graphviz file and `dot` renders it. Deptrac only sets the
# edge labels and colours the edges that break the ruleset red, so the -G, -N
# and -E defaults below control the rest of the look.

set -eu

dot_file=build/deptrac.dot
png_file=build/deptrac.png

mkdir -p build
rm -f "$dot_file"

# Deptrac exits non-zero when an edge breaks the ruleset. That is what the red
# edges show, so only stop when it wrote no file at all. phpdocumentor/graphviz
# still uses ${var} interpolation, which PHP 8.5 reports as deprecated.
php -d error_reporting='E_ALL & ~E_DEPRECATED' vendor/bin/deptrac analyse \
    --no-progress --formatter=graphviz-dot --output="$dot_file" || test -f "$dot_file"

font='Inter'

dot -Tpng -o "$png_file" \
    -Gdpi=200 -Gpad=0.3 -Gnodesep=0.5 -Granksep=0.8 \
    -Gfontname="$font" -Gfontsize=11 -Gfontcolor='#6b6b76' \
    -Gstyle='rounded,dashed' -Gcolor='#b4b4bd' \
    -Nfontname="$font" -Nfontsize=12 -Nfontcolor='#1f1f26' \
    -Nshape=box -Nstyle='rounded,filled' -Nfillcolor='#f4f4f6' -Ncolor='#4a4a55' -Nmargin='0.25,0.1' \
    -Efontname="$font" -Efontsize=9 -Efontcolor='#6b6b76' -Ecolor='#8a8a94' -Earrowsize=0.7 \
    "$dot_file"

echo "Graph written to $png_file"
