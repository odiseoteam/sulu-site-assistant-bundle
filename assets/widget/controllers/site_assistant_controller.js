import { Controller } from '@hotwired/stimulus';
import { describeToolCall, pendingComponent } from '../lib/tool-copy.js';
import { translator } from '../lib/i18n.js';
import { renderMarkdown } from '../lib/markdown.js';
import { clearTranscript, loadTranscript, rememberNudgeDismissed, rememberPanelOpen, rememberPanelWide, saveTranscript, wasNudgeDismissed, wasPanelOpen, wasPanelWide } from '../lib/transcript-store.js';

/**
 * Site assistant widget. Each message runs one turn over Server-Sent Events.
 *
 * A turn is a block of segments (text, card, error) with an activity line below it: shimmer
 * until the first event, then the running tool's label, then nothing once text arrives. A
 * presentation tool mounts a skeleton card that the next `ui` event of that component replaces.
 *
 * The session id and the rendered thread are kept in localStorage, so the conversation follows
 * the visitor from page to page. The visitor is identified by a cookie the server sets; when it
 * changes, the server answers 401 and a new session opens.
 */
export default class extends Controller {
  static targets = ['panel', 'launcher', 'thread', 'welcome', 'chips', 'form', 'input', 'submit', 'indicator', 'expand', 'newChat', 'jump', 'nudge'];
  static values = {
    sessionUrl: String, chatUrl: String, contactUrl: String, showcase: Object,
    // Strings translated by Symfony, and the page's locale and webspace, sent with every request.
    i18n: Object, locale: { type: String, default: 'en' }, webspace: String,
    // Where the person can go on: links ({label, url, detail}) by name.
    contactLinks: Object,
    // Hosts, besides the site's, whose links in a reply are kept.
    linkHosts: Array,
    // Under restricted access the widget shows itself only where the grant's cookie is.
    restricted: Boolean, accessCookie: String,
  };

  // Errors after which the chat cannot go on here: the composer gives way to the contact links.
  static CLOSING_ERRORS = ['budget_exceeded', 'not_configured', 'not_found'];
  // Errors that pass by waiting, so they have no retry.
  static WAIT_ERRORS = ['too_many_messages', 'too_many_sessions'];
  static ERROR_ICONS = { retry: 'retry', wait: 'clock', offline: 'offline' };
  // 24×24 stroke paths; the ones in FILLED_ICONS are filled brand marks.
  static FILLED_ICONS = ['whatsapp'];
  static ICONS = {
    retry: 'M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5',
    clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2',
    offline: 'M2 8.5a15 15 0 0 1 20 0M5 12a10 10 0 0 1 14 0M8.5 15.5a5 5 0 0 1 7 0M12 19h.01M3 3l18 18',
    // WhatsApp's mark, from Simple Icons (CC0).
    whatsapp: 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z',
    mail: 'M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM3 7l9 6 9-6',
    phone: 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z',
    link: 'M7 17 17 7M8 7h9v9',
    page: 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5',
  };

  static STORAGE_KEY = 'site_assistant.session';
  static STICK_PX = 96;
  static JUMP_MS = 600;
  // Room left above an answer's start once it reaches the top of the thread.
  static ANSWER_TOP_PX = 12;
  static LAUNCHER_FOLD_MS = 8000;
  static LAUNCHER_FOLD_PX = 100;
  static INPUT_MAX_LINES = 6;
  // A label stays until replaced or content arrives, and at least this long so fast tools don't flicker.
  static ACTIVITY_HOLD_MS = 600;

  connect() {
    // Restricted and without the grant: it stays hidden and never starts.
    this.started = !this.restrictedValue || this.hasAccessCookie();
    if (!this.started) return;
    this.element.removeAttribute('hidden');
    this.t = translator(this.i18nValue);
    this.sessionId = null;
    this.open = false;
    this.wide = wasPanelWide();
    this.setStick(true);
    this.lastMessage = null;
    this.turn = null;
    this.closedNode = null;
    this.items = [];
    // What a new conversation starts from: the welcome and the starter chips, before a turn removes them.
    this.blankThread = this.hasWelcomeTarget ? this.welcomeTarget.cloneNode(true) : null;
    this.starterChips = [...this.chipsTarget.children].map((chip) => chip.cloneNode(true));
    this.setBusy(false);
    this.draft();
    this.readonly = false;
    this.watchLauncherFold();
    if ((this.showcaseValue.cards ?? []).length > 0) {
      this.mountShowcase();
      return;
    }
    this.restore();
  }

  disconnect() {
    if (!this.started) return;
    this.stopLauncherFold();
    clearTimeout(this.draftTimer);
    clearTimeout(this.leaveTimer);
  }

  hasAccessCookie() {
    return document.cookie.split(';').some((cookie) => {
      const [name, value] = cookie.trim().split('=');
      return name === this.accessCookieValue && (value ?? '') !== '';
    });
  }

  // The launcher folds to its icon once the page scrolls or after a while, and stays folded
  // until the next page.
  watchLauncherFold() {
    if (!this.hasLauncherTarget) return;
    let lastY = window.scrollY;
    let travelled = 0;
    this.onPageScroll = () => {
      const delta = Math.abs(window.scrollY - lastY);
      lastY = window.scrollY;
      // A jump of more than a screen is the browser restoring the position, not the reader.
      if (delta > window.innerHeight) return;
      travelled += delta;
      if (travelled > this.constructor.LAUNCHER_FOLD_PX) this.foldLauncher();
    };
    window.addEventListener('scroll', this.onPageScroll, { passive: true });
    this.foldTimer = setTimeout(() => this.foldLauncher(), this.constructor.LAUNCHER_FOLD_MS);
  }

  foldLauncher() {
    this.launcherTarget.classList.add('is-folded');
    this.stopLauncherFold();
    this.showNudge();
  }

