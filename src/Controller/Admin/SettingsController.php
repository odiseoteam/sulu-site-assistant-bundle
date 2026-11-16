<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Encryption\ApiKeyEncrypterInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\SettingsInterface;
use Odiseo\SuluSiteAssistantBundle\Repository\SettingsRepository;
use Odiseo\SuluSiteAssistantBundle\Settings\ModelChoices;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Each webspace's settings: the list of webspaces, and one webspace's settings with the texts of
 * the requested locale. An empty field keeps the configuration's value. API keys are never sent
 * back: the form shows whether one is stored and its last four characters.
 */
final class SettingsController implements SecuredControllerInterface
{
    private const MAX_CHIPS = 5;

    private const MAX_CHIP_CHARS = 80;

    /** @param array<string, mixed> $defaults the configured values the form shows as placeholders */
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Webspaces $webspaces,
        private readonly EntityManagerInterface $em,
        private readonly ApiKeyEncrypterInterface $encrypter,
        private readonly ?Security $security = null,
        private readonly array $defaults = [],
    ) {
    }

    public function cgetAction(): Response
    {
        $rows = [];
        foreach ($this->webspaces->keys() as $key) {
            $settings = $this->settings->findOneByWebspace($key);
            $rows[] = [
                'id' => $key,
                'webspaceName' => $this->webspaces->name($key),
                'enabled' => $settings?->isEnabled() ?? true,
                'assistantName' => $settings?->getAssistantName(),
                'turnModel' => null === $settings?->getTurnModel() ? null : ModelChoices::describe((string) $settings->getTurnModel()),
                'updatedAt' => $settings?->getUpdatedAt()?->format(\DATE_ATOM),
                'updatedBy' => $settings?->getUpdatedBy(),
            ];
        }

        return new JsonResponse([
            '_embedded' => [SiteAssistantAdmin::SETTINGS_KEY => $rows],
            'total' => \count($rows),
            'page' => 1,
            'pages' => 1,
            'limit' => \max(1, \count($rows)),
        ]);
    }

    public function getAction(string $id, Request $request): Response
    {
        if (!$this->webspaces->has($id)) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return self::respond($this->serialize($id, $this->settings->findOneByWebspace($id), $this->locale($request, $id)));
    }

    public function putAction(string $id, Request $request): Response
    {
        if (!$this->webspaces->has($id)) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = Scalar::keyed($request->toArray());
        $errors = self::validate($data);
        if ([] !== $errors) {
            return new JsonResponse(['code' => 'invalid_settings', 'message' => \implode(' ', $errors), 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $locale = $this->locale($request, $id);
        $settings = $this->settings->findOneByWebspace($id);
        if (null === $settings) {
            $settings = $this->settings->create($id);
            $this->em->persist($settings);
        }

        $settings->setEnabled(false !== ($data['enabled'] ?? true));
        $settings->setTurnModel(self::choice($data['turnModel'] ?? null, ModelChoices::models()));
        $settings->setTurnEffort(self::choice($data['turnEffort'] ?? null, ModelChoices::EFFORTS));
        $settings->setMemoryModel(self::choice($data['memoryModel'] ?? null, ModelChoices::models()));
        $settings->setSessionBudgetUsd(self::number($data['sessionBudgetUsd'] ?? null));
        $settings->setDailyBudgetUsd(self::number($data['dailyBudgetUsd'] ?? null));
        $settings->setMonthlyBudgetUsd(self::number($data['monthlyBudgetUsd'] ?? null));
        $settings->setBrandName(self::text($data['brandName'] ?? null));
        $settings->setAssistantName(self::text($data['assistantName'] ?? null));
        $settings->setBrandVoice(self::text($data['brandVoice'] ?? null));
        $settings->setMemoryEnabled(match ($data['memoryEnabled'] ?? null) {
            'on' => true,
            'off' => false,
            default => null,
        });
        $days = self::number($data['memoryRetentionDays'] ?? null);
        $settings->setMemoryRetentionDays(null === $days ? null : (int) $days);
        foreach (['anthropic' => 'Anthropic', 'openai' => 'Openai'] as $platform => $method) {
            $key = self::text($data[$platform . 'ApiKey'] ?? null);
            if (true === ($data[$platform . 'ApiKeyClear'] ?? false)) {
                $settings->{'set' . $method . 'ApiKey'}(null);
            } elseif (null !== $key) {
                $settings->{'set' . $method . 'ApiKey'}($key);
            }
        }
        $settings->setTexts($locale, \array_filter([
            'welcome_title' => self::text($data['welcomeTitle'] ?? null),
            'welcome_body' => self::text($data['welcomeBody'] ?? null),
            'starter_chips' => self::chips($data['starterChips'] ?? null),
        ], static fn (mixed $value): bool => null !== $value && [] !== $value));
        $settings->setUpdatedBy($this->security?->getUser()?->getUserIdentifier());
        $settings->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return self::respond($this->serialize($id, $settings, $locale));
    }

    /**
     * Budgets stay floats in the JSON (5.0, not 5).
     *
     * @param array<string, mixed> $data
     */
    private static function respond(array $data): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_PRESERVE_ZERO_FRACTION);

        return $response->setData($data);
    }

    public function getSecurityContext(): string
    {
        return SiteAssistantAdmin::SETTINGS_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    private function serialize(string $webspace, ?SettingsInterface $settings, string $locale): array
    {
        $texts = $settings?->getTexts($locale) ?? [];
        $memory = $settings?->isMemoryEnabled();

        return [
            'id' => $webspace,
            'webspaceName' => $this->webspaces->name($webspace),
            'enabled' => $settings?->isEnabled() ?? true,
            'turnModel' => $settings?->getTurnModel(),
            'turnEffort' => $settings?->getTurnEffort(),
            'memoryModel' => $settings?->getMemoryModel(),
            'anthropicApiKey' => '',
            'anthropicApiKeyStored' => $this->stored($settings?->getAnthropicApiKey()),
            'anthropicApiKeyClear' => false,
            'openaiApiKey' => '',
            'openaiApiKeyStored' => $this->stored($settings?->getOpenaiApiKey()),
            'openaiApiKeyClear' => false,
            'sessionBudgetUsd' => $settings?->getSessionBudgetUsd(),
            'dailyBudgetUsd' => $settings?->getDailyBudgetUsd(),
            'monthlyBudgetUsd' => $settings?->getMonthlyBudgetUsd(),
            'brandName' => $settings?->getBrandName(),
            'assistantName' => $settings?->getAssistantName(),
            'brandVoice' => $settings?->getBrandVoice(),
            'memoryEnabled' => null === $memory ? null : ($memory ? 'on' : 'off'),
            'memoryRetentionDays' => $settings?->getMemoryRetentionDays(),
            'welcomeTitle' => $texts['welcome_title'] ?? null,
            'welcomeBody' => $texts['welcome_body'] ?? null,
            'starterChips' => \implode("\n", $texts['starter_chips'] ?? []),
            'defaults' => $this->defaults,
            'updatedAt' => $settings?->getUpdatedAt()?->format(\DATE_ATOM),
            'updatedBy' => $settings?->getUpdatedBy(),
        ];
    }

    /** "…a1b2" for a stored key; empty when there is none or it no longer decrypts. */
    private function stored(?string $key): string
    {
        if (null === $key || '' === $key) {
            return '';
        }
        $plain = $this->encrypter->decrypt($key);

        return '' === $plain ? '' : '…' . \substr($plain, -4);
    }

    private function locale(Request $request, string $webspace): string
    {
        $locale = $request->query->get('locale');

        return \is_string($locale) && \in_array($locale, $this->webspaces->locales($webspace), true) ? $locale : $this->webspaces->defaultLocale($webspace);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function validate(array $data): array
    {
        $errors = [];
        foreach (['turnModel', 'memoryModel'] as $field) {
            $value = $data[$field] ?? null;
            if (null !== $value && '' !== $value && !\in_array($value, ModelChoices::models(), true)) {
                $errors[] = \sprintf('%s is not one of the offered models.', $field);
            }
        }
        $effort = $data['turnEffort'] ?? null;
        if (null !== $effort && '' !== $effort && !\in_array($effort, ModelChoices::EFFORTS, true)) {
            $errors[] = 'turnEffort is not one of the offered efforts.';
        }
        foreach (['sessionBudgetUsd', 'dailyBudgetUsd', 'monthlyBudgetUsd'] as $field) {
            $value = $data[$field] ?? null;
            if (null !== $value && '' !== $value && (!\is_numeric($value) || (float) $value < 0)) {
                $errors[] = \sprintf('%s must be zero or more.', $field);
            }
        }
        $days = $data['memoryRetentionDays'] ?? null;
        if (null !== $days && '' !== $days && (!\is_numeric($days) || (int) $days < 1)) {
            $errors[] = 'memoryRetentionDays must be one or more.';
        }

        return $errors;
    }

    /** @param list<string> $choices */
    private static function choice(mixed $value, array $choices): ?string
    {
        return \is_string($value) && \in_array($value, $choices, true) ? $value : null;
    }

    private static function number(mixed $value): ?float
    {
        return \is_numeric($value) ? (float) $value : null;
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== \trim($value) ? \trim($value) : null;
    }

    /** @return list<string> one chip per line */
    private static function chips(mixed $value): array
    {
        $lines = \is_string($value) ? \preg_split('/\R/', $value) ?: [] : [];
        $chips = [];
        foreach ($lines as $line) {
            $line = \trim($line);
            if ('' !== $line) {
                $chips[] = \mb_substr($line, 0, self::MAX_CHIP_CHARS);
            }
        }

        return \array_slice($chips, 0, self::MAX_CHIPS);
    }
}
