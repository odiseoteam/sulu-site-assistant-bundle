// The rendered thread, kept in the browser to restore it on the next page. Tied to the
// session id: a new session starts empty.

const TRANSCRIPT_KEY = 'site_assistant.transcript';
const OPEN_KEY = 'site_assistant.open';
const WIDE_KEY = 'site_assistant.wide';
const NUDGE_KEY = 'site_assistant.nudge_dismissed';
const MAX_ITEMS = 40;

export function loadTranscript(sessionId) {
  try {
    const stored = JSON.parse(localStorage.getItem(TRANSCRIPT_KEY) ?? 'null');
    return stored && stored.session === sessionId && Array.isArray(stored.items) ? stored.items : [];
  } catch {
    return [];
  }
}

export function saveTranscript(sessionId, items) {
  try {
    localStorage.setItem(TRANSCRIPT_KEY, JSON.stringify({ session: sessionId, items: items.slice(-MAX_ITEMS) }));
  } catch {
    // Without storage (or when full) the thread lasts as long as the page.
  }
}

export function clearTranscript() {
  try {
    localStorage.removeItem(TRANSCRIPT_KEY);
  } catch {
    // Same as above.
  }
}

export function wasPanelOpen() {
  try {
    return localStorage.getItem(OPEN_KEY) === '1';
  } catch {
    return false;
  }
}

export function rememberPanelOpen(open) {
  try {
    localStorage.setItem(OPEN_KEY, open ? '1' : '0');
  } catch {
    // Same as above.
  }
}

export function wasPanelWide() {
  try {
    return localStorage.getItem(WIDE_KEY) === '1';
  } catch {
    return false;
  }
}

export function rememberPanelWide(wide) {
  try {
    localStorage.setItem(WIDE_KEY, wide ? '1' : '0');
  } catch {
    // Same as above.
  }
}

// Per visit: sessionStorage ends with the tab.
export function wasNudgeDismissed() {
  try {
    return sessionStorage.getItem(NUDGE_KEY) === '1';
  } catch {
    return false;
  }
}

export function rememberNudgeDismissed() {
  try {
    sessionStorage.setItem(NUDGE_KEY, '1');
  } catch {
    // Without storage it shows again on the next page.
  }
}