  // The nudge takes the folded label's place, once per visit: opening the chat or its X hides it
  // until the next one.
  showNudge() {
    if (!this.hasNudgeTarget || this.open || wasNudgeDismissed()) return;
    this.nudgeTarget.hidden = false;
  }

  dismissNudge() {
    rememberNudgeDismissed();
    if (this.hasNudgeTarget) this.nudgeTarget.hidden = true;
  }

  stopLauncherFold() {
    window.removeEventListener('scroll', this.onPageScroll);
    clearTimeout(this.foldTimer);
  }

  // Showcase page: every card with a ready payload, no session or turn. Read-only: what would
  // reach the server (chips, choices) is shown in a box instead.
  mountShowcase() {
    this.readonly = true;
    document.body.classList.add('agent-showcase');
    if (this.hasWelcomeTarget) this.welcomeTarget.remove();
    this.threadTarget.append(this.el('p', 'activity', 'agent-showcase__label'));
    const busy = this.activityLine();
    busy.message.textContent = this.t('tool.search_content');
    this.threadTarget.append(this.activityLine().line, busy.line);
    for (const card of this.showcaseValue.cards) {
      this.threadTarget.append(this.el('p', card.label, 'agent-showcase__label'));
      this.replay({ kind: 'assistant', segments: [{ type: 'ui', component: card.component, payload: card.payload }] });
    }
    this.threadTarget.append(this.el('p', 'error', 'agent-showcase__label'));
    this.threadTarget.append(this.errorNode(this.t('error.js.connection_lost'), 'offline'));
    this.threadTarget.append(this.el('p', 'suggestions', 'agent-showcase__label'));
    this.renderChips(this.showcaseValue.suggestions ?? []);
    this.prefill = this.el('div', undefined, 'agent-showcase__prefill');
    this.prefill.hidden = true;
    this.chipsTarget.after(this.prefill);
    this.setOpen(true);
  }

  showPrefill(text) {
    this.prefill.replaceChildren(this.el('p', 'composer prefill', 'agent-showcase__label'), this.el('p', text));
    this.prefill.hidden = false;
    this.prefill.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  toggle() {
    this.setOpen(!this.open);
    rememberPanelOpen(this.open);
    if (!this.open) return;
    this.inputTarget.focus();
    this.announce('open');
  }

  setOpen(open) {
    this.open = open;
    this.launcherTarget.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('agent-chat-open', open);
    if (!open) {
      this.leave();
      return;
    }
    this.dismissNudge();
    this.panelTarget.hidden = false;
    this.renderWide();
    this.enter();
    this.draft();
    this.scroll(true);
  }

  // Paint the panel off its edge, then let the transition bring it in on the next frame. Done
  // here rather than with a CSS animation so it also runs on restore.
  enter() {
    clearTimeout(this.leaveTimer);
    this.panelTarget.classList.remove('is-leaving');
    this.panelTarget.classList.add('is-entering');
    requestAnimationFrame(() => {
      requestAnimationFrame(() => this.panelTarget.classList.remove('is-entering'));
    });
  }

  // Same move backwards. The panel stays wide while it slides out, so it leaves the way it came;
  // the classes reset once it is out of sight.
  leave() {
    const panel = this.panelTarget;
    panel.classList.add('is-leaving', 'is-entering');
    clearTimeout(this.leaveTimer);
    this.leaveTimer = setTimeout(() => {
      panel.hidden = true;
      panel.classList.remove('is-entering', 'is-leaving', 'is-wide');
    }, this.durationMs('--sa-dur-panel-out'));
  }

  // Read from the token so the wait never drifts from the stylesheet.
  durationMs(token) {
    const raw = getComputedStyle(this.panelTarget).getPropertyValue(token);
    return parseFloat(raw) || 420;
  }

  // -- wide panel ----------------------------------------------------------------

  toggleWide() {
    this.wide = !this.wide;
    rememberPanelWide(this.wide);
    this.renderWide();
    // The font size changes at once and the width over the transition: measure for both.
    this.draft();
    clearTimeout(this.draftTimer);
    this.draftTimer = setTimeout(() => this.draft(), this.durationMs('--sa-dur-expand'));
  }

  renderWide() {
    const wide = this.open && this.wide === true;
    this.panelTarget.classList.toggle('is-wide', wide);
    if (!this.hasExpandTarget) return;
    this.expandTarget.setAttribute('aria-pressed', String(wide));
    this.expandTarget.setAttribute('aria-label', this.t(wide ? 'widget.collapse' : 'widget.expand'));
  }

  // -- composer ----------------------------------------------------------------

  keydown(event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      this.formTarget.requestSubmit();
    }
  }

  // Autosize the textarea. While the panel is hidden scrollHeight is 0, so it is measured
  // again on open.
  draft() {
    const input = this.inputTarget;
    this.submitTarget.disabled = this.busy || input.value.trim() === '';
    if (input.offsetParent === null) {
      input.style.height = '';
      return;
    }
    input.style.height = 'auto';
    const style = getComputedStyle(input);
    const line = parseFloat(style.lineHeight) || 20;
    const max = line * this.constructor.INPUT_MAX_LINES + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
    input.style.height = `${Math.min(input.scrollHeight, max)}px`;
    input.style.overflowY = input.scrollHeight > max ? 'auto' : 'hidden';
  }

  pick(event) {
    const chip = event.target.closest('.agent-chip');
    if (!chip || this.busy) return;
    this.submit(chip.textContent.trim(), 'chip');
  }

  send(event) {
    event.preventDefault();
    this.submit(this.inputTarget.value);
  }

