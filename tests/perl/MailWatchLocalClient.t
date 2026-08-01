use strict;
use warnings;

use FindBin;
use lib "$FindBin::Bin/../../MailScanner_perl_scripts";

use Test::More;

use MailWatchClient;

sub client_with {
    my (%args) = @_;
    my @sender_results = @{$args{sender_results}};
    my @payloads;
    my @logs;
    my @sleeps;
    my $starts = 0;

    my $client = MailWatchClient->new(
        user_agent          => bless({}, 'Local::UnusedUserAgent'),
        api_endpoint        => 'https://unused.example.test/api/logmail.php',
        api_key             => 'unused-api-key',
        api_max_retries     => 1,
        api_retry_delay     => 0,
        api_max_retry_delay => 0,
        api_logger          => sub { die 'API logger must not be used by local tests' },
        local_sender => sub {
            push @payloads, $_[0];

            return shift @sender_results;
        },
        local_starter      => sub { ++$starts },
        local_max_retries  => $args{max_retries} // 3,
        local_retry_delay  => $args{retry_delay} // 5,
        local_logger       => sub { push @logs, [@_] },
        sleeper            => sub { push @sleeps, $_[0] },
    );

    return ($client, \@payloads, \@logs, \@sleeps, \$starts);
}

subtest 'a message is sent once when the local logger is available' => sub {
    my ($client, $payloads, $logs, $sleeps, $starts) = client_with(
        sender_results => [1],
    );

    ok($client->send_local_message("encoded-message\n", 'message-001'), 'message is delivered');
    is_deeply($payloads, ["encoded-message\nEND\n"], 'the existing END delimiter is appended');
    is_deeply($sleeps, [], 'no delay is used');
    is(${$starts}, 0, 'the logging child is not restarted');
    is_deeply($logs, [['info', 'Logging message message-001 to API']], 'delivery is logged');
};

subtest 'the logging child is started after a connection failure' => sub {
    my ($client, $payloads, $logs, $sleeps, $starts) = client_with(
        sender_results => [0, 1],
    );

    ok($client->send_local_message('payload', 'message-002'), 'message succeeds on retry');
    is(scalar @{$payloads}, 2, 'the payload is attempted twice');
    is(${$starts}, 1, 'the logging child is started once');
    is_deeply($sleeps, [5], 'the configured startup delay is used');
    is($logs->[0][0], 'warn', 'the failed connection is logged');
    is($logs->[-1][0], 'info', 'eventual delivery is logged');
};

subtest 'connection attempts stop at the configured limit' => sub {
    my ($client, $payloads, $logs, $sleeps, $starts) = client_with(
        sender_results => [0, 0, 0],
        max_retries    => 3,
        retry_delay    => 2,
    );

    ok(!$client->send_local_message('payload', 'message-003'), 'message is reported as undelivered');
    is(scalar @{$payloads}, 3, 'only three connection attempts are made');
    is(${$starts}, 2, 'the child is not restarted after the final attempt');
    is_deeply($sleeps, [2, 2], 'there is no delay after the final attempt');
    is_deeply(
        $logs->[-1],
        ['error', 'Unable to deliver message message-003 to the local MailWatch logger after 3 attempts.'],
        'the bounded failure is logged explicitly'
    );
};

done_testing;
