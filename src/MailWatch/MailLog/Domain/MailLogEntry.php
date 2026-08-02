<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Domain;

final class MailLogEntry
{
    private const BOOLEAN_FIELDS = [
        'isspam',
        'ishigh',
        'issaspam',
        'isrblspam',
        'spamallowlisted',
        'spamblocklisted',
        'virusinfected',
        'otherinfected',
        'ismcp',
        'ishighmcp',
        'issamcp',
        'mcpallowlisted',
        'mcpblocklisted',
        'quarantined',
    ];

    private const KNOWN_FIELDS = [
        'timestamp', 'id', 'size', 'from', 'from_domain', 'to', 'to_domain', 'subject', 'clientip',
        'archiveplaces', 'isspam', 'ishigh', 'issaspam', 'isrblspam', 'spamallowlisted',
        'spamblocklisted', 'sascore', 'spamreport', 'virusinfected', 'nameinfected', 'otherinfected',
        'reports', 'ismcp', 'ishighmcp', 'issamcp', 'mcpallowlisted', 'mcpblocklisted', 'mcpsascore',
        'mcpreport', 'hostname', 'date', 'time', 'headers', 'quarantined', 'rblspamreport', 'token',
        'messageid',
    ];

    public $timestamp;
    public $id;
    public $size;
    public $from;
    public $from_domain;
    public $to;
    public $to_domain;
    public $subject;
    public $clientip;
    public $archiveplaces;
    public $isspam;
    public $ishigh;
    public $issaspam;
    public $isrblspam;
    public $spamallowlisted;
    public $spamblocklisted;
    public $sascore;
    public $spamreport;
    public $virusinfected;
    public $nameinfected;
    public $otherinfected;
    public $reports;
    public $ismcp;
    public $ishighmcp;
    public $issamcp;
    public $mcpallowlisted;
    public $mcpblocklisted;
    public $mcpsascore;
    public $mcpreport;
    public $hostname;
    public $date;
    public $time;
    public $headers;
    public $quarantined;
    public $rblspamreport;
    public $token;
    public $messageid;

    /** @var list<string> */
    private array $observations = [];

    public function __construct(array $data)
    {
        $this->assertKnownFieldsAreScalar($data);

        $this->timestamp = $this->stringValue($data, 'timestamp', null);
        $this->id = $this->stringValue($data, 'id', null);
        $this->size = $this->numericValue($data, 'size', 0, true);
        $this->from = $this->stringValue($data, 'from', '');
        $this->from_domain = $this->stringValue($data, 'from_domain', '');
        $this->to = $this->stringValue($data, 'to', '');
        $this->to_domain = $this->stringValue($data, 'to_domain', '');
        $this->subject = $this->stringValue($data, 'subject', '');
        $this->clientip = $this->stringValue($data, 'clientip', '');
        $this->archiveplaces = $this->stringValue($data, 'archiveplaces', '');

        foreach (self::BOOLEAN_FIELDS as $field) {
            $this->{$field} = $this->booleanValue($data, $field);
        }

        $this->sascore = $this->numericValue($data, 'sascore', 0.0);
        $this->spamreport = $this->stringValue($data, 'spamreport', '');
        $this->nameinfected = $this->numericValue($data, 'nameinfected', 0, true);
        $this->reports = $this->stringValue($data, 'reports', '');
        $this->mcpsascore = $this->numericValue($data, 'mcpsascore', 0.0);
        $this->mcpreport = $this->stringValue($data, 'mcpreport', '');
        $this->hostname = $this->stringValue($data, 'hostname', '');
        $this->date = $this->stringValue($data, 'date', null);
        $this->time = $this->stringValue($data, 'time', null);
        $this->headers = $this->stringValue($data, 'headers', '');
        $this->rblspamreport = $this->stringValue($data, 'rblspamreport', '');
        $this->token = $this->stringValue($data, 'token', '');
        $this->messageid = $this->stringValue($data, 'messageid', '');

        $this->observeNonCanonicalFormats();
    }

    public function isValid(): bool
    {
        return null !== $this->id && '' !== $this->id;
    }

    /**
     * @return list<string>
     */
    public function observations(): array
    {
        return $this->observations;
    }

    private function assertKnownFieldsAreScalar(array $data): void
    {
        foreach (self::KNOWN_FIELDS as $field) {
            if (array_key_exists($field, $data) && null !== $data[$field] && !is_scalar($data[$field])) {
                throw new \InvalidArgumentException("Field $field must be scalar");
            }
        }
    }

    private function stringValue(array $data, string $field, ?string $default): ?string
    {
        if (!array_key_exists($field, $data) || null === $data[$field]) {
            return $default;
        }

        return (string)$data[$field];
    }

    private function numericValue(array $data, string $field, int|float $default, bool $integer = false): int|float
    {
        if (!array_key_exists($field, $data) || null === $data[$field] || '' === $data[$field]) {
            return $default;
        }
        if (!is_numeric($data[$field])) {
            $this->observations[] = "$field contained a non-numeric scalar and used its compatibility default";

            return $default;
        }

        if ($integer && (string)(int)$data[$field] !== (string)$data[$field]) {
            $this->observations[] = "$field contained a non-integer numeric representation";
        }

        return $integer ? (int)$data[$field] : (float)$data[$field];
    }

    private function booleanValue(array $data, string $field): int
    {
        if (!array_key_exists($field, $data) || null === $data[$field] || '' === $data[$field]) {
            return 0;
        }
        if (is_bool($data[$field])) {
            return (int)$data[$field];
        }

        $value = strtolower(trim((string)$data[$field]));
        if (in_array($value, ['y', 'yes', 'true', 'on'], true)) {
            return 1;
        }
        if (in_array($value, ['n', 'no', 'false', 'off'], true)) {
            return 0;
        }
        if (is_numeric($value)) {
            $numericValue = (int)$value;
            if (0 !== $numericValue && 1 !== $numericValue) {
                $this->observations[] = "$field used a non-canonical numeric boolean";
            }

            return $numericValue;
        }

        $this->observations[] = "$field contained an unknown boolean representation and used its compatibility default";

        return 0;
    }

    private function observeNonCanonicalFormats(): void
    {
        if (null !== $this->timestamp && 1 !== preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->timestamp)) {
            $this->observations[] = 'timestamp used a non-canonical format';
        }
        if (null !== $this->date && 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
            $this->observations[] = 'date used a non-canonical format';
        }
        if (null !== $this->time && 1 !== preg_match('/^\d{2}:\d{2}:\d{2}$/', $this->time)) {
            $this->observations[] = 'time used a non-canonical format';
        }
        if ('' !== $this->clientip && false === filter_var($this->clientip, FILTER_VALIDATE_IP)) {
            $this->observations[] = 'clientip used a non-canonical format';
        }
    }
}
