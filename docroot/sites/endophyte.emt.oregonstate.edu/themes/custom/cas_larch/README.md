# CAS Larch

Bootstrap 5 sub-theme for the OSU Endophyte Service Lab
(`endophyte.emt.oregonstate.edu`).

```
cas_larch  →  bootstrap5  →  stable9 (core)
```

## Status

**Transitional.** The lab site is moving onto the `osu_cas` platform, whose
front end is `manzanita` (a Madrone sub-theme). cas_larch is packaged so the
platform move and the re-theme stay separate changes — landing both at once
would give every visual regression two possible causes. Expect this theme and
its `bootstrap5` base to be retired once manzanita takes over, most likely in
favour of a thin manzanita sub-theme carrying the lab's own styling.

Because of that, this theme is in maintenance only. Fix what breaks; don't
invest in it.

## What's here

- `cas_larch.info.yml` — nine regions, including a two-sidebar layout
  (`sidebar_first`, `sidebar_second`) that has no direct manzanita equivalent.
- `templates/` — three overrides: `html`, `page`, `page-title`.
- `scss/` — `style.scss` plus variable overrides in `scss/overrides/` and
  `scss/custom/`. Compiled output is committed at `css/style.css`.
- `config/install/cas_larch.settings.yml` — includes `cas_larch_parent_url`,
  the "parent site" link, pointing at `https://emt.oregonstate.edu/`.
- `style-guide/` — the Bootstrap starter kit's style guide page, linked from
  the theme settings form.

## Note on the SCSS build

`package.json` is an empty object and `package-lock.json` declares no packages,
so there is no working build here despite appearances. `css/style.css` is
compiled and committed. If you need to rebuild it, use `sass` directly:

```
sass scss/style.scss css/style.css
```

## Not theme-dependent

The lab's functional styling lives in the `osu_endophyte` module, not here:
`assets/test_certificate.css` is handed to mPDF as `pdf_css_file`, and
`assets/labstatsblock.css` is attached by the lab statistics block. Both
survive a theme change untouched.

## Installation

Installed through Composer as part of the `osu_cas` platform, into the site
directory rather than the shared theme tree:

```
docroot/sites/endophyte.emt.oregonstate.edu/themes/custom/cas_larch
```
