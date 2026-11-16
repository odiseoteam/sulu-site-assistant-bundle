[← Configuration](configuration.md) · [Docs index](../README.md) · [Where to improve what →](improving.md)

# Knowledge

The assistant reads your pages through `search_content`. What is not a page goes in the
knowledge, read through `search_knowledge`: your terms, how an engagement runs, what you do not
take on, answers to frequent questions. A question about those goes through a read first; the
assistant does not fill a gap from general knowledge.

## Markdown files

One file per topic in `knowledge_dir` (`config/site_assistant/knowledge` by default), with front
matter:

```markdown
---
id: working-with-us
title: How an engagement works
kind: policy
---

# How an engagement works

## Payment

Projects are billed monthly against the hours worked.

## Privacy

Visitor conversations are kept for 180 days and never shared.
```

| Key | What it is |
|---|---|
| `id` | Stable: the admin's versions are kept under it |
| `title` | Shown in the results |
| `kind` | `policy`, `guide` or `snippet` |
| `when` | For a guide: one line on when to read it |
| `url` | A page that says more, returned with the result |
| `enabled` | `false` leaves the file out |

Each `##` section is ranked on its own, so write one question per section. Write a document in
one language: the assistant answers in the visitor's.

## Guides

A document with `kind: guide` is listed, with its `when` line, in the prompt on every turn, so the
assistant knows it exists before a visitor asks. Use guides for how to choose: what to ask, what
fits whom. `limits.max_guides` and `limits.max_guide_when_chars` cap them.

## Documents in the admin

**Site assistant › Knowledge** lists the files and the documents written in the admin. A file's
document can be edited there: the published version wins over the file, and "back to the
configuration" returns to it. A document can be saved as a draft, published with a note, compared
with any earlier version and copied back to the draft. See [The admin](admin.md).

## Another source

A class that implements the core's `Knowledge\KnowledgeSourceInterface` (autoconfigured) adds
documents from anywhere, a help desk or a CRM's FAQ:

```php
final class HelpCenterSource implements KnowledgeSourceInterface
{
    public function documents(): iterable
    {
        foreach ($this->client->articles() as $article) {
            yield new KnowledgeDocument('help-' . $article->id, $article->title, $article->body, KnowledgeKind::Snippet, url: $article->url);
        }
    }
}
```

A search that ranks its own hits (a vector store, say) implements `KnowledgeSearchInterface`; its
results are merged with the lexical search by position.
