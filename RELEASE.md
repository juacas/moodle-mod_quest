# QUESTOURnament v2.0.0 release notes

## Requirements

- Moodle 4.5 or later (`$plugin->requires = 2024100700`). Use a PHP version
  supported by the Moodle installation.
- Install the activity in Moodle's `mod/quest` directory. On Moodle 5.x this
  may be under `public/mod/quest`.
- Back up the database, files and existing Quest activity data before upgrading.

## Highlights

- Compose one challenge from several ordered question-bank questions. The
  composer provides an inline question preview, drag-to-reorder interaction,
  per-question maximum marks and question creation or selection.
- Combine automatically graded questions with essay or other manually graded
  questions. Automatic marks contribute proportionally to the result; manual
  questions use the challenge rubric.
- Schedule challenges with an interactive timeline and editable dates.
- Retain previous question attempts when a new submission is authorised.
- Include linked question-bank questions and relevant question data in Moodle
  backup and restore workflows.
- Updated challenge, answer and ranking interfaces, plus English and Spanish
  documentation and automated test coverage.

## Installation and upgrade

Obtain the **matching v2.0.0 package** from [Moodle Marketplace](https://marketplace.moodle.com/plugins/mod_quest)
or [GitHub Releases](https://github.com/juacas/moodle-mod_quest/releases)
once published. Alternatively, deploy the repository under `mod/quest` and
update it with Git. Check `version.php` in the package before upgrading: an
older package may not contain the features described above.

After placing the files, visit **Site administration → Notifications** or run
Moodle's `admin/cli/upgrade.php` to apply database changes. Purge caches and
test a plain challenge, a fully automatic challenge, a mixed challenge and a
backup/restore in a test course before using the release in production.

## License

GNU General Public License, version 3 or later (GPL-3.0-or-later), as declared
in the plugin's PHP file headers. The software is supplied without warranty.

See [CHANGELOG.md](CHANGELOG.md) for the change history and
[website/install.html](website/install.html) for the three installation routes.
