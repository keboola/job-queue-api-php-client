<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use JsonException;
use Keboola\JobQueueClient\DTO\Job;
use Keboola\JobQueueClient\Exception\ClientException as JobClientException;
use Keboola\JobQueueClient\Exception\ResponseException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Throwable;

class Client
{
    private const DEFAULT_USER_AGENT = 'Job Queue PHP Client';
    private const DEFAULT_BACKOFF_RETRIES = 3;
    private const GUZZLE_CONNECT_TIMEOUT_SECONDS = 10;
    private const GUZZLE_TIMEOUT_SECONDS = 120;
    private const MAX_WAIT_DELAY_SECONDS = 10;
    protected GuzzleClient $guzzle;

    /**
     * @param array{
     *     backoffMaxTries?: int,
     *     userAgent?: string,
     *     handler?: HandlerStack,
     *     logger?: LoggerInterface,
     * } $options
     */
    public function __construct(
        string $publicApiUrl,
        string $storageToken,
        array $options = [],
    ) {
        $validator = Validation::createValidator();
        $errors = $validator->validate($publicApiUrl, [new Url()]);
        $errors->addAll(
            $validator->validate($storageToken, [new NotBlank()]),
        );

        // @phpstan-ignore-next-line
        if (!isset($options['backoffMaxTries']) || $options['backoffMaxTries'] === '') {
            $options['backoffMaxTries'] = self::DEFAULT_BACKOFF_RETRIES;
        }

        $errors->addAll($validator->validate($options['backoffMaxTries'], [new Range(['min' => 0, 'max' => 100])]));
        $options['backoffMaxTries'] = (int) $options['backoffMaxTries'];

        if (empty($options['userAgent'])) {
            $options['userAgent'] = self::DEFAULT_USER_AGENT;
        }
        if ($errors->count() !== 0) {
            $messages = '';
            /** @var ConstraintViolationInterface $error */
            foreach ($errors as $error) {
                $messages .= 'Value "' . $error->getInvalidValue() . '" is invalid: ' . $error->getMessage() . "\n";
            }
            throw new JobClientException('Invalid parameters when creating client: ' . $messages);
        }
        $this->guzzle = $this->initClient($publicApiUrl, $storageToken, $options);
    }

    public function createJob(JobData $jobData): Job
    {
        try {
            $jobDataJson = json_encode($jobData->getArray(), JSON_THROW_ON_ERROR);
            $request = new Request('POST', 'jobs', [], $jobDataJson);
        } catch (JsonException $e) {
            throw new JobClientException('Invalid job data: ' . $e->getMessage(), $e->getCode(), $e);
        }
        $result = $this->sendRequest($request);
        return $this->mapJobFromResponse($result);
    }

    public function getJob(string $jobId): Job
    {
        $request = new Request('GET', sprintf('jobs/%s', $jobId));
        $result = $this->sendRequest($request);
        return $this->mapJobFromResponse($result);
    }

    public function listJobs(ListJobsOptions $listOptions): array
    {
        $request = new Request(
            'GET',
            'jobs?' . http_build_query($listOptions->getQueryParameters()),
        );
        $result = $this->sendRequest($request);
        return $this->mapJobsFromResponse($result);
    }

    public function terminateJob(string $jobId): Job
    {
        $result = $this->sendRequest(new Request('POST', sprintf('jobs/%s/kill', $jobId)));
        return $this->mapJobFromResponse($result);
    }

    public function getJobsDurationSum(): int
    {
        $request = new Request('GET', 'stats/project');
        $response = $this->sendRequest($request);
        return $response['jobs']['durationSum'];
    }

    public function getJobLineage(string $jobId): array
    {
        $request = new Request('GET', sprintf('job/%s/open-api-lineage', $jobId));
        return $this->sendRequest($request);
    }

    /**
     * @return array<Job>
     */
    private function mapJobsFromResponse(array $responseBody): array
    {
        return array_map([$this, 'mapJobFromResponse'], $responseBody);
    }

    private function mapJobFromResponse(array $response): Job
    {
        try {
            return Job::fromApiResponse($response);
        } catch (Throwable $e) {
            throw new ResponseException('Failed to parse Job data: ' . $e->getMessage(), $e->getCode(), $response, $e);
        }
    }

    private function createDefaultDecider(int $maxRetries): Closure
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?Throwable $error = null,
        ) use ($maxRetries) {
            if ($retries >= $maxRetries) {
                return false;
            } elseif ($response && $response->getStatusCode() >= 500) {
                return true;
            } elseif ($error && $error->getCode() >= 500) {
                return true;
            } elseif ($error &&
                (is_a($error, RequestException::class) || is_a($error, ConnectException::class)) &&
                isset($error->getHandlerContext()['errno']) &&
                in_array($error->getHandlerContext()['errno'], [CURLE_RECV_ERROR, CURLE_SEND_ERROR])
            ) {
                return true;
            } else {
                return false;
            }
        };
    }

    private function initClient(string $url, string $token, array $options = []): GuzzleClient
    {
        // Initialize handlers (start with those supplied in constructor)
        $handlerStack = $options['handler'] ?? HandlerStack::create();

        // Set exponential backoff
        $handlerStack->push(Middleware::retry($this->createDefaultDecider($options['backoffMaxTries'])));
        // Set handler to set default headers
        $handlerStack->push(Middleware::mapRequest(
            function (RequestInterface $request) use ($token, $options) {
                return $request
                    ->withHeader('User-Agent', $options['userAgent'])
                    ->withHeader('X-StorageApi-Token', $token)
                    ->withHeader('Content-type', 'application/json');
            },
        ));
        // Set client logger
        if (isset($options['logger']) && $options['logger'] instanceof LoggerInterface) {
            $handlerStack->push(Middleware::log(
                $options['logger'],
                new MessageFormatter(
                    '{hostname} {req_header_User-Agent} - [{ts}] "{method} {resource} {protocol}/{version}"' .
                    ' {code} {res_header_Content-Length}',
                ),
            ));
        }
        // finally create the instance
        return new GuzzleClient([
            'base_uri' => $url,
            'handler' => $handlerStack,
            'connect_timeout' => self::GUZZLE_CONNECT_TIMEOUT_SECONDS,
            'timeout' => self::GUZZLE_TIMEOUT_SECONDS,
        ]);
    }

    private function sendRequest(Request $request): array
    {
        $exception = null;
        try {
            $response = $this->guzzle->send($request);
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
        } catch (GuzzleException $exception) {
            $response = null;
        }

        $data = null;
        if ($response !== null) {
            try {
                $data = (array) json_decode(
                    $response->getBody()->getContents(),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
            } catch (JsonException $e) {
                throw new JobClientException(
                    'Unable to parse response body into JSON: ' . $e->getMessage(),
                    0,
                    $exception,
                );
            }
        }

        if ($exception === null) {
            return $data ?? [];
        }

        if ($exception instanceof ClientException) {
            throw new ResponseException($exception->getMessage(), $exception->getCode(), $data, $exception);
        }

        throw new JobClientException($exception->getMessage(), $exception->getCode(), $exception);
    }

    public function waitForJobCompletion(string $jobId): Job
    {
        $maxDelay = self::MAX_WAIT_DELAY_SECONDS;

        $finished = false;
        $attempt = 0;
        do {
            $job = $this->getJob($jobId);
            if ($job->isFinished) {
                $finished = true;
            }
            $attempt++;
            sleep(min(pow(2, $attempt), $maxDelay));
        } while (!$finished);
        return $job;
    }
}
