use strict;
use warnings;

use FindBin;
use JSON qw(decode_json);
use Test::More;

BEGIN {
    package DBI;

    our $errstr = 'fixture database unavailable';
    our $fail = 0;
    our %rows;

    sub connect {
        return if $fail;

        return bless {mariadb_serverversion => 10_011_000}, 'Local::SpamSettingsDatabase';
    }

    package DBD::MariaDB;

    package Local::SpamSettingsDatabase;

    sub disconnect { return 1 }
    sub do { return 1 }

    sub prepare {
        my ($self, $query) = @_;
        my ($column) = $query =~ /SELECT username, (\w+)/;

        return bless {
            rows => [map { [@{$_}] } @{$DBI::rows{$column} // []}],
        }, 'Local::SpamSettingsStatement';
    }

    package Local::SpamSettingsStatement;

    sub execute { return 1 }

    sub bind_columns {
        my ($self, undef, $username, $value) = @_;
        $self->{username} = $username;
        $self->{value} = $value;

        return 1;
    }

    sub fetch {
        my ($self) = @_;
        my $row = shift @{$self->{rows}};
        return unless defined $row;

        ${$self->{username}} = $row->[0];
        ${$self->{value}} = $row->[1];

        return 1;
    }

    sub finish { return 1 }

    package MailScanner::Log;

    our @messages;

    sub InfoLog { push @messages, ['info', @_] }
    sub WarnLog { push @messages, ['warn', @_] }

    $INC{'DBI.pm'} = __FILE__;
    $INC{'DBD/MariaDB.pm'} = __FILE__;
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

subtest 'refresh keeps entries that disappeared or became unset' => sub {
    local $DBI::fail = 0;
    local %DBI::rows = (
        spamscore => [['new@example.test', 6.0]],
        noscan    => [['new@example.test', 1]],
    );
    my %scores = ('removed@example.test' => 3.0);
    my %scan = ('removed@example.test' => 1);

    is(MailScanner::CustomConfig::CreateScoreList('spamscore', \%scores), 1, 'one score row is refreshed');
    is(MailScanner::CustomConfig::CreateNoScanList('noscan', \%scan), 1, 'one no-scan row is refreshed');
    ok(exists $scores{'removed@example.test'}, 'a removed score remains in memory');
    ok(exists $scan{'removed@example.test'}, 'a removed no-scan value remains in memory');
};

subtest 'database failure retains the existing in-memory values' => sub {
    local $DBI::fail = 1;
    my %scores = ('known@example.test' => 4.0);

    is(MailScanner::CustomConfig::CreateScoreList('spamscore', \%scores), 0, 'the failed refresh reports no rows');
    is($scores{'known@example.test'}, 4.0, 'the existing score remains available');
};

done_testing();
