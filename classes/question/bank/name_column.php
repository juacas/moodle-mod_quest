<?php

namespace mod_quest\question\bank;

/** Question name column for the Quest bank picker. */
class name_column extends \core_question\local\bank\column_base {
    /** {@inheritDoc} */
    public function get_name(): string {
        return 'questquestionname';
    }

    /** {@inheritDoc} */
    public function get_title(): string {
        return get_string('questionname', 'question');
    }

    /** {@inheritDoc} */
    public function is_sortable() {
        return 'q.name';
    }

    /** {@inheritDoc} */
    public function get_required_fields(): array {
        return ['q.name', 'q.qtype'];
    }

    /** {@inheritDoc} */
    public function get_extra_classes(): array {
        return [];
    }

    /** {@inheritDoc} */
    protected function display_content($question, $rowclasses): void {
        echo \html_writer::tag('label', format_string($question->name), [
            'for' => 'checkq' . $question->id,
            'class' => 'mb-0 me-2 cursor-pointer',
        ]);
    }
}
