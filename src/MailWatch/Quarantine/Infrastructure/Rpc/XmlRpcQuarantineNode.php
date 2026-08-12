<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Infrastructure\Rpc;

use MailWatch\Quarantine\Application\RemoteQuarantineFailure;
use MailWatch\Quarantine\Application\RemoteQuarantineNode;

/**
 * The node-to-node protocol MailWatch has always used.
 *
 * The vendored XML-RPC library is loaded by the legacy bootstrap rather than
 * by Composer, which is why this class is only ever reached from a page: it
 * is the last thing in the quarantine that depends on it, and the seam that
 * lets section 12's REST replacement arrive as a second adapter rather than
 * as another rewrite of the operations.
 *
 * @see docs/mailscanner-api-operations.md for the direction of travel
 */
final readonly class XmlRpcQuarantineNode implements RemoteQuarantineNode
{
    public function __construct(
        private string $path,
        private int $port,
        private string $scheme,
        private bool $debug,
        private bool $verifyPeer,
    ) {
    }

    public function items(string $host, string $messageId): array
    {
        $decoded = $this->call($host, 'quarantine_list_items', [new \xmlrpcval($messageId)]);

        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    public function release(string $host, array $items, array $selection, string $recipients): string
    {
        return (string)$this->call($host, 'quarantine_release', [
            self::itemsValue($items),
            self::selectionValue($selection),
            new \xmlrpcval($recipients, 'string'),
        ]);
    }

    public function learn(string $host, array $items, array $selection, string $action): string
    {
        return (string)$this->call($host, 'quarantine_learn', [
            self::itemsValue($items),
            self::selectionValue($selection),
            new \xmlrpcval($action, 'string'),
        ]);
    }

    public function delete(string $host, array $items, array $selection): string
    {
        return (string)$this->call($host, 'quarantine_delete', [
            self::itemsValue($items),
            self::selectionValue($selection),
        ]);
    }

    /**
     * @param list<\xmlrpcval> $parameters
     *
     * @throws RemoteQuarantineFailure
     */
    private function call(string $host, string $method, array $parameters): mixed
    {
        $client = new \xmlrpc_client($this->path . '/rpcserver.php', $host, $this->port);
        if ($this->debug) {
            $client->setDebug(1);
        }

        // Without verification the hop between nodes resists eavesdropping but
        // not an attacker sitting in the middle of it. Installations wired with
        // self-signed certificates turn it off; 2 is the host check that also
        // matches the certificate's common name.
        $client->setSSLVerifyPeer($this->verifyPeer);
        $client->setSSLVerifyHost($this->verifyPeer ? 2 : 0);

        $response = $client->send(new \xmlrpcmsg($method, $parameters), 0, $this->scheme);

        if (0 !== $response->faultCode()) {
            throw new RemoteQuarantineFailure($response->faultString());
        }

        return php_xmlrpc_decode($response->value());
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private static function itemsValue(array $items): \xmlrpcval
    {
        $structs = [];
        foreach ($items as $item) {
            $fields = [];
            foreach ($item as $name => $value) {
                $fields[$name] = new \xmlrpcval(\is_scalar($value) ? (string)$value : '');
            }
            $structs[] = new \xmlrpcval($fields, 'struct');
        }

        return new \xmlrpcval($structs, 'array');
    }

    /**
     * @param list<int> $selection
     */
    private static function selectionValue(array $selection): \xmlrpcval
    {
        return new \xmlrpcval(
            array_map(static fn(int $position): \xmlrpcval => new \xmlrpcval((string)$position), $selection),
            'array',
        );
    }
}
