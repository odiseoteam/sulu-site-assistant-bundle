import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SiteAssistantController from '../../assets/widget/controllers/site_assistant_controller.js';

const I18N = {
  'card.retry': 'Retry',
  'card.sources': 'From',
  'card.handoff.title': 'We passed this on',
  'card.handoff.choose': 'How would you like to go on?',
  'card.handoff.reference': 'Reference',
  'card.handoff.message': 'Hi! My reference is %reference%.',
  'card.handoff.team_open': 'The team will get in touch during working hours.',
  'card.handoff.team_closed': 'We are closed now.',
  'card.handoff.failed': 'We could not pass this on.',
  'card.handoff.path.visitor_writes': 'I will write to you',
  'card.handoff.path.team_contact': 'Contact me',
  'card.handoff.heading.visitor_writes': 'Write to us with your reference',
  'card.type.service': 'Service',
  'error.http.turn_failed': 'Something went wrong.',
  'error.http.too_many_messages': 'Too fast.',
  'error.js.unanswered': 'Unanswered.',
  'error.limit.month': 'Not available for now.',
  'error.closed.text': 'Write to us.',
};

const CONTACT = { email: { label: 'hello@site.test', url: 'mailto:hello@site.test' }, whatsapp: { label: 'WhatsApp', url: 'https://wa.me/5491100000000', detail: 'Mon to Fri' } };

const widget = (values = '') => `
  <div data-controller="site-assistant"
       data-site-assistant-i18n-value='${JSON.stringify(I18N)}'
       data-site-assistant-locale-value="es"
       data-site-assistant-webspace-value="example"
       data-site-assistant-contact-links-value='${JSON.stringify(CONTACT)}'
       data-site-assistant-session-url-value="/agent/session"
       data-site-assistant-chat-url-value="/agent/chat"
       data-site-assistant-contact-url-value="/agent/contact-link"
       data-site-assistant-access-cookie-value="sa_access_ui"
       ${values}>
    <div data-site-assistant-target="nudge" hidden></div>
    <button data-site-assistant-target="launcher" data-action="site-assistant#toggle"></button>
    <section data-site-assistant-target="panel" hidden>
      <button data-site-assistant-target="newChat" data-action="site-assistant#startOver" hidden></button>
      <template data-site-assistant-target="indicator"><i></i></template>
      <div data-site-assistant-target="thread" data-action="scroll->site-assistant#trackScroll">
        <div data-site-assistant-target="welcome">Welcome</div>
        <div data-site-assistant-target="chips" data-action="click->site-assistant#pick"><button type="button" class="agent-chip">What do you do?</button></div>
      </div>
      <button type="button" data-site-assistant-target="jump" data-action="site-assistant#jump"></button>
      <form data-site-assistant-target="form" data-action="site-assistant#send">
        <textarea data-site-assistant-target="input"></textarea>
        <button type="submit" data-site-assistant-target="submit"></button>
      </form>
    </section>
  </div>`;

const sse = (...events) => {
  const body = events.map(([event, data]) => `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`).join('');
  return new Response(new ReadableStream({
    start(controller) {
      controller.enqueue(new TextEncoder().encode(body));
      controller.close();
    },
  }), { status: 200, headers: { 'Content-Type': 'text/event-stream' } });
};

const page = { content_id: 'pages__sylius__es', title: 'Desarrollo Sylius', type: 'service', url: '/es/desarrollo-sylius', reason: 'Lo que buscás.' };

let application;

// One application at a time: a stale one would connect a second controller to the new widget.
const mount = async (values = '') => {
  application?.stop();
  document.body.innerHTML = widget(values);
  application = Application.start();
  application.register('site-assistant', SiteAssistantController);
  await vi.waitFor(() => expect(application.controllers).toHaveLength(1));
};

const controller = () => application.controllers[0];

const send = async (text) => {
  document.querySelector('textarea').value = text;
  document.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
};

const replyWith = (...events) => {
  globalThis.fetch = vi.fn(async (url) => {
    if (url === '/agent/session') return Response.json({ session_id: 'session-1' }, { status: 201 });
    if (url === '/agent/contact-link') return new Response(null, { status: 204 });

    return sse(...events, ['turn_complete', {}]);
  });
};

