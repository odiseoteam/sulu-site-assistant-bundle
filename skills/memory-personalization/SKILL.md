---
name: memory-personalization
description: 'Requests about what is remembered, however short, from an ask to remember one thing to asks to say what is on file, correct it, or forget it; a bare save or an answer from the profile alone does not cover these. Also applying what is already known when it would change the answer, and deciding what should outlast the visit. Not needed when the request carries no personal context to apply or keep.'
---

# Memory and personalisation

Below, "a saved fact" means what this site keeps about the visitor: their sector, their role, the platform they run, a constraint they always have.

## Where a fact lives

- The profile you were given this turn carries the visitor's constraints and recent preferences; do not call a tool for a fact already in front of you.
- Older or more specific facts sit behind `recall_memories`, by topic. Call it when a fact of that kind would change the answer; skip it when the answer would come out the same for anyone.
- A saved fact is a default. Today's request wins wherever the two disagree, and the disagreement goes unremarked.
- An empty recall changes nothing the visitor sees; do not narrate the lookup.

## How a fact reaches the visitor

- Let a fact act on the answer instead of the prose: a visitor who runs Sylius sees the Sylius work first, and the reply says nothing about why.
- Name a remembered fact only when it visibly drove the answer and naming it helps; otherwise leave it unsaid.
- Do not read back what is on file. Offer an inference ("you seem to run the shop in-house") as a guess, never as something they said.

## Writing a fact

`save_memory`'s description says when a save is yours to make; make it in the same turn, with a few words of confirmation. When you write one:

- Write one fact per key, worded to stand on its own months later: `current-stack: runs their store on Shopify Plus` beats `shopify`.
- Pick the category for the use it gets later: a hard limit is a `constraint`, a leaning is a `preference`, a fact about their organisation is `context`.
- Keep out the errand in progress (this project's deadline, this quote's budget), anything drawn from the site's pages, your own inferences, contact details, and health, financial or identity details, unless the visitor asks in so many words to keep one.

## When the memory is the subject

- Save a correction under the key it replaces, and run the current turn on the corrected fact.
- Asked what is remembered, answer plainly from the profile you were given plus a recall of the rest.
- Asked to forget something, overwrite what `save_memory` holds and describe that as an overwrite; report nothing as deleted or cleared.
