<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\SystemException;
use App\Services\SampleAccessService;
use App\Utilities\MiscUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The id format of SampleAccessService.
 *
 * In #472 the EID result page read its id with desqid() while its links still sent
 * base64, and desqid('MTIz') (the link for sample 123) is 15948: the page opened
 * another patient's sample. The format here must therefore refuse every value it
 * did not write itself, and above all the two old formats.
 */
final class SampleAccessServiceIdTest extends TestCase
{
    /**
     * encode() and decode() touch neither the database nor CommonService, which
     * are final, so the service is built without its constructor.
     */
    private function service(): SampleAccessService
    {
        return (new ReflectionClass(SampleAccessService::class))->newInstanceWithoutConstructor();
    }

    public function testAnIdRoundTrips(): void
    {
        $service = $this->service();
        foreach ([1, 123, 15948, 2147483647] as $id) {
            self::assertSame($id, $service->decode($service->encode($id)));
        }
    }

    public function testAListRoundTrips(): void
    {
        $service = $this->service();
        self::assertSame([5, 3, 99], $service->decodeList($service->encode(5, 3, 99)));
    }

    public function testDecodeRefusesAList(): void
    {
        $service = $this->service();
        self::assertNull($service->decode($service->encode(5, 3)));
    }

    /** @return iterable<string, array{string}> */
    public static function oldLinks(): iterable
    {
        yield 'base64 of 123' => [base64_encode('123')];
        yield 'base64 of 45678' => [base64_encode('45678')];
        yield 'sqid of 123' => [MiscUtility::sqid(123)];
        yield 'plain number' => ['123'];
    }

    #[DataProvider('oldLinks')]
    public function testAnOldLinkOpensNothing(string $value): void
    {
        self::assertNull($this->service()->decode($value));
    }

    /** @return iterable<string, array{?string}> */
    public static function notValid(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'prefix only' => ['s.'];
        yield 'garbage after the prefix' => ['s.!!!'];
        yield 'prefix in another case' => ['S.' . substr(self::encoded(123), 2)];
    }

    #[DataProvider('notValid')]
    public function testAValueItDidNotWriteOpensNothing(?string $value): void
    {
        self::assertNull($this->service()->decode($value));
        self::assertSame([], $this->service()->decodeList($value));
    }

    public function testAHandEditedValueOpensNothing(): void
    {
        $value = self::encoded(123);
        // Change one character: either another id's encoding or nothing, but
        // never accepted unless it is exactly what encode() writes for that id.
        $edited = substr($value, 0, -1) . (str_ends_with($value, 'a') ? 'b' : 'a');
        $id = $this->service()->decode($edited);
        if ($id !== null) {
            self::assertSame($edited, $this->service()->encode($id));
        }
        self::assertNotSame(123, $id);
    }

    public function testTheLinkCarriesTheEncodedId(): void
    {
        $service = $this->service();
        $url = $service->url('/eid/results/eid-update-result.php', 123);
        self::assertStringStartsWith('/eid/results/eid-update-result.php?id=', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame(123, $service->decode($query['id']));
    }

    public function testEncodeRefusesAnIdThatIsNotPositive(): void
    {
        $this->expectException(SystemException::class);
        $this->service()->encode(0);
    }

    private static function encoded(int $id): string
    {
        return (new ReflectionClass(SampleAccessService::class))->newInstanceWithoutConstructor()->encode($id);
    }
}
