<?php
// This file is part of Questournament for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the
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

namespace mod_quest\question\bank;

/**
 * Selection-only question bank view for Quest challenges.
 *
 * This deliberately extends Moodle's question bank view so the search,
 * category and pagination controls behave like the standard question bank.
 *
 * @package mod_quest
 * @copyright 2026 onwards EDUVALab, University of Valladolid
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_view extends \core_question\local\bank\view {
    /** @var int Default number of questions shown per page. */
    public const DEFAULT_PAGE_SIZE = 20;

    /** @var string Component used by Moodle's fragment filter callbacks. */
    public $component = 'mod_quest';

    /** @var string Callback used for filtered question data fragments. */
    public $callback = 'quest_question_data';

    /**
     * Authorise both the destination Quest activity and the selected source bank.
     */
    public function __construct($contexts, $pageurl, $course, $cm = null, $params = [], $extraparams = []) {
        $destination = \context_module::instance((int)($extraparams['questcmid'] ?? 0));
        require_capability('mod/quest:manage', $destination);
        \mod_quest\question\bank_provider::require_bank($cm->id);
        $this->pagesize = self::DEFAULT_PAGE_SIZE;
        parent::__construct($contexts, $pageurl, $course, $cm, $params, $extraparams);
    }

    /**
     * Initialise the column manager.
     *
     * @return void
     */
    protected function init_column_manager(): void {
        $this->columnmanager = new \core_question\local\bank\column_manager_base();
    }

    /**
     * Disable question-level actions in this picker.
     *
     * @return void
     */
    protected function init_question_actions(): void {
        $this->questionactions = [];
    }

    /**
     * Disable bulk actions in this picker.
     *
     * @return void
     */
    protected function init_bulk_actions(): void {
        $this->bulkactions = [];
    }

    /**
     * Return the columns displayed by the picker.
     *
     * @return array Question-bank columns.
     */
    protected function get_question_bank_plugins(): array {
        $columns = [];
        foreach ([checkbox_column::class, 'qbank_viewquestiontype\\question_type_column',
                name_column::class, action_column::class] as $class) {
            $name = substr($class, strrpos($class, '\\') + 1);
            $id = $class . \core_question\local\bank\column_base::ID_SEPARATOR . $name;
            $columns[$id] = $class::from_column_name($this, $name);
        }
        return $columns;
    }

    /**
     * Return the heading column class.
     *
     * @return string Heading column class.
     */
    protected function heading_column(): string {
        return name_column::class;
    }

    /**
     * Return the default sorting configuration.
     *
     * @return array Sort configuration.
     */
    protected function default_sort(): array {
        return ['mod_quest__question\\bank\\name_column' => SORT_ASC];
    }

    /**
     * Return plugin controls for the question bank.
     *
     * @param \context $context Question-bank context.
     * @param int $categoryid Category ID.
     * @return string Controls HTML.
     */
    protected function get_plugin_controls(\context $context, int $categoryid): string {
        return '';
    }

    /**
     * Render the question bank header.
     *
     * @return void
     */
    protected function display_question_bank_header(): void {
        if (!empty($this->extraparams['requirebankswitch']) && \mod_quest\question\bank_provider::has_bank_helper()) {
            $bankname = format_string($this->cm->name ?? '');
            echo \html_writer::start_div('d-flex align-items-center mb-2');
            echo \html_writer::tag('h5', $bankname, ['class' => 'm-0']);
            echo \html_writer::tag('button', get_string('switchbank', 'core_question'), [
                'data-action' => 'switch-question-bank',
                'type' => 'button',
                'class' => 'btn btn-secondary ms-auto',
                'id' => 'switch-question-bank',
            ]);
            echo \html_writer::end_div();
        }
    }

    /**
     * Do not render the standard new-question form.
     *
     * @param object $category Question category.
     * @param bool $canadd Whether the user can add a question.
     * @return void
     */
    protected function create_new_question_form($category, $canadd): void {
    }

    /**
     * Apply mandatory source/version constraints before user filters.
     *
     * @return void
     */
    protected function build_query(): void {
        global $USER;

        [$fields, $joins] = $this->get_component_requirements($this->requiredcolumns);
        $sorts = [];
        foreach ($this->sort as $name => $order) {
            [$column, $subsort] = $this->parse_subsort($name);
            $sorts[] = $this->requiredcolumns[$column]->sort_expression($order == SORT_DESC, $subsort);
        }

        $this->sqlparams = [
            'questready' => 'ready',
            'questnewready' => 'ready',
        ];
        $where = [
            'q.parent = 0',
            'qv.status = :questready',
            'NOT EXISTS (SELECT 1 FROM {question_versions} newer
              WHERE newer.questionbankentryid = qv.questionbankentryid
                AND newer.version > qv.version AND newer.status = :questnewready)',
        ];

        if (!has_capability('moodle/question:useall', $this->contexts->lowest())) {
            $where[] = 'q.createdby = :questauthor';
            $this->sqlparams['questauthor'] = $USER->id;
        }

        $conditions = [];
        foreach ($this->searchconditions as $condition) {
            if ($condition->where()) {
                $conditions[] = '(' . $condition->where() . ')';
                $this->sqlparams = array_merge($this->sqlparams, $condition->params());
            }
        }
        if ($conditions) {
            $jointype = (int)($this->pagevars['jointype'] ?? \core\output\datafilter::JOINTYPE_ALL);
            $separator = $jointype === \core\output\datafilter::JOINTYPE_ALL ? ' AND ' : ' OR ';
            $not = $jointype === \core\output\datafilter::JOINTYPE_NONE ? 'NOT ' : '';
            $where[] = $not . '(' . implode($separator, $conditions) . ')';
        }

        $sql = ' FROM {question} q ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where);
        $this->countsql = 'SELECT COUNT(1)' . $sql;
        $this->loadsql = 'SELECT ' . implode(', ', $fields) . $sql . ' ORDER BY ' . implode(', ', $sorts);
    }

    /**
     * Whether a question can be linked to a Quest challenge.
     */
    public static function selectable($question): bool {
        $usable = method_exists('\question_bank', 'is_qtype_usable')
            ? \question_bank::is_qtype_usable($question->qtype)
            : (\question_bank::is_qtype_installed($question->qtype) && $question->qtype !== 'missingtype');

        return $question->parent == 0 && $question->status === 'ready' &&
            $usable && question_has_capability_on($question, 'use');
    }

    /**
     * Return to the Quest page after editing a bank question.
     */
    public function question_edit_url(int $questionid): \moodle_url {
        $returnurl = new \moodle_url('/mod/quest/challenges.php', [
            'id' => (int)$this->extraparams['questcmid'],
            'action' => 'addqchallenge',
        ]);
        return new \moodle_url('/question/bank/editquestion/question.php', [
            'id' => $questionid,
            'cmid' => $this->cm->id,
            'returnurl' => $returnurl->out_as_local_url(false),
        ]);
    }

    /**
     * Render the bottom controls for selecting a question.
     *
     * @param \context $catcontext Category context.
     * @return void
     */
    protected function display_bottom_controls(\context $catcontext): void {
        echo \html_writer::tag('button', get_string('useexistingquestion', 'quest'), [
            'type' => 'submit',
            'class' => 'btn btn-primary mt-2',
        ]);
    }

    /**
     * Render the standard filters and question list inside the modal.
     */
    public function display(): void {
        echo \html_writer::start_div('questionbankwindow');
        $this->wanted_filters();
        $this->display_question_list();
        echo \html_writer::end_div();
    }
}
