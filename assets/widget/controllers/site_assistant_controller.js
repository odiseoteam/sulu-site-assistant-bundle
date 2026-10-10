import { Controller } from '@hotwired/stimulus';
import { describeToolCall, pendingComponent } from '../lib/tool-copy.js';
import { translator } from '../lib/i18n.js';
import { renderMarkdown } from '../lib/markdown.js';
import { clearTranscript, loadTranscript, rememberPanelOpen, rememberPanelWide, saveTranscript, wasPanelOpen, wasPanelWide } from '../lib/transcript-store.js';

/**
 * Site assistant widget. Each message runs one turn over Server-Sent Events.
 *
 * A turn is a block of segments (text, card, error) with an activity line below it: shimmer
 * until the first event, then the running tool's label, then nothing once text arrives. A
 * presentation tool mounts a skeleton card that the next `ui` event of that component replaces.
 *
 * The session id and the rendered thread are kept in localStorage, so the conversation follows
 * the visitor from page to page. The visitor is identified by a cookie the server sets.
 */
export default class extends Controller {
  static targets = ['panel', 'launcher', 'thread', 'welcome', 'chips', 'form', 'input', 'submit', 'indicator', 'expand', 'veil'];
  static values = {
    sessionUrl: String, chatUrl: String, handoffUrl: String,
    i18n: Object, locale: { type: String, default: 'en' },
  };

  static STORAGE_KEY = 'site_assistant.session';
  static STICK_PX = 96;
  static INPUT_MAX_LINES = 6;
  static ACTIVITY_HOLD_MS = 600;

  connect() {
    this.t = translator(this.i18nValue);
    this.sessionId = null;
    this.open = false;
    this.wide = wasPanelWide();
    this.stick = true;
    this.lastMessage = null;
    this.turn = null;
    this.items = [];
    this.setBusy(false);
    this.draft();
    this.restore();
  }

  disconnect() {
    clearTimeout(this.draftTimer);
    clearTimeout(this.leaveTimer);
  }

  toggle() {
    this.setOpen(!this.open);
    rememberPanelOpen(this.open);
    if (this.open) this.inputTarget.focus();
  }

  setOpen(open) {
    this.open = open;
    this.launcherTarget.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('agent-chat-open', open);
    if (!open) {
      this.leave();
      return;
    }
    this.panelTarget.hidden = false;
    this.renderWide();
    this.enter();
    this.draft();
    this.scroll(true);
  }

  // Paint the panel off its edge, then let the transition bring it in on the next frame.
  enter() {
    clearTimeout(this.leaveTimer);
    this.panelTarget.classList.remove('is-leaving');
    this.panelTarget.classList.add('is-entering');
    requestAnimationFrame(() => {
      requestAnimationFrame(() => this.panelTarget.classList.remove('is-entering'));
    });
  }

  // Same move backwards; the classes reset once it is out of sight.
  leave() {
    const panel = this.panelTarget;
    if (this.hasVeilTarget) this.veilTarget.classList.remove('is-shown');
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
    this.draft();
    clearTimeout(this.draftTimer);
    this.draftTimer = setTimeout(() => this.draft(), this.durationMs('--sa-dur-expand'));
  }

  renderWide() {
    const wide = this.open && this.wide === true;
    this.panelTarget.classList.toggle('is-wide', wide);
    if (this.hasVeilTarget) this.veilTarget.classList.toggle('is-shown', wide);
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
    this.submit(chip.textContent.trim());
  }

  send(event) {
    event.preventDefault();
    this.submit(this.inputTarget.value);
  }

  async submit(text) {
    const message = text.trim();
    if (message === '' || this.busy) return;

    this.inputTarget.value = '';
    this.draft();
    this.clearChips();
    this.lastMessage = message;
    this.stick = true;
    if (this.hasWelcomeTarget) this.welcomeTarget.remove();
    this.items.push({ kind: 'user', text: message });
    this.appendUser(message);
    this.openTurn();
    this.setBusy(true);

    try {
      await this.stream(message);
    } catch (error) {
      // A refused session start (429) carries the server's reason; anything else is the network.
      this.appendError(this.errorText(error?.data, this.t('error.js.connection_lost')));
    } finally {
      this.closeTurn();
      this.setBusy(false);
      this.inputTarget.focus();
    }
  }

  retry() {
    if (this.lastMessage) this.submit(this.lastMessage);
  }

  // -- transport ----------------------------------------------------------------

