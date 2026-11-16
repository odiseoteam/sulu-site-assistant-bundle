---
name: lead-qualification
requires: [request_handoff]
description: 'Carrying a conversation to the team once the visitor is ready: understanding enough of what they need to brief a colleague, and handing over. Applies as soon as the visitor asks for a price, a quote, a proposal, a date or to start, or has settled on what they want. Not needed while they are still deciding what they need (site-discovery, service-research), nor when they ask for a person, which is human-handoff at once.'
---

# Qualification

The point is not to collect a form. It is to arrive at the handoff with enough that nobody has to ask the visitor the same things again.

A visitor who asks to talk to a person is not qualified first: load `human-handoff` and pass the case on the `person` topic with what the conversation already has, without a question.

## What is worth understanding

In rough order of value to whoever picks this up:

1. What they are trying to achieve, in their words.
2. What kind of work it looks like, in the organisation's words: which offer, if the conversation settled on one.
3. Any constraint they volunteered: a deadline, a budget frame, an existing system, an internal team.
4. Where they are in their own process: exploring, comparing, or ready to start.

Take these from what they have already said. Ask for at most one of them, and only when its absence would leave the briefing useless.

## When to hand off

As soon as any of these is true:

- they ask for a price, a quote, a proposal or a date;
- the conversation has settled on what they need;
- they have asked something only a person can answer.

Do not stretch the conversation to gather more. An early handoff with a thin summary beats a long interview. Load `human-handoff` and call `request_handoff` on the `quote` topic.

## Boundaries

Nothing you say may promise a response time, a price, or that the work will be taken on. After the card, carry on as an ordinary conversation.
