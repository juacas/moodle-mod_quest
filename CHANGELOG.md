# Changelog

Notable changes to QUESTOURnament (`mod_quest`). The version advertised by the
current source is defined in `version.php`; a changelog entry is not evidence
that its ZIP has already been published.

## v2.1.0 — 2026-11-02

### Added

- Optional `local_reschedule` adapter for editing Quest activities and their
  challenges in the course timeline without bypassing Quest consistency rules.
- Activity-scoped Rescheduler link for **Schedule challenges**, with automatic
  fallback to Quest's internal scheduler.
- Progressive `datetime-local` enhancement for challenge start and end fields.
- Assessment and collaboration activity-chooser purposes, branded icon support
  and a link to the QUESTOURnament documentation website.

### Changed

- Challenge and answer forms moved from legacy root/`locallib.php` classes to
  `mod_quest\form` classes loaded by Moodle's PSR-4 autoloader.
- Internal functions, parameters and variables now consistently use
  “challenge” where older code used the ambiguous “submission” terminology.
- Event data and descriptions were normalised; course-module changes rely on
  Moodle's core event instead of the redundant custom `quest_updated` event.
- PHPUnit coverage was adapted to PHPUnit 11 without deprecated annotations.
- The activity settings menu no longer adds a redundant link to the course-wide
  Quest index page.

### Fixed

- Challenge date editing now presents one accessible modern control while
  retaining and synchronising Moodle's underlying date selector.
- `quest_pluginfile()` now returns consistently on every code path.
- Missing challenge language-string references introduced by the terminology
  cleanup were corrected.
- Compatibility and code-style issues affecting Moodle 4.5 and Moodle 5.x were
  addressed.

## v2.0.0 — 2026-10-16 (release notes prepared)

### Added

- Multi-question challenge composer with ordered question-bank slots, inline
  preview, drag-to-reorder controls and independent maximum marks.
- Mixed automatic and rubric-based assessment for challenges containing
  questions that require manual grading.
- Interactive challenge scheduler, inline date editing and date validation.
- Automated PHPUnit and Behat coverage for challenge and answer workflows.
- Standalone English and Spanish website with installation, usage and
  educational-evidence guides.

### Changed

- Challenge and answer views, status badges, scoring chart and tournament
  rankings have been modernised.
- Moodle backup and restore include composed question references and content.
- A new answer attempt is created on resubmission while earlier attempts stay
  in the history.
- Minimum Moodle version in the current `version.php` is 4.5.

### Fixed

- Question-bank preview and embedded-file handling, grading-state reporting,
  and several security and compatibility issues addressed during the rewrite.

## v1.5.1b — previous published line

- Moodle 4.5 compatibility update. See the project's
  [published release history](https://github.com/juacas/moodle-mod_quest/releases)
  for the original release notes and older versions.
