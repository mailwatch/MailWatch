<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Application;

/**
 * The quarantine of another MailScanner node.
 *
 * A message is held on the machine that processed it, so an operator acting
 * on one processed elsewhere is really asking that machine to act. The remote
 * runs the same operations against its own storage, which is why the shapes
 * crossing this boundary are the ones the local operations already use.
 */
interface RemoteQuarantineNode
{
    /**
     * @return list<array<string, mixed>>
     *
     * @throws RemoteQuarantineFailure
     */
    public function items(string $host, string $messageId): array;

    /**
     * @param list<array<string, mixed>> $items     the listing the operator acted on
     * @param list<int>                  $selection which of them, by position
     *
     * @throws RemoteQuarantineFailure
     */
    public function release(string $host, array $items, array $selection, string $recipients): string;

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int>                  $selection
     *
     * @throws RemoteQuarantineFailure
     */
    public function learn(string $host, array $items, array $selection, string $action): string;

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int>                  $selection
     *
     * @throws RemoteQuarantineFailure
     */
    public function delete(string $host, array $items, array $selection): string;
}
