<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuarantineReportFilterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../mailscanner/quarantine_report.inc.php';
    }

    /**
     * @dataProvider recipients
     *
     * @param string $filter
     * @param string $userType
     * @param string $toAddress
     * @param bool   $expected
     */
    public function testFilterMatchesWholeAddressesAndDomains($filter, $userType, $toAddress, $expected)
    {
        $regex = '/' . \Quarantine_Report::recipient_regex($filter, $userType) . '/i';

        $this->assertSame($expected, 1 === preg_match($regex, $toAddress));
    }

    public function recipients()
    {
        return [
            'domain' => ['mail.it', 'D', 'bob@mail.it', true],
            'domain of a later recipient' => ['mail.it', 'D', 'x@other.tld,bob@mail.it,y@other.tld', true],
            // the report of mail.it listed the quarantine of gmail.it
            'longer domain' => ['mail.it', 'D', 'bob@gmail.it', false],
            'subdomain' => ['mail.it', 'D', 'bob@sub.mail.it', false],
            'domain as prefix' => ['mail.it', 'D', 'bob@mail.item.tld', false],
            'dot is literal' => ['mail.it', 'D', 'bob@mailxit', false],
            'address of a domain admin' => ['bob@mail.it', 'D', 'x@other.tld,bob@mail.it', true],
            'address' => ['bob@mail.it', 'U', 'bob@mail.it', true],
            'longer address' => ['bob@mail.it', 'U', 'jimbob@mail.it', false],
            'username without domain' => ['bob', 'U', 'bob', true],
            'username inside an address' => ['bob', 'U', 'bobby@mail.it', false],
        ];
    }
}