  async stream(message) {
    let response = await this.post(await this.session(), message);

    if (response.status === 401) {
      // Expired session or a new visitor cookie: open another one and retry once.
      this.forgetSession();
      response = await this.post(await this.session(), message);
    }

    return this.consume(response);
  }

  post(sessionId, message) {
    return fetch(this.chatUrlValue, {
      method: 'POST',
      credentials: 'same-origin',
      headers: this.headers({ 'Content-Type': 'application/json', 'X-Session-Id': sessionId }),
      body: JSON.stringify({ message, page: this.page() }),
    });
  }

  async consume(response) {
    if (response.status === 429 || response.status === 400) {
      this.appendError(this.errorText(await response.json().catch(() => ({}))));
      return;
    }

    if (!response.ok || !response.body) {
      this.appendError(this.t('error.js.unavailable'));
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
        this.appendError(this.errorText(data));
        break;
      default:
        break;
    }
  }

  // -- turn ----------------------------------------------------------------------

  openTurn() {
    const node = this.element('div', undefined, 'agent-turn');
    const segments = this.element('div', undefined, 'agent-turn__segments');
    const activity = this.element('div', undefined, 'agent-turn__activity');
    activity.setAttribute('role', 'status');
    node.append(segments, activity);
    this.threadTarget.append(node);

    const record = { kind: 'assistant', segments: [], chips: [] };
    this.items.push(record);
    this.turn = { node, segments, activity, record, text: null, textRecord: null, raw: '', pending: new Map(), streams: new Map(), afterTool: false, message: null, messageAt: 0, messageTimer: null };
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
    if (this.hasWelcomeTarget) this.welcomeTarget.remove();
    for (const item of items) this.replay(item);

    const last = items[items.length - 1];
    if (last.kind === 'assistant' && (last.chips ?? []).length > 0) this.renderChips(last.chips);
    this.lastMessage = items.filter((item) => item.kind === 'user').pop()?.text ?? null;

    if (wasPanelOpen()) this.setOpen(true);
    this.scroll(true);
  }

  replay(item) {
    if (item.kind === 'user') {
      this.appendUser(item.text);
      return;
    }

    const node = this.element('div', undefined, 'agent-turn');
    const segments = this.element('div', undefined, 'agent-turn__segments');
    for (const segment of item.segments ?? []) {
      if (segment.type === 'text') {
        const text = this.element('div', undefined, 'agent-text');
        renderMarkdown(segment.text, text);
        segments.append(text);
      } else if (segment.type === 'ui') {
        const card = this.card(segment.component, segment.payload);
        card.classList.remove('agent-reveal');
        card.dataset.component = segment.component;
        segments.append(card);
      } else if (segment.type === 'error') {
        segments.append(this.errorNode(segment.text));
      }
    }
    node.append(segments);
    this.threadTarget.append(node);
  }

  // Activity indicator below the streaming text; the node comes from the template.
  showThinking() {
    const { line, message } = this.activityLine();
    this.turn.message = message;
    this.turn.activity.replaceChildren(line);
  }

  activityLine() {
    const template = this.hasIndicatorTarget ? this.indicatorTarget.content.firstElementChild : null;
    const indicator = template ? template.cloneNode(true) : this.element('i', undefined, 'agent-activity__dot');
    indicator.classList.add('agent-activity__indicator');
    const line = this.element('p', undefined, 'agent-activity');
    line.setAttribute('aria-label', this.t('widget.status.thinking'));
    const message = this.element('span');
    line.append(indicator, message);
    return { line, message };
  }

  // A label stays until replaced or content arrives, and at least ACTIVITY_HOLD_MS so fast
  // tools don't flicker.
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
    const node = this.element('section', undefined, `agent-card agent-card--skeleton agent-card--${component}`);
    node.setAttribute('role', 'status');
    node.setAttribute('aria-label', label);

    if (component === 'pages') {
      const row = this.element('div', undefined, 'agent-pages agent-pages--carousel');
      for (let i = 0; i < 3; i++) row.append(this.skeletonTile());
      node.append(row);
    } else if (component === 'comparison') {
      const grid = this.element('div', undefined, 'agent-comparison');
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
  }

  // A partial frame ends with a skeleton for the item the model is still writing.
  card(component, payload, partial = false) {
    const node = this.element('section', undefined, `agent-card agent-card--${component} agent-reveal`);

    const render = {
      pages: () => this.renderPages(node, payload, partial),
      comparison: () => this.renderComparison(node, payload, partial),
      handoff: () => this.renderHandoff(node, payload),
    }[component];

    if (render) render();
    return node;
  }

