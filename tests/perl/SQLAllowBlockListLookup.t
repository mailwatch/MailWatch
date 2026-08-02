use strict;
use warnings;

use FindBin;
use JSON qw(decode_json);
use Test::More;

BEGIN {
    # LookupList does not use DBI. Stub the database modules so its historical
    # matching behaviour can be characterised without a MariaDB client driver.
    $INC{'DBI.pm'} = __FILE__;
    $INC{'DBD/MariaDB.pm'} = __FILE__;
}

require "$FindBin::Bin/../../MailScanner_perl_scripts/SQLAllowBlockList.pm";

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

my $fixture = fixture();
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

done_testing();
