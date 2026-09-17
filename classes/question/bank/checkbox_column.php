<?php

namespace mod_quest\question\bank;

/** Checkbox column for selecting a question from the official bank view. */
class checkbox_column extends \core_question\local\bank\checkbox_column {
    /** {@inheritDoc} */
    protected function display_content($question, $rowclasses): void {
        if (custom_view::selectable($question)) {
            parent::display_content($question, $rowclasses);
        }
    }
}
