// English fallbacks. The real strings come translated from the template (`i18n` value); these
// cover an outdated template override so the widget never shows raw keys.

export const FALLBACK = {
  'card.recommended': 'Recommended',
  'card.pros': 'Pros',
  'card.cons': 'Cons',
  'card.best_for': 'Best for',
  'card.retry': 'Try again',
  'card.carousel.prev': 'Previous',
  'card.carousel.next': 'More',
  'card.comparison.compared_on': 'Compared on',
  'card.handoff.summary': 'What we understood',
  'card.handoff.route.page': 'Contact us',
  'card.handoff.route.email': 'Write to us',
  'card.handoff.route.booking': 'Book a call',

  'widget.input.placeholder': 'Ask a question',
  'widget.input.placeholder_busy': 'Thinking…',
  'widget.expand': 'Expand',
  'widget.collapse': 'Collapse',
  'widget.status.thinking': 'Thinking',

  'tool.search_content': 'Searching the site…',
  'tool.get_content': 'Reading the page…',
  'tool.recall_memories': 'Remembering what you told me…',
  'tool.save_memory': 'Saving that for next time…',
  'tool.load_skill': 'Loading…',
  'tool.presenting': 'Preparing the answer…',

  'error.js.connection_lost': 'Connection lost. Try again.',
  'error.js.unavailable': "The assistant isn't available right now.",
};

/** @param {Record<string, string>} dictionary the translated strings handed over by the template */
export function translator(dictionary) {
  return (key) => dictionary?.[key] ?? FALLBACK[key] ?? key;
}
