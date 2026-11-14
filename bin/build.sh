#!/bin/bash
# Builds assets/dist/ from assets/ (rivena.css, rivena_colors.css, rivena.min.js, rivena_spa.min.js) and downloads
# the latest Alpine.js 3.x, the latest Open Sans font (Fontsource's 5.x: its Latin and Latin Extended subsets,
# variable, regular and italic) and GeoIp's database (ip-location-db's user-country, PDDL, updated daily).
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


FONTS="https://cdn.jsdelivr.net/npm/@fontsource-variable/open-sans@5"
mkdir -p assets/dist/fonts
curl -fsSL "$FONTS/LICENSE" -o assets/dist/fonts/LICENSE.txt
for style in normal italic; do
    for subset in latin latin-ext; do
        name="open-sans$([ $style = italic ] && echo -italic)-$subset.woff2"
        curl -fsSL "$FONTS/files/open-sans-$subset-wght-$style.woff2" -o "assets/dist/fonts/$name"
    done
done
