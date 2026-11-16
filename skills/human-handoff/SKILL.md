---
name: human-handoff
requires: [request_handoff]
description: 'Passing a case to a person on the team, whether the visitor asks for one outright, wants a price, a quote, a date or to hire something, has a problem with work already under way, or the case could not be settled here. Not needed while a read or a published answer still resolves the question.'
---

# Human handoff

The visitor leaves with a reference and a clear picture of what happens next. Nothing is promised or agreed here; the team does that.

## Before calling

- In a `quote`, understand what they need first: what, for what, and any constraint they stated (a deadline, an existing system, a team). Take it from what they already said; ask one question at most, and only when its absence would leave the summary useless.
- In a `support` case, understand what happened first, with one question at most. Do not ask again for what the conversation already has.
- Do not ask for a name, an email or a phone number before calling: the tool knows what the site has and says when something is missing. Never ask for passwords, card numbers or one-time codes.

## Calling

- Pick the topic by its description. A visitor who asks for a person goes to `person` at once, even in the middle of another topic; do not argue them into staying with you. A legal threat goes to `sensitive` just as fast, with no question first.
- Write `summary` for the team: two to four sentences, the facts first (what they need, for what, constraints, the pages they looked at), what they want last. Keep to what is in the conversation: assume no gender, and say they want to be contacted only if they asked for it.
- Pass `name`, `email` or `phone` only when the visitor wrote them in this chat.
- In anything the visitor reads, say you are passing it to the team; "handoff" and its translations are internal words.
- New details on the same case go in another call with the same topic, with the whole `summary` again: it replaces the last one.

## What comes back

- `registered`: call `present_handoff`, which ends the turn. When the visitor is upset or reports a problem, its `opening` is one short sentence that acknowledges how they feel or what happened, without pointing at their mistake and saying nothing about the case or what happens next; otherwise leave it empty. When `contact_path` comes back, nobody contacts the visitor yet: if they ask to be contacted, or write a phone or an email, call again with that `path` and what they wrote. Promise no time, no price and no outcome.
- `choose_path`: call `present_handoff`, whose buttons ask how to go on, without describing the paths. When they choose, call again with their `path`.
- `needs_contact`: ask for it once, without suggestion chips. If they would rather not give it, call again with one of `paths_without_contact`.
- `offer`: offer once, promising only what `paths` says, then follow what the visitor says.
- An error: say the team could not be reached right now, promise no contact, and call `present_handoff`, which shows how to reach them.

## After

- The conversation goes on: a question after a handoff is answered as usual.
- With an upset visitor, short factual sentences.
