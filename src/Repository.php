<?php

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use Workerman\MySQL\Connection as DB;

class Repository
{
    public function __construct(private readonly DB $db)
    {
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function findDocumentOrFail(string $id): array
    {
        $document = $this->db
            ->select(['id', 'external_id', 'status', 'email', 'answer', 'created_at', 'updated_at'])
            ->from('documents')
            ->where('id = :id')
            ->bindValues([
                'id' => $id
            ])
            ->row();

        if (empty($document)) {
            throw new DocumentNotFoundException('Document not found');
        }

        return $document;
    }

    public function findDocumentByEmail(string $email): string
    {
        return $this->db
            ->select('id')
            ->from('documents')
            ->where('email = :email')
            ->bindValues([
                'email' => $email
            ])
            ->single();
    }

    public function createDocument(string $id, string $external_id, string $status, string $email, string $payload): void
    {
        $this->db
            ->insert('documents')
            ->cols([
                'id'          => $id,
                'external_id' => $external_id,
                'status'      => $status,
                'email'       => $email,
                'answer'      => $payload,

            ])
            ->query();
    }

    public function updateDocument(string $id, string $status, string $payload): void
    {
        $this->db
            ->update('documents')
            ->cols([
                'status'     => $status,
                'answer'     => $payload,
                'updated_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ])
            ->where('id = :id')
            ->bindValues([
                'id' => $id
            ])
            ->query();
    }
}
