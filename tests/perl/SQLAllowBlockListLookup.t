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
    package Local::SnapshotClient;

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

require "$FindBin::Bin/../../MailScanner_perl_scripts/SQLAllowBlockList.pm";

my $fixture;

sub fixture {
    my $path = "$FindBin::Bin/../fixtures/api/allow-block-list-v1.json";
    open my $file, '<', $path or die "Cannot open $path: $!";
    local $/;
    my $data = decode_json(<$file>);
    close $file;

    return $data;
}

sub lookup_map {
    my ($rows, $filters) = @_;
    my %list;

    for my $row (@{$rows}) {
        $list{lc $row->{to_address}}{lc $row->{from_address}} = 1;
    }

    # This mirrors the historical INNER JOIN in CreateList. It intentionally
    # does not inspect "active", because the current SQL query does not either.
    for my $row (@{$rows}) {
        for my $filter (@{$filters // []}) {
            next unless lc $row->{to_address} eq lc $filter->{username};

            $list{lc $filter->{filter}}{lc $row->{from_address}} = 1;
        }
    }

    return \%list;
}

sub effective_entries {
    my ($rows, $filters) = @_;
    my @entries = map {
        {to_address => lc $_->{to_address}, from_address => lc $_->{from_address}}
    } @{$rows};

    for my $row (@{$rows}) {
        for my $filter (@{$filters}) {
            next unless lc $row->{to_address} eq lc $filter->{username};

            push @entries, {
                to_address   => lc $filter->{filter},
                from_address => lc $row->{from_address},
            };
        }
    }

    return \@entries;
}

sub snapshot {
    return {
        contract         => 'mailwatch.allow-block-list.v1',
        snapshot_version => 'a' x 64,
        allowlist        => effective_entries($fixture->{allowlist}, $fixture->{user_filters}),
        blocklist        => effective_entries($fixture->{blocklist}, $fixture->{user_filters}),
    };
}

sub message {
    my (%overrides) = @_;

    return {
        from       => 'unknown@untrusted.test',
        fromdomain => 'untrusted.test',
        to         => ['nobody@example.invalid'],
        todomain   => ['example.invalid'],
        clientip   => '203.0.113.7',
        %overrides,
    };
}

$fixture = fixture();
my $allowlist = lookup_map($fixture->{allowlist}, $fixture->{user_filters});
my $blocklist = lookup_map($fixture->{blocklist}, $fixture->{user_filters});

subtest 'exact sender addresses use the default recipient scope' => sub {
    ok(
        MailScanner::CustomConfig::LookupList(
            message(from => 'sender@example.com', fromdomain => 'example.com'),
            $allowlist,
        ),
        'the exact sender matches',
    );
    ok(
        !MailScanner::CustomConfig::LookupList(
            message(from => 'other@example.com', fromdomain => 'example.com'),
            $allowlist,
        ),
        'another sender does not match',
    );
};

subtest 'recipient address and recipient domain select scoped rules' => sub {
    ok(
        MailScanner::CustomConfig::LookupList(
            message(
                from       => 'someone@trusted.example.org',
                fromdomain => 'trusted.example.org',
                to         => ['recipient@example.net'],
                todomain   => ['example.net'],
            ),
            $allowlist,
        ),
        'an exact recipient selects its sender-domain rule',
    );
    ok(
        MailScanner::CustomConfig::LookupList(
            message(
                from       => 'someone@partner.example.org',
                fromdomain => 'partner.example.org',
                to         => ['other@example.net'],
                todomain   => ['example.net'],
            ),
            $allowlist,
        ),
        'a recipient domain selects its at-domain sender rule',
    );
};

subtest 'user filters replace a matching username with recipient scopes' => sub {
    for my $recipient_domain ('filtered.example.net', 'historically-inactive.example.net') {
        ok(
            MailScanner::CustomConfig::LookupList(
                message(
                    from       => 'filtered-sender@example.com',
                    fromdomain => 'example.com',
                    todomain   => [$recipient_domain],
                ),
                $allowlist,
            ),
            "$recipient_domain selects the rule linked to its username",
        );
    }
};

subtest 'sender subdomains and local-part subdomains preserve wildcard semantics' => sub {
    ok(
        MailScanner::CustomConfig::LookupList(
            message(
                from       => 'person@news.example.org',
                fromdomain => 'news.example.org',
            ),
            $allowlist,
        ),
        'a wildcard sender subdomain matches',
    );
    ok(
        MailScanner::CustomConfig::LookupList(
            message(
                from       => 'bounce@deep.news.example.org',
                fromdomain => 'deep.news.example.org',
            ),
            $allowlist,
        ),
        'a local-part wildcard sender subdomain matches',
    );
};

subtest 'IPv4 rules match exact addresses and historical octet prefixes' => sub {
    ok(
        MailScanner::CustomConfig::LookupList(
            message(clientip => '192.0.2.45'),
            $allowlist,
        ),
        'a three-octet prefix ending in a dot matches',
    );
    ok(
        MailScanner::CustomConfig::LookupList(
            message(
                to       => ['recipient@example.net'],
                todomain => ['example.net'],
                clientip => '198.51.100.23',
            ),
            $blocklist,
        ),
        'an exact IP matches in a recipient scope',
    );
};

subtest 'missing messages and unrelated senders do not match' => sub {
    is(MailScanner::CustomConfig::LookupList(undef, $allowlist), 0, 'an absent message is rejected safely');
    is(MailScanner::CustomConfig::LookupList(message(), $allowlist), 0, 'an unrelated message does not match');
};

subtest 'snapshot validation builds both lookup maps atomically' => sub {
    my ($allow, $block) = MailScanner::CustomConfig::ValidateAllowBlockSnapshot(snapshot());

    ok($allow->{'filtered.example.net'}{'filtered-sender@example.com'}, 'expanded allowlist filter is loaded');
    ok($block->{default}{'blocked@example.com'}, 'blocklist entry is loaded');

    my $invalid = snapshot();
    $invalid->{blocklist}[0]{from_address} = ['not', 'scalar'];
    my @invalid_result = MailScanner::CustomConfig::ValidateAllowBlockSnapshot($invalid);
    is(scalar @invalid_result, 0, 'one invalid list rejects the complete snapshot');
};

subtest 'a failed refresh retains the last known valid snapshot' => sub {
    my $client = Local::SnapshotClient->new(
        {
            not_modified => 0,
            etag         => '"snapshot-a"',
            snapshot     => snapshot(),
        },
        undef,
    );
    no warnings 'once';
    local $MailScanner::CustomConfig::mailWatchListClient = $client;

    ok(MailScanner::CustomConfig::RefreshAllowBlockLists(), 'the valid snapshot is activated');
    ok(
        MailScanner::CustomConfig::SQLAllowlist(
            message(from => 'sender@example.com', fromdomain => 'example.com'),
        ),
        'the active snapshot is used for lookup',
    );
    ok(!MailScanner::CustomConfig::RefreshAllowBlockLists(), 'the failed refresh is reported');
    ok(
        MailScanner::CustomConfig::SQLAllowlist(
            message(from => 'sender@example.com', fromdomain => 'example.com'),
        ),
        'the previous valid snapshot remains active',
    );
    is($client->{requests}[1][1], '"snapshot-a"', 'the active ETag is sent during refresh');
};

done_testing();
