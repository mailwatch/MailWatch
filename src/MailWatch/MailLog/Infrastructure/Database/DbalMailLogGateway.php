<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use MailWatch\MailLog\Application\IngestionResult;
use MailWatch\MailLog\Application\MailLogGateway;
use MailWatch\MailLog\Domain\MailLogEntry;

final readonly class DbalMailLogGateway implements MailLogGateway
{
    private const INSERT = <<<'SQL'
        INSERT INTO maillog (
            timestamp, id, size, from_address, from_domain, to_address, to_domain, subject,
            clientip, archive, isspam, ishighspam, issaspam, isrblspam, spamallowlisted,
            spamblocklisted, sascore, spamreport, virusinfected, nameinfected, otherinfected,
            report, ismcp, ishighmcp, issamcp, mcpallowlisted, mcpblocklisted, mcpsascore,
            mcpreport, hostname, date, time, headers, quarantined, rblspamreport, token,
            messageid, ingestion_id
        ) VALUES (
            :timestamp, :id, :size, :from_address, :from_domain, :to_address, :to_domain, :subject,
            :clientip, :archive, :isspam, :ishighspam, :issaspam, :isrblspam, :spamallowlisted,
            :spamblocklisted, :sascore, :spamreport, :virusinfected, :nameinfected, :otherinfected,
            :report, :ismcp, :ishighmcp, :issamcp, :mcpallowlisted, :mcpblocklisted, :mcpsascore,
            :mcpreport, :hostname, :date, :time, :headers, :quarantined, :rblspamreport, :token,
            :messageid, :ingestion_id
        )
        SQL;

    public function __construct(private Connection $connection)
    {
    }

    public function store(MailLogEntry $entry, ?string $idempotencyKey): IngestionResult
    {
        try {
            $affectedRows = $this->connection->executeStatement(self::INSERT, [
                'timestamp' => $entry->timestamp,
                'id' => $entry->id,
                'size' => $entry->size,
                'from_address' => $entry->from,
                'from_domain' => $entry->from_domain,
                'to_address' => $entry->to,
                'to_domain' => $entry->to_domain,
                'subject' => $entry->subject,
                'clientip' => $entry->clientip,
                'archive' => $entry->archiveplaces,
                'isspam' => $entry->isspam,
                'ishighspam' => $entry->ishigh,
                'issaspam' => $entry->issaspam,
                'isrblspam' => $entry->isrblspam,
                'spamallowlisted' => $entry->spamallowlisted,
                'spamblocklisted' => $entry->spamblocklisted,
                'sascore' => $entry->sascore,
                'spamreport' => $entry->spamreport,
                'virusinfected' => $entry->virusinfected,
                'nameinfected' => $entry->nameinfected,
                'otherinfected' => $entry->otherinfected,
                'report' => $entry->reports,
                'ismcp' => $entry->ismcp,
                'ishighmcp' => $entry->ishighmcp,
                'issamcp' => $entry->issamcp,
                'mcpallowlisted' => $entry->mcpallowlisted,
                'mcpblocklisted' => $entry->mcpblocklisted,
                'mcpsascore' => $entry->mcpsascore,
                'mcpreport' => $entry->mcpreport,
                'hostname' => $entry->hostname,
                'date' => $entry->date,
                'time' => $entry->time,
                'headers' => $entry->headers,
                'quarantined' => $entry->quarantined,
                'rblspamreport' => $entry->rblspamreport,
                'token' => $entry->token,
                'messageid' => $entry->messageid,
                'ingestion_id' => $idempotencyKey,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (null !== $idempotencyKey) {
                return IngestionResult::Duplicate;
            }

            throw $exception;
        }

        if (1 !== (int)$affectedRows) {
            throw new \RuntimeException('Mail log ingestion did not insert exactly one row');
        }

        return IngestionResult::Inserted;
    }
}
