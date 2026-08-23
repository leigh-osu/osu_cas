# OSU Endophyte

Custom Drupal module for the Oregon State University Endophyte Service Lab
(`endophyte.emt.oregonstate.edu`), which tests grass and seed samples for
endophyte alkaloids — ergovaline, lolitrem B, ergot and pellet assays.

## What it provides

- **Sample workflow** — a `state_machine` workflow (`osu_endophyte.workflows.yml`)
  with a guard (`src/Guard/SampleGuard.php`) that decides which transitions a
  given role may apply, and a transition subscriber that reacts once one lands.
- **Test certificates** — `src/Controller/TestCertificate.php` renders the
  signed PDF a client receives, via `pdf_using_mpdf`. The certificate is styled
  by `assets/test_certificate.css`, handed to mPDF directly as `pdf_css_file`,
  so it does not depend on the active theme.
- **Sample tracker and dashboard** — controllers and templates for the lab's
  day-to-day queue views.
- **Lab statistics block** — `src/Plugin/Block/LabStatsBlock.php`, styled by
  `assets/labstatsblock.css` attached through the block's own library.
- **Validation constraints** — sample status and sample test constraints that
  keep a sample's paragraphs consistent with its workflow state.

## Requirements

Declared in `osu_endophyte.info.yml`: `pdf_using_mpdf`, `state_machine`,
`computed_field` and `auto_entitylabel`.

## Installation

Installed through Composer as part of the `osu_cas` platform, into the site
directory rather than the shared module tree:

```
docroot/sites/endophyte.emt.oregonstate.edu/modules/custom/osu_endophyte
```

See the platform's `composer.json` `installer-paths` for the entry that places
it there.
