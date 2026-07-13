<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests\Exception;

use Keboola\ApiClientBase\Exception\ClientException as BaseClientException;
use Keboola\JobQueueClient\Exception\ClientException;
use PHPUnit\Framework\TestCase;

class ClientExceptionTest extends TestCase
{
    public function testIsBaseClientException(): void
    {
        self::assertInstanceOf(BaseClientException::class, new ClientException('message'));
    }

    public function testStatusCodeAndResponseBody(): void
    {
        $exception = new ClientException('message', 400, null, 400, '{"a":1}');
        self::assertSame(400, $exception->getStatusCode());
        self::assertSame('{"a":1}', $exception->getResponseBody());
    }

    public function testGetResponseData(): void
    {
        $exception = new ClientException('message', 0, null, 200, '{"some":"data","nested":{"a":[1,2]}}');
        self::assertSame(['some' => 'data', 'nested' => ['a' => [1, 2]]], $exception->getResponseData());
    }

    public function testGetResponseDataNullWhenNoBody(): void
    {
        self::assertNull((new ClientException('message'))->getResponseData());
    }

    public function testGetResponseDataNullWhenInvalidJson(): void
    {
        $exception = new ClientException('message', 0, null, 500, 'not json');
        self::assertNull($exception->getResponseData());
    }

    public function testGetErrorCode(): void
    {
        self::assertNull((new ClientException('m', 0, null, 400, '{}'))->getErrorCode());

        $exception = new ClientException('m', 0, null, 400, '{"context":{"errorCode":"some.error"}}');
        self::assertSame('some.error', $exception->getErrorCode());
    }

    public function testGetErrorCodeNumeric(): void
    {
        $exception = new ClientException('m', 0, null, 400, '{"context":{"errorCode":123}}');
        self::assertSame('123', $exception->getErrorCode());
    }

    public function testIsErrorCode(): void
    {
        self::assertFalse((new ClientException('m', 0, null, 400, '{}'))->isErrorCode('some.error'));

        $exception = new ClientException('m', 0, null, 400, '{"context":{"errorCode":"some.error"}}');
        self::assertTrue($exception->isErrorCode('some.error'));
    }
}
