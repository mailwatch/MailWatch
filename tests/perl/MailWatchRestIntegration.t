use strict;
use warnings;

use Digest::SHA qw(sha256_hex);
use File::Copy qw(copy);
use File::Path qw(make_path);
use File::Spec;
use File::Temp qw(tempdir);
use FindBin;
use IO::Socket::INET;
use JSON qw(decode_json encode_json);
use POSIX qw(WNOHANG);
use Test::More;

use lib "$FindBin::Bin/../../MailScanner_perl_scripts";
use MailWatchClient;
use LWP::UserAgent;

my $project_root = File::Spec->rel2abs("$FindBin::Bin/../..");
my $temporary_directory = tempdir('mailwatch-rest-integration-XXXXXXXX', TMPDIR => 1, CLEANUP => 1);
my $mailwatch_directory = File::Spec->catdir($temporary_directory, 'mailscanner');
my $vendor_directory = File::Spec->catdir($temporary_directory, 'vendor');
my $public_directory = File::Spec->catdir($temporary_directory, 'public_html');
my $fixture_path = File::Spec->catfile($temporary_directory, 'fixture.json');
my $capture_path = File::Spec->catfile($temporary_directory, 'insert.json');
my $server_log_path = File::Spec->catfile($temporary_directory, 'server.log');
my $api_key = 'perl-php-integration-api-key';
my $server_pid;

END {
    my $exit_status = $?;
    if (defined $server_pid && $server_pid > 0) {
        kill 'TERM', $server_pid;
        waitpid $server_pid, 0;
    }
    $? = $exit_status;
}

make_path($mailwatch_directory, $vendor_directory, $public_directory, {mode => 0700});
copy(
    File::Spec->catfile($project_root, 'public_html', 'index.php'),
    File::Spec->catfile($public_directory, 'index.php'),
) or die "Unable to copy the public front controller: $!";
write_file(
    File::Spec->catfile($vendor_directory, 'autoload.php'),
    "<?php\nrequire " . php_string(File::Spec->catfile($project_root, 'vendor', 'autoload.php')) . ";\n",
);
copy(
    File::Spec->catfile($project_root, 'tests', 'fixtures', 'api', 'IntegrationDatabase.php'),
    File::Spec->catfile($mailwatch_directory, 'Database.php'),
) or die "Unable to copy the integration database: $!";

my $list_fixture = read_json_fixture('allow-block-list-v1.json');
my $spam_fixture = read_json_fixture('spam-settings-v1.json');
write_file($fixture_path, encode_json({%{$list_fixture}, %{$spam_fixture}}));
write_file(
    File::Spec->catfile($mailwatch_directory, 'conf.php'),
    join(
        "\n",
        '<?php',
        'define(\'API_KEY\', ' . php_string($api_key) . ');',
        'define(\'API_MAX_PAYLOAD_BYTES\', 1048576);',
        'define(\'API_MAX_SNAPSHOT_BYTES\', 5242880);',
        'define(\'TEST_FIXTURE_PATH\', ' . php_string($fixture_path) . ');',
        'define(\'TEST_CAPTURE_PATH\', ' . php_string($capture_path) . ');',
        'define(\'DB_HOST\', \'test\');',
        'define(\'DB_USER\', \'test\');',
        'define(\'DB_PASS\', \'test\');',
        'define(\'DB_NAME\', \'test\');',
        'define(\'DB_PORT\', 3306);',
        '',
    ),
);

my $port_socket = IO::Socket::INET->new(
    LocalAddr => '127.0.0.1',
    LocalPort => 0,
    Listen    => 1,
    ReuseAddr => 1,
) or die "Unable to allocate a test port: $!";
my $port = $port_socket->sockport();
close $port_socket;

$server_pid = start_php_server($port, $public_directory, $server_log_path);
my $base_url = "http://127.0.0.1:$port/api";
my @logs;
my $client = integration_client($api_key, "$base_url/messages", \@logs);

