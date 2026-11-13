# Fonts

Served at `/site-assets/font/<file>` and named in the Site Style's `@font-face` rules.

| File | Font | Coverage | Licence |
| --- | --- | --- | --- |
| `instrument-sans-latin-v1.woff2` | Instrument Sans (variable, 400–700) | Latin | SIL OFL 1.1, `LICENSE-instrument-sans.txt` |
| `instrument-sans-latin-ext-v1.woff2` | Instrument Sans (variable, 400–700) | Latin Extended | SIL OFL 1.1, `LICENSE-instrument-sans.txt` |
| `noto-sans-arabic-v1.woff2` | Noto Sans Arabic (variable, 100–900) | Arabic script, including Kurdish (Sorani) letters | SIL OFL 1.1, `LICENSE-noto-sans-arabic.txt` |

The files are the unmodified Fontsource 5.3.0 subsets (`@fontsource-variable/instrument-sans`, `@fontsource-variable/noto-sans-arabic`), the same as flocms-admin ships.

Files are cached as immutable. To update a font, add it under a new name (`-v2`), update `AssetController::FONTS` and `Style::PACKAGE_FONTS`, and never replace the bytes of an existing name.
