# Changelog

Notable changes to QUESTOURnament (`mod_quest`). The version advertised by the
current source is defined in `version.php`; a changelog entry is not evidence
that its ZIP has already been published.

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
