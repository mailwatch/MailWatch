<?php

namespace App\Tests\Tickets;

use PHPUnit\Framework\TestCase;

class Ticket1290Test extends TestCase
{
    private $eximPattern = '/^([-=+.\w]+@[-.\w]+)$/';
    private $sendmailPattern = '/^R.+<(.+)>$/';

    /**
     * @dataProvider provideValidEmailsForEximQueue
     */
    public function testValidEmailsForEximQueue($email)
    {
        $this->assertMatchesRegularExpression(
            $this->eximPattern,
            $email,
            "The email '{$email}' should be considered valid."
        );
    }

    /**
     * @dataProvider provideValidEmailsForSendmailQueue
     */
    public function testSendmailQueuePattern($line, $expectedEmail)
    {
        preg_match($this->sendmailPattern, $line, $matches);
        $this->assertNotEmpty($matches, "The pattern should match the line: $line");
        $this->assertEquals($expectedEmail, $matches[1], "The extracted email should be $expectedEmail");
    }

    public function provideValidEmailsForEximQueue()
    {
        return [
            ['test@example.com'],
            ['user.name@example.com'],
            ['user-name@example.com'],
            ['user_name@example.com'],
            ['user+name@example.com'],
            ['user=name@example.com'],
            ['user2name@example.com'],
            ['SRS0+HHH=HH=example.com=test@example.com'],
            ['SRS1=HH=example.com=test@example.com'],
            ['SRS0==HH=example.com=test@example.com'],
            ['SRS1+=HH=example.com=test@example.com'],
            ['SRS0+test=example.com=test@example.com'],
            ['SRS1=test=example.com=test@example.com'],
            ['SRS1=HHH=example.com==HHH=TT=example.org=alice@example.net'],
            ['SRS0=HHH=TT=example.org=alice@example.com'],
            ['bounces+srs=ikblq=fq@example.com'],
        ];
    }

    public function provideValidEmailsForSendmailQueue()
    {
        $eximEmails = $this->provideValidEmailsForEximQueue();
        $sendmailEmails = [];
        $flags = ['', 'N', 'S', 'F', 'D', 'P', 'A', 'B']; // Are there other sendmail mqueue/qf* flags?

        foreach ($eximEmails as $emailArray) {
            $email = $emailArray[0];
            foreach ($flags as $flag) {
                // Create an entry for each flag combination with the email.
                $sendmailEmails[] = ["R{$flag}:<{$email}>", $email];
            }
        }

        return $sendmailEmails;
    }
}
