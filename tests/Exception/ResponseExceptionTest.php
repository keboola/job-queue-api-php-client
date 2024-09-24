<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests\Exception;

use Keboola\JobQueueClient\Exception\ResponseException;
use PHPUnit\Framework\TestCase;

class ResponseExceptionTest extends TestCase
{
    public function testGetRequestData(): void
    {
        $responseData = [
            'some' => 'data',
            'nested' => [
                'a' => [1, 2],
                'b' => true,
            ],
        ];

        $exception = new ResponseException('message', 0, $responseData);

        self::assertSame($responseData, $exception->getResponseData());
    }

    public function testGetErrorCode(): void
    {
        $exception = new ResponseException('message', 0, []);
        self::assertNull($exception->getErrorCode());

        $exception = new ResponseException('message', 0, [
            'context' => [
                'errorCode' => 'some.error',
            ],
        ]);
        self::assertSame('some.error', $exception->getErrorCode());
    }

    public function testGetErrorCodeNumeric(): void
    {
        $exception = new ResponseException('message', 0, []);
        self::assertNull($exception->getErrorCode());

        $exception = new ResponseException('message', 0, [
            'context' => [
                'errorCode' => 123,
            ],
        ]);
        self::assertSame('123', $exception->getErrorCode());
    }

    public function testIsErrorCode(): void
    {
        $exception = new ResponseException('message', 0, []);
        self::assertFalse($exception->isErrorCode('some.error'));

        $exception = new ResponseException('message', 0, [
            'context' => [
                'errorCode' => 'some.error',
            ],
        ]);
        self::assertTrue($exception->isErrorCode('some.error'));
    }
}
