<?php

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use Redis;
use RedisException;
use Workerman\MySQL\Connection as DB;

class Repository
{
    private const REDIS_KEY_EMAIL_PREFIX = 'document:email:';
    private const REDIS_KEY_EMAIL_TTL = 24 * 60 * 60; // Без погружения в контекст - толковое значение не выбрать. Пусть будет сутки.

    private const REDIS_KEY_ID_PREFIX = 'document:id:';
    private const REDIS_KEY_ID_TTL = 60 * 60; // Без погружения в контекст - толковое значение не выбрать. Пусть будет один час.

    public function __construct(
        private readonly DB $db,
        private readonly Redis $redis
    ) {
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function findDocumentOrFail(string $id): array
    {
        try {
            $document = $this->redis->get(self::REDIS_KEY_ID_PREFIX . $id);
            if (!empty($document)) {
                return json_decode($document, JSON_UNESCAPED_UNICODE);
            }
        } catch (RedisException) {
        }

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
        try {
            $document_id = $this->redis->get(self::REDIS_KEY_EMAIL_PREFIX . $email);
            if (!empty($document_id)) {
                return $document_id;
            }
        } catch (RedisException) {
        }

        return $this->db
            ->select('id')
            ->from('documents')
            ->where('email = :email')
            ->bindValues([
                'email' => $email
            ])
            ->single();
    }

    public function createDocument(string $id, string $external_id, string $status, ?string $email, string $payload): void
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

        try {
            if (!empty($email)) {
                $this->redis->setex(self::REDIS_KEY_EMAIL_PREFIX . $email, self::REDIS_KEY_EMAIL_TTL, $id);
            }
        } catch (RedisException) {
        }
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

        try {
            $this->redis->setex(self::REDIS_KEY_ID_PREFIX . $id, self::REDIS_KEY_ID_TTL, $payload);
        } catch (RedisException) {
        }
    }
}
