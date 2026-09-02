/**
 * Schnellbearbeitung für Programmpunkte und Acts.
 *
 * WordPress rendert die Zusatzfelder leer; die Werte der jeweiligen Zeile liegen
 * in einem versteckten `#kuh-inline-<ID>`-Element (siehe inc/program-cpt.php) und
 * werden hier beim Öffnen der Schnellbearbeitung übertragen.
 */
/* global jQuery, inlineEditPost */
(function ($) {
  if (typeof inlineEditPost === 'undefined') {
    return;
  }

  const FIELDS = {
    date: 'kuh_slot_date',
    start: 'kuh_slot_start',
    end: 'kuh_slot_end',
    act: 'kuh_slot_act',
    note: 'kuh_slot_note',
    genre: 'kuh_act_genre',
    url: 'kuh_act_url',
    color: 'kuh_act_color',
  };

  const originalEdit = inlineEditPost.edit;

  inlineEditPost.edit = function (id) {
    originalEdit.apply(this, arguments);

    const postId = typeof id === 'object' ? this.getId(id) : id;
    if (!postId) {
      return;
    }

    const source = $('#kuh-inline-' + postId);
    if (!source.length) {
      return;
    }

    const row = $('#edit-' + postId);

    Object.keys(FIELDS).forEach(function (key) {
      const value = source.data(key);
      if (typeof value === 'undefined') {
        return;
      }
      row.find('[name="' + FIELDS[key] + '"]').val(String(value));
    });
  };
})(jQuery);
