import { Plugin } from 'ckeditor5/src/core';
import DateICalEditing from './DateICalEditing';
import DateICalUI from './DateICalUI';


export default class DateIcal extends Plugin {
  static get requires() {
    return [DateICalEditing, DateICalUI];
  }
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return "dateIcal";
  }

}
