<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\InnValidation\Infrastructure\DaData\DadataInnValidator;
use App\Modules\InnValidation\Infrastructure\DaData\DadataHttpClient;
use App\Shared\Clock\Clock;
use App\Modules\InnValidation\Port\ValidationCache;
use App\Modules\InnValidation\Infrastructure\DaData\TtlValidationCache;
use App\Shared\Error\ApiException;
use App\Shared\Error\ValidationException;
use App\Shared\Infrastructure\Http\HttpResponse;
use App\Shared\Infrastructure\Http\HttpTransport;
use App\Shared\Infrastructure\Http\HttpTransportException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class InnValidationTest extends TestCase
{
    public function testInnValidationCacheExpiresEntriesAfterTtl(): void
    {
        $clock = new MutableClock(100);
        $cache = new TtlValidationCache($clock);

        $cache->put('7701234567', true, 10);
        self::assertTrue($cache->get('7701234567'));

        $clock->current = 110;
        self::assertNull($cache->get('7701234567'));
    }

    public function testValidInnIsFetchedFromDadataAndCached(): void
    {
        $transport = new FakeHttpTransport(new HttpResponse(200, '{"suggestions":[{"data":{"inn":"7701234567"}}]}'));
        $cache = new FakeInnValidationCache();
        $strategy = new DadataInnValidator(
            new DadataHttpClient($transport, 'https://example.test/suggest', 'token'),
            $cache,
        );

        $strategy->validate('7701234567');
        $strategy->validate('7701234567');

        self::assertSame(1, $transport->calls);
        self::assertSame('https://example.test/suggest', $transport->url);
        self::assertSame('Token token', $transport->headers['Authorization']);
        self::assertSame('{"query":"7701234567"}', $transport->body);
        self::assertSame(3, $transport->timeoutSeconds);
        self::assertSame(['7701234567' => true], $cache->values);
    }

    public function testUnknownInnIsReturnedAsValidationErrorAndCached(): void
    {
        $transport = new FakeHttpTransport(new HttpResponse(200, '{"suggestions":[]}'));
        $cache = new FakeInnValidationCache();
        $strategy = new DadataInnValidator(
            new DadataHttpClient($transport, 'https://example.test/suggest', 'token'),
            $cache,
        );

        try {
            $strategy->validate('7701234567');
            self::fail('Expected a validation exception');
        } catch (ValidationException $exception) {
            self::assertSame('validation_error', $exception->codeName());
            self::assertSame('INN was not found in DaData', $exception->details()['inn']);
        }

        try {
            $strategy->validate('7701234567');
            self::fail('Expected a cached validation exception');
        } catch (ValidationException) {
            // The negative result must be served from the cache as well.
        }

        self::assertSame(1, $transport->calls);
        self::assertSame(['7701234567' => false], $cache->values);
    }

    #[DataProvider('unavailableResponses')]
    public function testDadataFailureIsExposedAsControlledApiError(int $status, string $code, int $expectedStatus): void
    {
        $strategy = new DadataInnValidator(
            new DadataHttpClient(
                new FakeHttpTransport(new HttpResponse($status, '{}')),
                'https://example.test/suggest',
                'token',
            ),
            new FakeInnValidationCache(),
        );

        try {
            $strategy->validate('7701234567');
            self::fail('Expected an API exception');
        } catch (ApiException $exception) {
            self::assertSame($code, $exception->codeName());
            self::assertSame($expectedStatus, $exception->status());
        }
    }

    #[DataProvider('transportFailures')]
    public function testTransportFailureIsExposedAsControlledApiError(bool $timedOut, string $code, int $expectedStatus): void
    {
        $strategy = new DadataInnValidator(
            new DadataHttpClient(
                new ThrowingHttpTransport($timedOut),
                'https://example.test/suggest',
                'token',
            ),
            new FakeInnValidationCache(),
        );

        try {
            $strategy->validate('7701234567');
            self::fail('Expected an API exception');
        } catch (ApiException $exception) {
            self::assertSame($code, $exception->codeName());
            self::assertSame($expectedStatus, $exception->status());
        }
    }

    /** @return iterable<string, array{int, string, int}> */
    public static function unavailableResponses(): iterable
    {
        yield 'timeout' => [504, 'inn_validation_timeout', 504];
        yield 'unavailable' => [503, 'inn_validation_unavailable', 503];
    }

    /** @return iterable<string, array{bool, string, int}> */
    public static function transportFailures(): iterable
    {
        yield 'timeout' => [true, 'inn_validation_timeout', 504];
        yield 'unavailable' => [false, 'inn_validation_unavailable', 503];
    }
}

final class FakeHttpTransport implements HttpTransport
{
    public int $calls = 0;
    public string $url = '';
    /** @var array<string, string> */
    public array $headers = [];
    public string $body = '';
    public int $timeoutSeconds = 0;

    public function __construct(private readonly HttpResponse $response)
    {
    }

    public function post(string $url, array $headers, string $body, int $timeoutSeconds): HttpResponse
    {
        $this->calls++;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
        $this->timeoutSeconds = $timeoutSeconds;

        return $this->response;
    }
}

final class FakeInnValidationCache implements ValidationCache
{
    /** @var array<string, bool> */
    public array $values = [];

    public function get(string $inn): ?bool
    {
        return $this->values[$inn] ?? null;
    }

    public function put(string $inn, bool $isValid, int $ttlSeconds): void
    {
        $this->values[$inn] = $isValid;
    }
}

final class ThrowingHttpTransport implements HttpTransport
{
    public function __construct(private readonly bool $timedOut)
    {
    }

    public function post(string $url, array $headers, string $body, int $timeoutSeconds): HttpResponse
    {
        throw new HttpTransportException('DaData transport failed', $this->timedOut);
    }
}

final class MutableClock implements Clock
{
    public function __construct(public int $current)
    {
    }

    public function now(): int
    {
        return $this->current;
    }
}
