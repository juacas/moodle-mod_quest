// This file is part of Questournament activity for Moodle - http://moodle.org/
/**
 * Quiz-like editing interactions for a Quest question collection.
 *
 * @module     mod_quest/question_composer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import SortableList from 'core/sortable_list';

const SELECTORS = {
    form: '.quest-question-composer',
    list: '[data-region="question-composer-slots"]',
    slot: '[data-region="question-composer-slot"]',
    number: '[data-region="question-number"]',
    remove: '[data-action="remove-question"]',
    empty: '[data-region="question-composer-empty"]',
};

/**
 * Renumber the displayed rows after a drag or removal.
 *
 * @param {HTMLElement} list
 */
const updateQuestionNumbers = list => {
    list.querySelectorAll(SELECTORS.slot).forEach((slot, index) => {
        slot.querySelector(SELECTORS.number).textContent = index + 1;
    });
};

/**
 * Initialise ordering, deletion and submission checks.
 */
export const init = () => {
    const list = document.querySelector(SELECTORS.list);
    const form = document.querySelector(SELECTORS.form);
    if (!list || !form) {
        return;
    }

    const sortable = new SortableList(list);
    sortable.getElementName = element => Promise.resolve(
        element[0].querySelector('[data-region="question-name"]')?.textContent || ''
    );

    document.addEventListener(SortableList.EVENTS.elementDrop, event => {
        const row = event.detail.element?.[0];
        if (event.detail.positionChanged && row && list.contains(row)) {
            updateQuestionNumbers(list);
        }
    });

    list.addEventListener('click', event => {
        const removeButton = event.target.closest(SELECTORS.remove);
        if (!removeButton) {
            return;
        }
        const slot = removeButton.closest(SELECTORS.slot);
        const questionId = slot.dataset.questionid;
        slot.remove();

        // Keep the picker URL in sync so adding another question will not bring a removed row back.
        const url = new URL(window.location.href);
        const selected = Array.from(url.searchParams.entries())
            .filter(([name]) => /^questionids(?:\[\d*\])?$/.test(name));
        const remaining = selected.map(([, id]) => id).filter(id => id !== questionId);
        selected.forEach(([name]) => url.searchParams.delete(name));
        remaining.forEach(id => url.searchParams.append('questionids[]', id));
        window.history.replaceState({}, '', url);

        const toolbar = document.querySelector('.quest-composer-toolbar');
        const questionBankButton = toolbar?.querySelector('[data-action="questionbank"]');
        if (questionBankButton) {
            questionBankButton.dataset.questionids = remaining.join(',');
        }
        toolbar?.querySelectorAll('input[name="returnurl"]').forEach(input => {
            const returnUrl = new URL(input.value, window.location.origin);
            Array.from(returnUrl.searchParams.keys())
                .filter(name => /^questionids(?:\[\d*\])?$/.test(name))
                .forEach(name => returnUrl.searchParams.delete(name));
            remaining.forEach(id => returnUrl.searchParams.append('questionids[]', id));
            input.value = returnUrl.pathname + returnUrl.search;
        });

        const empty = form.querySelector(SELECTORS.empty);
        empty?.classList.toggle('d-none', list.querySelectorAll(SELECTORS.slot).length > 0);
        updateQuestionNumbers(list);
    });

    form.addEventListener('submit', event => {
        const slots = list.querySelectorAll(SELECTORS.slot);
        if (slots.length === 0) {
            event.preventDefault();
            form.querySelector(SELECTORS.empty)?.classList.remove('d-none');
            return;
        }
        slots.forEach(slot => {
            const orderfield = slot.querySelector('input[name="question_order[]"]');
            if (orderfield) {
                orderfield.value = slot.dataset.questionid;
            }
        });
    });
};
