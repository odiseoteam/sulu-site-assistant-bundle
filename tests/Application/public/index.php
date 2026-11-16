<?php

declare(strict_types=1);

use Odiseo\SuluSiteAssistantBundle\Tests\Application\Kernel;
use Sulu\Component\HttpKernel\SuluKernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(\dirname(__DIR__) . '/.env');

$request = Request::createFromGlobals();
$context = \str_starts_with($request->getPathInfo(), '/admin') ? SuluKernel::CONTEXT_ADMIN : SuluKernel::CONTEXT_WEBSITE;
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', true, $context);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
