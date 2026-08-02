use strict;
use warnings;

use FindBin;
use lib "$FindBin::Bin/../../MailScanner_perl_scripts";
use JSON qw(decode_json);
use Test::More;

{
    package MailScanner::Log;

    our @messages;

    sub InfoLog { push @messages, ['info', @_] }
    sub WarnLog { push @messages, ['warn', @_] }
}

{
    package Local::SpamSettingsClient;

    sub new {
        my ($class, @results) = @_;

        return bless {results => \@results, requests => []}, $class;
    }

    sub fetch_api_snapshot {
        my ($self, @arguments) = @_;
        push @{$self->{requests}}, \@arguments;

        return shift @{$self->{results}};
    }
}

require "$FindBin::Bin/../../MailScanner_perl_scripts/SQLSpamSettings.pm";

sub fixture {
    my $path = "$FindBin::Bin/../fixtures/api/spam-settings-v1.json";
    open my $file, '<', $path or die "Cannot open $path: $!";
    local $/;
    my $data = decode_json(<$file>);
    close $file;

    return $data;
}

sub positive_map {
    my ($users, $field) = @_;

    return {
        map { lc($_->{username}) => $_->{$field} }
        grep { $_->{$field} > 0 } @{$users}
    };
}

sub message {
    my (%overrides) = @_;

    return {
        to       => ['unknown@unknown.test'],
        todomain => ['unknown.test'],
        %overrides,
    };
}

my $fixture = fixture();
my $spam_scores = positive_map($fixture->{users}, 'spamscore');
my $high_spam_scores = positive_map($fixture->{users}, 'highspamscore');
my $no_scan = positive_map($fixture->{users}, 'noscan');

sub snapshot {
    return {
        contract         => 'mailwatch.spam-settings.v1',
        snapshot_version => 'a' x 64,
        spam_scores      => {%{$spam_scores}},
        high_spam_scores => {%{$high_spam_scores}},
        no_scan          => [sort keys %{$no_scan}],
    };
}

subtest 'spam scores use the historical precedence order' => sub {
    is(
        MailScanner::CustomConfig::LookupScoreList(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
            $spam_scores,
        ),
        3.5,
        'an exact recipient wins',
    );

    my %without_recipient = %{$spam_scores};
    delete $without_recipient{'recipient@example.com'};
    is(
        MailScanner::CustomConfig::LookupScoreList(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
            \%without_recipient,
        ),
        4.0,
        'a username equal to the recipient domain is next',
    );

    my %domain_admin_only = %without_recipient;
    delete $domain_admin_only{'example.com'};
    is(
        MailScanner::CustomConfig::LookupScoreList(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
            \%domain_admin_only,
        ),
        4.5,
        'domain-admin at the recipient domain is next',
    );

    my %system_only = %domain_admin_only;
    delete $system_only{'domain-admin@example.com'};
    is(
        MailScanner::CustomConfig::LookupScoreList(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
            \%system_only,
        ),
        5.0,
        'admin is the final configured fallback',
    );
    is(
        MailScanner::CustomConfig::LookupScoreList(message(), {}),
        999,
        'missing configuration uses the historical pass-through score',
    );
};

subtest 'high spam scores use the same precedence rules' => sub {
    is(
        MailScanner::CustomConfig::LookupScoreList(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
            $high_spam_scores,
        ),
        7.0,
        'the exact high-spam score wins',
    );
    is(
        MailScanner::CustomConfig::LookupScoreList(message(), $high_spam_scores),
        10.0,
        'the system high-spam score is the fallback',
    );
};

subtest 'only the first recipient and first recipient domain are considered' => sub {
    is(
        MailScanner::CustomConfig::LookupScoreList(
            message(
                to       => ['unknown@unknown.test', 'recipient@example.com'],
                todomain => ['unknown.test', 'example.com'],
            ),
            $spam_scores,
        ),
        5.0,
        'a configured second recipient is ignored',
    );
};

