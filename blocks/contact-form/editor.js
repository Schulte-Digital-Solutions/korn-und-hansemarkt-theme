/**
 * Gutenberg Editor-Script fuer den Kontaktformular-Block.
 */
/* global wp */
(function () {
  const { registerBlockType } = wp.blocks;
  const { useBlockProps, InspectorControls } = wp.blockEditor;
  const { PanelBody, TextControl, TextareaControl, ToggleControl, SelectControl, Notice } = wp.components;
  const { createElement: el, Fragment, useState, useEffect } = wp.element;
  const apiFetch = wp.apiFetch;

  registerBlockType('kuh/contact-form', {
    edit({ attributes, setAttributes }) {
      const blockProps = useBlockProps({ className: 'kuh-contact-form-editor' });
      const {
        formId = 0,
        confirmationMail = false,
        subject,
        recipientEmail,
        formTitle,
        formIntro,
        submitLabel,
        successMessage,
        privacyNote,
      } = attributes;

      const [savedForms, setSavedForms] = useState([]);
      const [formsError, setFormsError] = useState(false);

      useEffect(() => {
        apiFetch({ path: '/wp/v2/kuh_form?per_page=100&status=publish&context=edit' })
          .then((forms) => setSavedForms(Array.isArray(forms) ? forms : []))
          .catch(() => setFormsError(true));
      }, []);

      const hasFormRef = Number(formId) > 0;

      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Gespeichertes Formular', initialOpen: true },
            el(SelectControl, {
              label: 'Formular auswaehlen',
              value: String(formId),
              options: [
                { label: 'Keines (Block-Felder verwenden)', value: '0' },
                ...savedForms.map((form) => ({
                  label: form.title?.rendered || `Formular #${form.id}`,
                  value: String(form.id),
                })),
              ],
              onChange: (value) => setAttributes({ formId: parseInt(value, 10) }),
              help: 'Formulare werden unter "Formulare" im Backend verwaltet.',
            }),
            formsError
              ? el(Notice, { status: 'warning', isDismissible: false }, 'Formulare konnten nicht geladen werden.')
              : null,
            hasFormRef
              ? el(Notice, { status: 'info', isDismissible: false }, 'Felder, Texte und Versand-Einstellungen kommen aus dem gespeicherten Formular. Die Block-Einstellungen unten werden ignoriert.')
              : null
          ),
          hasFormRef ? null : el(
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
            }),
            el(ToggleControl, {
              label: 'Bestaetigungsmail an Absender',
              checked: Boolean(confirmationMail),
              onChange: (value) => setAttributes({ confirmationMail: value }),
              help: 'Sendet eine Empfangsbestaetigung mit Standardtext. Ein eigener Text laesst sich nur in einem gespeicherten Formular hinterlegen.',
            })
          ),
          hasFormRef ? null : el(
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
            hasFormRef
              ? el('p', { style: { margin: '0.5rem 0 0', color: '#111827', fontWeight: '600' } }, `Verknuepftes Formular: ${savedForms.find((f) => f.id === Number(formId))?.title?.rendered || `#${formId}`}`)
              : null,
            !hasFormRef
              ? el('p', { style: { margin: '0.5rem 0 0', color: '#b91c1c' } }, 'Bitte oben ein gespeichertes Formular auswählen.')
              : null,
            hasFormRef ? null : el('p', { style: { margin: '0.5rem 0 0', color: '#374151' } }, `Betreff: ${subject || 'Kontaktanfrage'}`),
            hasFormRef ? null : el(
              'p',
              { style: { margin: '0.25rem 0 0', color: '#374151' } },
              recipientEmail ? `Empfaenger: ${recipientEmail}` : 'Empfaenger: Standard aus Theme-Einstellungen'
            ),
            hasFormRef ? null : el('p', { style: { margin: '0.25rem 0 0', color: '#374151' } }, `Button: ${submitLabel || 'Nachricht senden'}`),
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
