# Enicore Rivena: styles and scripts

The frontend of Enicore Rivena, a PHP framework for admin-focused websites (Composer package `enicore/rivena`).

- `dist/rivena.css`, `dist/rivena_colors.css`: the styles and the color classes
- `dist/rivena.min.js`: the scripts; `dist/rivena_spa.min.js`: page loading without reloads
- `dist/alpine.min.js`: Alpine.js 3
- `dist/fonts`: Ubuntu Sans
- `scss/`, `js/`: the sources

```scss
@use "../node_modules/@enicore/rivena/scss/rivena" with ($icons: ('logo': '<svg ...>'));

.box {
    width: 50%;
    @include rivena.size(xs) { width: 100%; }   // xs, sm, md, lg, xl, sm-down, sm-up...
}

:root { --line-height: 1.6; }                  // the settings are CSS variables
```

`$icons` become `icon-<name>` classes; `$font-path` is the URL of the fonts (`/fonts`), `false` uses the system font.
Hide by size with `hide-xs`, `hide-sm-down`...

## License

Enicore Rivena is licensed under the MIT License. See LICENSE for more information.
