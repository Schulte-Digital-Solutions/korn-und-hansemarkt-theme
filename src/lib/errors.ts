import { ApiError } from './api';

export interface ErrorPresentation {
  code: string | null;
  icon: string;
  title: string;
  description: string;
  /** Ob ein "Erneut versuchen" sinnvoll ist (bei 404 z.B. nicht). */
  retryable: boolean;
}

/**
 * Beliebigen Fehler in eine besucherfreundliche Darstellung übersetzen.
 *
 * Technische Meldungen wie "API Error: 403 Forbidden" tauchen im Frontend
 * bewusst nicht mehr auf – sie sagen Besuchern nichts und wirken wie ein Defekt.
 */
export function describeError(error: unknown): ErrorPresentation {
  if (error instanceof ApiError) {
    if (error.isOffline) {
      return {
        code: null,
        icon: 'wifi_off',
        title: 'Keine Verbindung',
        description:
          'Die Inhalte konnten nicht geladen werden. Bitte prüfen Sie Ihre Internetverbindung und versuchen Sie es erneut.',
        retryable: true,
      };
    }

    if (error.status === 404) {
      return {
        code: '404',
        icon: 'travel_explore',
        title: 'Nicht gefunden',
        description: 'Dieser Inhalt existiert nicht (mehr) oder wurde verschoben.',
        retryable: false,
      };
    }

    if (error.status === 401 || error.status === 403) {
      return {
        code: null,
        icon: 'hourglass_disabled',
        title: 'Sitzung abgelaufen',
        description:
          'Diese Seite war längere Zeit geöffnet. Bitte laden Sie sie neu, dann geht es weiter.',
        retryable: true,
      };
    }

    if (error.status >= 500) {
      return {
        code: String(error.status),
        icon: 'cloud_off',
        title: 'Server nicht erreichbar',
        description:
          'Auf dem Server ist ein Fehler aufgetreten. Bitte versuchen Sie es in einem Moment noch einmal.',
        retryable: true,
      };
    }
  }

  return {
    code: null,
    icon: 'error_outline',
    title: 'Inhalte konnten nicht geladen werden',
    description: 'Bitte versuchen Sie es erneut. Falls das Problem bleibt, laden Sie die Seite neu.',
    retryable: true,
  };
}

/** Fehlerzustand für "Inhalt existiert nicht" (kein HTTP-Fehler, leeres Ergebnis). */
export function notFoundPresentation(label: string): ErrorPresentation {
  return {
    code: '404',
    icon: 'travel_explore',
    title: `${label} nicht gefunden`,
    description: 'Vielleicht wurde der Inhalt verschoben oder der Link enthält einen Tippfehler.',
    retryable: false,
  };
}
