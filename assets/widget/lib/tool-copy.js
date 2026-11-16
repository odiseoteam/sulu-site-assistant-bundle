// The label shown while a tool runs, unless the model sent its own. Only maps each tool to its
// translation key; `t` resolves it.

const QUERY_TOOLS = new Set(['search_content', 'search_knowledge']);

// The skeleton mounts when the model starts writing the card, not on search: otherwise it stays
// empty when the model answers in text.
const PENDING_COMPONENTS = {
  present_pages: 'pages',
  present_comparison: 'comparison',
  present_guide: 'guide',
  present_handoff: 'handoff',
};

// Tools with their own label; the rest fall back to the generic one.
const KNOWN_TOOLS = new Set([
  'search_content',
  'get_content',
  'search_knowledge',
  'request_handoff',
  'recall_memories',
  'save_memory',
  'load_skill',
]);

export function describeToolCall(tool, input = {}, t = (key) => key) {
  const copy = KNOWN_TOOLS.has(tool) ? t(`tool.${tool}`) : null;
  const query = typeof input.query === 'string' ? input.query.trim() : '';
  if (copy && query && QUERY_TOOLS.has(tool)) {
    return `${copy.replace(/…$/, '')} · “${query}”`;
  }
  if (copy) return copy;
  if (tool.startsWith('present_')) return t('tool.presenting');

  return `${tool.replaceAll('_', ' ')}…`;
}

export function pendingComponent(tool) {
  return PENDING_COMPONENTS[tool] ?? null;
}
