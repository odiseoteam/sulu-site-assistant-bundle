// Renders the assistant's Markdown to DOM nodes, never raw HTML: all text goes through textContent.
// It re-renders fully on each delta; at this size that is cheaper than parsing incrementally.

const INLINE = /(\*\*[^*]+\*\*|`[^`]+`|\[[^\]]+\]\([^)\s]+\)|https?:\/\/[^\s<>()]+|\*[^*\s][^*]*\*|_[^_\s][^_]*_)/g;
// Sentence punctuation after a bare URL is not part of it.
const URL_TRAIL = /[.,;:!?]+$/;
const BULLET = /^\s*[-*•]\s+/;
const ORDERED = /^\s*\d+[.)]\s+/;
const HEADING = /^\s*(#{1,4})\s+(.*)$/;
const QUOTE = /^\s*>\s?/;
const TABLE_ROW = /^\s*\|.*\|\s*$/;
const TABLE_SEPARATOR = /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/;
const RULE = /^\s*([-*_])\1{2,}\s*$/;

function node(tag, text, className) {
  const element = document.createElement(tag);
  if (text !== undefined) element.textContent = text;
  if (className) element.className = className;
  return element;
}

// Links to the site (a relative path or the same origin), the hosts the site allows (subdomains
// included), and mailto: and tel: to the site's own contact. Anything else stays text: a link or a
// phone number injected through a page never becomes clickable.
let linkHosts = [];
let contactEmails = [];
let contactPhones = [];

const digits = (value) => String(value).replace(/\D+/g, '');

// The same number with or without the country and area codes: one ends with the other.
function isContactPhone(number) {
  const dialled = digits(number);
  return dialled.length >= 8 && contactPhones.some((phone) => phone.endsWith(dialled) || dialled.endsWith(phone));
}

function linkTarget(url) {
  if (url.startsWith('/') && !url.startsWith('//')) return 'site';
  let parsed;
  try {
    parsed = new URL(url);
  } catch {
    return null;
  }
  if (parsed.origin === window.location.origin) return 'site';
  if (parsed.protocol === 'mailto:') {
    const address = decodeURIComponent(parsed.pathname).toLowerCase();
    return contactEmails.includes(address) ? 'contact' : null;
  }
  if (parsed.protocol === 'tel:') return isContactPhone(parsed.pathname) ? 'contact' : null;
  if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') return null;
  const host = parsed.hostname.toLowerCase();
  return linkHosts.some((allowed) => host === allowed || host.endsWith(`.${allowed}`)) ? 'external' : null;
}

function appendLink(target, label, url) {
  const kind = linkTarget(url);
  if (!kind) {
    target.append(document.createTextNode(label));
    return;
  }
  const link = node('a', label);
  link.href = url;
  if (kind === 'external') {
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
  }
  target.append(link);
}

function inline(text, target) {
  for (const part of text.split(INLINE)) {
    if (part === '' || part === undefined) continue;

    if (part.startsWith('**') && part.endsWith('**') && part.length > 4) {
      target.append(node('strong', part.slice(2, -2)));
    } else if (part.startsWith('`') && part.endsWith('`') && part.length > 2) {
      target.append(node('code', part.slice(1, -1)));
    } else if (part.startsWith('[') && part.endsWith(')')) {
      const close = part.indexOf('](');
      appendLink(target, part.slice(1, close), part.slice(close + 2, -1));
    } else if (/^https?:\/\//.test(part)) {
      const trail = part.match(URL_TRAIL)?.[0] ?? '';
      const url = part.slice(0, part.length - trail.length);
      appendLink(target, url, url);
      if (trail) target.append(document.createTextNode(trail));
    } else if ((part.startsWith('*') && part.endsWith('*')) || (part.startsWith('_') && part.endsWith('_'))) {
      target.append(node('em', part.slice(1, -1)));
    } else {
      target.append(document.createTextNode(part));
    }
  }
}

function splitRow(line) {
  return line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((cell) => cell.trim());
}

function table(rows) {
  const wrapper = node('div', undefined, 'agent-md-table');
  const element = document.createElement('table');
  const hasHeader = rows.length > 1 && TABLE_SEPARATOR.test(rows[1]);

  if (hasHeader) {
    const head = document.createElement('thead');
    const row = document.createElement('tr');
    for (const cell of splitRow(rows[0])) {
      const th = document.createElement('th');
      inline(cell, th);
      row.append(th);
    }
    head.append(row);
    element.append(head);
  }

  const body = document.createElement('tbody');
  for (const line of hasHeader ? rows.slice(2) : rows) {
    const row = document.createElement('tr');
    for (const cell of splitRow(line)) {
      const td = document.createElement('td');
      inline(cell, td);
      row.append(td);
    }
    body.append(row);
  }
  element.append(body);
  wrapper.append(element);
  return wrapper;
}

/**
 * $options.linkHosts: the hosts, besides the site's, whose links are kept.
 * $options.contacts: the site's email and phone; only those take mailto: and tel: links.
 */
export function renderMarkdown(text, root, options = {}) {
  linkHosts = (options.linkHosts ?? []).map((host) => String(host).toLowerCase());
  const contacts = (options.contacts ?? []).filter(Boolean).map(String);
  contactEmails = contacts.filter((value) => value.includes('@')).map((value) => value.toLowerCase());
  contactPhones = contacts.filter((value) => !value.includes('@')).map(digits).filter((value) => value.length >= 8);
  root.replaceChildren();

  let paragraph = [];
  let list = null;
  let rows = [];
  let quote = [];

  const flush = () => {
    if (paragraph.length > 0) {
      const p = document.createElement('p');
      paragraph.forEach((line, index) => {
        if (index > 0) p.append(document.createElement('br'));
        inline(line, p);
      });
      root.append(p);
      paragraph = [];
    }
    if (list) {
      root.append(list);
      list = null;
    }
    if (rows.length > 0) {
      root.append(table(rows));
      rows = [];
    }
    if (quote.length > 0) {
      const block = document.createElement('blockquote');
      for (const line of quote) {
        const p = document.createElement('p');
        inline(line, p);
        block.append(p);
      }
      root.append(block);
      quote = [];
    }
  };

  for (const line of text.split('\n')) {
    const heading = HEADING.exec(line);
    const listTag = BULLET.test(line) ? 'ul' : ORDERED.test(line) ? 'ol' : null;

    if (listTag) {
      if (!list || list.tagName.toLowerCase() !== listTag) {
        flush();
        list = document.createElement(listTag);
      }
      const item = document.createElement('li');
      inline(line.replace(listTag === 'ul' ? BULLET : ORDERED, ''), item);
      list.append(item);
    } else if (TABLE_ROW.test(line)) {
      if (rows.length === 0) flush();
      rows.push(line);
    } else if (QUOTE.test(line)) {
      if (quote.length === 0) flush();
      quote.push(line.replace(QUOTE, ''));
    } else if (heading) {
      flush();
      const level = Math.min(heading[1].length + 2, 5);
      const h = document.createElement(`h${level}`);
      inline(heading[2], h);
      root.append(h);
    } else if (RULE.test(line)) {
      flush();
      root.append(document.createElement('hr'));
    } else if (line.trim() === '') {
      flush();
    } else {
      if (list || rows.length > 0 || quote.length > 0) flush();
      paragraph.push(line);
    }
  }
  flush();
}
