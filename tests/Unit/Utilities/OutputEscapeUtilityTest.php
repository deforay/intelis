<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\OutputEscapeUtility;
use PHPUnit\Framework\TestCase;

/**
 * The escaping pages use to print a request value where it lands.
 */
final class OutputEscapeUtilityTest extends TestCase
{
    public function testAQuoteCannotCloseTheAttributeARequestValueIsPrintedIn(): void
    {
        $this->assertSame(
            '12&quot; onmouseover=&quot;alert(1)',
            OutputEscapeUtility::requestValue('12" onmouseover="alert(1)')
        );
        $this->assertSame('&#039;&gt;&lt;svg&gt;', OutputEscapeUtility::requestValue("'><svg>"));
    }

    public function testASanitizedRequestValueRendersAsBefore(): void
    {
        // The input sanitizer has already turned & into &amp;; encoding it again
        // would show "A&amp;B" where the page has always shown "A&B".
        $this->assertSame('A&amp;B', OutputEscapeUtility::requestValue('A&amp;B'));
        $this->assertSame('A&amp;B', OutputEscapeUtility::requestValue('A&B'));
        $range = '15-Sep-2026 to 16-Sep-2026';
        $this->assertSame($range, OutputEscapeUtility::requestValue($range));
    }

    public function testHtmlEncodesEveryAmpersand(): void
    {
        $this->assertSame('A&amp;amp;B &quot;x&quot;', OutputEscapeUtility::html('A&amp;B "x"'));
    }

    public function testMissingValuesPrintNothing(): void
    {
        $this->assertSame('', OutputEscapeUtility::requestValue(null));
        $this->assertSame('', OutputEscapeUtility::html(null));
        $this->assertSame('""', OutputEscapeUtility::js(null));
    }

    public function testAJavaScriptLiteralCannotEndTheScriptBlock(): void
    {
        $literal = OutputEscapeUtility::js('</script><script>alert(1)</script>');

        $this->assertStringNotContainsString('<', $literal);
        $this->assertSame('</script><script>alert(1)</script>', json_decode($literal));
        $this->assertSame('""', OutputEscapeUtility::js("\xB1\x31"));
    }

    public function testAnInlineHandlerArgumentStaysOneJavaScriptString(): void
    {
        $payload = "1');alert(1);//";
        $attribute = OutputEscapeUtility::jsInAttribute($payload);

        // What the browser hands the JS engine after decoding the attribute.
        $decoded = html_entity_decode($attribute, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame($payload, json_decode($decoded));
        $this->assertStringNotContainsString('"', $attribute);
        $this->assertStringNotContainsString("'", $attribute);
    }
}
