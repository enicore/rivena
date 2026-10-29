#!/bin/bash
# Builds assets/dist/ from assets/ (rivena.css, rivena_colors.css, rivena.min.js, rivena_spa.min.js) and downloads
# the latest Alpine.js 3.x, the latest Ubuntu Sans font (its Latin and Latin Extended web font subsets, variable,
# regular and italic) and GeoIp's database (ip-location-db's user-country, PDDL, updated daily).
# Needs sass (Dart Sass), minify (tdewolff) and curl. For Rivena's development only; the projects don't build Rivena.
set -eu
cd -- "$(dirname -- "$0")/.."

sass --no-source-map --style=compressed assets/scss/rivena.scss assets/dist/rivena.css
sass --no-source-map --style=compressed assets/scss/rivena_colors.scss assets/dist/rivena_colors.css
minify --type js -o assets/dist/rivena.min.js assets/js/rivena.js
minify --type js -o assets/dist/rivena_spa.min.js assets/js/rivena_spa.js
curl -fsSL "https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js" -o assets/dist/alpine.min.js
curl -fsSL "https://github.com/sapics/ip-location-db/releases/download/latest/user-country.mmdb" \
    -o src/resources/geoip/user-country.mmdb


FONTS="https://raw.githubusercontent.com/canonical/Ubuntu-Sans-fonts/main"
mkdir -p assets/dist/fonts
for style in "" -Italic; do
    for subset in latin latin-extended; do
        name="ubuntu-sans${style,,}-$subset.woff2"
        curl -fsSL "$FONTS/fonts/webfont-subsets/UbuntuSans$style%5Bwdth,wght%5D-$subset.woff2" \
            -o "assets/dist/fonts/$name"
    done
done