subtest 'the Perl client delivers a message to the PHP ingestion endpoint' => sub {
    my $message = read_json_fixture('logmail-v1.json');

    ok($client->send_api_message($message), 'the real HTTP request succeeds');
    ok(-f $capture_path, 'the PHP endpoint reaches its persistence boundary');

    my $insert = decode_json(read_file($capture_path));
    is($insert->{id}, 'fixture-message-001', 'the message id crosses the Perl/PHP boundary');
    is($insert->{spamallowlisted}, 1, 'the endpoint maps the legacy allowlist field');
    is(
        $insert->{ingestion_id},
        sha256_hex(join "\0", @{$message}{qw(hostname id token)}),
        'the Perl idempotency key is accepted by PHP',
    );
};

subtest 'the Perl client consumes both PHP snapshot contracts' => sub {
    my $list_result = $client->fetch_api_snapshot("$base_url/allow-block-list");
    is($list_result->{snapshot}{contract}, 'mailwatch.allow-block-list.v1', 'allow/block contract is decoded');
    ok(
        (grep { $_->{from_address} eq 'blocked@example.com' } @{$list_result->{snapshot}{blocklist}}) > 0,
        'blocklist data crosses the PHP/Perl boundary',
    );
    like($list_result->{etag}, qr/\A"[a-f0-9]{64}"\z/, 'the list ETag is retained');
    is_deeply(
        $client->fetch_api_snapshot("$base_url/allow-block-list", $list_result->{etag}),
        {not_modified => 1, etag => $list_result->{etag}},
        'the real endpoint and client agree on conditional refresh',
    );

    my $spam_result = $client->fetch_api_snapshot("$base_url/spam-settings");
    is($spam_result->{snapshot}{contract}, 'mailwatch.spam-settings.v1', 'spam settings contract is decoded');
    is(
        $spam_result->{snapshot}{spam_scores}{'recipient@example.com'},
        3.5,
        'spam score data crosses the PHP/Perl boundary',
    );
    ok(
        (grep { $_ eq 'recipient@example.com' } @{$spam_result->{snapshot}{no_scan}}) > 0,
        'no-scan data crosses the PHP/Perl boundary',
    );
};

subtest 'one shared API key protects ingestion and snapshots' => sub {
    my @rejected_logs;
    my $rejected_client = integration_client('incorrect-api-key', "$base_url/messages", \@rejected_logs);
    my $message = read_json_fixture('logmail-v1.json');

    ok(!$rejected_client->send_api_message($message), 'PHP rejects ingestion with another key');
    ok(
        !defined $rejected_client->fetch_api_snapshot("$base_url/allow-block-list"),
        'PHP rejects snapshot access with another key',
    );
    ok(
        (grep { $_->[1] =~ /401 Unauthorized/ } @rejected_logs) > 0,
        'the Perl client reports authentication failures without retrying',
    );
};

subtest 'a message is spooled during an outage and replayed after recovery' => sub {
    stop_php_server($server_pid);
    $server_pid = undef;

    my $spool_directory = File::Spec->catdir($temporary_directory, 'spool');
    my @outage_logs;
    my $outage_client = integration_client(
        $api_key,
        "$base_url/messages",
        \@outage_logs,
        api_spool_directory   => $spool_directory,
        api_spool_max_messages => 10,
        api_spool_replay_limit => 10,
    );
    my $message = read_json_fixture('logmail-v1.json');
    $message->{id} = 'fixture-outage-message-001';

    ok(!$outage_client->send_api_message($message), 'delivery fails while the PHP API is offline');
    my @queued = queued_messages($spool_directory);
    is(scalar @queued, 1, 'the undelivered message is queued once');
    is((stat $spool_directory)[2] & 07777, 0700, 'the spool directory is private');
    is(
        (stat File::Spec->catfile($spool_directory, $queued[0]))[2] & 07777,
        0600,
        'the queued message is private',
    );

    $server_pid = start_php_server($port, $public_directory, $server_log_path);

    ok($outage_client->replay_api_spool(), 'the queue is replayed after the API recovers');
    is(scalar queued_messages($spool_directory), 0, 'the delivered message is removed from the queue');
    is(
        decode_json(read_file($capture_path))->{id},
        'fixture-outage-message-001',
        'the recovered endpoint receives the queued message',
    );
    ok(
        (grep { $_->[1] =~ /Replayed from the MailWatch API spool/ } @outage_logs) > 0,
        'successful recovery is recorded in the Perl log',
    );
};