  async submit(text, source = 'typed') {
    const message = text.trim();
    if (message === '' || this.busy || this.closedNode) return;
    if (this.readonly) {
      this.showPrefill(message);
      return;
    }

    this.announce('message', { source });
    this.inputTarget.value = '';
    this.draft();
    this.clearChips();
    if (this.hasWelcomeTarget) this.welcomeTarget.remove();
    // A new message leaves the earlier errors behind: only the last reply can be retried.
    for (const button of this.threadTarget.querySelectorAll('.agent-error__retry')) button.remove();
    this.items.push({ kind: 'user', text: message });
    this.refreshNewChat();
    this.appendUser(message);
    await this.ask(message);
  }

  async ask(message, retry = false) {
    this.lastMessage = message;
    this.setStick(true);
    this.openTurn();
    this.setBusy(true);

    try {
      await this.stream(message, retry);
    } catch (error) {
      // A refused session start (429) carries the server's reason; anything else is the network.
      const data = error?.data?.code ? error.data : null;
      this.appendError(this.errorText(data, this.t('error.js.connection_lost')), data ? this.errorOptions(data) : { kind: 'offline' });
    } finally {
      this.closeTurn();
      this.setBusy(false);
      this.inputTarget.focus();
    }
  }

  // The failed reply goes and the same question is answered again under it; the server does not
  // store it twice.
  retry() {
    if (!this.lastMessage || this.busy || this.closedNode) return;
    if (this.items[this.items.length - 1]?.kind === 'assistant') {
      this.items.pop();
      [...this.threadTarget.querySelectorAll(':scope > .agent-turn')].pop()?.remove();
    }
    this.ask(this.lastMessage, true);
  }

  // A new conversation: the thread and the session are dropped. The old conversation is still on
  // the server.
  startOver() {
    if (this.busy) return;
    this.forgetSession();
    this.items = [];
    this.lastMessage = null;
    this.turn = null;
    this.answer = null;
    this.reopen();
    this.threadTarget.replaceChildren(
      ...(this.blankThread ? [this.blankThread.cloneNode(true)] : []),
      this.chipsTarget,
    );
    this.chipsTarget.replaceChildren(...this.starterChips.map((chip) => chip.cloneNode(true)));
    this.refreshNewChat();
    this.setStick(true);
    this.inputTarget.focus();
  }

  refreshNewChat() {
    if (!this.hasNewChatTarget) return;
    this.newChatTarget.hidden = this.items.length === 0;
    this.newChatTarget.disabled = this.busy;
  }

  // -- transport ----------------------------------------------------------------

  async stream(message, retry = false) {
    let response = await this.post(await this.session(), message, retry);

    if (response.status === 401) {
      // Expired session or a new visitor cookie: open another one and retry once.
      this.forgetSession();
      response = await this.post(await this.session(), message);
    }

    return this.consume(response);
  }

  post(sessionId, message, retry = false) {
    return fetch(this.chatUrlValue, {
      method: 'POST',
      credentials: 'same-origin',
      headers: this.headers({ 'Content-Type': 'application/json', 'X-Session-Id': sessionId }),
      body: JSON.stringify({ message, page: this.page(), ...(retry ? { retry: true } : {}) }),
    });
  }

  async consume(response) {
    if (response.status === 429 || response.status === 400 || response.status === 404) {
      const data = await response.json().catch(() => ({}));
      this.appendError(this.errorText(data), this.errorOptions(data));
      return;
    }

    if (!response.ok || !response.body) {
      this.appendError(this.t('error.js.unavailable'), this.errorOptions({ code: 'unavailable' }));
      return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });

