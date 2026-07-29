import {Command} from "ckeditor5/src/core";

export default class InsertICalCommand extends Command {
  execute(iCalendar) {
    const {editor} = this;
    const {model} = editor;
    const config = editor.config.get('date_ical');
    let host = window.location.protocol + "//" + window.location.host;
    let url = new URL(host + config.download);
    const linkElement = document.createElement('a');
    linkElement.className = 'icon-link icon-link-hover';
    linkElement.innerHTML = `📅 ${iCalendar.summary}`;
    for (const key in iCalendar) {
      const value = iCalendar[key];
      if (value != '') {
        url.searchParams.set(key, value);
      }
    }
    let href = url.toString();
    linkElement.setAttribute('href', href.replace(host, ''));
    let iCal = `<div class='iCalendar'>${linkElement.outerHTML}</div>`;
    model.change(writer => {
      const content = writer.createElement('icalendar');
      const docFrag = writer.createDocumentFragment();
      const viewFragment = editor.data.processor.toView(iCal);
      const modelFragment = editor.data.toModel(viewFragment);
      writer.append(content, docFrag);
      writer.append(modelFragment, content);
      model.insertContent(docFrag);
    });
  }

  refresh() {
    const {model} = this.editor;
    const {selection} = model.document;
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'iCalendar',
    );
    this.isEnabled = allowedIn !== null;
  }

}

