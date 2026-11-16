<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability;

use Odiseo\AiConversationalAgentBundle\Capability\PromptFragment;
use Odiseo\AiConversationalAgentBundle\Capability\PromptSection;
use Odiseo\AiConversationalAgentBundle\Capability\ScopeArea;
use Odiseo\AiConversationalAgentBundle\Capability\ScopedCapabilityInterface;
use Odiseo\AiConversationalAgentBundle\Capability\ToolContext;
use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Presentation\ChipMode;
use Odiseo\AiConversationalAgentBundle\Presentation\EnrichmentContext;
use Odiseo\AiConversationalAgentBundle\Presentation\PayloadGuard;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationRefusedException;
use Odiseo\AiConversationalAgentBundle\Session\TurnState;
use Odiseo\AiConversationalAgentBundle\Streaming\ToolOutcome;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Handoff\HandoffDeskInterface;
use Odiseo\SuluSiteAssistantBundle\Model\ContactNeed;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffContact;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffMode;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcome;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffOutcomeStatus;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffRequest;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffTopic;

/**
 * Passing a case to a person. The model judges that one is needed and picks the topic by its
 * description; the mode, the path and the contact are decided in code. Registered only when the
 * site configured a route.
 */
final class HandoffCapability implements ScopedCapabilityInterface
{
    public const REQUEST = 'request_handoff';

    public const PRESENT = 'present_handoff';

    public const COMPONENT = 'handoff';

    /** Gates on a held call: nothing was handed off yet. */
    public const OFFER_GATE = 'handoff_offer';

    public const CONTACT_GATE = 'handoff_contact';

    public const CARD_GATE = 'handoff_card';

    /** What the card shows, kept by the last request: the model never writes it. */
    private const CARD_KEY = 'handoff_card';

    /** Topics already offered in this session: a second call hands off. */
    private const OFFERED_KEY = 'handoff_offered';

    private const MAX_SUMMARY_CHARS = 1000;

    private const MAX_OPENING_CHARS = 200;

    private const MAX_NAME_CHARS = 120;

    private const MIN_PHONE_DIGITS = 8;

    private const MAX_PHONE_DIGITS = 15;

    public function __construct(
        private readonly HandoffDeskInterface $desk,
        private readonly HandoffPolicy $policy,
    ) {
    }

    public function name(): string
    {
        return 'handoff';
    }

    public function scopeArea(): ScopeArea
    {
        return new ScopeArea('putting the visitor in touch with the team', 20);
    }

