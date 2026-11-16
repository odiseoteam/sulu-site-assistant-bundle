---
name: site-discovery
description: 'Turning a described need into a shortlist and a recommendation, when the visitor has said what they are trying to achieve rather than which page they want, or is choosing between options already in view. Not needed when one search for the thing they named answers the request, nor when they do not yet know what to weigh (service-research).'
---

# Site discovery

Below, "offer" means whatever this organisation provides: a service, a product, a way of engaging.

Turn what the visitor described into two or three options and a recommendation, in as few turns as the request allows.

## Read the request first

Pull out what they are trying to achieve, what they have already tried, and any constraint they stated — a deadline, a technology, a scale, an internal team. Those go into the search words, not into a question back to them.

A request that names an outcome is enough to search on. A request that names nothing but a field is one clarifying question, with the likely answers as chips.

## Search

Run one `search_content` per distinct thing the request names, in the same round. Phrase the query in the site's own vocabulary, not the visitor's, when the two differ; pass `type` when they asked for one kind of page (a case, a post, a service).

If a search comes back thin, widen it once — drop the type, reword the query — before saying the organisation does not do something. An empty result says what that query matched, nothing about what the organisation offers.

## Shortlist

Two or three options with `present_pages`, never a catalogue. Each one carries the reason it is on the list, tied to what the visitor said rather than to the page's own description. Read the finalists with `get_content` before you recommend between them; a summary is not enough to compare on.

When the options differ along dimensions the visitor cares about, use `present_comparison` instead. When they ask how something works or how to get started, `present_guide` lays it out in steps, with the pages it comes from. When one option clearly fits and the others are there for contrast, say so plainly.

Cases and posts are evidence, not offers: bring one in when it shows the organisation has done what the visitor needs, and say what it shows.

## What not to do

- Do not put a price, a timeline or a commitment on any of it. Those belong to the team, and to the handoff (`human-handoff`).
- Do not invent an offer the site does not publish, however obvious the gap looks.
- When nothing fits, say what the organisation does in the nearest area, and offer to put the visitor in touch.
