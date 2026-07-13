<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Exception;

use JsonException;
use Keboola\ApiClientBase\Exception\ClientException as BaseClientException;
use Keboola\ApiClientBase\Json;

class JobQueueClientException extends BaseClientException
{
    public function getResponseData(): ?array
    {
        $body = $this->getResponseBody();
        if ($body === null) {
            return null;
        }

        try {
            return Json::decodeArray($body);
        } catch (JsonException) {
            return null;
        }
    }

    public function getErrorCode(): ?string
    {
        $responseData = $this->getResponseData();
        if ($responseData === null) {
            return null;
        }

        $context = $responseData['context'] ?? null;
        if (!is_array($context)) {
            return null;
        }

        $errorCode = $context['errorCode'] ?? null;
        if (!is_scalar($errorCode)) {
            return null;
        }

        return (string) $errorCode;
    }

    public function isErrorCode(string $errorCode): bool
    {
        return $this->getErrorCode() === $errorCode;
    }
}