  renderPages(node, payload, partial = false) {
    if (payload.title) node.append(this.element('h3', payload.title, 'agent-card__title'));

    const list = this.carousel();
    for (const pick of payload.picks ?? []) list.append(this.pageTile(pick, pick.reason ?? pick.summary ?? null));
    if (partial) list.append(this.skeletonTile());
    node.append(list.closest('.agent-carousel'));
  }

  // Horizontal scroller with arrows and fades shown only when there is more on that side.
  carousel() {
    const wrapper = this.element('div', undefined, 'agent-carousel');
    const list = this.element('div', undefined, 'agent-pages agent-pages--carousel');
    const nav = (direction, label) => {
      const button = this.element('button', undefined, `agent-carousel__nav agent-carousel__nav--${direction > 0 ? 'next' : 'prev'}`);
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
    const tile = this.element('a', undefined, 'agent-page');
    if (this.safeUrl(page.url)) tile.href = page.url;

    if (page.image) {
      const media = this.element('div', undefined, 'agent-page__media');
      const image = document.createElement('img');
      image.src = page.image;
      image.alt = '';
      image.loading = 'lazy';
      media.append(image);
      tile.append(media);
    }

    const body = this.element('div', undefined, 'agent-page__body');
    if (page.type) body.append(this.element('span', this.typeLabel(page.type), 'agent-page__kind'));
    body.append(this.element('span', page.title ?? '', 'agent-page__title'));
    if (note) body.append(this.element('p', note, 'agent-page__note'));
    tile.append(body);

    return tile;
  }

  // `card.type.<type>` when the site translated it, else the type name itself.
  typeLabel(type) {
    const key = `card.type.${type}`;
    const label = this.t(key);
    return label === key ? type.replaceAll(/[-_]/g, ' ') : label;
  }

  skeletonTile(variant = 'tile') {
    return this.element('div', undefined, `agent-skeleton agent-skeleton--tile agent-skeleton--${variant}`);
  }

  skeletonLines(widths) {
    const block = this.element('div', undefined, 'agent-skeleton__lines');
    for (const width of widths) {
      const line = this.element('div', undefined, 'agent-skeleton agent-skeleton--line');
      line.style.width = width;
      block.append(line);
    }
    return block;
  }

  renderComparison(node, payload, partial = false) {
    if (payload.title) node.append(this.element('h3', payload.title, 'agent-card__title'));

    const grid = this.element('div', undefined, 'agent-comparison');
    for (const entry of payload.entries ?? []) {
      const card = this.element('article', undefined, 'agent-compare');
      const recommended = entry.content_id === payload.recommended_content_id;
      if (recommended) card.classList.add('is-recommended');

      const head = this.element('div', undefined, 'agent-compare__head');
      if (entry.image) {
        const media = this.element('div', undefined, 'agent-compare__media');
        const image = document.createElement('img');
        image.src = entry.image;
        image.alt = '';
        image.loading = 'lazy';
        media.append(image);
        head.append(media);
      }
      const info = this.element('div', undefined, 'agent-compare__info');
      if (recommended) info.append(this.element('span', this.t('card.recommended'), 'agent-compare__label'));
      const name = this.element('a', entry.title ?? '', 'agent-compare__name');
      if (this.safeUrl(entry.url)) name.href = entry.url;
      info.append(name);
      head.append(info);
      card.append(head);

      const best = this.element('div', undefined, 'agent-compare__best');
      if (entry.best_for) {
        best.append(this.element('span', `${this.t('card.best_for')}: `, 'agent-compare__best-label'), document.createTextNode(entry.best_for));
      } else {
        best.hidden = true;
      }
      card.append(best);

      for (const [label, items, kind] of [[this.t('card.pros'), entry.pros, 'pros'], [this.t('card.cons'), entry.cons, 'cons']]) {
        const block = this.element('div', undefined, `agent-compare__terms agent-compare__terms--${kind}`);
        if ((items ?? []).length > 0) {
          block.append(this.element('span', label, 'agent-compare__terms-label'));
          const list = document.createElement('ul');
          for (const item of items) list.append(this.element('li', item));
          block.append(list);
        }
        card.append(block);
      }
      grid.append(card);
    }
    if (partial) grid.append(this.skeletonTile('compare'));
    node.append(grid);

    if ((payload.dimensions ?? []).length > 0) {
      node.append(this.element('p', `${this.t('card.comparison.compared_on')}: ${payload.dimensions.join(' · ')}`, 'agent-card__sources'));
    }
  }

  // The ways to get in touch come from the server; opening one is reported so the next turn
  // knows, then the link does its default thing.
  renderHandoff(node, payload) {
    if (payload.title) node.append(this.element('h3', payload.title, 'agent-card__title'));

    if (payload.summary) {
      const summary = this.element('div', undefined, 'agent-handoff__summary');
      summary.append(this.element('span', this.t('card.handoff.summary'), 'agent-handoff__label'), document.createTextNode(payload.summary));
      node.append(summary);
    }

    if ((payload.pages ?? []).length > 0) {
      const pages = this.element('div', undefined, 'agent-handoff__pages');
      for (const page of payload.pages) {
        const link = this.element('a', page.title ?? '', 'agent-handoff__page');
        if (this.safeUrl(page.url)) link.href = page.url;
        pages.append(link);
      }
      node.append(pages);
    }

    const routes = this.element('div', undefined, 'agent-handoff__routes');
    (payload.routes ?? []).forEach((route, index) => {
      if (!this.safeUrl(route.href)) return;
      const link = this.element('a', this.t(`card.handoff.route.${route.kind}`), `agent-handoff__route${index === 0 ? ' agent-handoff__route--primary' : ''}`);
      link.href = route.href;
      if (route.kind === 'booking') {
        link.target = '_blank';
        link.rel = 'noopener';
      }
      link.addEventListener('click', () => this.reportHandoff(route.kind));
      routes.append(link);
    });
    if (routes.childElementCount > 0) node.append(routes);

    if (payload.note) node.append(this.element('p', payload.note, 'agent-handoff__note'));
  }

  reportHandoff(kind) {
    if (!this.handoffUrlValue || !this.sessionId) return;
    fetch(this.handoffUrlValue, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: this.headers({ 'Content-Type': 'application/json', 'X-Session-Id': this.sessionId }),
      body: JSON.stringify({ kind }),
    }).catch(() => {});
  }