beforeEach(() => {
  localStorage.clear();
  sessionStorage.clear();
  document.cookie = 'sa_access_ui=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
  replyWith(
    ['text_delta', { text: 'Hacemos **marketplaces**.' }],
    ['ui', { component: 'pages', payload: { title: 'Por dónde empezar', picks: [page] }, stream_id: 'tu-1' }],
    ['ui', { component: 'suggestions', payload: { suggestions: ['Compararlos'] } }],
  );
});

afterEach(() => {
  application?.stop();
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

describe('site assistant widget', () => {
  it('opens a session, streams the reply and renders the pages', async () => {
    await mount();
    await send('¿Hacen marketplaces?');

    await vi.waitFor(() => expect(document.querySelector('.agent-page')).not.toBeNull());
    const tile = document.querySelector('.agent-page');
    expect(tile.getAttribute('href')).toBe('/es/desarrollo-sylius');
    expect(tile.querySelector('.agent-page__kind').textContent).toBe('Service');
    expect(tile.querySelector('.agent-page__note').textContent).toBe('Lo que buscás.');
    expect(document.querySelector('.agent-text strong').textContent).toBe('marketplaces');
    expect([...document.querySelectorAll('.agent-chip')].map((chip) => chip.textContent)).toEqual(['Compararlos']);
    expect(localStorage.getItem('site_assistant.session')).toContain('session-1');
  });

  it('sends the page language and webspace with every request, and the page with the message', async () => {
    await mount();
    await send('Hola');
    await vi.waitFor(() => expect(globalThis.fetch).toHaveBeenCalledTimes(2));

    const [, [chatUrl, chat]] = globalThis.fetch.mock.calls;
    expect(chatUrl).toBe('/agent/chat');
    expect(chat.headers['X-Agent-Locale']).toBe('es');
    expect(chat.headers['X-Agent-Webspace']).toBe('example');
    expect(chat.headers['X-Session-Id']).toBe('session-1');
    expect(JSON.parse(chat.body).page.url).toBe(window.location.pathname);
  });

  it('opens a new session when the server no longer knows this one', async () => {
    localStorage.setItem('site_assistant.session', JSON.stringify({ id: 'old' }));
    let chats = 0;
    globalThis.fetch = vi.fn(async (url) => {
      if (url === '/agent/session') return Response.json({ session_id: 'fresh' }, { status: 201 });
      chats += 1;
      return chats === 1 ? new Response(null, { status: 401 }) : sse(['text_delta', { text: 'Hola.' }], ['turn_complete', {}]);
    });
    await mount();
    await send('Hola');

    await vi.waitFor(() => expect(document.querySelector('.agent-text')?.textContent).toBe('Hola.'));
    expect(localStorage.getItem('site_assistant.session')).toContain('fresh');
  });

  it('closes the chat with the contact links when a cap is reached', async () => {
    replyWith(['error', { code: 'budget_exceeded', message: 'limit', limit: 'month' }]);
    await mount();
    await send('Hola');

    await vi.waitFor(() => expect(document.querySelector('.agent-closed')).not.toBeNull());
    const closed = document.querySelector('.agent-closed');
    expect(document.querySelector('form').hidden).toBe(true);
    expect(closed.querySelector('.agent-closed__title').textContent).toBe('Not available for now.');
    const [email, whatsapp] = closed.querySelectorAll('.agent-closed__link');
    expect([email.getAttribute('href'), email.classList.contains('agent-closed__link--primary')]).toEqual(['mailto:hello@site.test', true]);
    expect([whatsapp.target, whatsapp.rel]).toEqual(['_blank', 'noopener noreferrer']);
    expect(closed.querySelector('.agent-closed__detail').textContent).toBe('Mon to Fri');

    globalThis.fetch.mockClear();
    await send('Again');
    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  it('retries in place, without repeating the question', async () => {
    const bodies = [];
    globalThis.fetch = vi.fn(async (url, init) => {
      if (url === '/agent/session') return Response.json({ session_id: 'session-1' }, { status: 201 });
      bodies.push(JSON.parse(init.body));
      return bodies.length === 1
        ? sse(['error', { code: 'turn_failed', message: 'failed' }], ['turn_complete', {}])
        : sse(['text_delta', { text: 'Ahora sí.' }], ['turn_complete', {}]);
    });
    await mount();
    await send('¿Precios?');

    await vi.waitFor(() => expect(document.querySelector('.agent-error__retry')).not.toBeNull());
    expect(document.querySelector('.agent-error').textContent).toContain('Something went wrong.');
    document.querySelector('.agent-error__retry').click();

    await vi.waitFor(() => expect(document.querySelector('.agent-text')?.textContent).toBe('Ahora sí.'));
    expect(document.querySelectorAll('.agent-user')).toHaveLength(1);
    expect(document.querySelector('.agent-error')).toBeNull();
    expect(bodies[1]).toMatchObject({ message: '¿Precios?', retry: true });
  });

  it('asks to wait, with no retry, when messages come too fast', async () => {
    replyWith(['error', { code: 'too_many_messages', message: 'slow down' }]);
    await mount();
    await send('Hola');

    await vi.waitFor(() => expect(document.querySelector('.agent-error--wait')).not.toBeNull());
    expect(document.querySelector('.agent-error__retry')).toBeNull();
  });

  it('marks a dropped connection apart', async () => {
    globalThis.fetch = vi.fn(async (url) => {
      if (url === '/agent/session') return Response.json({ session_id: 'session-1' }, { status: 201 });
      throw new TypeError('offline');
    });
    await mount();
    await send('Hola');

    await vi.waitFor(() => expect(document.querySelector('.agent-error--offline')).not.toBeNull());
  });

  it('starts a new conversation from the header', async () => {
    await mount();
    await send('Hola');
    await vi.waitFor(() => expect(document.querySelector('.agent-page')).not.toBeNull());
    expect(document.querySelector('[data-site-assistant-target="newChat"]').hidden).toBe(false);

    document.querySelector('[data-site-assistant-target="newChat"]').click();

    expect(document.querySelector('.agent-user')).toBeNull();
    expect(document.querySelector('[data-site-assistant-target="welcome"]').textContent).toBe('Welcome');
    expect([...document.querySelectorAll('.agent-chip')].map((chip) => chip.textContent)).toEqual(['What do you do?']);
    expect(localStorage.getItem('site_assistant.session')).toBeNull();
  });

  it('restores the thread on the next page', async () => {
    await mount();
    await send('Hola');
    await vi.waitFor(() => expect(document.querySelector('.agent-page')).not.toBeNull());

    application.stop();
    await mount();

    await vi.waitFor(() => expect(document.querySelector('.agent-user')?.textContent).toBe('Hola'));
    expect(document.querySelector('.agent-page')).not.toBeNull();
    expect(document.querySelector('[data-site-assistant-target="welcome"]')).toBeNull();
  });

  it('folds the launcher after a while and shows the nudge once', async () => {
    vi.useFakeTimers();
    await mount();
    vi.advanceTimersByTime(8000);

    expect(document.querySelector('[data-site-assistant-target="launcher"]').classList.contains('is-folded')).toBe(true);
    expect(document.querySelector('[data-site-assistant-target="nudge"]').hidden).toBe(false);

    controller().dismissNudge();
    expect(sessionStorage.getItem('site_assistant.nudge_dismissed')).toBe('1');
    vi.useRealTimers();
  });

  it('stays hidden under restricted access without the grant, and shows itself with it', async () => {
    await mount('data-site-assistant-restricted-value="true" hidden');
    await vi.waitFor(() => expect(application.controllers).toHaveLength(1));
    expect(document.querySelector('[data-controller]').hasAttribute('hidden')).toBe(true);
    expect(controller().started).toBe(false);
    application.stop();

    document.cookie = 'sa_access_ui=1';
    await mount('data-site-assistant-restricted-value="true" hidden');
    await vi.waitFor(() => expect(document.querySelector('[data-controller]').hasAttribute('hidden')).toBe(false));
  });

  it('renders a guide with the pages it comes from', async () => {
    replyWith(['ui', { component: 'guide', payload: { title: 'Cómo empezamos', sections: [{ heading: 'Una llamada', body: 'Hablamos.' }], pages: [page] } }]);
    await mount();
    await send('¿Cómo empiezan?');

    await vi.waitFor(() => expect(document.querySelector('.agent-guide')).not.toBeNull());
    expect(document.querySelector('.agent-guide__num').textContent).toBe('1');
    expect(document.querySelector('.agent-card__sources a').getAttribute('href')).toBe('/es/desarrollo-sylius');
  });

  describe('handoff card', () => {
    const render = async (payload) => {
      await mount();
      const node = document.createElement('div');
      controller().renderHandoff(node, payload);

      return node;
    };

    it('asks to write, with the reference in the only button', async () => {
      const node = await render({ status: 'registered', reference: 'W-K7M2QX', path: 'visitor_writes' });

      expect(node.querySelector('h3').textContent).toBe('Write to us with your reference');
      expect(node.textContent).toContain('W-K7M2QX');
      expect(node.querySelector('[data-contact="email"]').getAttribute('href')).toBe('mailto:hello@site.test?subject=Hi!%20My%20reference%20is%20W-K7M2QX.');
      expect(node.querySelector('[data-contact="whatsapp"]')).toBeNull();
    });

    it('lets the visitor choose how to go on, as a message', async () => {
      const node = await render({ status: 'choose_path', reference: 'W-1', paths: ['visitor_writes', 'team_contact'] });

      expect(node.querySelector('h3').textContent).toBe('How would you like to go on?');
      const choices = [...node.querySelectorAll('.agent-handoff__choice')].map((choice) => choice.textContent);
      expect(choices).toEqual(['I will write to you', 'Contact me']);
    });

    it('tells when the team answers by the opening hours', async () => {
      expect((await render({ status: 'registered', reference: 'W-1', path: 'team_contact', open: true })).textContent).toContain('during working hours');
      expect((await render({ status: 'registered', reference: 'W-1', path: 'team_contact', open: false })).textContent).toContain('We are closed now.');
    });

    it('shows every way to reach the team when it failed', async () => {
      const node = await render({ status: 'failed' });

      expect(node.querySelector('h3').textContent).toBe('We could not pass this on.');
      expect(node.querySelectorAll('.agent-closed__link')).toHaveLength(2);
    });

    it('gives a page of the site the reference as a parameter and a messaging app as the text', async () => {
      await mount();

      expect(controller().withReference('/es/contacto', 'W-1')).toBe('/es/contacto?reference=W-1');
      expect(controller().withReference('https://wa.me/5491100000000?text=hola', 'W-1')).toBe('https://wa.me/5491100000000?text=Hi!%20My%20reference%20is%20W-1.');
      expect(controller().withReference('tel:+5491100000000', 'W-1')).toBe('tel:+5491100000000');
    });

    it('reports an opened link of a case to the server', async () => {
      await mount();
      await send('Hola');
      await vi.waitFor(() => expect(document.querySelector('.agent-page')).not.toBeNull());
      const node = document.createElement('div');
      controller().renderHandoff(node, { status: 'registered', reference: 'W-1', path: 'visitor_writes' });

      const link = node.querySelector('[data-contact="email"]');
      link.addEventListener('click', (event) => event.preventDefault());
      link.click();

      await vi.waitFor(() => expect(globalThis.fetch.mock.calls.some(([url]) => url === '/agent/contact-link')).toBe(true));
      const report = globalThis.fetch.mock.calls.find(([url]) => url === '/agent/contact-link');
      expect(JSON.parse(report[1].body)).toEqual({ link: 'email' });
      expect(report[1].headers['X-Session-Id']).toBe('session-1');
    });
  });

  describe('activity', () => {
    it('announces what the visitor does, never what they wrote', async () => {
      const events = [];
      document.addEventListener('agent:activity', (event) => events.push(event.detail));
      await mount();

      controller().toggle();
      await send('Algo privado');
      await vi.waitFor(() => expect(document.querySelector('.agent-page')).not.toBeNull());
      const tile = document.querySelector('.agent-page');
      tile.addEventListener('click', (event) => event.preventDefault());
      tile.click();

      expect(events.map((event) => event.action)).toEqual(['open', 'message', 'pages_shown', 'page_click']);
      expect(events[1]).toEqual({ action: 'message', source: 'typed' });
      expect(events[2].pages).toEqual([{ content_id: 'pages__sylius__es', title: 'Desarrollo Sylius', type: 'service' }]);
      expect(JSON.stringify(events)).not.toContain('Algo privado');
    });
  });
});
