// English fallbacks. The real strings come translated from the template (`i18n` value); these
// cover an outdated template override so the widget never shows raw keys.
const FALLBACK = {
  'card.recommended': 'Recommended',
  'card.pros': 'Pros',
  'card.cons': 'Cons',
  'card.best_for': 'Best for',
  'card.sources': 'From',
  'card.retry': 'Try again',
  'card.carousel.prev': 'Previous',
  'card.carousel.next': 'More',
  'card.comparison.compared_on': 'Compared on',
  'card.handoff.title': 'We passed this on to the team',
  'card.handoff.choose': 'How would you like to go on?',
  'card.handoff.reference': 'Reference',
  'card.handoff.message': 'Hi! My reference is %reference%.',
  'card.handoff.leave_contact': 'Leave an email or a phone number in the chat and the team will reach out.',
  'card.handoff.team_open': 'The team will get in touch during working hours.',
  'card.handoff.team_closed': 'We are closed now: the team will get in touch on the next working day.',
  'card.handoff.phone': 'We will call you at %phone%.',
  'card.handoff.failed': 'We could not pass this on right now. You can reach the team here:',
  'card.handoff.path.visitor_writes': 'I will write to you',
  'card.handoff.path.team_contact': 'Contact me',

  'widget.input.placeholder': 'Ask a question',
  'widget.input.placeholder_busy': 'Thinking…',
  'widget.expand': 'Expand',
  'widget.collapse': 'Collapse',
  'widget.status.thinking': 'Thinking',

  'tool.search_content': 'Searching the site…',
  'tool.get_content': 'Reading the page…',
  'tool.search_knowledge': 'Checking how we work…',
  'tool.request_handoff': 'Passing it to the team…',
  'tool.recall_memories': 'Remembering what you told me…',
  'tool.save_memory': 'Saving that for next time…',
  'tool.load_skill': 'Thinking it through…',
  'tool.presenting': 'Preparing the answer…',

  'error.js.connection_lost': 'Connection lost. Try again.',
  'error.js.unavailable': "The assistant isn't available right now.",
  'error.js.unanswered': 'This question was not answered.',
  'error.closed.text': 'You can still reach the team directly:',
};

export { FALLBACK };

/** @param {Record<string, string>} dictionary the translated strings handed over by the template */
export function translator(dictionary) {
  return (key) => dictionary?.[key] ?? FALLBACK[key] ?? key;
}
