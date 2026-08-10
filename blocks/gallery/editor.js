/**
 * Gutenberg Editor-Script für den Galerie-Block.
 */
/* global wp */
(function () {
  const { registerBlockType } = wp.blocks;
  const { useBlockProps, InspectorControls } = wp.blockEditor;
  const { PanelBody, ToggleControl, TextControl, RangeControl, SelectControl, Placeholder, Spinner } = wp.components;
  const { createElement: el, Fragment, useState, useEffect } = wp.element;
  const { addQueryArgs } = wp.url;
  const apiFetch = wp.apiFetch;

  registerBlockType('kuh/gallery', {
    edit({ attributes, setAttributes }) {
      const {
        title,
        showTitle,
        columns,
        showYearFilter,
        showPhotographerFilter,
        showTypeFilter,
        showResultCount,
        showCredit,
        defaultYear,
        limit,
        order,
      } = attributes;

      const [data, setData] = useState(null);
      const [error, setError] = useState('');

      useEffect(() => {
        let cancelled = false;
        apiFetch({ path: addQueryArgs('/kuh/v1/gallery', { per_page: limit }) })
          .then((result) => {
            if (!cancelled) setData(result);
          })
          .catch((err) => {
            if (!cancelled) setError(err.message || 'Galerie-Daten konnten nicht geladen werden.');
          });
        return () => {
          cancelled = true;
        };
      }, [limit]);

      const yearOptions = [{ label: 'Keine Vorauswahl (alle Jahre)', value: '' }].concat(
        (data && data.years ? data.years : []).map((year) => ({ label: year.name, value: year.slug }))
      );

      let preview;
      if (error) {
        preview = el(Placeholder, { icon: 'format-gallery', label: 'Bildergalerie' }, error);
      } else if (!data) {
        preview = el(Placeholder, { icon: 'format-gallery', label: 'Bildergalerie' }, el(Spinner));
      } else if (!data.items.length) {
        preview = el(
          Placeholder,
          { icon: 'format-gallery', label: 'Bildergalerie' },
          el(
            'p',
            null,
            'Noch keine Inhalte verschlagwortet. Weise Bildern in der Medienbibliothek ein ',
            el('strong', null, 'Galerie-Jahr'),
            ' zu oder lege unter Medien → Galerie-Videos ein Video an.'
          )
        );
      } else {
        const videoCount = data.items.filter((item) => item.type === 'video').length;
        preview = el(
          Placeholder,
          { icon: 'format-gallery', label: showTitle && title ? title : 'Bildergalerie' },
          el(
            'p',
            null,
            data.total +
              ' Einträge insgesamt · ' +
              data.years.length +
              ' Jahre · ' +
              data.photographers.length +
              ' Fotografen · erste Seite: ' +
              (data.items.length - videoCount) +
              ' Bilder / ' +
              videoCount +
              ' Videos'
          ),
          el(
            'div',
            { style: { display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: '4px', width: '100%' } },
            data.items.slice(0, 6).map((item) =>
              el('img', {
                key: item.id,
                src: item.thumb,
                alt: '',
                style: { width: '100%', aspectRatio: '1', objectFit: 'cover', borderRadius: '4px' },
              })
            )
          )
        );
      }

      return el(
        'div',
        useBlockProps(),
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Darstellung', initialOpen: true },
            el(ToggleControl, {
              label: 'Überschrift anzeigen',
              checked: showTitle,
              onChange: (val) => setAttributes({ showTitle: val }),
            }),
            showTitle &&
              el(TextControl, {
                label: 'Überschrift',
                value: title,
                onChange: (val) => setAttributes({ title: val }),
              }),
            el(RangeControl, {
              label: 'Spalten (Desktop)',
              value: columns,
              onChange: (val) => setAttributes({ columns: val }),
              min: 2,
              max: 6,
            }),
            el(ToggleControl, {
              label: 'Fotograf als Bildunterschrift anzeigen',
              checked: showCredit,
              onChange: (val) => setAttributes({ showCredit: val }),
            })
          ),
          el(
            PanelBody,
            { title: 'Filter', initialOpen: true },
            el(ToggleControl, {
              label: 'Filter „Jahr" anzeigen',
              checked: showYearFilter,
              onChange: (val) => setAttributes({ showYearFilter: val }),
            }),
            el(ToggleControl, {
              label: 'Filter „Fotograf" anzeigen',
              checked: showPhotographerFilter,
              onChange: (val) => setAttributes({ showPhotographerFilter: val }),
            }),
            el(ToggleControl, {
              label: 'Filter „Bilder / Videos" anzeigen',
              checked: showTypeFilter,
              onChange: (val) => setAttributes({ showTypeFilter: val }),
              help: 'Erscheint im Frontend nur, wenn sowohl Bilder als auch Videos vorhanden sind.',
            }),
            el(ToggleControl, {
              label: 'Trefferanzahl anzeigen',
              checked: showResultCount,
              onChange: (val) => setAttributes({ showResultCount: val }),
            }),
            el(SelectControl, {
              label: 'Vorausgewähltes Jahr',
              value: defaultYear,
              options: yearOptions,
              onChange: (val) => setAttributes({ defaultYear: val }),
              help: 'Ein Jahr in der URL (?jahr=…) hat Vorrang.',
            })
          ),
          el(
            PanelBody,
            { title: 'Daten', initialOpen: false },
            el(RangeControl, {
              label: 'Bilder pro Seite',
              value: limit,
              onChange: (val) => setAttributes({ limit: val }),
              min: 12,
              max: 200,
              step: 12,
              help: 'Weitere Einträge werden im Frontend beim Scrollen nachgeladen.',
            }),
            el(SelectControl, {
              label: 'Sortierung',
              value: order,
              options: [
                { label: 'Neuestes Jahr zuerst', value: 'DESC' },
                { label: 'Ältestes Jahr zuerst', value: 'ASC' },
              ],
              onChange: (val) => setAttributes({ order: val }),
              help: 'Innerhalb eines Jahres wird nach Datum sortiert.',
            })
          )
        ),
        preview
      );
    },
    save() {
      return null; // Dynamischer Block, wird serverseitig gerendert
    },
  });
})();
