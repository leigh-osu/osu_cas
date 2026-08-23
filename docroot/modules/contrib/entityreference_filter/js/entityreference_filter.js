((Drupal) => {
  /**
   * Reads a filter field value: an array for multi-value selects, else string.
   *
   * @param {HTMLElement} element
   *   The form field element.
   *
   * @return {string|string[]}
   *   The current value(s).
   */
  const fieldValue = (element) => {
    if (element.multiple) {
      return Array.from(element.selectedOptions, (option) => option.value);
    }
    return element.value;
  };

  /**
   * Finds an exposed filter field by its name (single or multi-value variant).
   *
   * @param {HTMLElement} form
   *   The exposed form element.
   * @param {string} name
   *   The exposed filter identifier.
   *
   * @return {HTMLElement|null}
   *   The matching field, or null.
   */
  const findField = (form, name) =>
    form.querySelector(`[name="${name}"]`) ||
    form.querySelector(`[name="${name}[]"]`);

  Drupal.behaviors.entityReferenceFilter = {
    attach(context, settings) {
      const config = settings.entityreference_filter;
      if (!config) {
        return;
      }

      Object.entries(config).forEach(([formId, filterSetting]) => {
        const form = context.querySelector(`#${formId}`);
        if (!form) {
          return;
        }

        const { dependent_filters_data: dependentFiltersData } = filterSetting;
        let { ajax_path: ajaxPath } = filterSetting.view;
        if (Array.isArray(ajaxPath)) {
          [ajaxPath] = ajaxPath;
        }

        const controllingFilters = {};
        const dependentFilters = {};

        // Collect the controlling and dependent filter elements so we can
        // react to changes and post their current values.
        Object.entries(dependentFiltersData).forEach(
          ([dependentFilterName, depControllingFilters]) => {
            const dependentField = findField(form, dependentFilterName);
            if (dependentField) {
              dependentField.setAttribute('autocomplete', 'off');
              dependentFilters[dependentFilterName] = dependentField;
            }

            depControllingFilters.forEach((controllingFilterName) => {
              const controllingField = findField(form, controllingFilterName);
              if (controllingField) {
                controllingField.setAttribute('autocomplete', 'off');
                controllingFilters[controllingFilterName] = controllingField;
              }
            });
          },
        );

        Object.entries(controllingFilters).forEach(([filterName, element]) => {
          once('entityreference_filter', element).forEach((field) => {
            field.addEventListener('change', (event) => {
              const submitValues = {};

              // Post the raw current values of every controlling and
              // dependent filter.
              Object.entries(controllingFilters).forEach(([name, el]) => {
                submitValues[name] = fieldValue(el);
              });
              Object.entries(dependentFilters).forEach(([name, el]) => {
                submitValues[name] = fieldValue(el);
              });

              // The server walks the dependency graph itself; it only needs
              // the view metadata, the current values and which filter changed.
              Object.assign(submitValues, filterSetting, {
                form_id: formId,
                changed_filter: filterName,
              });

              const ajax = new Drupal.Ajax(false, false, {
                url: ajaxPath,
                submit: submitValues,
              });
              ajax.eventResponse(ajax, event);
            });
          });
        });
      });
    },
  };

  /**
   * Rebuilds a dependent filter's options from structured server data.
   *
   * The server sends plain-text option values and labels; this handler builds
   * the selector and DOM elements on the client side.
   */
  Drupal.AjaxCommands.prototype.entityReferenceFilterRebuild = (
    ajax,
    response,
  ) => {
    const {
      filter_name: filterName,
      form_html_id: formHtmlId,
      options,
      hide_empty_filter: hideEmptyFilter,
      has_values: hasValues,
      selected,
    } = response;

    const form = document.getElementById(formHtmlId);
    const select = form && findField(form, filterName);
    if (!select) {
      return;
    }

    // Show or hide the filter depending on options and `hide empty filter`.
    if (hideEmptyFilter) {
      const parent = select.parentNode;
      const grandparent = parent.parentNode;
      const isHidden = grandparent.classList.contains('hidden');
      if (!hasValues && !isHidden) {
        const wrapper = document.createElement('div');
        wrapper.className = 'hidden';
        parent.before(wrapper);
        wrapper.appendChild(parent);
      }
      if (hasValues && isHidden) {
        // Move the field's parent out of the '.hidden' wrapper and drop it.
        grandparent.before(parent);
        grandparent.remove();
      }
    }

    // The server already computed the effective selection for this level and
    // cascaded the rest, so apply it explicitly instead of letting the browser
    // pick the first option.
    const selectedValues = Array.isArray(selected) ? selected : [selected];

    // Rebuild the options; `options` is already an ordered array, so 'All'
    // stays first.
    select.innerHTML = '';
    options.forEach(({ value, label }) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      option.selected = selectedValues.indexOf(value) !== -1;
      select.appendChild(option);
    });

    // Notify select-widget libraries (Chosen, Liszt). No 'change' event: the
    // server owns the cascade, so re-triggering here would be redundant and
    // could re-open the recursion the server-side walk eliminates.
    ['liszt:updated', 'chosen:updated'].forEach((type) => {
      select.dispatchEvent(new CustomEvent(type, { bubbles: true }));
    });
  };
})(Drupal);
