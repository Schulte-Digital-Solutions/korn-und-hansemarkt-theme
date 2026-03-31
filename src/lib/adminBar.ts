/**
 * WordPress Admin-Bar im SPA-Kontext aktualisieren.
 *
 * Nach jeder clientseitigen Navigation wird der "Bearbeiten"-Link
 * (#wp-admin-bar-edit) auf den aktuell angezeigten Post/Page gesetzt
 * oder ausgeblendet, falls kein editierbarer Inhalt vorliegt.
 */

const EDIT_NODE_ID = 'wp-admin-bar-edit';

function getAdminUrl(path: string): string {
  const home = window.kuhData?.homeUrl?.replace(/\/$/, '') ?? '';
  return `${home}/wp-admin/${path}`;
}

/**
 * Admin-Bar "Bearbeiten"-Link aktualisieren.
 *
 * @param postId  ID des aktuellen Posts/Pages – oder `null`, um den Link auszublenden.
 */
export function updateAdminBar(postId: number | null) {
  const node = document.getElementById(EDIT_NODE_ID);
  if (!node) return; // Kein Admin-Bar vorhanden (nicht eingeloggt)

  if (postId) {
    const link = node.querySelector<HTMLAnchorElement>('a.ab-item');
    if (link) {
      link.href = getAdminUrl(`post.php?post=${postId}&action=edit`);
    }
    node.style.display = '';
  } else {
    node.style.display = 'none';
  }
}
