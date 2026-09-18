// This file is part of Questournament activity for Moodle http://moodle.org/
/**
 * Contain the logic for the question bank modal in Moodle 4.5.
 *
 * @module     mod_quest/modal_quest_question_bank_45
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from './add_question_modal';
import * as Fragment from 'core/fragment';
import * as FormChangeChecker from 'core_form/changechecker';
import * as ModalEvents from 'core/modal_events';

const SELECTORS = {
    ANCHOR: 'a[href]',
    ADD_QUESTIONS_FORM: 'form#questionsubmit',
    ADD_TO_QUEST_CONTAINER: 'td.action_column',
    USE_QUESTION: 'button[data-action="quest-use-question"]',
};

export default class ModalQuestQuestionBank45 extends Modal {
    static TYPE = 'mod_quest-quest-question-bank-45';

    /**
     * Create the question bank modal.
     *
     * @param {Number} contextId Current module context id.
     * @param {Number} questCmId Current quest course module id.
     * @param {Number} courseId Current course id.
     */
    static init(contextId, questCmId, courseId) {
        const selector = '[data-action="questionbank"]';
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest(selector);
            if (!trigger) {
                return;
            }
            e.preventDefault();

            ModalQuestQuestionBank45.create({
                contextId,
                questCmId,
                bankCmId: questCmId,
                title: trigger.dataset.header,
                templateContext: {
                    hidden: true,
                },
                large: true,
                courseId,
            });
        });
    }

    /**
     * Override the parent show function.
     *
     * @return {void}
     */
    show() {
        this.getRoot().addClass('quest-questionbank-modal');
        this.reloadBodyContent(window.location.search);
        return super.show(this);
    }

    /**
     * Replaces the current body contents with a new version of the question
     * bank.
     *
     * @param {string} querystring URL encoded string.
     */
    reloadBodyContent(querystring) {
        this.hideFooter();
        this.setTitle(this.originalTitle);
        this.setBody(Fragment.loadFragment(
            'mod_quest',
            'quest_question_bank',
            this.getContextId(),
            {
                querystring,
                questcmid: this.questCmId,
                bankcmid: this.bankCmId || this.questCmId,
            }
        ));
    }

    /**
     * Redirect to use the question.
     *
     * @param {Number} questionid The ID of the question to use.
     */
    useQuestion(questionid) {
        const url = new URL(window.location.href);
        url.searchParams.set('id', this.questCmId);
        url.searchParams.set('action', 'processexistingqchallenge');
        url.searchParams.set('questionid', questionid);
        url.searchParams.set('sesskey', M.cfg.sesskey);
        window.location.assign(url.toString());
    }

    /**
     * Set up all of the event handling for the modal.
     */
    registerEventListeners() {
        super.registerEventListeners(this);

        this.getModal().on('submit', SELECTORS.ADD_QUESTIONS_FORM, (e) => {
            e.preventDefault();
            const formElement = e.currentTarget;
            const ids = Array.from(formElement.querySelectorAll("input[type='checkbox']:checked"))
                .filter(input => /^q\d+$/.test(input.name)).map(input => Number(input.name.slice(1)));
            if (ids.length) {
                this.useQuestion(ids[0]);
            }
        });

        this.getModal().on('click', SELECTORS.USE_QUESTION, (e) => {
            e.preventDefault();
            const questionId = e.currentTarget.dataset.questionid;
            if (questionId) {
                this.useQuestion(Number(questionId));
            }
        });

        this.getModal().on('click', SELECTORS.ANCHOR, (e) => {
            const anchorElement = e.currentTarget;

            if (anchorElement.closest(SELECTORS.ADD_TO_QUEST_CONTAINER) ||
                    anchorElement.closest("[data-action='quest-use-question']")) {
                e.preventDefault();
                let qid = anchorElement.dataset.questionid;
                if (!qid) {
                    const href = anchorElement.getAttribute('href');
                    if (href) {
                        const match = href.match(/addquestion=(\d+)/) || href.match(/questionid=(\d+)/);
                        if (match) {
                            qid = match[1];
                        }
                    }
                }
                if (qid) {
                    this.useQuestion(Number(qid));
                }
                return;
            }

            if (anchorElement.getAttribute('target') === '_blank') {
                return;
            }

            e.preventDefault();
        });

        this.getRoot().on(ModalEvents.bodyRendered, () => {
            FormChangeChecker.disableAllChecks();
        });
    }
}

ModalQuestQuestionBank45.registerModalType();
