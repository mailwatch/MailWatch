<?php

class MailLogEntry
{
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

    public function __construct($data)
    {
        $this->timestamp = $data['timestamp'] ?? null;
        $this->id = $data['id'] ?? null;
        $this->size = $data['size'] ?? null;
        $this->from = $data['from'] ?? null;
        $this->from_domain = $data['from_domain'] ?? null;
        $this->to = $data['to'] ?? null;
        $this->to_domain = $data['to_domain'] ?? null;
        $this->subject = $data['subject'] ?? null;
        $this->clientip = $data['clientip'] ?? null;
        $this->archiveplaces = $data['archiveplaces'] ?? null;
        $this->isspam = $data['isspam'] ?? 0;
        $this->ishigh = $data['ishigh'] ?? 0;
        $this->issaspam = $data['issaspam'] ?? 0;
        $this->isrblspam = $data['isrblspam'] ?? 0;
        $this->spamallowlisted = $data['spamallowlisted'] ?? 0;
        $this->spamblocklisted = $data['spamblocklisted'] ?? 0;
        $this->sascore = $data['sascore'] ?? 0.00;
        $this->spamreport = $data['spamreport'] ?? '';
        $this->virusinfected = $data['virusinfected'] ?? 0;
        $this->nameinfected = $data['nameinfected'] ?? 0;
        $this->otherinfected = $data['otherinfected'] ?? 0;
        $this->reports = $data['reports'] ?? '';
        $this->ismcp = $data['ismcp'] ?? 0;
        $this->ishighmcp = $data['ishighmcp'] ?? 0;
        $this->issamcp = $data['issamcp'] ?? 0;
        $this->mcpallowlisted = $data['mcpallowlisted'] ?? 0;
        $this->mcpblocklisted = $data['mcpblocklisted'] ?? 0;
        $this->mcpsascore = $data['mcpsascore'] ?? 0.00;
        $this->mcpreport = $data['mcpreport'] ?? '';
        $this->hostname = $data['hostname'] ?? '';
        $this->date = $data['date'] ?? null;
        $this->time = $data['time'] ?? null;
        $this->headers = $data['headers'] ?? '';
        $this->quarantined = $data['quarantined'] ?? 0;
        $this->rblspamreport = $data['rblspamreport'] ?? '';
        $this->token = $data['token'] ?? '';
        $this->messageid = $data['messageid'] ?? '';
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        if (
            empty($this->timestamp)
            || empty($this->id)
            || empty($this->size)
            || empty($this->from)
            || empty($this->to)
            || empty($this->subject)
            || empty($this->clientip)
        ) {
            return false;
        }

        return true;
    }
}
