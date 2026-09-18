<?php

namespace mod_quest\question\bank;

/** Action column for the Quest question picker. */
class action_column extends \core_question\local\bank\column_base {
    /** {@inheritDoc} */
    public function get_name(): string {
        return 'actions';
    }

    /** {@inheritDoc} */
    public function get_title(): string {
        return get_string('actions');
    }

    /** {@inheritDoc} */
    public function get_required_fields(): array {
        return ['q.id', 'q.qtype'];
    }

    /** {@inheritDoc} */
    public function get_extra_classes(): array {
        return ['text-nowrap', 'text-right', 'text-end'];
    }

    /** {@inheritDoc} */
    protected function display_content($question, $rowclasses): void {
        global $OUTPUT;

        if (custom_view::selectable($question)) {
            echo \html_writer::tag('button', get_string('useexistingquestion', 'quest'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm me-1',
                'data-action' => 'quest-use-question',
                'data-questionid' => $question->id,
            ]);
        }

        if (question_has_capability_on($question, 'edit') && \question_bank::is_qtype_installed($question->qtype)) {
            $title = get_string('editbankquestion', 'quest');
            echo \html_writer::link($this->qbank->question_edit_url((int)$question->id),
                $OUTPUT->pix_icon('t/edit', $title, 'moodle', ['class' => 'icon']), [
                    'class' => 'btn btn-icon btn-sm btn-outline-secondary me-1',
                    'title' => $title,
                    'aria-label' => $title,
                    'target' => '_blank',
                ]);
        }

        $usable = method_exists('\question_bank', 'is_qtype_usable')
            ? \question_bank::is_qtype_usable($question->qtype)
            : (\question_bank::is_qtype_installed($question->qtype) && $question->qtype !== 'missingtype');

        if (question_has_capability_on($question, 'use') && $usable) {
            $title = get_string('preview');
            echo \html_writer::link(
                new \moodle_url('/question/bank/previewquestion/preview.php', ['id' => $question->id]),
                $OUTPUT->pix_icon('t/preview', $title, 'moodle', ['class' => 'icon']), [
                    'class' => 'btn btn-icon btn-sm btn-outline-secondary',
                    'title' => $title,
                    'aria-label' => $title,
                    'target' => '_blank',
                    'rel' => 'noopener',
                ]
            );
        }
    }
}
