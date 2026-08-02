use strict;
use warnings;

use FindBin;
use lib "$FindBin::Bin/../../MailScanner_perl_scripts";

use Digest::SHA qw(sha256_hex);
use File::Temp qw(tempdir);
use HTTP::Response;
use JSON qw(decode_json);
use Test::More;

use MailWatchClient;

{
    package Local::FakeUserAgent;

    sub new {
        my ($class, @responses) = @_;

        return bless {
            requests  => [],
            responses => \@responses,
        }, $class;
    }

    sub request {
        my ($self, $request) = @_;
        push @{$self->{requests}}, $request;

        die 'No fake HTTP response remains' unless @{$self->{responses}};

        return shift @{$self->{responses}};
    }
}

sub fixture {
    my $fixture_path = "$FindBin::Bin/../fixtures/api/logmail-v1.json";
    open my $fixture_file, '<', $fixture_path or die "Cannot open $fixture_path: $!";
    local $/;
    my $json = <$fixture_file>;
    close $fixture_file;

    return decode_json($json);
}

sub response {
    my ($status, $reason) = @_;

    return HTTP::Response->new($status, $reason);
}

sub client_with {
    my (%args) = @_;
    my @logs;
    my @sleeps;
    my $user_agent = Local::FakeUserAgent->new(@{$args{responses}});
    my %spool_options;
    if (defined $args{spool_directory}) {
        %spool_options = (
            api_spool_directory    => $args{spool_directory},
            api_spool_max_messages => $args{spool_max_messages} // 10,
            api_spool_replay_limit => $args{spool_replay_limit} // 10,
        );
    }

    my $client = MailWatchClient->new(
        user_agent          => $user_agent,
        api_endpoint        => 'https://mailwatch.example.test/api/logmail.php',
        api_key             => 'perl-characterisation-api-key',
        api_max_retries     => $args{max_retries} // 3,
        api_retry_delay     => $args{retry_delay} // 5,
        api_max_retry_delay => $args{max_retry_delay} // 60,
        api_logger          => sub { push @logs, [@_] },
        local_sender        => sub { die 'Local sender must not be used by API tests' },
        local_starter       => sub { die 'Local starter must not be used by API tests' },
        local_max_retries   => 1,
        local_retry_delay   => 0,
        local_logger        => sub { die 'Local logger must not be used by API tests' },
        sleeper             => sub { push @sleeps, $_[0] },
        %spool_options,
    );

    return ($client, $user_agent, \@logs, \@sleeps);
}

subtest 'a successful request is sent once with the current contract' => sub {
    my ($client, $user_agent, $logs, $sleeps) = client_with(
        responses => [response(201, 'Created')],
    );

    ok($client->send_api_message(fixture()), 'message is reported as delivered');
    is(scalar @{$user_agent->{requests}}, 1, 'one HTTP request is made');
    is_deeply($sleeps, [], 'no retry delay is used');

    my $request = $user_agent->{requests}[0];
    is($request->method, 'POST', 'uses POST');
    is($request->uri->as_string, 'https://mailwatch.example.test/api/logmail.php', 'uses the configured endpoint');
    is($request->header('Content-Type'), 'application/json', 'uses the JSON content type');
    is($request->header('X-MailWatch-API-Key'), 'perl-characterisation-api-key', 'sends the API key header');
    is(
        $request->header('Idempotency-Key'),
        sha256_hex(join "\0", 'mx.example.test', 'fixture-message-001', '0000000000000000000000000000000000000000'),
        'sends a stable idempotency key'
    );
    is_deeply(decode_json($request->content), fixture(), 'sends the complete Perl payload');
    is_deeply(
        $logs,
        [['info', 'fixture-message-001: Logged to MailWatch API']],
        'logs successful delivery'
    );
};

subtest 'a snapshot request sends the read contract and returns its ETag' => sub {
    my $snapshot = {
        contract         => 'mailwatch.allow-block-list.v1',
        snapshot_version => 'a' x 64,
        allowlist        => [],
        blocklist        => [],
    };
    my $response = response(200, 'OK');
    $response->header('ETag' => '"snapshot-a"');
    $response->content(JSON::encode_json($snapshot));
    my ($client, $user_agent) = client_with(responses => [$response]);

    my $result = $client->fetch_api_snapshot(
        'https://mailwatch.example.test/api/allow-block-list.php',
        '"snapshot-before"',
    );

    is_deeply($result->{snapshot}, $snapshot, 'the decoded snapshot is returned');
    is($result->{etag}, '"snapshot-a"', 'the response ETag is returned');
    my $request = $user_agent->{requests}[0];
    is($request->method, 'GET', 'uses GET');
    is($request->header('X-MailWatch-Contract-Version'), '1', 'requests contract version 1');
    is($request->header('If-None-Match'), '"snapshot-before"', 'sends the previous ETag');
    is($request->header('X-MailWatch-API-Key'), 'perl-characterisation-api-key', 'uses the configured API key');
};

