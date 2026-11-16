[← The admin API](api.md) · [Docs index](../README.md) · [Console and showcase →](console.md)

# Customizing

## Replacing a part

Services are registered as `odiseo_sulu_site_assistant.*`. To replace a part, alias its interface
to your own service:

| Interface | Default |
|---|---|
| `Content\ContentIndexInterface` | The bundle's SEAL index |
| `Handoff\HandoffDeskInterface` | Topics, routes, paths and notices from the configuration |
| `Handoff\HandoffContactResolverInterface` | The contact the visitor left on an earlier case of the conversation |
| `Encryption\ApiKeyEncrypterInterface` | Libsodium's secret box |

```yaml
services:
    Odiseo\SuluSiteAssistantBundle\Handoff\HandoffContactResolverInterface: '@App\Assistant\SignedInContact'
```

A site with its own sign-in (Sulu's community bundle, say) replaces the contact resolver, so a
case never asks for what the account already knows. Handoff paths, conditions and notices are
added with tags, see [Handing off to a person](handoff.md).

## Extending an entity

Every entity is a mapped superclass the bundle turns into the entity. To add a column, extend the
model, point the configuration at it and migrate the difference:

```yaml
odiseo_sulu_site_assistant:
    objects:
        handoff:
            model: App\Entity\AssistantHandoff
```

The names are `conversation`, `message`, `memory_fact`, `spend_entry`, `content_version`,
`handoff` and `settings`.

## Theming the widget

The widget's styles come from `--sa-*` custom properties. Redefine them on `.agent-widget` in your
own stylesheet: the selector wins over the defaults whatever order the stylesheets load in.

```css
.agent-widget {
    --sa-font: 'Inter', sans-serif;
    --sa-primary: #0f766e;
    --sa-on-primary: #ffffff;
}
```

| Property | Default | What it is |
| --- | --- | --- |
| `--sa-font`, `--sa-font-mono` | inherited, system mono | The text, and the kind of a page |
| `--sa-primary`, `--sa-primary-deep`, `--sa-primary-soft` | `#2563eb` and its shades | Buttons, links, focus rings |
| `--sa-on-primary` | `#ffffff` | Text on a primary fill |
| `--sa-bubble` | `#efefef` | The visitor's messages |
| `--sa-foot` | `#f6f6f5` | The band that holds the composer |
| `--sa-width-docked`, `--sa-width-wide`, `--sa-height` | `480px`, `880px`, `800px` | The window, and the window widened |
| `--sa-launcher-right`, `--sa-launcher-bottom` | `28px` (`16px` on mobile) | The launcher's distance to the corner |

## Overriding templates

Override any of these in `templates/bundles/OdiseoSuluSiteAssistantBundle/`:

| Template | What it is |
|---|---|
| `launcher_icon.html.twig` | The launcher's icon, a chat bubble by default |
| `brand.html.twig` | The panel's header: the assistant's name, or your logo |
| `widget_foot.html.twig` | Under the composer, empty: a disclaimer, a privacy link |
| `activity_indicator.html.twig` | The dot that pulses while a turn runs |
| `widget.html.twig` | The whole widget |
| `email/handoff_notice.html.twig` | The email the team gets for a case |

## Texts

Every text the visitor reads lives under `odiseo_sulu_site_assistant` in the `messages` domain, in
English and Spanish; override any of them in your `translations/`. The starter questions are
`widget.chips.first` to `fifth` (an untranslated one is skipped), and a webspace can set its own
in the admin. The nudge, a bubble over the folded launcher offering help once per visit, is
`widget.nudge.text`: translate it to an empty string to turn it off.

## Measuring the chat

The widget announces what the visitor does as an `agent:activity` event on `document`, never with
what they wrote:

| `action` | Detail |
|---|---|
| `open` | |
| `message` | `source`: `typed`, `chip` or `card` |
| `pages_shown` | `pages`: `{content_id, title, type}` each |
| `page_click` | The page and its `url` |
| `handoff` | The case's `status` and `path` |
| `contact_click` | The contact link's `name` |

```js
document.addEventListener('agent:activity', ({ detail }) => {
    window.dataLayer?.push({ event: 'assistant_' + detail.action, ...detail });
});
```

## The skills and the prompts

The skills in `skills/` and the memory prompt in `prompts/` are the bundle's. To change one,
point the core at your own copy:

```yaml
odiseo_sulu_site_assistant:
    memory:
        extraction_prompt_file: '%kernel.project_dir%/config/site_assistant/memory_extraction.md'
```
