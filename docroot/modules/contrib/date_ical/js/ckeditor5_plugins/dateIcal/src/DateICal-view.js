import { View, LabeledFieldView, LabeledInputView, InputView, Template, createLabeledInputText, ButtonView, submitHandler} from 'ckeditor5/src/ui';
import { IconCheck, IconCancel } from '@ckeditor/ckeditor5-icons';

/**
 * A class rendering the information required from user input.
 *
 * @extends module:ui/view~View
 *
 * @internal
 */
export default class DateICalIconView extends View {

  /**
   * @inheritdoc
   */
  constructor(editor) {
    const locale = editor.locale;
    super(locale);
    const config = editor.config.get('date_ical');
    this.dtstartInputView = this._createInputDate(editor.t('Date Start'), 'datetime-local');
    this.dtendInputView = this._createInputDate(editor.t('Date end'), 'datetime-local');
    this.summaryInputView = this._createInput(editor.t('Summary / title'), 'text');
    this.descriptionInputView = this._createInput(editor.t('Description'), config.description ? 'text' : 'hidden');
    this.locationInputView = this._createInput(editor.t('Location'), config.description ? 'text' : 'hidden');
    this.categoriesInputView = this._createInput(editor.t('Categories'), config.categories ? 'text' : 'hidden');
    this.organizerInputView = this._createInput(editor.t('Organizer'), config.organizer ? 'email' : 'hidden');
    this.urlInputView = this._createInput(editor.t('Url'), config.url ? 'url' : 'hidden');
    let collection = [
      this.dtstartInputView,
      this.dtendInputView,
      this.summaryInputView,
    ];
    for (let key in config) {
      if(undefined != this[key + 'InputView']){
        collection.push(this[key + 'InputView']);
      }
    }
    // Create the save and cancel buttons.
    this.saveButtonView = this._createButton(
      editor.t('Save'), IconCheck, 'ck-button-save'
    );
    this.saveButtonView.type = 'submit';
    collection.push(this.saveButtonView);
    this.cancelButtonView = this._createButton(
      editor.t('Cancel'), IconCancel, 'ck-button-cancel'
    );
    // Delegate ButtonView#execute to FormView#cancel.
    this.cancelButtonView.delegate('execute').to(this, 'cancel');
    collection.push(this.cancelButtonView);
    this.childViews = this.createCollection(collection);
    this.setTemplate({
      tag: 'form',
      attributes: {
        class: ['ck', 'ck-responsive-form', 'ck-date-ical'],
        tabindex: '-1'
      },
      children: this.childViews
    });
  }

  /**
   * @inheritdoc
   */
  render() {
    super.render();
    // Submit the form when the user clicked the save button or
    // pressed enter the input.
    submitHandler({
      view: this
    });
  }

  /**
   * @inheritdoc
   */
  focus() {
    this.childViews.first.focus();
  }


  /**
   * Creates an input with a label.
   *
   * @return {module:ui/view~View}
   *   Labeled field view instance.
   */
  _createInputDate(label, type = 'text') {
    const labeledInput = this._createInput(label, type);
    labeledInput.extendTemplate({
      attributes: {
        class: 'ck-labeled-field-view_focused',
      }
    });
    return labeledInput;
  }

  // Create a generic input field.
  _createInput(label, type = 'text') {
    const labeledInput = new LabeledFieldView(this.locale, createLabeledInputText);
    if (type != 'hidden') {
      labeledInput.label = Drupal.t(label);
    }
    if (type != 'text') {
      labeledInput.fieldView.inputMode = type;
      let tmp = labeledInput.fieldView.template;
      tmp.attributes.type = type;
      labeledInput.fieldView.setTemplate(new Template(tmp));
    }
    return labeledInput;
  }

  // Create a generic button.
  _createButton(label, icon, className) {
    const button = new ButtonView();

    button.set({
      label: label,
      icon: icon,
      tooltip: true,
      class: className,
    });

    return button;
  }

}