subtest 'an unchanged snapshot is reported without decoding a body' => sub {
    my ($client) = client_with(responses => [response(304, 'Not Modified')]);

    my $result = $client->fetch_api_snapshot(
        'https://mailwatch.example.test/api/allow-block-list.php',
        '"snapshot-a"',
    );

    is_deeply(
        $result,
        {not_modified => 1, etag => '"snapshot-a"'},
        '304 retains the active snapshot and ETag',
    );
};

subtest 'invalid and oversized snapshots are rejected' => sub {
    for my $case (
        [response(200, 'OK'), 1024, 'invalid JSON'],
        [response(200, 'OK'), 4, 'oversized JSON'],
    ) {
        my ($response, $maximum, $label) = @{$case};
        $response->content('not-json');
        my ($client, undef, $logs) = client_with(responses => [$response]);
        $client->{api_snapshot_max_bytes} = $maximum;

        ok(
            !defined $client->fetch_api_snapshot('https://mailwatch.example.test/api/allow-block-list.php'),
            "$label is rejected",
        );
        is($logs->[-1][0], 'error', "$label is logged as an error");
    }
};

subtest 'a temporary server failure is retried until success' => sub {
    my ($client, $user_agent, $logs, $sleeps) = client_with(
        responses => [
            response(500, 'Internal Server Error'),
            response(201, 'Created'),
        ],
    );

    ok($client->send_api_message(fixture()), 'message succeeds on retry');
    is(scalar @{$user_agent->{requests}}, 2, 'two HTTP requests are made');
    is_deeply($sleeps, [5], 'configured retry delay is used once');
    is($logs->[0][0], 'warn', 'the first failure is logged as a warning');
    is($logs->[-1][0], 'info', 'eventual success is logged');
};

subtest 'a transport timeout response is retried' => sub {
    my ($client, $user_agent, $logs, $sleeps) = client_with(
        responses => [
            response(500, 'read timeout'),
            response(201, 'Created'),
        ],
    );

    ok($client->send_api_message(fixture()), 'message succeeds after the timeout');
    is(scalar @{$user_agent->{requests}}, 2, 'the timeout response causes a retry');
    is_deeply($sleeps, [5], 'the retry waits for the configured delay');
    like($logs->[0][1], qr/read timeout/, 'the timeout reason is logged');
};

subtest 'an authentication failure is not retried' => sub {
    my ($client, $user_agent, $logs, $sleeps) = client_with(
        responses => [response(401, 'Unauthorized')],
    );

    ok(!$client->send_api_message(fixture()), 'message is reported as undelivered');
    is(scalar @{$user_agent->{requests}}, 1, '401 stops after the first request');
    is_deeply($sleeps, [], 'the client does not wait after a permanent failure');
    is_deeply(
        $logs,
        [['error', 'fixture-message-001: MailWatch API rejected message: 401 Unauthorized; not retrying.']],
        'the permanent failure is logged as an error'
    );
};

subtest 'permanent client errors are not retried' => sub {
    for my $case (
        [400, 'Bad Request'],
        [403, 'Forbidden'],
        [404, 'Not Found'],
        [422, 'Unprocessable Content'],
    ) {
        my ($status, $reason) = @{$case};
        my ($client, $user_agent, undef, $sleeps) = client_with(
            responses => [response($status, $reason)],
        );

        ok(!$client->send_api_message(fixture()), "$status is reported as undelivered");
        is(scalar @{$user_agent->{requests}}, 1, "$status is attempted once");
        is_deeply($sleeps, [], "$status has no retry delay");
    }
};

subtest 'temporary client errors are retried' => sub {
    for my $case (
        [408, 'Request Timeout'],
        [429, 'Too Many Requests'],
    ) {
        my ($status, $reason) = @{$case};
        my ($client, $user_agent, undef, $sleeps) = client_with(
            responses => [
                response($status, $reason),
                response(201, 'Created'),
            ],
        );

        ok($client->send_api_message(fixture()), "$status succeeds on retry");
        is(scalar @{$user_agent->{requests}}, 2, "$status causes a retry");
        is_deeply($sleeps, [5], "$status uses the configured retry delay");
    }
};

subtest 'retry delay increases exponentially' => sub {
    my ($client, $user_agent, undef, $sleeps) = client_with(
        responses => [
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
            response(201, 'Created'),
        ],
        max_retries => 4,
        retry_delay => 5,
    );

    ok($client->send_api_message(fixture()), 'message eventually succeeds');
    is(scalar @{$user_agent->{requests}}, 4, 'four attempts are made');
    is_deeply($sleeps, [5, 10, 20], 'delay doubles after every failed attempt');
};

