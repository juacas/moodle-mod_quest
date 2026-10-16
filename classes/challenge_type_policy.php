<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_quest;

/**
 * Resolves site-wide and per-activity challenge creation restrictions.
 *
 * The activity value reuses the historical allowqbankquestions field:
 * 0 meant simple challenges and 1 meant both types. Value 2 adds quiz-only.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class challenge_type_policy {
    /** Only basic, open challenges may be created. */
    public const SIMPLE_ONLY = 0;

    /** Both basic and question-bank challenges may be created. */
    public const BOTH = 1;

    /** Only challenges composed from question-bank questions may be created. */
    public const QUIZ_ONLY = 2;

    /**
     * Return the site restriction, defaulting to the previous behaviour.
     *
     * @return int
     */
    public static function global_mode(): int {
        $configured = get_config('quest', 'allowedchallengetypes');
        if ($configured === false) {
            return self::BOTH;
        }
        return self::normalise_mode($configured);
    }

    /**
     * Return the mode saved on an activity for its students.
     *
     * @param \stdClass $quest Activity record.
     * @return int
     */
    public static function student_mode(\stdClass $quest): int {
        return self::normalise_mode($quest->allowqbankquestions ?? self::BOTH);
    }

    /**
     * Whether an actor may create a basic challenge.
     *
     * @param \stdClass $quest Activity record.
     * @param bool $ismanager Whether the actor manages the activity.
     * @return bool
     */
    public static function allows_simple(\stdClass $quest, bool $ismanager): bool {
        return self::global_mode() !== self::QUIZ_ONLY &&
            ($ismanager || self::student_mode($quest) !== self::QUIZ_ONLY);
    }

    /**
     * Whether an actor may create a question-bank challenge.
     *
     * @param \stdClass $quest Activity record.
     * @param bool $ismanager Whether the actor manages the activity.
     * @return bool
     */
    public static function allows_question_bank(\stdClass $quest, bool $ismanager): bool {
        return self::global_mode() !== self::SIMPLE_ONLY &&
            ($ismanager || self::student_mode($quest) !== self::SIMPLE_ONLY);
    }

    /**
     * Options for a site setting or, when requested, a student activity setting.
     *
     * @param bool $restrictforstudents Exclude options forbidden at site level.
     * @return array<int, string>
     */
    public static function mode_options(bool $restrictforstudents = false): array {
        $options = [
            self::SIMPLE_ONLY => get_string('challengetype_simple', 'quest'),
            self::QUIZ_ONLY => get_string('challengetype_quiz', 'quest'),
            self::BOTH => get_string('challengetype_both', 'quest'),
        ];
        if (!$restrictforstudents || self::global_mode() === self::BOTH) {
            return $options;
        }
        $mode = self::global_mode();
        return [$mode => $options[$mode]];
    }

    /**
     * Map missing or invalid configuration to the backward-compatible default.
     *
     * @param mixed $mode Saved value.
     * @return int
     */
    private static function normalise_mode($mode): int {
        $mode = (int)$mode;
        return in_array($mode, [self::SIMPLE_ONLY, self::BOTH, self::QUIZ_ONLY], true)
            ? $mode : self::BOTH;
    }
}
