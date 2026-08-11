#
# MailWatch for MailScanner
# Copyright (C) 2014-2026 MailWatch Team
#
#   Shared MailWatch HTTP client
#
#   Version 1.0
#
# This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
# License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
# version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
# warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
#
# In addition, as a special exception, the copyright holder gives permission to link the code of this program with
# those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
# that use the same license as those files), and distribute linked combinations including the two.
# You must obey the GNU General Public License in all respects for all of the code used other than those files in the
# PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
# your version of the program, but you are not obligated to do so.
# If you do not wish to do so, delete this exception statement from your version.
#
# You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
# Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
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

our $VERSION = '1.0';

sub new {
    my ($class, %args) = @_;

    for my $required (
        qw(
            user_agent api_key api_max_retries api_retry_delay api_logger
        )
    ) {
        die "Missing required MailWatchClient argument: $required"
            unless defined $args{$required};
    }

    $args{sleeper} = sub { sleep $_[0] } unless defined $args{sleeper};
    $args{api_max_retry_delay} = 60 unless defined $args{api_max_retry_delay};
    $args{api_spool_max_messages} = 10_000 unless defined $args{api_spool_max_messages};
    $args{api_spool_replay_limit} = 10 unless defined $args{api_spool_replay_limit};
    $args{api_snapshot_max_bytes} = 5 * 1024 * 1024 unless defined $args{api_snapshot_max_bytes};
    $args{local_sender} = sub { 0 } unless defined $args{local_sender};
    $args{local_starter} = sub { 0 } unless defined $args{local_starter};
    $args{local_max_retries} = 1 unless defined $args{local_max_retries};
    $args{local_retry_delay} = 0 unless defined $args{local_retry_delay};
    $args{local_logger} = $args{api_logger} unless defined $args{local_logger};
    $args{request_id_generator} = sub {
        return sha256_hex(join "\0", time, $$, rand(), {});
    } unless defined $args{request_id_generator};

    return bless \%args, $class;
}

sub fetch_api_snapshot {
    my ($self, $endpoint, $etag) = @_;
    my $request_id = _request_id($self);

    for my $attempt (1 .. $self->{api_max_retries}) {
        my $request = HTTP::Request->new(GET => $endpoint);
        $request->header('Accept' => 'application/json');
        $request->header('X-MailWatch-API-Key' => $self->{api_key});
        $request->header('X-MailWatch-Contract-Version' => '1');
        $request->header('X-Request-ID' => $request_id);
        $request->header('If-None-Match' => $etag) if defined $etag && $etag ne '';

        my $response = $self->{user_agent}->request($request);
        if ($response->code == 304) {
            return {
                not_modified => 1,
                etag         => $etag,
            };
        }
        if ($response->is_success) {
            if (length($response->content) > $self->{api_snapshot_max_bytes}) {
                $self->{api_logger}->(
                    'error',
                    "MailWatch API snapshot exceeded the configured response limit. [request_id=$request_id]"
                );

                return;
            }

            my $snapshot = eval { decode_json($response->content) };
            unless (ref $snapshot eq 'HASH') {
                $self->{api_logger}->(
                    'error',
                    "MailWatch API returned an invalid JSON snapshot. [request_id=$request_id]"
                );

                return;
            }

            return {
                not_modified => 0,
                etag         => $response->header('ETag'),
                snapshot     => $snapshot,
            };
        }

        unless (_is_retryable_status($response->code)) {
            $self->{api_logger}->(
                'error',
                'MailWatch API rejected the snapshot request: ' . $response->status_line
                    . ". [request_id=$request_id]"
            );

            return;
        }

        $self->{api_logger}->(
            'warn',
            'MailWatch API snapshot request failed: ' . $response->status_line
                . ". [request_id=$request_id]"
        );
        if ($attempt < $self->{api_max_retries}) {
            my $delay = _api_retry_delay($self, $attempt);
            $self->{sleeper}->($delay);
        }
    }

    $self->{api_logger}->(
        'error',
        "MailWatch API snapshot request failed after $self->{api_max_retries} attempts. [request_id=$request_id]"
    );

    return;
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

    die 'Missing MailWatch API ingestion endpoint' unless defined $self->{api_endpoint};

    my $request_id = _request_id($self);
    my $retry_count = 0;
    while ($retry_count < $self->{api_max_retries}) {
        my $json_data = encode_json($message);
        my $request = HTTP::Request->new(POST => $self->{api_endpoint});
        $request->content_type('application/json');
        $request->header('X-MailWatch-API-Key' => $self->{api_key});
        $request->header('X-Request-ID' => $request_id);
        my $idempotency_key = _idempotency_key($message);
        $request->header('Idempotency-Key' => $idempotency_key)
            if defined $idempotency_key;
        $request->content($json_data);

        my $response = $self->{user_agent}->request($request);
        if ($response->is_success) {
            $self->{api_logger}->(
                'info',
                "$$message{id}: Logged to MailWatch API [request_id=$request_id]"
            );

            return 1;
        }

        unless (_is_retryable_status($response->code)) {
            $self->{api_logger}->(
                'error',
                "$$message{id}: MailWatch API rejected message: " . $response->status_line
                    . "; not retrying. [request_id=$request_id]"
            );

            return 0;
        }

        $self->{api_logger}->(
            'warn',
            "$$message{id}: Failed to log to MailWatch API: " . $response->status_line
                . " [request_id=$request_id]"
        );
        ++$retry_count;
        if ($retry_count < $self->{api_max_retries}) {
            my $delay = _api_retry_delay($self, $retry_count);
            $self->{api_logger}->('warn', "$$message{id}: Retrying in $delay seconds...");
            $self->{sleeper}->($delay);
        } else {
            $self->{api_logger}->(
                'error',
                "$$message{id}: Failed to log to MailWatch API after $self->{api_max_retries} attempts. "
                    . "[request_id=$request_id]"
            );
        }
    }

    return 0;
}

sub _request_id {
    my ($self) = @_;

    my $request_id = $self->{request_id_generator}->();
    die 'MailWatch request ID generator returned an unsafe value'
        unless defined $request_id && $request_id =~ /\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/;

    return $request_id;
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