subtest 'retry delay is capped' => sub {
    my ($client, $user_agent, undef, $sleeps) = client_with(
        responses => [
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
            response(201, 'Created'),
        ],
        max_retries    => 5,
        retry_delay    => 20,
        max_retry_delay => 30,
    );

    ok($client->send_api_message(fixture()), 'message succeeds after capped delays');
    is(scalar @{$user_agent->{requests}}, 5, 'five attempts are made');
    is_deeply($sleeps, [20, 30, 30, 30], 'delay never exceeds the configured cap');
};

subtest 'a message is abandoned after all attempts fail' => sub {
    my ($client, $user_agent, $logs, $sleeps) = client_with(
        responses => [
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
        ],
        max_retries => 2,
        retry_delay => 7,
    );

    ok(!$client->send_api_message(fixture()), 'message is reported as undelivered');
    is(scalar @{$user_agent->{requests}}, 2, 'attempts stop at the configured limit');
    is_deeply($sleeps, [7], 'there is no delay after the final attempt');
    is_deeply(
        $logs->[-1],
        ['error', 'fixture-message-001: Failed to log to MailWatch API after 2 attempts.'],
        'the abandoned message is visible in the error log'
    );
};

subtest 'the API key is not included in failure logs' => sub {
    my ($client, undef, $logs) = client_with(
        responses => [response(500, 'Internal Server Error')],
        max_retries => 1,
    );

    $client->send_api_message(fixture());
    my $combined_logs = join "\n", map { join ' ', @{$_} } @{$logs};
    unlike($combined_logs, qr/perl-characterisation-api-key/, 'API key is redacted');
};

subtest 'an undelivered message is persisted and replayed' => sub {
    my $spool_directory = tempdir(CLEANUP => 1);
    my ($client, $user_agent, $logs) = client_with(
        responses => [
            response(503, 'Service Unavailable'),
            response(201, 'Created'),
        ],
        max_retries     => 1,
        spool_directory => $spool_directory,
    );

    ok(!$client->send_api_message(fixture()), 'failed delivery is reported to the caller');
    my @queued = glob "$spool_directory/*.json";
    is(scalar @queued, 1, 'one message is persisted');
    is((stat($queued[0]))[2] & 07777, 0600, 'queued message is private');
    is((stat($spool_directory))[2] & 07777, 0700, 'spool directory is private');
    like(join("\n", map { $_->[1] } @{$logs}), qr/Queued in the MailWatch API spool/, 'queueing is logged');

    ok($client->replay_api_spool(), 'queued delivery is replayed');
    is(scalar @{$user_agent->{requests}}, 2, 'replay makes a second HTTP request');
    my @remaining = glob "$spool_directory/*.json";
    is(scalar @remaining, 0, 'message is removed after successful replay');
    like(join("\n", map { $_->[1] } @{$logs}), qr/Replayed from the MailWatch API spool/, 'replay is logged');
};

subtest 'the spool message limit is enforced' => sub {
    my $spool_directory = tempdir(CLEANUP => 1);
    my ($client, undef, $logs) = client_with(
        responses => [
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
        ],
        max_retries        => 1,
        spool_directory    => $spool_directory,
        spool_max_messages => 1,
    );

    ok(!$client->send_api_message(fixture()), 'first failed message is queued');
    my $second_message = fixture();
    $second_message->{id} = 'fixture-message-002';
    $second_message->{token} = '1111111111111111111111111111111111111111';
    ok(!$client->send_api_message($second_message), 'second failed message remains undelivered');

    my @queued = glob "$spool_directory/*.json";
    is(scalar @queued, 1, 'spool does not grow past its configured limit');
    like(join("\n", map { $_->[1] } @{$logs}), qr/spool limit reached/, 'full spool is logged');
};

subtest 'spool replay is bounded per run' => sub {
    my $spool_directory = tempdir(CLEANUP => 1);
    my ($client) = client_with(
        responses => [
            response(503, 'Service Unavailable'),
            response(503, 'Service Unavailable'),
            response(201, 'Created'),
        ],
        max_retries        => 1,
        spool_directory    => $spool_directory,
        spool_replay_limit => 1,
    );

    my $first_message = fixture();
    my $second_message = fixture();
    $second_message->{id} = 'fixture-message-002';
    $second_message->{token} = '1111111111111111111111111111111111111111';
    $client->send_api_message($first_message);
    $client->send_api_message($second_message);

    my @before_replay = glob "$spool_directory/*.json";
    is(scalar @before_replay, 2, 'two messages are waiting');
    ok($client->replay_api_spool(), 'bounded replay succeeds');
    my @after_replay = glob "$spool_directory/*.json";
    is(scalar @after_replay, 1, 'only one message is replayed in this run');
};

done_testing;
