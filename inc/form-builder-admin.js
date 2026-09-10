/**
 * Formular-Builder fuer den CPT kuh_form.
 *
 * Rendert die Felder-Repeater-UI und serialisiert die gesamte
 * Konfiguration in das versteckte JSON-Feld beim Speichern.
 */
(function () {
  'use strict';

  const dataEl = document.getElementById('kuh-form-builder-data');
  const fieldsEl = document.getElementById('kuh-form-fields');
  const jsonEl = document.getElementById('kuh-form-config-json');
  if (!dataEl || !fieldsEl || !jsonEl) return;

  const { config, i18n } = JSON.parse(dataEl.textContent);
  const fullWidthTypes = ['textarea', 'checkbox'];

  let fields = Array.isArray(config.fields) ? config.fields.slice() : [];

  function el(tag, attrs, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs || {})) {
      if (value === null || value === undefined || value === false) continue;
      if (key === 'className') node.className = value;
      else if (key.startsWith('on')) node.addEventListener(key.slice(2).toLowerCase(), value);
      else node.setAttribute(key, value === true ? '' : String(value));
    }
    for (const child of children.flat()) {
      if (child === null || child === undefined) continue;
      node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    }
    return node;
  }

  function input(type, value, onChange) {
    const node = el('input', { type, class: 'widefat' });
    node.value = value ?? '';
    node.addEventListener('input', () => onChange(node.value));
    return node;
  }

  function select(options, value, onChange) {
    const node = el('select', { class: 'widefat' });
    for (const opt of options) {
      const option = el('option', { value: opt.value }, opt.label);
      if (String(opt.value) === String(value)) option.selected = true;
      node.appendChild(option);
    }
    node.addEventListener('change', () => onChange(node.value));
    return node;
  }

  function labeled(text, control) {
    return el('label', null, el('span', null, text), control);
  }

  function newField(type) {
    return {
      id: `field_${Date.now()}_${Math.floor(Math.random() * 1000)}`,
      name: 'feld',
      label: i18n.field,
      type,
      required: false,
      placeholder: '',
      options: type === 'select' ? ['Option 1', 'Option 2'] : [],
      cols: fullWidthTypes.includes(type) ? 4 : 2,
    };
  }

  function render() {
    fieldsEl.innerHTML = '';

    fields.forEach((field, index) => {
      const head = el(
        'div',
        { className: 'kuh-field-head' },
        el('strong', null, `${i18n.field} ${index + 1}`),
        el(
          'span',
          null,
          el('button', {
            type: 'button',
            class: 'button button-small',
            disabled: index === 0,
            title: '\u2191',
            onclick: () => { [fields[index - 1], fields[index]] = [fields[index], fields[index - 1]]; render(); },
          }, '\u2191'),
          ' ',
          el('button', {
            type: 'button',
            class: 'button button-small',
            disabled: index === fields.length - 1,
            title: '\u2193',
            onclick: () => { [fields[index + 1], fields[index]] = [fields[index], fields[index + 1]]; render(); },
          }, '\u2193'),
          ' ',
          el('button', {
            type: 'button',
            class: 'button button-small button-link-delete',
            onclick: () => { fields.splice(index, 1); render(); },
          }, i18n.remove)
        )
      );

      const grid = el(
        'div',
        { className: 'kuh-field-grid' },
        labeled(i18n.name, input('text', field.name, (v) => { field.name = v; })),
        labeled(i18n.label, input('text', field.label, (v) => { field.label = v; })),
        labeled(i18n.type, select(i18n.typeOptions, field.type, (v) => {
          field.type = v;
          if (v === 'select' && (!Array.isArray(field.options) || field.options.length === 0)) {
            field.options = ['Option 1', 'Option 2'];
          }
          render();
        })),
        labeled(i18n.width, select(i18n.widthOptions, field.cols ?? 2, (v) => { field.cols = parseInt(v, 10); }))
      );

      if (field.type !== 'checkbox') {
        grid.appendChild(labeled(i18n.placeholder, input('text', field.placeholder, (v) => { field.placeholder = v; })));
      }

      if (field.type === 'select') {
        const textarea = el('textarea', { class: 'widefat', rows: '4' });
        textarea.value = Array.isArray(field.options) ? field.options.join('\n') : '';
        textarea.addEventListener('input', () => {
          field.options = textarea.value.split('\n').map((v) => v.trim()).filter(Boolean);
        });
        const wrap = labeled(i18n.options, textarea);
        wrap.classList.add('kuh-wide');
        grid.appendChild(wrap);
      }

      const requiredLabel = el(
        'label',
        null,
        (() => {
          const box = el('input', { type: 'checkbox' });
          box.checked = Boolean(field.required);
          box.addEventListener('change', () => { field.required = box.checked; });
          return box;
        })(),
        ` ${i18n.required}`
      );
      grid.appendChild(requiredLabel);

      fieldsEl.appendChild(el('div', { className: 'kuh-field' }, head, grid));
    });

    const addRow = el('p', null);
    for (const opt of i18n.typeOptions) {
      addRow.appendChild(el('button', {
        type: 'button',
        class: 'button',
        onclick: () => { fields.push(newField(opt.value)); render(); },
      }, `+ ${opt.label}`));
      addRow.appendChild(document.createTextNode(' '));
    }
    fieldsEl.appendChild(addRow);
  }

  function serialize() {
    const val = (id) => {
      const node = document.getElementById(id);
      return node ? node.value : '';
    };
    const checked = (id) => {
      const node = document.getElementById(id);
      return node ? node.checked : false;
    };

    jsonEl.value = JSON.stringify({
      subject: val('kuh-form-subject'),
      recipientEmail: val('kuh-form-recipient'),
      confirmationMail: checked('kuh-form-confirmation'),
      confirmationSubject: val('kuh-form-confirmation-subject'),
      confirmationMessage: val('kuh-form-confirmation-message'),
      confirmationIncludeData: checked('kuh-form-confirmation-include-data'),
      formTitle: val('kuh-form-title'),
      formIntro: val('kuh-form-intro'),
      submitLabel: val('kuh-form-submit'),
      successMessage: val('kuh-form-success'),
      privacyNote: val('kuh-form-privacy'),
      fields,
    });
  }

  const form = document.getElementById('post');
  if (form) {
    form.addEventListener('submit', serialize);
  }
  // Fallback: direkt vor dem Absenden via Publish/Update-Buttons.
  document.querySelectorAll('#publish, #save-post').forEach((btn) => {
    btn.addEventListener('click', serialize);
  });

  render();
  serialize();
})();
