<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__) . '/.env', 'dev', []);
}

$requiredEnvs = ['public_queue_api_url', 'test_storage_api_token', 'storage_api_url'];
foreach ($requiredEnvs as $env) {
    if (empty(getenv($env))) {
        throw new Exception(sprintf('Environment variable "%s" is empty', $env));
    }
}
