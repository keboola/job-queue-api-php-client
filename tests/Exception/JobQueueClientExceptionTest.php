<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests\Exception;

use Keboola\ApiClientBase\Exception\ClientException as BaseClientException;
use Keboola\JobQueueClient\Exception\JobQueueClientException;
use PHPUnit\Framework\TestCase;

class JobQueueClientExceptionTest extends TestCase
{
    public function testIsBaseClientException(): void
    {
        self::assertInstanceOf(BaseClientException::class, new JobQueueClientException('message'));
    }

    public function testStatusCodeAndResponseBody(): void
    {
        $exception = new JobQueueClientException('message', 400, null, 400, '{"a":1}');
        self::assertSame(400, $exception->getStatusCode());
        self::assertSame('{"a":1}', $exception->getResponseBody());
    }

    public function testGetResponseData(): void
    {
        $exception = new JobQueueClientException('message', 0, null, 200, '{"some":"data","nested":{"a":[1,2]}}');
        self::assertSame(['some' => 'data', 'nested' => ['a' => [1, 2]]], $exception->getResponseData());
    }

    public function testGetResponseDataNullWhenNoBody(): void
    {
        self::assertNull((new JobQueueClientException('message'))->getResponseData());
    }

    public function testGetResponseDataNullWhenInvalidJson(): void
    {
        $exception = new JobQueueClientException('message', 0, null, 500, 'not json');
        self::assertNull($exception->getResponseData());
    }

    public function testGetErrorCode(): void
    {
        self::assertNull((new JobQueueClientException('m', 0, null, 400, '{}'))->getErrorCode());

        $exception = new JobQueueClientException('m', 0, null, 400, '{"context":{"errorCode":"some.error"}}');
        self::assertSame('some.error', $exception->getErrorCode());
    }

    public function testGetErrorCodeNumeric(): void
    {
        $exception = new JobQueueClientException('m', 0, null, 400, '{"context":{"errorCode":123}}');
        self::assertSame('123', $exception->getErrorCode());
    }

    public function testGetErrorCodeNullWhenNoBody(): void
    {
        self::assertNull((new JobQueueClientException('m'))->getErrorCode());
    }

    public function testGetErrorCodeNullWhenContextNotArray(): void
    {
        $exception = new JobQueueClientException('m', 0, null, 400, '{"context":"oops"}');
        self::assertNull($exception->getErrorCode());
        self::assertFalse($exception->isErrorCode('x'));
    }

    public function testGetErrorCodeNullWhenErrorCodeIsArray(): void
    {
        $exception = new JobQueueClientException('m', 0, null, 400, '{"context":{"errorCode":[1,2]}}');
        self::assertNull($exception->getErrorCode());
    }

    public function testGetErrorCodeNullWhenErrorCodeIsObject(): void
    {
        $exception = new JobQueueClientException('m', 0, null, 400, '{"context":{"errorCode":{"x":1}}}');
        self::assertNull($exception->getErrorCode());
    }

    public function testIsErrorCode(): void
    {
        self::assertFalse((new JobQueueClientException('m', 0, null, 400, '{}'))->isErrorCode('some.error'));

        $exception = new JobQueueClientException('m', 0, null, 400, '{"context":{"errorCode":"some.error"}}');
        self::assertTrue($exception->isErrorCode('some.error'));
    }
}
