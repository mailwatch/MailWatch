#
# MailWatch for MailScanner
# Copyright (C) 2014-2026 MailWatch Team
#

package MailWatchClient;

use strict;
use warnings;

use Digest::SHA qw(sha256_hex);
use HTTP::Request;
use JSON qw(encode_json);

sub new {
    my ($class, %args) = @_;

    for my $required (
        qw(
            user_agent api_endpoint api_key api_max_retries api_retry_delay api_logger
            local_sender local_starter local_max_retries local_retry_delay local_logger
        )
    ) {
        die "Missing required MailWatchClient argument: $required"
            unless defined $args{$required};
    }

    $args{sleeper} = sub { sleep $_[0] } unless defined $args{sleeper};
    $args{api_max_retry_delay} = 60 unless defined $args{api_max_retry_delay};

    return bless \%args, $class;
}

sub send_api_message {
    my ($self, $message) = @_;

    my $retry_count = 0;
    while ($retry_count < $self->{api_max_retries}) {
        my $json_data = encode_json($message);
        my $request = HTTP::Request->new(POST => $self->{api_endpoint});
        $request->content_type('application/json');
        $request->header('x-mailwatch-api-key' => $self->{api_key});
        my $idempotency_key = _idempotency_key($message);
        $request->header('Idempotency-Key' => $idempotency_key)
            if defined $idempotency_key;
        $request->content($json_data);

        my $response = $self->{user_agent}->request($request);
        if ($response->is_success) {
            $self->{api_logger}->('info', "$$message{id}: Logged to MailWatch API");

            return 1;
        }

        unless (_is_retryable_status($response->code)) {
            $self->{api_logger}->(
                'error',
                "$$message{id}: MailWatch API rejected message: " . $response->status_line . '; not retrying.'
            );

            return 0;
        }

        $self->{api_logger}->('warn', "$$message{id}: Failed to log to MailWatch API: " . $response->status_line);
        ++$retry_count;
        if ($retry_count < $self->{api_max_retries}) {
            my $delay = _api_retry_delay($self, $retry_count);
            $self->{api_logger}->('warn', "$$message{id}: Retrying in $delay seconds...");
            $self->{sleeper}->($delay);
        } else {
            $self->{api_logger}->(
                'error',
                "$$message{id}: Failed to log to MailWatch API after $self->{api_max_retries} attempts."
            );
        }
    }

    return 0;
}

sub send_local_message {
    my ($self, $payload, $message_id) = @_;

    for my $attempt (1 .. $self->{local_max_retries}) {
        if ($self->{local_sender}->($payload . "END\n")) {
            $self->{local_logger}->('info', "Logging message $message_id to API");

            return 1;
        }

        if ($attempt < $self->{local_max_retries}) {
            $self->{local_logger}->(
                'warn',
                "Unable to connect to the local MailWatch logger for $message_id "
                    . "(attempt $attempt/$self->{local_max_retries}); "
                    . "retrying in $self->{local_retry_delay} seconds..."
            );
            $self->{local_starter}->();
            $self->{sleeper}->($self->{local_retry_delay});
        }
    }

    $self->{local_logger}->(
        'error',
        "Unable to deliver message $message_id to the local MailWatch logger "
            . "after $self->{local_max_retries} attempts."
    );

    return 0;
}

sub _is_retryable_status {
    my ($status) = @_;

    return 1 if $status == 408 || $status == 429;
    return 1 if $status >= 500 && $status <= 599;

    return 0;
}

sub _api_retry_delay {
    my ($self, $retry_count) = @_;

    my $delay = $self->{api_retry_delay} * (2 ** ($retry_count - 1));
    return $self->{api_max_retry_delay} if $delay > $self->{api_max_retry_delay};

    return $delay;
}

sub _idempotency_key {
    my ($message) = @_;

    for my $field (qw(hostname id token)) {
        return unless defined $$message{$field} && $$message{$field} ne '';
    }

    return sha256_hex(join "\0", @{$message}{qw(hostname id token)});
}

1;
