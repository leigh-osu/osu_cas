/**
 * @file registers the Address Suggestion toolbar button and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView, ContextualBalloon, clickOutsideHandler } from 'ckeditor5/src/ui';
import FormView from './DateICal-view';
import icon from '../../../../icons/date-ical.svg';

export default class DateICalUI extends Plugin {
  init() {
    const editor = this.editor;
    this._balloon = this.editor.plugins.get(ContextualBalloon);
    this.formView = this._createFormView();

    // This will register the iCalendar toolbar button.
    editor.ui.componentFactory.add('dateIcal', (locale) => {
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('iCalendar'),
        icon: icon,
        tooltip: true,
      });

      // Bind the state of the button to the command.
      const command = editor.commands.get('InsertICalCommand');
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      // Execute the command when the button is clicked (executed).
      this.listenTo(buttonView, 'execute', () => {
        this._showUI();
      });

      return buttonView;
    });

  }

  _createFormView() {
    const editor = this.editor;
    const formView = new FormView(editor);

    // On submit send the user data to the writer, then hide the form view.
    this.listenTo(formView, 'submit', () => {
      let ical = {
        'dtstart': formView.dtstartInputView.fieldView.element.value,
        'dtend': formView.dtendInputView.fieldView.element.value,
        'summary': formView.summaryInputView.fieldView.element.value,
        'description': formView.descriptionInputView.fieldView.element.value,
        'location': formView.locationInputView.fieldView.element.value,
        'categories': formView.categoriesInputView.fieldView.element.value,
        'organizer': formView.organizerInputView.fieldView.element.value,
        'url': formView.urlInputView.fieldView.element.value,
      };
      editor.execute('InsertICalCommand', ical);
      this._hideUI();
    });

    // Hide the form view after clicking the "Cancel" button.
    this.listenTo(formView, 'cancel', () => {
      this._hideUI();
    });

    // Hide the form view when clicking outside the balloon.
    clickOutsideHandler({
      emitter: formView,
      activator: () => this._balloon.visibleView === formView,
      contextElements: [this._balloon.view.element],
      callback: () => this._hideUI()
    });

    return formView;
  }

  _hideUI() {
    this.formView.dtstartInputView.fieldView.value = '';
    this.formView.dtendInputView.fieldView.value = '';
    this.formView.summaryInputView.fieldView.value = '';
    this.formView.descriptionInputView.fieldView.value = '';
    this.formView.locationInputView.fieldView.value = '';
    this.formView.categoriesInputView.fieldView.value = '';
    this.formView.organizerInputView.fieldView.value = '';
    this.formView.urlInputView.fieldView.value = '';
    this.formView.element.reset();
    this._balloon.remove(this.formView);

    // Focus the editing view after closing the form view.
    this.editor.editing.view.focus();
  }

  _showUI() {
    this._balloon.add({
      view: this.formView,
      position: this._getBalloonPositionData(),
    });
    this.formView.focus();
  }

  _getBalloonPositionData() {
    const view = this.editor.editing.view;
    const viewDocument = view.document;
    let target = null;

    // Set a target position by converting view selection range to DOM.
    target = () => view.domConverter.viewRangeToDom(
      viewDocument.selection.getFirstRange()
    );

    return {
      target
    };
  }

}
