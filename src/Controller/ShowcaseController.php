<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller;

use Odiseo\SuluSiteAssistantBundle\Showcase\ShowcaseBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/** Every card of the assistant over the site's own pages, with no model call. Off unless `showcase.enabled`. */
final class ShowcaseController
{
    public function __construct(
        private readonly ShowcaseBuilder $showcase,
        private readonly Environment $twig,
        private readonly string $template,
        private readonly bool $enabled,
    ) {
    }

    public function show(): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('The showcase is off.');
        }

        $response = new Response($this->twig->render($this->template, ['showcase' => $this->showcase->build()]));
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->setPrivate();

        return $response;
    }
}
