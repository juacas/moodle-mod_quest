// This file is part of Questournament activity for Moodle http://moodle.org/
/**
 * Contain the logic for the add random question modal.
 *
 * @module     mod_quest/add_question_modal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

export default class AddQuestionModal extends Modal {
    configure(modalConfig) {
        // Add question modals are always large.
        modalConfig.large = true;

        // Always show on creation.
        modalConfig.show = true;
        modalConfig.removeOnClose = true;

        // Apply question modal configuration.
        this.setContextId(modalConfig.contextId);

        // Store the quest module id for when we need to POST to the quest.
        // This is because the URL cmid param will change during filter operations as we will be in another bank context.
        this.questCmId = modalConfig.questCmId;
        this.bankCmId = modalConfig.bankCmId;
        this.courseId = modalConfig.courseId;

        // Store the original title of the modal, so we can revert back to it once we have switched to another bank.
        this.originalTitle = modalConfig.title;

        // Apply standard configuration.
        super.configure(modalConfig);
    }

    constructor(root) {
        super(root);
        this.contextId = null;
    }

    /**
     * Save the Moodle context id that the question bank is being rendered in.
     *
     * @method setContextId
     * @param {Number} id
     */
    setContextId(id) {
        this.contextId = id;
    }

    /**
     * Retrieve the saved Moodle context id.
     *
     * @method getContextId
     * @return {Number}
     */
    getContextId() {
        return this.contextId;
    }
}
