<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use JsonException;
use Keboola\ApiClientBase\ApiClient;
use Keboola\ApiClientBase\ApiClientOptions;
use Keboola\ApiClientBase\Auth\StorageApiTokenAuthenticator;
use Keboola\ApiClientBase\Json;
use Keboola\JobQueueClient\DTO\Job;
use Keboola\JobQueueClient\DTO\ProjectStats;
use Keboola\JobQueueClient\Exception\JobQueueClientException;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Webmozart\Assert\Assert;

class JobQueueClient
{
    private const DEFAULT_USER_AGENT = 'Job Queue PHP Client';
    private const DEFAULT_BACKOFF_MAX_TRIES = 3;
    private const MAX_WAIT_DELAY_SECONDS = 10;

    private readonly ApiClient $apiClient;

    /**
     * @param non-empty-string $publicApiUrl
     * @param non-empty-string $storageToken
     * @param int<0, max> $backoffMaxTries
     */
    public function __construct(
        string $publicApiUrl,
        #[SensitiveParameter] string $storageToken,
        ?LoggerInterface $logger = null,
        int $backoffMaxTries = self::DEFAULT_BACKOFF_MAX_TRIES,
        int $connectTimeout = ApiClientOptions::DEFAULT_CONNECT_TIMEOUT,
        int $requestTimeout = ApiClientOptions::DEFAULT_REQUEST_TIMEOUT,
        ?string $userAgent = null,
        null|Closure|HandlerStack $requestHandler = null,
    ) {
        Assert::stringNotEmpty($publicApiUrl, 'Public API URL must be a non-empty string.');
        Assert::stringNotEmpty($storageToken, 'Storage API token must be a non-empty string.');

        $this->apiClient = new ApiClient(
            $publicApiUrl,
            new StorageApiTokenAuthenticator($storageToken),
            new ApiClientOptions(
                userAgent: $userAgent ?? self::DEFAULT_USER_AGENT,
                backoffMaxTries: $backoffMaxTries,
                connectTimeout: $connectTimeout,
                requestTimeout: $requestTimeout,
                requestHandler: $requestHandler,
                logger: $logger,
            ),
            exceptionClass: JobQueueClientException::class,
        );
    }

    public function createJob(JobData $jobData): Job
    {
        $jobDataArray = $jobData->getArray();
        try {
            $body = Json::encodeArray($jobDataArray);
        } catch (JsonException $e) {
            throw new JobQueueClientException('Invalid job data: ' . $e->getMessage(), $e->getCode(), $e);
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($jobDataArray['parentRunId'] !== null) {
            $headers['X-KBC-RunId'] = (string) $jobDataArray['parentRunId'];
        }

        return $this->apiClient->sendRequestAndMapResponse(
            new Request('POST', 'jobs', $headers, $body),
            Job::class,
        );
    }

    public function getJob(string $jobId): Job
    {
        return $this->apiClient->sendRequestAndMapResponse(
            new Request('GET', sprintf('jobs/%s', $jobId)),
            Job::class,
        );
    }

    /**
     * @return list<Job>
     */
    public function listJobs(ListJobsOptions $listOptions): array
    {
        return $this->apiClient->sendRequestAndMapResponse(
            new Request('GET', 'search/jobs?' . http_build_query($listOptions->getQueryParameters())),
            Job::class,
            isList: true,
        );
    }

    public function terminateJob(string $jobId): Job
    {
        return $this->apiClient->sendRequestAndMapResponse(
            new Request('POST', sprintf('jobs/%s/kill', $jobId)),
            Job::class,
        );
    }

    public function getJobsDurationSum(): int
    {
        return $this->apiClient->sendRequestAndMapResponse(
            new Request('GET', 'stats/project'),
            ProjectStats::class,
        )->jobsDurationSum;
    }

    /**
     * @return array<mixed>
     */
    public function getJobLineage(string $jobId): array
    {
        return $this->apiClient->sendRequestAndMapResponse(
            new Request('GET', sprintf('job/%s/open-api-lineage', $jobId)),
            ArrayResponse::class,
        )->data;
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
