# QUESTOURnament v2.1.0 release notes

Release date: 2 November 2026.

## Requirements

- Moodle 4.5 or later (`$plugin->requires = 2024100700`).
- A PHP version supported by the selected Moodle release.
- Install the activity in `mod/quest`. On Moodle 5.x this may be located below
  Moodle's `public` directory.
- Back up the database, Moodle data directory and existing Quest activities
  before upgrading.

`local_reschedule` is optional. QUESTOURnament remains fully operational when
that plugin is not installed.

## Highlights since v2.0.0

- Native optional integration with Activity Rescheduler. Quest activities and
  their challenges can be edited in the course timeline while Quest's date,
  answer and calendar-event consistency rules remain in force.
- The **Schedule challenges** action opens Rescheduler's activity-scoped view
  when it is available and falls back to Quest's own scheduler otherwise.
- Challenge start and end fields use a modern date-and-time control while
  preserving Moodle's original selector as the submitted data source and
  retaining user-timezone behaviour.
- Quest is presented as an assessment activity with collaboration as a
  secondary purpose in Moodle versions that support activity chooser purposes.
  Its branded icon retains its original colours and the chooser includes a
  documentation link.
- Challenge and answer forms now use Moodle's PSR-4 class autoloader. Legacy
  challenge code was renamed and reorganised to distinguish challenges from
  answer submissions more clearly.
- Event handling now uses the relevant challenge and answer events together
  with Moodle's core course-module update event. The redundant custom
  `quest_updated` event was removed.
- PHPUnit tests were updated for PHPUnit 11 and the supported Moodle versions.

## Upgrade notes

This release does not add a required dependency or a new database table. After
deploying the files, visit **Site administration → Notifications** or run:

```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
```

Sites that install `local_reschedule` gain the integration automatically after
the Moodle cache is rebuilt. Without it, the existing Quest scheduling page is
used.

Before production rollout, verify challenge creation, date editing, answer
grading and backup/restore in a test course.

## Release verification

The release candidate was checked with PHP 8.3 against Moodle 4.5 and Moodle
5.3. The Moodle Plugin CI lint, CodeSniffer, plugin validation and upgrade
savepoint checks pass. The PHPUnit suite passes on both Moodle branches with
75 tests and 340 assertions, using `--fail-on-warning`. The AMD build and ESLint
checks also pass.

## Licence

QUESTOURnament is distributed under the GNU General Public License, version 3
or later (`GPL-3.0-or-later`), without warranty.

See [CHANGELOG.md](CHANGELOG.md) for the detailed change summary and
[README.md](README.md) for usage and technical documentation.