    public function tools(): array
    {
        return [
            new ToolSpec(
                self::REQUEST,
                'Pass this case to a person on the team. The result says what actually happened and what to do next.',
                [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string', 'enum' => \array_keys($this->policy->topics), 'description' => 'The topic that fits.'],
                        'summary' => ['type' => 'string', 'maxLength' => self::MAX_SUMMARY_CHARS, 'description' => 'What the person needs to know so the visitor does not repeat it: what they need, for what, any constraint they stated, and the pages they looked at. On another call for the same case, write it whole: it replaces the last one.'],
                        'name' => ['type' => 'string', 'maxLength' => self::MAX_NAME_CHARS, 'description' => 'Only a name the visitor wrote in this chat.'],
                        'email' => ['type' => 'string', 'description' => 'Only an email the visitor wrote in this chat.'],
                        'phone' => ['type' => 'string', 'description' => 'Only a phone number the visitor wrote in this chat.'],
                        'path' => ['type' => 'string', 'description' => 'The way to go on, once the visitor picked one from the result.'],
                    ],
                    'required' => ['topic', 'summary'],
                    'additionalProperties' => false,
                ],
            ),
            new ToolSpec(
                self::PRESENT,
                'Show the card of the case: its reference, what happens next and the buttons to go on; the UI fills them in. Call it whenever request_handoff says so.',
                // A required field: a call with no input is replayed as a list and the API rejects it.
                [
                    'type' => 'object',
                    'properties' => [
                        'opening' => ['type' => 'string', 'maxLength' => self::MAX_OPENING_CHARS, 'description' => 'One short sentence for a visitor who is upset or reports a problem: acknowledge how they feel or what happened, without pointing at their mistake. Say nothing about the case or what happens next: the card does. An empty string otherwise.'],
                    ],
                    'required' => ['opening'],
                    'additionalProperties' => false,
                ],
                wantsStatusLine: false,
            ),
        ];
    }

    public function promptFragments(): array
    {
        $topics = \array_map(static fn (HandoffTopic $topic): string => \sprintf('  - %s: %s', $topic->id, $topic->description), \array_values($this->policy->topics));

        return [
            new PromptFragment(
                PromptSection::Tools,
                "- When a case needs a person, load the human-handoff skill and call request_handoff with the topic that fits:\n" . \implode("\n", $topics) . "\n  A fact the site publishes is not a case: give it and do not call the tool.",
                priority: 40,
            ),
            new PromptFragment(
                PromptSection::Boundaries,
                '- You do not quote a price, commit to a date or accept a piece of work: that is a case for the team. Say a case is registered only when request_handoff returned it in this conversation, and that someone will contact the visitor only when its result says so.',
                priority: 40,
            ),
        ];
    }

    public function groundingRules(): array
    {
        return [];
    }

    public function components(): array
    {
        return [new PresentationComponent(self::PRESENT, self::COMPONENT, $this->validate(...), $this->enrich(...), chips: ChipMode::None)];
    }

    public function execute(string $tool, array $input, ToolContext $context): ToolOutcome
    {
        if (self::REQUEST !== $tool) {
            return ToolOutcome::error(\sprintf('Unknown tool: %s', $tool));
        }

        $topic = $this->policy->topic(Scalar::string($input['topic'] ?? null));
        if (null === $topic) {
            return ToolOutcome::error(\sprintf('Unknown topic. Use one of: %s.', \implode(', ', \array_keys($this->policy->topics))));
        }

        $summary = $context->sanitize($input['summary'] ?? null, self::MAX_SUMMARY_CHARS);
        if ('' === \trim($summary)) {
            return ToolOutcome::error('Write a summary of the case.');
        }

        if (HandoffMode::Offer === $topic->mode && $this->offerFirst($topic, $context->state)) {
            $paths = $this->desk->paths($topic, $context->session);
            if ([] !== $paths) {
                return ToolOutcome::held(self::OFFER_GATE, $context->fenced([
                    'status' => 'offer',
                    'paths' => $paths,
                    'instruction' => 'Nothing was handed off yet. Offer it once, in one sentence, with what you can still do, and say how it would go on: paths is all that can happen, so promise nothing else. Any suggestion chips are a plain yes and no in the visitor\'s words. Call again only if the visitor accepts or stays on the same problem; if they decline or move on, carry on and do not offer again.',
                ]));
            }
        }

        $email = self::email($input['email'] ?? null);
        if (false === $email) {
            return ToolOutcome::error('That email does not look valid. Ask the visitor to check it.');
        }
        $phone = self::phone($input['phone'] ?? null);
        if (false === $phone) {
            return ToolOutcome::error('That phone number does not look valid. Ask the visitor to check it.');
        }
        $name = self::optional($context->sanitize($input['name'] ?? null, self::MAX_NAME_CHARS));

        return $this->reply($topic, $this->desk->open(new HandoffRequest(
            $topic,
            $summary,
            new HandoffContact($email, $phone, $name),
            self::optional(Scalar::string($input['path'] ?? null)),
        ), $context->session), $context);
    }

    private function offerFirst(HandoffTopic $topic, TurnState $state): bool
    {
        $offered = $state->get(self::OFFERED_KEY, []);
        $offered = \is_array($offered) ? $offered : [];
        if (\in_array($topic->id, $offered, true)) {
            return false;
        }

        $state->set(self::OFFERED_KEY, [...$offered, $topic->id]);

        return true;
    }

    private function reply(HandoffTopic $topic, HandoffOutcome $outcome, ToolContext $context): ToolOutcome
    {
        if (HandoffOutcomeStatus::NeedsContact !== $outcome->status) {
            $context->state->set(self::CARD_KEY, self::card($outcome));
        }

        return match ($outcome->status) {
            HandoffOutcomeStatus::Registered => new ToolOutcome($context->fenced(\array_filter([
                'status' => $outcome->status->value,
                'reference' => $outcome->reference,
                'next_step' => $outcome->result?->nextStep,
                'phone_on_file' => $outcome->maskedPhone,
                'contact_path' => \array_key_first($outcome->alternatives),
                'instruction' => 'Call present_handoff, which ends the turn: the card shows what comes next. If contact_path came back and the visitor asks to be contacted, or writes a phone or an email, call this tool again with that path and what they wrote; until then nobody will contact them.',
            ], static fn (mixed $v): bool => null !== $v))),
            HandoffOutcomeStatus::ChoosePath => new ToolOutcome($context->fenced(\array_filter([
                'status' => $outcome->status->value,
                'reference' => $outcome->reference,
                'paths' => $outcome->paths,
                'instruction' => 'Call present_handoff: its buttons ask how to go on, so do not describe the paths. When they choose, call this tool again with that path.',
            ], static fn (mixed $v): bool => null !== $v))),
            HandoffOutcomeStatus::NeedsContact => ToolOutcome::held(self::CONTACT_GATE, $context->fenced([
                'status' => $outcome->status->value,
                'needs' => self::needed($outcome->need),
                'paths_without_contact' => $outcome->paths,
                'instruction' => 'Nothing is registered yet. Ask for it once, with their name if they want to give it, without suggestion chips. If the visitor would rather not give it, call again with one of paths_without_contact; if there is none, tell them how to reach the team.',
            ])),
            HandoffOutcomeStatus::Unavailable => ToolOutcome::error('No handoff is available here right now. Nothing was registered: do not promise a contact. Call present_handoff, which shows how to reach the team.'),
            HandoffOutcomeStatus::Failed => ToolOutcome::error('The handoff failed and nobody was told. Do not promise a contact: apologise briefly and call present_handoff, which shows how to reach the team.'),
        };
    }

    /** @return array<string, mixed> */
    private static function card(HandoffOutcome $outcome): array
    {
        $failed = \in_array($outcome->status, [HandoffOutcomeStatus::Failed, HandoffOutcomeStatus::Unavailable], true);

        return \array_filter([
            'status' => $failed ? 'failed' : $outcome->status->value,
            'reference' => $outcome->reference,
            'path' => $outcome->path,
            'paths' => \array_keys($outcome->paths),
            'open' => $outcome->open,
            'phone_on_file' => $outcome->maskedPhone,
            'alternatives' => $outcome->alternatives,
            'self_serve' => $outcome->selfServe,
            'actions' => $outcome->result->actions ?? [],
        ], static fn (mixed $v): bool => null !== $v && [] !== $v);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        return ['opening' => PayloadGuard::optionalText($input['opening'] ?? null, self::MAX_OPENING_CHARS)];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function enrich(array $payload, EnrichmentContext $context): array
    {
        $card = $context->tools->state->get(self::CARD_KEY);
        if (!\is_array($card)) {
            throw new PresentationRefusedException(\sprintf('no case was opened in this conversation; call %s first.', self::REQUEST), self::CARD_GATE);
        }

        return \array_filter(['opening' => $payload['opening'] ?? null] + Scalar::keyed($card), static fn (mixed $v): bool => null !== $v && '' !== $v);
    }

    private static function needed(?ContactNeed $need): string
    {
        return match ($need) {
            ContactNeed::Email => 'an email',
            ContactNeed::Phone => 'a phone number',
            default => 'an email or a phone number',
        };
    }

    private static function optional(string $value): ?string
    {
        $value = \trim($value);

        return '' === $value ? null : $value;
    }

    /** The email, null when absent, false when invalid. */
    private static function email(mixed $value): string|false|null
    {
        $email = self::optional(Scalar::string($value));

        return null === $email ? null : (false === \filter_var($email, \FILTER_VALIDATE_EMAIL) ? false : $email);
    }

    /** The phone, null when absent, false when invalid. */
    private static function phone(mixed $value): string|false|null
    {
        $phone = self::optional(Scalar::string($value));
        if (null === $phone) {
            return null;
        }

        $digits = \strlen((string) \preg_replace('/\D/', '', $phone));

        return $digits >= self::MIN_PHONE_DIGITS && $digits <= self::MAX_PHONE_DIGITS ? $phone : false;
    }
}
