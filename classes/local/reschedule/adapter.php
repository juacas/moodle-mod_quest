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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quest\local\reschedule;

/**
 * Native Activity Rescheduler adapter for Quest tournaments and challenges.
 *
 * This optional integration is autoloaded only when local_reschedule asks for
 * it. Keeping the class under this dedicated namespace lets mod_quest operate
 * normally when local_reschedule is not installed.
 *
 * @package    mod_quest
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adapter extends \local_reschedule\adapter\base_adapter implements \local_reschedule\adapter\adapter_interface {
    /** @var float Default hours for a challenge without an answer-time estimate. */
    public const DEFAULT_CHALLENGE_EFFORT = 1.0;

    /**
     * Load challenge records through mod_quest's tournament API.
     *
     * @param array $rule Normalised subtype mapping rule.
     * @param int[] $parentids Parent tournament IDs.
     * @return \stdClass[] Challenge records keyed by challenge ID.
     */
    #[\Override]
    public function get_subitems(array $rule, array $parentids): array {
        return self::get_challenges($parentids);
    }

    /**
     * Provide challenge-specific effort and answer-bound metadata.
     *
     * @param \stdClass $record Loaded challenge record.
     * @param array $rule Normalised subtype mapping rule.
     * @return array Adapter-specific item attributes.
     */
    #[\Override]
    public function get_subitem_attributes(\stdClass $record, array $rule): array {
        $effort = self::estimate_effort(
            isset($record->predictedduration) ? (int)$record->predictedduration : null,
            isset($record->perceiveddifficulty) ? (int)$record->perceiveddifficulty : null
        );
        return [
            'editable' => (bool)$rule['editable'],
            'absolutelimits' => $record->absolutelimits ?? [],
            'effort' => $effort ?? self::DEFAULT_CHALLENGE_EFFORT,
            'effortsource' => $effort === null ? 'default' : 'adapter',
        ];
    }

    /**
     * Estimate challenge effort from the teacher's answer-time estimate.
     * Quest stores the duration in minutes and difficulty as 0, 1 or 2.
     * An absent duration has no usable estimate; callers use the challenge
     * default instead of the Quest activity's module-type weight.
     *
     * @param int|null $minutes Estimated answer time in minutes.
     * @param int|null $difficulty 0 easy, 1 attainable, 2 hard.
     * @return float|null Estimated work hours, or null when no duration is available.
     */
    public static function estimate_effort(?int $minutes, ?int $difficulty): ?float {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }
        $multipliers = [0 => 0.7, 1 => 1.0, 2 => 1.3];
        return round(($minutes / 60) * ($multipliers[$difficulty] ?? 1.0), 4);
    }

    /**
     * Load challenges through mod_quest's tournament service.
     *
     * @param int[] $questids Quest tournament ids.
     * @return \stdClass[] Challenge records keyed by submission id.
     */
    public static function get_challenges(array $questids): array {
        global $CFG;

        if (!class_exists(\mod_quest\service\tournament_manager::class)) {
            return [];
        }
        require_once($CFG->dirroot . '/mod/quest/lib.php');

        $challenges = [];
        foreach (array_unique(array_filter(array_map('intval', $questids))) as $questid) {
            foreach (\mod_quest\service\tournament_manager::get_challenges($questid) as $challenge) {
                $item = clone $challenge;
                $item->parentid = (int)$challenge->questid;
                $item->datestart = (int)$challenge->datestart;
                $item->dateend = (int)$challenge->dateend;
                $challenges[(int)$challenge->id] = $item;
            }
        }

        foreach ($challenges as $challenge) {
            $answerdates = self::get_challenge_answer_dates($challenge);
            $challenge->firstanswerdate = $answerdates['first'];
            $challenge->lastanswerdate = $answerdates['last'];
            $challenge->answerstartvalidationmessage = $challenge->firstanswerdate > 0 ?
                get_string('scheduleerroranswerbeforestart', 'quest', (object)[
                    'title' => format_string($challenge->title),
                    'date' => userdate(
                        $challenge->firstanswerdate,
                        get_string('strftimedatetimeshort', 'langconfig')
                    ),
                ]) : '';
            $challenge->answerendvalidationmessage = $challenge->lastanswerdate > 0 ?
                get_string('scheduleerroranswerafterend', 'quest', (object)[
                    'title' => format_string($challenge->title),
                    'date' => userdate(
                        $challenge->lastanswerdate,
                        get_string('strftimedatetimeshort', 'langconfig')
                    ),
                ]) : '';
            $challenge->absolutelimits = self::get_answer_absolute_limits($challenge);
        }

        uasort($challenges, static fn(\stdClass $left, \stdClass $right): int =>
            [$left->datestart, $left->id] <=> [$right->datestart, $right->id]);
        return $challenges;
    }

    /**
     * Get response time bounds using the public mod_quest helper.
     *
     * @param \stdClass $challenge Quest challenge.
     * @return array First and last answer timestamps.
     */
    private static function get_challenge_answer_dates(\stdClass $challenge): array {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quest/lib.php');
        $answers = quest_get_challenge_answers($challenge);
        $dates = array_map(static fn(\stdClass $answer): int => (int)$answer->date, $answers);
        if (!$dates) {
            return ['first' => 0, 'last' => 0];
        }

        return ['first' => min($dates), 'last' => max($dates)];
    }

    /**
     * Build the two possible answer submission bounds for one challenge.
     *
     * @param \stdClass $challenge Challenge with answer dates already loaded.
     * @return array Absolute bounds on the challenge interval.
     */
    private static function get_answer_absolute_limits(\stdClass $challenge): array {
        $limits = [];
        $firstanswerdate = (int)($challenge->firstanswerdate ?? 0);
        if ($firstanswerdate > 0) {
            $limits[] = [
                'type' => 'start_max',
                'date' => $firstanswerdate,
                'reason' => (string)($challenge->answerstartvalidationmessage ?? ''),
            ];
        }
        $lastanswerdate = (int)($challenge->lastanswerdate ?? 0);
        if ($lastanswerdate > 0) {
            $limits[] = [
                'type' => 'end_min',
                'date' => $lastanswerdate,
                'reason' => (string)($challenge->answerendvalidationmessage ?? ''),
            ];
        }
        return $limits;
    }

    #[\Override]
    public function supports(string $modname, array $item): bool {
        return ($modname === 'quest' || $item['table'] === 'quest' || $item['table'] === 'quest_submissions');
    }

    /**
     * Prevent moving a challenge start beyond answers that have already been submitted.
     *
     * @param array $item Reschedule item metadata.
     * @param int $newstart Proposed start timestamp.
     * @param int $newend Proposed end timestamp.
     * @return array Error messages when the proposed range violates response boundaries.
     */
    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = parent::validate($item, $newstart, $newend);
        if (($item['table'] ?? '') !== 'quest_submissions') {
            return $errors;
        }

        $questid = (int)($item['parentrecordid'] ?? 0);
        $challenges = $questid > 0 ? self::get_challenges([$questid]) : [];
        $challenge = $challenges[(int)$item['recordid']] ?? null;
        $firstanswerdate = (int)($challenge->firstanswerdate ?? 0);
        if ($firstanswerdate > 0 && $newstart > $firstanswerdate) {
            $errors[] = get_string('scheduleerroranswerbeforestart', 'quest', (object)[
                'title' => format_string((string)($item['title'] ?? '')),
                'date' => userdate($firstanswerdate, get_string('strftimedatetimeshort', 'langconfig')),
            ]);
        }
        $lastanswerdate = (int)($challenge->lastanswerdate ?? 0);
        if ($lastanswerdate > 0 && $newend < $lastanswerdate) {
            $errors[] = get_string('scheduleerroranswerafterend', 'quest', (object)[
                'title' => format_string((string)($item['title'] ?? '')),
                'date' => userdate($lastanswerdate, get_string('strftimedatetimeshort', 'langconfig')),
            ]);
        }

        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB, $CFG;

        $table = $item['table'];
        $recordid = (int)$item['recordid'];

        if ($table === 'quest_submissions') {
            $this->save_challenge_schedules([[
                'item' => $item,
                'newstart' => $newstart,
                'newend' => $newend,
            ]]);
            return;
        }

        // Updating main quest tournament.
        $quest = $DB->get_record('quest', ['id' => $recordid]);
        if (!$quest) {
            return;
        }

        $quest->datestart = $newstart;
        $quest->dateend = $newend;
        $quest->timemodified = time();
        $DB->update_record('quest', $quest);

        $cm = $this->get_cm('quest', $recordid);
        if ($cm && file_exists($CFG->dirroot . '/mod/quest/locallib.php')) {
            require_once($CFG->dirroot . '/mod/quest/locallib.php');
            if (function_exists('quest_update_quest_calendar')) {
                quest_update_quest_calendar($quest, $cm);
            }
            if (function_exists('quest_update_grades')) {
                quest_update_grades($quest);
            }
            $this->trigger_cm_updated($cm);
        }
    }

    /**
     * Save adjacent Quest challenge edits together through the module service.
     *
     * @param array $tasks Planned changes with item, newstart and newend keys.
     */
    #[\Override]
    public function save_batch(array $tasks): void {
        $challenges = [];
        $flush = function () use (&$challenges): void {
            if ($challenges) {
                $this->save_challenge_schedules($challenges);
                $challenges = [];
            }
        };

        foreach ($tasks as $task) {
            if (($task['item']['table'] ?? '') === 'quest_submissions') {
                $challenges[] = $task;
            } else {
                $flush();
                $this->save($task['item'], (int)$task['newstart'], (int)$task['newend']);
            }
        }
        $flush();
    }

    /**
     * Save challenge rows in one service call per Quest tournament.
     *
     * @param array $tasks Planned changes with item, newstart and newend keys.
     */
    public function save_challenge_schedules(array $tasks): void {
        global $DB;

        if (!class_exists(\mod_quest\service\tournament_manager::class)) {
            throw new \coding_exception('The mod_quest tournament manager service is unavailable.');
        }

        $byquest = [];
        foreach ($tasks as $task) {
            $item = $task['item'] ?? [];
            $questid = (int)($item['parentrecordid'] ?? 0);
            if ($questid <= 0 && preg_match('/^main_quest_(\d+)$/', (string)($item['parentkey'] ?? ''), $matches)) {
                $questid = (int)$matches[1];
            }
            if ($questid <= 0) {
                throw new \coding_exception('A Quest challenge is missing its parent tournament id.');
            }
            $byquest[$questid][] = [
                'id' => (int)($item['recordid'] ?? 0),
                'datestart' => (int)$task['newstart'],
                'dateend' => (int)$task['newend'],
            ];
        }

        foreach ($byquest as $questid => $schedules) {
            $quest = $DB->get_record('quest', ['id' => $questid], '*', MUST_EXIST);
            $cm = $this->get_cm('quest', $questid);
            if (!$cm) {
                throw new \coding_exception('The course module for Quest tournament ' . $questid . ' was not found.');
            }
            $result = \mod_quest\service\tournament_manager::update_challenge_schedules(
                $quest,
                $cm,
                $schedules,
                false
            );
            if (empty($result['success'])) {
                $message = (string)($result['message'] ?? 'Quest could not update the challenge schedules.');
                throw new \RuntimeException($message);
            }
        }
    }
}