  renderChips(chips) {
    this.clearChips();
    chips.forEach((chip, index) => {
      const button = this.element('button', chip, 'agent-chip agent-reveal');
      button.type = 'button';
      button.style.animationDelay = `${index * 70}ms`;
      this.chipsTarget.append(button);
    });
  }

  // -- thread ------------------------------------------------------------------

  appendUser(text) {
    this.threadTarget.append(this.element('div', text, 'agent-user'));
    this.scroll();
  }

  appendText(text) {
    if (!this.turn) this.openTurn();
    if (!this.turn.text) {
      this.turn.text = this.element('div', undefined, 'agent-text');
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
    renderMarkdown(this.turn.raw, this.turn.text);
    this.clearActivity();
    this.scroll();
  }

  // Server errors come as `{code, message}`: translate the code, or show `message`.
  errorText(data, fallback = this.t('error.js.unavailable')) {
    const key = data?.code ? `error.http.${data.code}` : null;
    if (key && this.t(key) !== key) return this.t(key);
    return data?.message ?? fallback;
  }

  appendError(message) {
    if (!this.turn) this.openTurn();
    this.turn.record.segments.push({ type: 'error', text: message });
    this.turn.segments.append(this.errorNode(message));
    this.turn.text = null;
    this.clearActivity();
    this.scroll();
  }

  errorNode(message) {
    const node = this.element('div', undefined, 'agent-error');
    node.setAttribute('role', 'alert');
    node.append(this.element('span', message));
    const retry = this.element('button', this.t('card.retry'), 'agent-error__retry');
    retry.type = 'button';
    retry.addEventListener('click', () => this.retry());
    node.append(retry);
    return node;
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
  }

  // Follow the answer while the reader is at the bottom; stay put if they scrolled up.
  trackScroll() {
    const node = this.threadTarget;
    this.stick = node.scrollHeight - node.scrollTop - node.clientHeight < this.constructor.STICK_PX;
  }

  scroll(force = false) {
    if (!force && !this.stick) return;
    this.threadTarget.scrollTop = this.threadTarget.scrollHeight;
  }

  // -- helpers -----------------------------------------------------------------

  element(tag, text, className) {
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
    return { ...headers, 'X-Agent-Locale': this.localeValue };
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
