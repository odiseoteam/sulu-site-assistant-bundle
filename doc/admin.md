[← Where to improve what](improving.md) · [Docs index](../README.md) · [Handing off to a person →](handoff.md)

# The admin

**Site assistant** in the admin's navigation. Each part has its own permission under
*Settings › User roles › System › Site assistant*:

| Permission | Parts |
|---|---|
| Conversations: view, delete | Statistics, Conversations |
| Handoffs: view, edit | Handoffs |
| Knowledge: view, add, edit | House rules, Knowledge |
| Settings: view, edit | Settings |

## Statistics

The conversations that started in a period (the last 7 or 30 days, this month or the last one) and
what came of them: how many went past one message, the cases handed to the team by topic, the
contact links opened from a case, what the model cost and its cost per conversation, and the pages
where conversations start. With several webspaces, one at a time or all together. A session opened
without a message does not count.

## Conversations

The list, newest first, with the first message, the number of messages, the language and the page.
A conversation shows its transcript (the visitor's messages, the replies, what the assistant
searched and the pages each card named), the cases it opened, the models it used and what it cost,
and what the assistant remembers about that visitor. Deleting a conversation deletes its messages;
its cases stay, without the transcript.

## Handoffs

The cases, newest first, filterable by state. A case shows its reference, topic, how it goes on,
the summary the assistant wrote for the team, the contact the visitor left, the page they were on
and a link to the conversation. The team moves it: **Take** it, **Resolve** it, **Reopen** it.
Every move is a `HandoffEvent` (see [Handing off to a person](handoff.md#events)).

## House rules and Knowledge

The configured rules and knowledge files, and the ones written in the admin, with their versions:

- **Save draft** keeps a change without using it.
- **Publish** makes it the version in use, with an optional note. Publishing checks the caps (how
  many rules or guides can be in use, how long each can be) and says what stops it.
- **Discard draft** drops the draft.
- **Back to the configuration** publishes a version that returns to the file's or the
  configuration's item.
- The history lists every version, the configured one as version 1. **Compare** shows what a
  version changed against the one in use; **Copy to the draft** starts a draft from it.

The assistant uses a published change from its next turn: the cached prompt is rebuilt when the
versions change.

## Settings

One row per webspace. A webspace's settings win over the configuration, field by field; an empty
field keeps the configured value, shown in the field's help.

| Section | Fields |
|---|---|
| General | The assistant on or off for the webspace |
| Identity | The assistant's name, the organisation's, the voice |
| Widget texts | Welcome title and text, starter questions: per language, chosen at the top |
| Models | The model that answers and its effort, the model that decides what to remember |
| API keys | Anthropic's and OpenAI's: stored encrypted, never shown again, only their last four characters |
| Spend caps | Per conversation, per day and per month, for this webspace |
| Memory | On or off, and for how many days |

The keys are encrypted with `encryption.secret` (the kernel secret by default). Changing that
secret leaves the stored keys unreadable: the configured key applies until they are saved again.
