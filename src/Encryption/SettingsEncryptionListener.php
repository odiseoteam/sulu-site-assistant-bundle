<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Encryption;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Odiseo\SuluSiteAssistantBundle\Entity\SettingsInterface;

/**
 * Encrypts the API keys of the settings being saved. Only encrypts: SettingsApiKeys decrypts a
 * key when a call needs it, so the entity never holds one in clear between flushes.
 */
final class SettingsEncryptionListener
{
    public function __construct(private readonly ApiKeyEncrypterInterface $encrypter)
    {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $unitOfWork = $em->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $entity) {
            if (!$entity instanceof SettingsInterface) {
                continue;
            }

            $changed = false;
            foreach (['Anthropic', 'Openai'] as $platform) {
                $key = $entity->{'get' . $platform . 'ApiKey'}();
                if (\is_string($key) && '' !== $key) {
                    $encrypted = $this->encrypter->encrypt($key);
                    if ($encrypted !== $key) {
                        $entity->{'set' . $platform . 'ApiKey'}($encrypted);
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $unitOfWork->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
            }
        }
    }
}
