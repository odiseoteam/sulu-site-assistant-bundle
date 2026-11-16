import { beforeEach, describe, expect, it } from 'vitest';
import { FALLBACK, translator } from '../../assets/widget/lib/i18n.js';
import { renderMarkdown } from '../../assets/widget/lib/markdown.js';
import { describeToolCall, pendingComponent } from '../../assets/widget/lib/tool-copy.js';
import { clearTranscript, loadTranscript, rememberNudgeDismissed, rememberPanelOpen, saveTranscript, wasNudgeDismissed, wasPanelOpen } from '../../assets/widget/lib/transcript-store.js';

const render = (text, options = {}) => {
  const root = document.createElement('div');
  renderMarkdown(text, root, options);
  return root;
};

describe('renderMarkdown', () => {
  it('renders emphasis, lists and headings as elements', () => {
    const root = render('## Services\n\n- **Sylius** stores\n- *Symfony* apps');

    expect(root.querySelector('h2, h3, h4')?.textContent).toBe('Services');
    expect(root.querySelectorAll('li')).toHaveLength(2);
    expect(root.querySelector('strong')?.textContent).toBe('Sylius');
    expect(root.querySelector('em')?.textContent).toBe('Symfony');
  });

  it('never turns text into HTML', () => {
    const root = render('<img src=x onerror="alert(1)"> and <script>alert(1)</script>');

    expect(root.querySelector('img, script')).toBeNull();
    expect(root.textContent).toContain('<script>');
  });

  it('keeps links to the site and drops the rest', () => {
    const root = render('[Services](/en/services) and [elsewhere](https://example.com/x)');

    expect([...root.querySelectorAll('a')].map((a) => a.getAttribute('href'))).toEqual(['/en/services']);
    expect(root.textContent).toContain('elsewhere');
  });

  it('keeps an allowed host and the site contact, and nothing else', () => {
    const root = render('[WhatsApp](https://wa.me/5491100000000), [mail](mailto:Hello@site.test), [scam](mailto:help@scam.test), [x](javascript:alert(1))', {
      linkHosts: ['wa.me'],
      contacts: ['hello@site.test'],
    });

    expect([...root.querySelectorAll('a')].map((a) => a.getAttribute('href'))).toEqual(['https://wa.me/5491100000000', 'mailto:Hello@site.test']);
  });
});

describe('describeToolCall', () => {
  const t = (key) => ({ 'tool.search_content': 'Searching the site…', 'tool.presenting': 'Preparing…', 'tool.request_handoff': 'Passing it on…' })[key] ?? key;

  it('shows the query of a search next to its label', () => {
    expect(describeToolCall('search_content', { query: ' marketplaces ' }, t)).toBe('Searching the site · “marketplaces”');
  });

  it('uses one label for every card and its own for the handoff', () => {
    expect(describeToolCall('present_guide', {}, t)).toBe('Preparing…');
    expect(describeToolCall('request_handoff', {}, t)).toBe('Passing it on…');
    expect(describeToolCall('lookup_hours', {}, t)).toBe('lookup hours…');
  });

  it('maps a card tool to the card it will render', () => {
    expect(pendingComponent('present_pages')).toBe('pages');
    expect(pendingComponent('present_guide')).toBe('guide');
    expect(pendingComponent('search_content')).toBeNull();
  });
});

describe('translator', () => {
  it('prefers the dictionary, then English, then the key', () => {
    expect(translator({ 'card.pros': 'A favor' })('card.pros')).toBe('A favor');
    expect(translator({})('card.pros')).toBe(FALLBACK['card.pros']);
    expect(translator({})('card.unknown')).toBe('card.unknown');
  });
});

describe('transcript store', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  it('gives the thread back only to the session that saved it', () => {
    saveTranscript('s1', [{ kind: 'user', text: 'hi' }]);

    expect(loadTranscript('s1')).toEqual([{ kind: 'user', text: 'hi' }]);
    expect(loadTranscript('s2')).toEqual([]);
  });

  it('forgets the thread, remembers the panel and the nudge for the visit', () => {
    saveTranscript('s1', [{ kind: 'user', text: 'hi' }]);
    clearTranscript();
    rememberPanelOpen(true);
    rememberNudgeDismissed();

    expect(loadTranscript('s1')).toEqual([]);
    expect(wasPanelOpen()).toBe(true);
    expect(wasNudgeDismissed()).toBe(true);
  });
});