subtest 'supported Perl integration contains no database access' => sub {
    for my $file (qw(MailWatch.pm MailWatchClient.pm SQLAllowBlockList.pm SQLSpamSettings.pm)) {
        my $source = read_file(File::Spec->catfile($project_root, 'MailScanner_perl_scripts', $file));
        unlike($source, qr/\b(?:use|require)\s+DBI\b|DBI:(?:MariaDB|mysql)/, "$file has no DBI dependency");
    }

    my $configuration = read_file(
        File::Spec->catfile($project_root, 'MailScanner_perl_scripts', 'MailWatchConf.pm')
    );
    unlike($configuration, qr/mailwatch_get_db_|\$db_(?:host|name|user|pass)\b/, 'Perl configuration has no DB settings');
};

done_testing();

sub integration_client {
    my ($key, $endpoint, $logs, %options) = @_;

    return MailWatchClient->new(
        user_agent          => LWP::UserAgent->new(timeout => 5),
        api_endpoint        => $endpoint,
        api_key             => $key,
        api_max_retries     => 1,
        api_retry_delay     => 0,
        api_max_retry_delay => 0,
        api_logger          => sub { push @{$logs}, [@_] },
        sleeper             => sub { },
        %options,
    );
}

sub queued_messages {
    my ($directory) = @_;

    opendir my $handle, $directory or die "Unable to read $directory: $!";
    my @queued = grep { /\A[a-f0-9]{64}\.json\z/ } readdir $handle;
    closedir $handle;

    return @queued;
}

sub read_json_fixture {
    my ($filename) = @_;

    return decode_json(
        read_file(File::Spec->catfile($project_root, 'tests', 'fixtures', 'api', $filename))
    );
}

sub read_file {
    my ($path) = @_;

    open my $file, '<', $path or die "Unable to read $path: $!";
    local $/;
    my $contents = <$file>;
    close $file;

    return $contents;
}

sub write_file {
    my ($path, $contents) = @_;

    open my $file, '>', $path or die "Unable to write $path: $!";
    print {$file} $contents or die "Unable to write $path: $!";
    close $file or die "Unable to close $path: $!";
}

sub php_string {
    my ($value) = @_;

    $value =~ s/\\/\\\\/g;
    $value =~ s/'/\\'/g;

    return "'$value'";
}

sub start_php_server {
    my ($server_port, $document_root, $log_path) = @_;

    my $pid = fork();
    die "Unable to fork the PHP test server: $!" unless defined $pid;
    if ($pid == 0) {
        open STDOUT, '>>', $log_path or die "Unable to open the PHP server log: $!";
        open STDERR, '>&', STDOUT or die "Unable to redirect the PHP server error log: $!";
        exec(
            ($ENV{PHP_BINARY} // 'php'),
            '-S',
            "127.0.0.1:$server_port",
            '-t',
            $document_root,
            File::Spec->catfile($document_root, 'index.php'),
        );
        die "Unable to start the PHP test server: $!";
    }

    wait_for_server($server_port, $log_path, $pid);

    return $pid;
}

sub stop_php_server {
    my ($pid) = @_;

    return unless defined $pid && $pid > 0;
    kill 'TERM', $pid;
    waitpid $pid, 0;
}

sub wait_for_server {
    my ($server_port, $log_path, $pid) = @_;

    for (1 .. 250) {
        my $socket = IO::Socket::INET->new(
            PeerAddr => '127.0.0.1',
            PeerPort => $server_port,
            Proto    => 'tcp',
            Timeout  => 0.1,
        );
        if ($socket) {
            close $socket;

            return;
        }
        last if waitpid($pid, WNOHANG) == $pid;
        select undef, undef, undef, 0.02;
    }

    my $server_log = -f $log_path ? read_file($log_path) : '(no server log)';
    die "The PHP test server did not start:\n$server_log";
}