subtest 'non-positive database values are treated as unset' => sub {
    ok(!exists $spam_scores->{'zero@example.net'}, 'a zero score is omitted');
    ok(!exists $spam_scores->{'negative@example.net'}, 'a negative score is omitted');
    ok(!exists $no_scan->{'zero@example.net'}, 'zero does not disable scanning');
    ok(!exists $no_scan->{'negative@example.net'}, 'a negative value does not disable scanning');
};

subtest 'no-scan rules apply only to exact users and domain usernames' => sub {
    is(
        MailScanner::CustomConfig::LookupNoScanList(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
            $no_scan,
        ),
        0,
        'an exact no-scan user disables scanning',
    );
    is(
        MailScanner::CustomConfig::LookupNoScanList(
            message(to => ['other@example.com'], todomain => ['example.com']),
            $no_scan,
        ),
        0,
        'a domain username disables scanning',
    );
    is(
        MailScanner::CustomConfig::LookupNoScanList(message(), $no_scan),
        1,
        'the admin no-scan value is not a system fallback',
    );
    is(
        MailScanner::CustomConfig::LookupNoScanList(undef, $no_scan),
        0,
        'an absent message returns the historical no-scan value',
    );
};

subtest 'snapshot validation builds all three setting maps atomically' => sub {
    my ($spam, $high_spam, $validated_no_scan) =
        MailScanner::CustomConfig::ValidateSpamSettingsSnapshot(snapshot());

    is($spam->{'recipient@example.com'}, 3.5, 'the Spam score map is built');
    is($high_spam->{'recipient@example.com'}, 7.0, 'the high-Spam score map is built');
    ok($validated_no_scan->{'recipient@example.com'}, 'the no-scan map is built');

    my $invalid = snapshot();
    $invalid->{high_spam_scores}{'recipient@example.com'} = 'not-a-score';
    my @invalid_result = MailScanner::CustomConfig::ValidateSpamSettingsSnapshot($invalid);
    is(scalar @invalid_result, 0, 'one invalid setting rejects the complete snapshot');
};

subtest 'successful replacement removes old values and failure retains the last valid snapshot' => sub {
    my $replacement = snapshot();
    $replacement->{snapshot_version} = 'b' x 64;
    $replacement->{spam_scores} = {admin => 6.0};
    $replacement->{high_spam_scores} = {admin => 11.0};
    $replacement->{no_scan} = [];
    my $client = Local::SpamSettingsClient->new(
        {not_modified => 0, etag => '"snapshot-a"', snapshot => snapshot()},
        {not_modified => 0, etag => '"snapshot-b"', snapshot => $replacement},
        undef,
    );
    no warnings 'once';
    local $MailScanner::CustomConfig::mailWatchSpamSettingsClient = $client;

    ok(MailScanner::CustomConfig::RefreshSpamSettings(), 'the first valid snapshot is activated');
    is(
        MailScanner::CustomConfig::SQLSpamScores(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
        ),
        3.5,
        'the first snapshot supplies the recipient score',
    );
    ok(MailScanner::CustomConfig::RefreshSpamSettings(), 'the replacement snapshot is activated');
    is(
        MailScanner::CustomConfig::SQLSpamScores(
            message(to => ['recipient@example.com'], todomain => ['example.com']),
        ),
        6.0,
        'a removed recipient score no longer remains in memory',
    );
    ok(!MailScanner::CustomConfig::RefreshSpamSettings(), 'the failed refresh is reported');
    is(
        MailScanner::CustomConfig::SQLSpamScores(message()),
        6.0,
        'the replacement remains active after failure',
    );
    is($client->{requests}[1][1], '"snapshot-a"', 'the first ETag is sent for replacement');
    is($client->{requests}[2][1], '"snapshot-b"', 'the replacement ETag is sent after failure');
};

done_testing();
