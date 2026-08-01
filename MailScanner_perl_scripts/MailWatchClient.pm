#
# MailWatch for MailScanner
# Copyright (C) 2014-2026 MailWatch Team
#

package MailWatchClient;

use strict;
use warnings;

use Digest::SHA qw(sha256_hex);
use Fcntl qw(:DEFAULT :flock);
use File::Path qw(make_path);
use File::Spec;
use File::Temp qw(tempfile);
use HTTP::Request;
use IO::Handle;
use JSON qw(decode_json encode_json);

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
    $args{api_spool_max_messages} = 10_000 unless defined $args{api_spool_max_messages};
    $args{api_spool_replay_limit} = 10 unless defined $args{api_spool_replay_limit};

    return bless \%args, $class;
}

sub send_api_message {
    my ($self, $message) = @_;

    if ($self->_send_api_message($message)) {
        $self->replay_api_spool();

        return 1;
    }

    $self->_spool_api_message($message);

    return 0;
}

sub replay_api_spool {
    my ($self) = @_;

    return 1 unless defined $self->{api_spool_directory};
    return 1 unless -e $self->{api_spool_directory};
    return 0 unless $self->_ensure_spool_directory();

    my $lock_path = File::Spec->catfile($self->{api_spool_directory}, '.replay.lock');
    sysopen(my $lock, $lock_path, O_WRONLY | O_CREAT, 0600) or do {
        $self->{api_logger}->('error', 'Unable to open the MailWatch API spool lock.');

        return 0;
    };
    return 1 unless flock($lock, LOCK_EX | LOCK_NB);

    opendir(my $directory, $self->{api_spool_directory}) or do {
        $self->{api_logger}->('error', 'Unable to read the MailWatch API spool directory.');

        return 0;
    };
    my @queued = sort {
        (stat(File::Spec->catfile($self->{api_spool_directory}, $a)))[9]
            <=> (stat(File::Spec->catfile($self->{api_spool_directory}, $b)))[9]
    } grep { /\A[a-f0-9]{64}\.json\z/ } readdir $directory;
    closedir $directory;

    my $processed = 0;
    for my $filename (@queued) {
        last if $processed >= $self->{api_spool_replay_limit};

        my $path = File::Spec->catfile($self->{api_spool_directory}, $filename);
        my $message = $self->_read_spooled_message($path);
        unless (defined $message) {
            unless (rename $path, $path . '.invalid') {
                $self->{api_logger}->('error', 'Unable to move an invalid MailWatch API spool message aside.');

                return 0;
            }
            ++$processed;
            next;
        }

        last unless $self->_send_api_message($message);

        unless (unlink $path) {
            $self->{api_logger}->('error', 'Unable to remove a delivered message from the MailWatch API spool.');

            return 0;
        }
        $self->{api_logger}->('info', "$$message{id}: Replayed from the MailWatch API spool");
        ++$processed;
    }

    return 1;
}

sub _send_api_message {
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

sub _spool_api_message {
    my ($self, $message) = @_;

    return 0 unless defined $self->{api_spool_directory};
    return 0 unless $self->_ensure_spool_directory();

    my $json = encode_json($message);
    my $key = _idempotency_key($message) // sha256_hex($json);
    my $final_path = File::Spec->catfile($self->{api_spool_directory}, $key . '.json');
    if (-f $final_path) {
        $self->{api_logger}->('warn', "$$message{id}: Already queued in the MailWatch API spool");

        return 1;
    }

    opendir(my $directory, $self->{api_spool_directory}) or do {
        $self->{api_logger}->('error', 'Unable to inspect the MailWatch API spool directory.');

        return 0;
    };
    my $queued = grep { /\A[a-f0-9]{64}\.json\z/ } readdir $directory;
    closedir $directory;
    if ($queued >= $self->{api_spool_max_messages}) {
        $self->{api_logger}->('error', 'MailWatch API spool limit reached; message could not be queued.');

        return 0;
    }

    my ($file, $temporary_path);
    my $stored = eval {
        ($file, $temporary_path) = tempfile('.mailwatch-XXXXXXXX', DIR => $self->{api_spool_directory}, UNLINK => 0);
        chmod 0600, $temporary_path or die 'chmod failed';
        binmode $file;
        print {$file} $json or die 'write failed';
        $file->flush() or die 'flush failed';
        $file->sync() or die 'sync failed';
        close $file or die 'close failed';
        rename $temporary_path, $final_path or die 'rename failed';
        1;
    };
    unless ($stored) {
        close $file if defined $file;
        unlink $temporary_path if defined $temporary_path && -e $temporary_path;
        $self->{api_logger}->('error', "$$message{id}: Unable to persist message in the MailWatch API spool");

        return 0;
    }

    $self->{api_logger}->('warn', "$$message{id}: Queued in the MailWatch API spool");

    return 1;
}

sub _ensure_spool_directory {
    my ($self) = @_;

    my $path = $self->{api_spool_directory};
    if (-e $path) {
        if (-l $path || !-d $path) {
            $self->{api_logger}->('error', 'MailWatch API spool path is not a safe directory.');

            return 0;
        }
    } else {
        eval { make_path($path, {mode => 0700}); };
        if ($@ || !-d $path) {
            $self->{api_logger}->('error', 'Unable to create the MailWatch API spool directory.');

            return 0;
        }
    }

    chmod 0700, $path or do {
        $self->{api_logger}->('error', 'Unable to secure the MailWatch API spool directory.');

        return 0;
    };

    return 1;
}

sub _read_spooled_message {
    my ($self, $path) = @_;

    open my $file, '<', $path or do {
        $self->{api_logger}->('error', 'Unable to open a queued MailWatch API message.');

        return;
    };
    binmode $file;
    local $/;
    my $json = <$file>;
    close $file;

    my $message = eval { decode_json($json) };
    unless (ref $message eq 'HASH') {
        $self->{api_logger}->('error', 'Invalid message moved aside in the MailWatch API spool.');

        return;
    }

    return $message;
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
