/**
 * Gutenberg Editor-Script fuer den Kontaktformular-Block.
 */
/* global wp */
(function () {
  const { registerBlockType } = wp.blocks;
  const { useBlockProps, InspectorControls } = wp.blockEditor;
  const { PanelBody, TextControl, TextareaControl, ToggleControl, SelectControl, Button, Notice } = wp.components;
  const { createElement: el, Fragment } = wp.element;

  const FIELD_TYPE_OPTIONS = [
    { label: 'Text', value: 'text' },
    { label: 'E-Mail', value: 'email' },
    { label: 'Nummer', value: 'number' },
    { label: 'Telefon', value: 'tel' },
    { label: 'Textarea', value: 'textarea' },
    { label: 'Select', value: 'select' },
    { label: 'Checkbox', value: 'checkbox' },
  ];

  const WIDTH_OPTIONS = [
    { label: '25 %', value: '1' },
    { label: '50 %', value: '2' },
    { label: '100 %', value: '4' },
  ];

  function createField(type = 'text') {
    const fullWidthTypes = ['textarea', 'checkbox'];
    return {
      id: `field_${Date.now()}_${Math.floor(Math.random() * 1000)}`,
      name: 'feld',
      label: 'Neues Feld',
      type,
      required: false,
      placeholder: '',
      options: type === 'select' ? ['Option 1', 'Option 2'] : [],
      cols: fullWidthTypes.includes(type) ? 4 : 2,
    };
  }

  registerBlockType('kuh/contact-form', {
    edit({ attributes, setAttributes }) {
      const blockProps = useBlockProps({ className: 'kuh-contact-form-editor' });
      const {
        subject,
        recipientEmail,
        fields = [],
        formTitle,
        formIntro,
        submitLabel,
        successMessage,
        privacyNote,
      } = attributes;

      const normalizedFields = Array.isArray(fields) ? fields : [];

      const updateField = (index, patch) => {
        const next = [...normalizedFields];
        next[index] = { ...next[index], ...patch };
        setAttributes({ fields: next });
      };

      const removeField = (index) => {
        const next = normalizedFields.filter((_, i) => i !== index);
        setAttributes({ fields: next });
      };

      const moveField = (index, direction) => {
        const next = [...normalizedFields];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        setAttributes({ fields: next });
      };

      const addField = (type) => {
        setAttributes({ fields: [...normalizedFields, createField(type)] });
      };

      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Versand', initialOpen: true },
            el(TextControl, {
              label: 'Betreff',
              value: subject,
              onChange: (value) => setAttributes({ subject: value }),
              help: 'Wird als Betreff der gesendeten E-Mail verwendet.',
            }),
            el(TextControl, {
              label: 'Empfaenger E-Mail',
              type: 'email',
              value: recipientEmail,
              onChange: (value) => setAttributes({ recipientEmail: value }),
              help: 'Optional. Leer lassen, um die Standard-Empfaengeradresse aus den Theme-Einstellungen zu nutzen.',
            }),
            el(TextControl, {
              label: 'Erfolgsmeldung',
              value: successMessage,
              onChange: (value) => setAttributes({ successMessage: value }),
            })
          ),
          el(
            PanelBody,
            { title: `Felder (${normalizedFields.length})`, initialOpen: true },
            normalizedFields.map((field, index) =>
              el(
                'div',
                {
                  key: field.id || index,
                  style: {
                    border: '1px solid #d1d5db',
                    borderRadius: '8px',
                    padding: '12px',
                    marginBottom: '12px',
                    background: '#fff',
                  },
                },
                el(
                  'div',
                  { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' } },
                  el('strong', null, `Feld ${index + 1}`),
                  el(
                    'div',
                    { style: { display: 'flex', gap: '4px' } },
                    el(Button, {
                      variant: 'secondary',
                      isSmall: true,
                      disabled: index === 0,
                      onClick: () => moveField(index, -1),
                      title: 'Nach oben',
                    }, '↑'),
                    el(Button, {
                      variant: 'secondary',
                      isSmall: true,
                      disabled: index === normalizedFields.length - 1,
                      onClick: () => moveField(index, 1),
                      title: 'Nach unten',
                    }, '↓'),
                    el(Button, {
                      isDestructive: true,
                      isSmall: true,
                      onClick: () => removeField(index),
                      title: 'Feld entfernen',
                    }, '✕')
                  )
                ),
                el(TextControl, {
                  label: 'Feldname (technisch)',
                  value: field.name || '',
                  onChange: (value) => updateField(index, { name: value }),
                  help: 'Wird in der E-Mail als Kennung verwendet.',
                }),
                el(TextControl, {
                  label: 'Label',
                  value: field.label || '',
                  onChange: (value) => updateField(index, { label: value }),
                }),
                el(SelectControl, {
                  label: 'Typ',
                  value: field.type || 'text',
                  options: FIELD_TYPE_OPTIONS,
                  onChange: (value) => {
                    const patch = { type: value };
                    if (value === 'select' && (!Array.isArray(field.options) || field.options.length === 0)) {
                      patch.options = ['Option 1', 'Option 2'];
                    }
                    updateField(index, patch);
                  },
                }),
                field.type !== 'checkbox'
                  ? el(TextControl, {
                      label: 'Platzhalter',
                      value: field.placeholder || '',
                      onChange: (value) => updateField(index, { placeholder: value }),
                    })
                  : null,
                field.type === 'select'
                  ? el(TextareaControl, {
                      label: 'Select-Optionen (eine pro Zeile)',
                      value: Array.isArray(field.options) ? field.options.join('\n') : '',
                      onChange: (value) => {
                        const options = value
                          .split('\n')
                          .map((v) => v.trim())
                          .filter(Boolean);
                        updateField(index, { options });
                      },
                    })
                  : null,
                el(SelectControl, {
                  label: 'Breite (Desktop)',
                  value: String(field.cols ?? 2),
                  options: WIDTH_OPTIONS,
                  onChange: (value) => updateField(index, { cols: parseInt(value, 10) }),
                }),
                el(ToggleControl, {
                  label: 'Pflichtfeld',
                  checked: Boolean(field.required),
                  onChange: (value) => updateField(index, { required: value }),
                })
              )
            ),
            el(
              'div',
              { style: { display: 'flex', gap: '8px', flexWrap: 'wrap' } },
              FIELD_TYPE_OPTIONS.map((opt) =>
                el(
                  Button,
                  {
                    key: opt.value,
                    variant: 'secondary',
                    onClick: () => addField(opt.value),
                  },
                  `+ ${opt.label}`
                )
              )
            )
          ),
          el(
            PanelBody,
            { title: 'Texte & Labels', initialOpen: false },
            el(TextControl, {
              label: 'Formular-Titel',
              value: formTitle,
              onChange: (value) => setAttributes({ formTitle: value }),
            }),
            el(TextareaControl, {
              label: 'Einleitungstext',
              value: formIntro,
              onChange: (value) => setAttributes({ formIntro: value }),
            }),
            el(TextControl, {
              label: 'Button-Text',
              value: submitLabel,
              onChange: (value) => setAttributes({ submitLabel: value }),
            }),
            el(TextareaControl, {
              label: 'Datenschutz-Hinweis',
              value: privacyNote,
              onChange: (value) => setAttributes({ privacyNote: value }),
            })
          )
        ),
        el(
          'div',
          blockProps,
          el(
            'div',
            {
              style: {
                background: '#f6f6f6',
                border: '2px dashed #9ca3af',
                borderRadius: '8px',
                padding: '1.25rem',
              },
            },
            el('strong', null, 'Kontaktformular (SPA)'),
            formTitle ? el('p', { style: { margin: '0.5rem 0 0', color: '#111827', fontWeight: '600' } }, formTitle) : null,
            el('p', { style: { margin: '0.5rem 0 0', color: '#374151' } }, `Betreff: ${subject || 'Kontaktanfrage'}`),
            el(
              'p',
              { style: { margin: '0.25rem 0 0', color: '#374151' } },
              recipientEmail ? `Empfaenger: ${recipientEmail}` : 'Empfaenger: Standard aus Theme-Einstellungen'
            ),
            el('p', { style: { margin: '0.25rem 0 0', color: '#374151' } }, `Felder: ${normalizedFields.length}`),
            el('p', { style: { margin: '0.25rem 0 0', color: '#374151' } }, `Button: ${submitLabel || 'Nachricht senden'}`),
            el(Notice, { status: 'info', isDismissible: false }, 'Das eigentliche Formular wird im Frontend von der SPA gerendert.')
          )
        )
      );
    },

    save() {
      return null;
    },
  });
})();