      let boundary;
      while ((boundary = buffer.indexOf('\n\n')) !== -1) {
        const frame = buffer.slice(0, boundary);
        buffer = buffer.slice(boundary + 2);
        this.handleFrame(frame);
      }
    }
  }

  handleFrame(frame) {
    let name = null;
    let payload = '';

    for (const line of frame.split('\n')) {
      if (line.startsWith('event: ')) name = line.slice(7).trim();
      if (line.startsWith('data: ')) payload += line.slice(6);
    }

    if (name === null) return;

    let data;
    try {
      data = JSON.parse(payload);
    } catch {
      return;
    }

    this.handle(name, data);
  }

  handle(name, data) {
    switch (name) {
      case 'text_delta':
        this.appendText(data.text);
        break;
      case 'progress':
        if (data.message) this.setActivity(data.message);
        break;
      case 'tool_call':
        this.toolCalled(data);
        break;
      case 'ui_partial':
        this.renderComponent(data.component, data.payload, data.stream_id, true);
        break;
      case 'ui':
        this.renderComponent(data.component, data.payload, data.stream_id ?? null);
        break;
      case 'turn_complete':
        this.clearActivity();
        break;
      case 'error':
        this.appendError(this.errorText(data), this.errorOptions(data));
        break;
      default:
        break;
    }
  }

  // The site listens to this to measure the chat. `action` is 'open', 'message' (with its
  // `source`: 'typed', 'chip' or 'card'), 'pages_shown' (with its `pages`), 'page_click' (with
  // the page and its `url`), 'handoff' (with the case's `status` and `path`) or 'contact_click'
  // (with the contact link's `name`). It never carries what the person wrote.
  announce(action, detail = {}) {
    document.dispatchEvent(new CustomEvent('agent:activity', { detail: { action, ...detail } }));
  }

  measured(page) {
    return { content_id: page.content_id, title: page.title, type: page.type ?? null };
  }

  announceShown(component, payload) {
    if (component === 'handoff') {
      this.announce('handoff', { status: payload.status ?? null, path: payload.path ?? null });
      return;
    }
    const pages = {
      pages: payload.picks,
      comparison: payload.entries,
      guide: payload.pages,
    }[component] ?? [];
    if (pages.length > 0) this.announce('pages_shown', { pages: pages.map((page) => this.measured(page)) });
  }

  // -- turn ----------------------------------------------------------------------

  openTurn() {
    const node = this.el('div', undefined, 'agent-turn');
    const segments = this.el('div', undefined, 'agent-turn__segments');
    const activity = this.el('div', undefined, 'agent-turn__activity');
    activity.setAttribute('role', 'status');
    node.append(segments, activity);
    this.threadTarget.append(node);

    const record = { kind: 'assistant', segments: [], chips: [] };
    this.items.push(record);
    this.turn = { node, segments, activity, record, text: null, textRecord: null, raw: '', pending: new Map(), streams: new Map(), afterTool: false, message: null, messageAt: 0, messageTimer: null };
    this.answer = node;
    this.showThinking();
    this.scroll();
  }

  closeTurn() {
    if (!this.turn) return;
    this.turn.activity.remove();
    for (const skeleton of this.turn.pending.values()) skeleton.remove();
    // A card still partial never got its ui event: the call was rejected.
    for (const node of this.turn.streams.values()) {
      if (node.dataset.partial === undefined) continue;
      this.turn.record.segments = this.turn.record.segments.filter((segment) => segment !== node.record);
      node.remove();
    }
    clearTimeout(this.turn.messageTimer);
    this.turn = null;
    if (this.sessionId) saveTranscript(this.sessionId, this.items);
    requestAnimationFrame(() => this.scroll());
  }

  // -- restore the previous page's thread ----------------------------------------

  restore() {
    const stored = this.storedSession();
    if (!stored) {
      clearTranscript();
      return;
    }

    const items = loadTranscript(stored.id);
    if (items.length === 0) return;

    this.items = items;
    this.refreshNewChat();
    if (this.hasWelcomeTarget) this.welcomeTarget.remove();
    this.clearChips();
    items.forEach((item, index) => this.replay(item, index === items.length - 1));

    const last = items[items.length - 1];
    if (last.kind === 'assistant' && (last.chips ?? []).length > 0) this.renderChips(last.chips);
    this.lastMessage = items.filter((item) => item.kind === 'user').pop()?.text ?? null;

    if (wasPanelOpen()) this.setOpen(true);
    this.scroll(true);
  }

  replay(item, last = false) {
    if (item.kind === 'user') {
      this.appendUser(item.text);
      return;
    }

    const node = this.el('div', undefined, 'agent-turn');
    const segments = this.el('div', undefined, 'agent-turn__segments');
    for (const segment of item.segments ?? []) {
      if (segment.type === 'text') {
        const text = this.el('div', undefined, 'agent-text');
        renderMarkdown(segment.text, text, this.markdownOptions());
        segments.append(text);
      } else if (segment.type === 'ui') {
        const card = this.card(segment.component, segment.payload);
        card.classList.remove('agent-reveal');
        card.dataset.component = segment.component;
        segments.append(card);
      } else if (segment.type === 'error') {
        // The chat closed there; the server decides again whether it goes on now.
        const kind = segment.kind ?? 'retry';
        segments.append(kind === 'closing' ? this.errorNode(this.t('error.js.unanswered'), 'retry', last) : this.errorNode(segment.text, kind, last));
      }
    }
    node.append(segments);
    this.threadTarget.append(node);
  }

  // Activity indicator, below the streaming text for the whole turn. The node comes from the
  // template (a dot by default), next to the running tool's label.
  showThinking() {
    const { line, message } = this.activityLine();
    this.turn.message = message;
    this.turn.activity.replaceChildren(line);
  }

  activityLine() {
    const template = this.hasIndicatorTarget ? this.indicatorTarget.content.firstElementChild : null;
    const indicator = template ? template.cloneNode(true) : this.el('i', undefined, 'agent-activity__dot');
    indicator.classList.add('agent-activity__indicator');
    const line = this.el('p', undefined, 'agent-activity');
    line.setAttribute('aria-label', this.t('widget.status.thinking'));
    const message = this.el('span');
    line.append(indicator, message);
    return { line, message };
  }

  setActivity(message) {
    if (!this.turn) return;
    clearTimeout(this.turn.messageTimer);
    this.turn.message.textContent = message;
    this.turn.messageAt = performance.now();
    this.scroll();
  }

  clearActivity() {
    if (!this.turn) return;
    const turn = this.turn;
    const remaining = this.constructor.ACTIVITY_HOLD_MS - (performance.now() - turn.messageAt);
    clearTimeout(turn.messageTimer);
    if (remaining > 0) turn.messageTimer = setTimeout(() => { turn.message.textContent = ''; }, remaining);
    else turn.message.textContent = '';
  }

  // Pending skeletons stay last: text that streams while the tool runs goes above the card.
  keepPendingLast() {
    for (const skeleton of this.turn.pending.values()) this.turn.segments.append(skeleton);
  }

  toolCalled(data) {
    if (!this.turn) return;
    this.turn.afterTool = true;
    const label = typeof data.label === 'string' && data.label.trim() !== '' ? data.label.trim() : describeToolCall(data.tool, data.input ?? {}, this.t);
    this.setActivity(label);

    const component = pendingComponent(data.tool);
    if (component && !this.turn.pending.has(component) && !this.turn.segments.querySelector(`[data-component="${component}"]`)) {
      const skeleton = this.skeleton(component, label);
      this.turn.pending.set(component, skeleton);
      this.turn.segments.append(skeleton);
      this.turn.text = null;
      this.scroll();
    }
  }

  skeleton(component, label) {
    // No text: the label already shows next to the activity indicator.
    const node = this.el('section', undefined, `agent-card agent-card--skeleton agent-card--${component}`);
    node.setAttribute('role', 'status');
    node.setAttribute('aria-label', label);

    if (component === 'pages') {
      const row = this.el('div', undefined, 'agent-pages agent-pages--carousel');
      for (let i = 0; i < 3; i++) row.append(this.skeletonTile());
      node.append(row);
    } else if (component === 'comparison') {
      const grid = this.el('div', undefined, 'agent-comparison');
      for (let i = 0; i < 2; i++) grid.append(this.skeletonTile('compare'));
      node.append(grid);
    } else {
      node.append(this.skeletonLines(['70%', '45%', '55%']));
    }
    return node;
  }

  // -- cards -------------------------------------------------------------------

  renderComponent(component, payload, streamId, partial = false) {
    if (component === 'suggestions') {
      this.renderChips(payload.suggestions ?? []);
      if (this.turn) this.turn.record.chips = payload.suggestions ?? [];
      return;
    }
    if (!this.turn) this.openTurn();

    const node = this.card(component, payload, partial);
    node.dataset.component = component;
    const existing = (streamId && this.turn.streams.get(streamId)) || this.turn.pending.get(component) || null;

    // A partial frame replacing another only animates the tiles that are new.
    if (partial) node.dataset.partial = '';
    if (existing?.dataset.partial !== undefined) {
      node.classList.remove('agent-reveal');
      const shown = existing.querySelectorAll('.agent-page').length;
      node.querySelectorAll('.agent-page').forEach((tile, index) => { if (index >= shown) tile.classList.add('agent-reveal'); });
    }

    // A replaced partial keeps its record; a skeleton has none.
    const record = existing?.record ?? { type: 'ui' };
    record.component = component;
    record.payload = payload;
    node.record = record;
    if (!existing?.record) this.turn.record.segments.push(record);

    if (existing) {
      existing.replaceWith(node);
      this.turn.pending.delete(component);
    } else {
      this.turn.segments.append(node);
      this.keepPendingLast();
    }

    if (streamId) this.turn.streams.set(streamId, node);
    this.turn.text = null;
    this.clearActivity();
    this.scroll();
    if (!partial) this.announceShown(component, payload);
  }

  // A partial frame ends with a skeleton for the item the model is still writing.
  card(component, payload, partial = false) {
    const node = this.el('section', undefined, `agent-card agent-card--${component} agent-reveal`);

    const render = {
      pages: () => this.renderPages(node, payload, partial),
      comparison: () => this.renderComparison(node, payload, partial),
      guide: () => this.renderGuide(node, payload, partial),
      handoff: () => this.renderHandoff(node, payload),
    }[component];

    if (render) render();
    return node;
  }

  renderPages(node, payload, partial = false) {
    if (payload.title) node.append(this.el('h3', payload.title, 'agent-card__title'));

    const list = this.carousel();
    for (const pick of payload.picks ?? []) list.append(this.pageTile(pick, pick.reason ?? pick.summary ?? null));
    if (partial) list.append(this.skeletonTile());
    node.append(list.closest('.agent-carousel'));
  }

  // Horizontal scroller with arrows and fades shown only when there is more on that side.
  // Returns the list; the wrapper is `list.closest('.agent-carousel')`.
  carousel(compact = false) {
    const wrapper = this.el('div', undefined, 'agent-carousel');
    const list = this.el('div', undefined, `agent-pages agent-pages--carousel${compact ? ' agent-pages--compact' : ''}`);
    const nav = (direction, label) => {
      const button = this.el('button', undefined, `agent-carousel__nav agent-carousel__nav--${direction > 0 ? 'next' : 'prev'}`);
      button.type = 'button';
      button.setAttribute('aria-label', label);
      button.addEventListener('click', () => list.scrollBy({ left: direction * (list.clientWidth - 80), behavior: 'smooth' }));
      return button;
    };
    wrapper.append(list, nav(-1, this.t('card.carousel.prev')), nav(1, this.t('card.carousel.next')));

    const sync = () => {
      wrapper.classList.toggle('has-prev', list.scrollLeft > 4);
      wrapper.classList.toggle('has-next', list.scrollLeft + list.clientWidth < list.scrollWidth - 4);
    };
    list.addEventListener('scroll', sync, { passive: true });
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(sync).observe(list);
    return list;
  }

  pageTile(page, note = null) {
    const tile = this.el('a', undefined, 'agent-page');
    this.linkPage(tile, page);

    const media = this.el('div', undefined, 'agent-page__media');
    if (page.image) {
      const image = document.createElement('img');
      image.src = page.image;
      image.alt = '';
      image.loading = 'lazy';
      media.append(image);
    } else {
      media.classList.add('agent-page__media--empty');
      media.append(this.icon('page'));
    }
    tile.append(media);

    const body = this.el('div', undefined, 'agent-page__body');
    if (page.type) body.append(this.el('span', this.typeLabel(page.type), 'agent-page__kind'));
    body.append(this.el('span', page.title ?? '', 'agent-page__title'));
    if (note) {
      const text = this.el('p', note, 'agent-page__note');
      text.title = note;
      body.append(text);
    }
    tile.append(body);

    return tile;
  }

  linkPage(link, page) {
    if (!this.safeUrl(page.url)) return;
    link.href = page.url;
    link.addEventListener('click', () => this.announce('page_click', { ...this.measured(page), url: page.url }));
  }

  // `card.type.<type>` when the site translated it, else the type name itself.
  typeLabel(type) {
    const key = `card.type.${type}`;
    const label = this.t(key);
    return label === key ? type.replaceAll(/[-_]/g, ' ') : label;
  }

  skeletonTile(variant = 'tile') {
    return this.el('div', undefined, `agent-skeleton agent-skeleton--tile agent-skeleton--${variant}`);
  }

  skeletonLines(widths) {
    const block = this.el('div', undefined, 'agent-skeleton__lines');
    for (const width of widths) {
      const line = this.el('div', undefined, 'agent-skeleton agent-skeleton--line');
      line.style.width = width;
      block.append(line);
    }
    return block;
  }

  // Each block (header, best for, pros, cons) is a subgrid row, so it lines up across columns.
  renderComparison(node, payload, partial = false) {
    if (payload.title) node.append(this.el('h3', payload.title, 'agent-card__title'));

    const grid = this.el('div', undefined, 'agent-comparison');
    for (const entry of payload.entries ?? []) {
      const card = this.el('article', undefined, 'agent-compare');
      const recommended = entry.content_id === payload.recommended_content_id;
      if (recommended) card.classList.add('is-recommended');

      const head = this.el('div', undefined, 'agent-compare__head');
      if (entry.image) {
        const media = this.el('div', undefined, 'agent-compare__media');
        const image = document.createElement('img');
        image.src = entry.image;
        image.alt = '';
        image.loading = 'lazy';
        media.append(image);
        head.append(media);
      }
      const info = this.el('div', undefined, 'agent-compare__info');
      if (recommended) info.append(this.el('span', this.t('card.recommended'), 'agent-compare__label'));
      const name = this.el('a', entry.title ?? '', 'agent-compare__name');
      this.linkPage(name, entry);
      info.append(name);
      if (entry.type) info.append(this.el('span', this.typeLabel(entry.type), 'agent-page__kind'));
      head.append(info);
      card.append(head);

      const best = this.el('div', undefined, 'agent-compare__best');
      if (entry.best_for) {
        best.append(this.el('span', `${this.t('card.best_for')}: `, 'agent-compare__best-label'), document.createTextNode(entry.best_for));
      } else {
        best.hidden = true;
      }
      card.append(best);

      for (const [label, items, kind] of [[this.t('card.pros'), entry.pros, 'pros'], [this.t('card.cons'), entry.cons, 'cons']]) {
        const block = this.el('div', undefined, `agent-compare__terms agent-compare__terms--${kind}`);
        if ((items ?? []).length > 0) {
          block.append(this.el('span', label, 'agent-compare__terms-label'));
          const list = document.createElement('ul');
          for (const item of items) list.append(this.el('li', item));
          block.append(list);
        }
        card.append(block);
      }
      grid.append(card);
    }
    if (partial) grid.append(this.skeletonTile('compare'));
    node.append(grid);

    if ((payload.dimensions ?? []).length > 0) {
      node.append(this.el('p', `${this.t('card.comparison.compared_on')}: ${payload.dimensions.join(' · ')}`, 'agent-card__sources'));
    }
  }

  renderGuide(node, payload, partial = false) {
    if (payload.title) node.append(this.el('h3', payload.title, 'agent-card__title'));
    const steps = this.el('ol', undefined, 'agent-guide');
    (payload.sections ?? []).forEach((section, index) => {
      const step = this.el('li', undefined, 'agent-guide__section');
      step.append(this.el('span', String(index + 1), 'agent-guide__num'));
      const body = this.el('div', undefined, 'agent-guide__body');
      body.append(this.el('h4', section.heading), this.el('p', section.body));
      step.append(body);
      steps.append(step);
    });
    if (partial) steps.append(this.skeletonLines(['35%', '90%', '75%']));
    node.append(steps);

    if ((payload.pages ?? []).length > 0) {
      const sources = this.el('p', `${this.t('card.sources')}: `, 'agent-card__sources');
      payload.pages.forEach((page, index) => {
        const link = this.el('a', page.title ?? '');
        this.linkPage(link, page);
        if (index > 0) sources.append(' · ');
        sources.append(link);
      });
      node.append(sources);
    }
  }

  // The case: its reference, what happens next and the way on. Only `opening` is the model's.
  renderHandoff(node, payload) {
    const failed = payload.status === 'failed';
    const choosing = payload.status === 'choose_path';
    if (payload.opening) node.append(this.el('p', payload.opening, 'agent-handoff__opening'));
    const heading = `card.handoff.heading.${payload.path}`;
    const title = failed ? 'card.handoff.failed' : choosing ? 'card.handoff.choose' : this.t(heading) !== heading ? heading : 'card.handoff.title';
    node.append(this.el('h3', this.t(title), 'agent-card__title'));
    if (payload.reference) {
      const reference = this.el('p', `${this.t('card.handoff.reference')}: `, 'agent-handoff__reference');
      reference.append(this.el('code', payload.reference));
      node.append(reference);
    }

    const text = `card.handoff.text.${payload.path}`;
    if (!failed && !choosing && this.t(text) !== text) node.append(this.el('p', this.t(text), 'agent-handoff__next'));

    if (choosing) {
      (payload.paths ?? []).forEach((path) => node.append(this.handoffChoice(path)));
    } else if (failed) {
      this.handoffLinks(node);
    } else if (payload.path === 'visitor_writes') {
      this.handoffLinks(node, payload.reference);
    } else if (payload.path === 'team_contact') {
      node.append(this.el('p', this.t(payload.open === false ? 'card.handoff.team_closed' : 'card.handoff.team_open'), 'agent-handoff__next'));
      if (payload.phone_on_file) node.append(this.el('p', this.t('card.handoff.phone').replace('%phone%', payload.phone_on_file), 'agent-handoff__next'));
      if (payload.self_serve?.includes('visitor_writes')) this.handoffLinks(node, payload.reference);
    }

    (payload.actions ?? []).forEach(({ label, url }, index) => node.append(this.contactLink(`action-${index}`, { label, url }, index === 0)));
    Object.entries(payload.alternatives ?? {}).forEach(([path, ready]) => {
      node.append(ready ? this.handoffChoice(path) : this.el('p', this.t('card.handoff.leave_contact'), 'agent-handoff__alternative'));
    });
  }

  // Answers like a chip: the choice goes back to the assistant as the visitor's message.
  handoffChoice(path) {
    const text = this.t(`card.handoff.path.${path}`);
    const button = this.el('button', text, 'agent-closed__link agent-handoff__choice');
    button.type = 'button';
    button.addEventListener('click', () => {
      if (!this.busy) this.submit(text, 'card');
    });
    return button;
  }

  // With a case, one clear way to write; without one, every way to reach the team.
  handoffLinks(node, reference = null) {
    const links = Object.entries(this.contactLinksValue);
    (reference ? links.slice(0, 1) : links).forEach(([name, link], index) => {
      node.append(this.contactLink(name, { ...link, url: this.withReference(link.url, reference) }, index === 0, true));
    });
  }

  // The reference travels in the message, so the team finds the case; nothing personal does. A
  // page of the site gets it as `?reference=`, for its contact form to fill in.
  withReference(url, reference) {
    if (!reference || url.startsWith('tel:')) return url;
    const message = encodeURIComponent(this.t('card.handoff.message').replace('%reference%', reference));
    if (url.startsWith('mailto:')) return `${url}${url.includes('?') ? '&' : '?'}subject=${message}`;

    let parsed;
    try {
      parsed = new URL(url, window.location.origin);
    } catch {
      return url;
    }
    if (parsed.origin === window.location.origin) {
      parsed.searchParams.set('reference', reference);
      return `${parsed.pathname}${parsed.search}${parsed.hash}`;
    }

    // A link may come with its own message: this one replaces it.
    parsed.searchParams.delete('text');
    return `${parsed.origin}${parsed.pathname}${parsed.search ? `${parsed.search}&` : '?'}text=${message}`;
  }

  // Below the latest answer, so they scroll away with it.
  renderChips(chips) {
    this.clearChips();
    chips.forEach((chip, index) => {
      const button = this.el('button', chip, 'agent-chip agent-reveal');
      button.type = 'button';
      button.style.animationDelay = `${index * 70}ms`;
      this.chipsTarget.append(button);
    });
    this.threadTarget.append(this.chipsTarget);
    this.scroll();
  }

  // -- thread ------------------------------------------------------------------

  appendUser(text) {
    this.threadTarget.append(this.el('div', text, 'agent-user'));
    this.scroll();
  }

  appendText(text) {
    if (!this.turn) this.openTurn();
    if (!this.turn.text) {
      this.turn.text = this.el('div', undefined, 'agent-text');
      this.turn.raw = '';
      this.turn.textRecord = { type: 'text', text: '' };
      this.turn.record.segments.push(this.turn.textRecord);
      this.turn.segments.append(this.turn.text);
      this.keepPendingLast();
    } else if (this.turn.afterTool && !/\s$/.test(this.turn.raw) && !/^\s/.test(text)) {
      // Text on both sides of a tool shares a segment, split by a paragraph break.
      this.turn.raw += '\n\n';
    }
    this.turn.afterTool = false;
    this.turn.raw += text;
    this.turn.textRecord.text = this.turn.raw;
    renderMarkdown(this.turn.raw, this.turn.text, this.markdownOptions());
    this.clearActivity();
    this.scroll();
  }

  // Server errors come as `{code, message}`, a reached cap also as `limit`: translate the limit
  // or the code, or show `message`.
  errorText(data, fallback = this.t('error.js.unavailable')) {
    for (const key of [data?.limit ? `error.limit.${data.limit}` : null, data?.code ? `error.http.${data.code}` : null]) {
      if (key && this.t(key) !== key) return this.t(key);
    }
    return data?.message ?? fallback;
  }

  errorOptions(data) {
    const code = data?.code ?? null;
    if (this.constructor.CLOSING_ERRORS.includes(code)) return { kind: 'closing' };
    if (this.constructor.WAIT_ERRORS.includes(code)) return { kind: 'wait' };
    return { kind: 'retry' };
  }

  appendError(message, { kind = 'retry' } = {}) {
    if (!this.turn) this.openTurn();
    this.turn.record.segments.push({ type: 'error', text: message, kind });
    if (kind === 'closing') {
      this.close(message);
    } else {
      this.turn.segments.append(this.errorNode(message, kind));
    }
    this.turn.text = null;
    this.clearActivity();
    this.scroll();
  }

  // A line in the turn: retry, wait, or offline (the connection dropped).
  errorNode(message, kind = 'retry', retry = true) {
    const node = this.el('div', undefined, `agent-error agent-error--${kind}`);
    node.setAttribute('role', 'alert');
    const text = this.el('span', message, 'agent-error__text');
    if (retry && kind !== 'wait') {
      const button = this.el('button', this.t('card.retry'), 'agent-error__retry');
      button.type = 'button';
      button.addEventListener('click', () => this.retry());
      text.append(' ', button);
    }
    node.append(this.icon(this.constructor.ERROR_ICONS[kind] ?? 'retry'), text);
    return node;
  }

  // The composer gives way to the contact links until the page reloads: by then the cap may have
  // lifted, and the next message asks the server again.
  close(reason) {
    if (this.closedNode) return;
    this.clearChips();
    this.closedNode = this.closedCard(reason);
    this.formTarget.hidden = true;
    this.formTarget.after(this.closedNode);
  }

  reopen() {
    this.closedNode?.remove();
    this.closedNode = null;
    this.formTarget.hidden = false;
  }

  // What happened as the title, the way on below it; the first contact link is the main button.
  closedCard(reason) {
    const node = this.el('div', undefined, 'agent-closed');
    node.setAttribute('role', 'status');
    node.append(
      this.el('p', reason, 'agent-closed__title'),
      this.el('p', this.t('error.closed.text'), 'agent-closed__text'),
    );
    Object.entries(this.contactLinksValue).forEach(([name, link], index) => node.append(this.contactLink(name, link, index === 0)));
    return node;
  }

  // From a handoff card, opening a link is reported so the next turn knows; then it does its default thing.
  contactLink(name, { label, url, detail }, primary, fromCase = false) {
    const item = this.el('div', undefined, 'agent-closed__contact');
    const link = this.el('a', undefined, primary ? 'agent-closed__link agent-closed__link--primary' : 'agent-closed__link');
    link.href = url;
    link.dataset.contact = name;
    if (url.startsWith('https:')) {
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    }
    link.append(this.icon(this.contactIcon(url)), this.el('span', label));
    link.addEventListener('click', () => {
      this.announce('contact_click', { name });
      if (fromCase && !this.readonly) this.reportContact(name);
    });
    item.append(link);
    if (detail) item.append(this.el('span', detail, 'agent-closed__detail'));
    return item;
  }

  reportContact(name) {
    if (!this.contactUrlValue || !this.sessionId) return;
    fetch(this.contactUrlValue, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: this.headers({ 'Content-Type': 'application/json', 'X-Session-Id': this.sessionId }),
      body: JSON.stringify({ link: name }),
    }).catch(() => {});
  }

  contactIcon(url) {
    if (url.startsWith('mailto:')) return 'mail';
    if (url.startsWith('tel:')) return 'phone';
    if (url.startsWith('/')) return 'page';
    try {
      if (/(^|\.)(wa\.me|whatsapp\.com)$/.test(new URL(url).hostname)) return 'whatsapp';
    } catch {
      // A malformed link gets the generic icon.
    }
    return 'link';
  }

  icon(name) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('agent-icon');
    if (this.constructor.FILLED_ICONS.includes(name)) svg.classList.add('agent-icon--filled');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', this.constructor.ICONS[name]);
    svg.append(path);
    return svg;
  }

  // The links a reply may keep: the allowed hosts plus the contact links' hosts, and mailto:/tel:
  // only to the contact links' addresses and numbers.
  markdownOptions() {
    const hosts = [...this.linkHostsValue];
    const contacts = [];
    for (const { url } of Object.values(this.contactLinksValue)) {
      try {
        const parsed = new URL(url, window.location.origin);
        if (parsed.protocol === 'https:') hosts.push(parsed.hostname);
        else contacts.push(decodeURIComponent(parsed.pathname));
      } catch {
        // A malformed link adds nothing.
      }
    }
    return { linkHosts: hosts, contacts };
  }

  clearChips() {
    this.chipsTarget.replaceChildren();
  }

  setBusy(busy) {
    this.busy = busy;
    this.inputTarget.disabled = busy;
    this.inputTarget.placeholder = this.t(busy ? 'widget.input.placeholder_busy' : 'widget.input.placeholder');
    this.submitTarget.disabled = busy || this.inputTarget.value.trim() === '';
    this.panelTarget.classList.toggle('is-busy', busy);
    this.refreshNewChat();
  }

  // Follow the answer while the reader is at the bottom; stay put if they scrolled up.
  trackScroll() {
    if (this.jumping) return;
    const node = this.threadTarget;
    this.setStick(node.scrollHeight - node.scrollTop - node.clientHeight < this.constructor.STICK_PX);
  }

  // The jump button shows while the reader is away from the bottom.
  setStick(stick) {
    this.stick = stick;
    if (this.hasJumpTarget) this.jumpTarget.classList.toggle('is-shown', !stick);
  }

  // Back to the latest message. The smooth scroll's own events would show the button again.
  jump() {
    this.setStick(true);
    this.jumping = true;
    clearTimeout(this.jumpTimer);
    this.jumpTimer = setTimeout(() => { this.jumping = false; }, this.constructor.JUMP_MS);
    const smooth = !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    this.threadTarget.scrollTo({ top: this.threadTarget.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  }

  // Follows the latest answer down until its start reaches the top, then stops: a long one is read
  // from its start, and the jump button takes the reader to its end.
  scroll(force = false) {
    if (!force && !this.stick) return;
    const thread = this.threadTarget;
    const start = !force && this.answer?.isConnected
      ? this.answer.getBoundingClientRect().top - thread.getBoundingClientRect().top + thread.scrollTop - this.constructor.ANSWER_TOP_PX
      : Infinity;
    const bottom = thread.scrollHeight - thread.clientHeight;
    if (bottom > 0 && start < bottom) {
      thread.scrollTop = Math.max(0, start);
      this.answer = null;
      this.setStick(false);
      return;
    }
    thread.scrollTop = thread.scrollHeight;
  }

  // -- helpers -----------------------------------------------------------------

  el(tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined && text !== null) node.textContent = text;
    if (className) node.className = className;
    return node;
  }

  // Links on cards come from the server; still, only same-site paths, http(s) and mailto pass.
  safeUrl(url) {
    return typeof url === 'string' && /^(\/(?!\/)|https?:\/\/|mailto:)/i.test(url);
  }

  headers(headers) {
    return { ...headers, 'X-Agent-Locale': this.localeValue, ...(this.webspaceValue ? { 'X-Agent-Webspace': this.webspaceValue } : {}) };
  }

  page() {
    return { url: window.location.pathname, title: document.title };
  }

  async session() {
    if (this.sessionId) return this.sessionId;

    const stored = this.storedSession();
    if (stored) {
      this.sessionId = stored.id;
      return this.sessionId;
    }

    const response = await fetch(this.sessionUrlValue, { method: 'POST', credentials: 'same-origin', headers: this.headers({}) });
    if (!response.ok) {
      const error = new Error('session');
      error.data = await response.json().catch(() => ({}));
      throw error;
    }

    this.sessionId = (await response.json()).session_id;
    this.storeSession(this.sessionId);
    return this.sessionId;
  }

  storeSession(id) {
    try {
      localStorage.setItem(this.constructor.STORAGE_KEY, JSON.stringify({ id }));
    } catch {
      // Without storage the session lasts as long as the page.
    }
  }

  storedSession() {
    try {
      const raw = localStorage.getItem(this.constructor.STORAGE_KEY);
      const stored = raw ? JSON.parse(raw) : null;
      return stored?.id ? stored : null;
    } catch {
      return null;
    }
  }

  forgetSession() {
    this.sessionId = null;
    clearTranscript();
    try {
      localStorage.removeItem(this.constructor.STORAGE_KEY);
    } catch {
      // Same as above.
    }
  }
}
