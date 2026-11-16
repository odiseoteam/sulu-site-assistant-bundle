[← Knowledge](knowledge.md) · [Docs index](../README.md) · [The admin →](admin.md)

# Where to improve what

When the assistant gets something wrong, the fix goes in one of five places. Pick the place by
what is wrong, not by the conversation it showed up in.

| What is wrong | Where it goes | Example |
|---|---|---|
| It behaves wrong everywhere: a word, the tone, a habit | A [rule](configuration.md#rules) | "Never quote a timeline" |
| It does not know a fact, or gets one wrong, and the site does not publish it | [Knowledge](knowledge.md) | "We do not take on WordPress projects" |
| It gets a fact wrong that a page does say | The page itself, then reindex | A service page that never names its stack |
| It finds the wrong pages | `content.types`, `excluded_templates`, `search_notes`, the grounding lexicon | A search results page in the index |
| It speaks in the wrong voice or name | The identity, in the configuration or the webspace's settings | "warm and brief" |
| A whole flow works the wrong way (choosing, handing off) | The skills, rarely | |

## Rules

A rule is in the prompt on every turn, so it is always followed and always paid for: keep it to a
sentence. It says how to behave, never a fact.

## Knowledge

What the organisation knows and the site does not say. Changing a fact is changing a document;
the prompt stays the same.

## Pages

The assistant reads your pages as the index has them: every text field of the template, a block
item as a section headed by its first short line. A page whose text is in images, or whose
template keeps the substance in fields of a type the index does not read, reads as empty:
`bin/websiteconsole site-assistant:search --show <content_id>` prints a page as the assistant
reads it.

## Skills

The bundle's skills (`skills/`) are how the assistant runs a flow: discovering what fits,
teaching what to weigh, qualifying a lead, handing off, using memory. They change rarely, and a
change changes behaviour everywhere: run the [evals](evals.md) after one.

## After a change

Run the cases that cover it with `agent:eval --tag <tag>`, and look at a few real conversations in
the admin a day later.
