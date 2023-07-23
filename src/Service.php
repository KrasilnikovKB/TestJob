<?php

namespace App;

use App\atol\Atol;
use GuzzleHttp\Exception\GuzzleException;
use Redis;

class Service
{

    public function __construct(
        private readonly Repository $repository,
        private readonly Atol       $atol,
        private readonly Redis      $redis) {
    }

    /**
     * @return string id зарегистрированного документа
     * @throws HasDocumentWithSameEmailException
     * @throws RegisterDocumentException
     * @throws GuzzleException
     */
    public function registerDocumentSell(string $email, string $sno, string $inn, string $payment_address): string
    {
        $document_id = $this->repository->findDocumentByEmail($email);
        if (!empty($document_id)) {
            throw new HasDocumentWithSameEmailException('Document for this email already exist'); // странное бизнес-правило, но раз надо так надо
        }

        $id = self::uuidV4(); // таки сгенерируем свой id документа, а то как-то вообще непонятно ТЗ… всё крутится вокруг одного документа
        $atol_result = $this->atol->sell($id, $email, $sno, $inn, $payment_address);

        if ($atol_result['status'] !== Atol::STATUS_WAIT) {
            throw new RegisterDocumentException('Register document failed', $atol_result['error']);
        }

        $this->repository->createDocument(
            $id,
            $atol_result['uuid'],
            $atol_result['status'],
            $email,
            json_encode($atol_result, JSON_UNESCAPED_UNICODE)
        );

        return $id;
    }

    /**
     * @throws DocumentNotFoundException|GuzzleException
     */
    public function checkDocumentStatus(string $id): array
    {
        $document = $this->repository->findDocumentOrFail($id);

        if ($document['status'] === Atol::STATUS_DONE) {
            return json_decode($document['answer'], true);
        }

        $atol_result = $this->atol->report($document['external_id']);
        $this->repository->updateDocument($id, $atol_result['status'], json_encode($atol_result, JSON_UNESCAPED_UNICODE));

        return $atol_result;
    }

    private static function uuidV4(): string
    {
        $hexData  = bin2hex(random_bytes(16));
        $parts    = str_split($hexData, 4);
        $parts[3] = '4' . substr($parts[3], 1);
        $parts[4] = '8' . substr($parts[4], 1);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            $parts
        );
    }
}
