<?php
declare(strict_types=1);

namespace Api\V2\Actions;

use JsonSerializable;

class ActionPayload implements JsonSerializable
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var array|object|null
     */
    private $data;
    /**
     * @var array|object|null
     */
    private $meta;

    /**
     * @var ActionError|null
     */
    private $error;

    /**
     * @param int                   $statusCode
     * @param array|object|null     $data
     * @param ActionError|null      $error
     */
    public function __construct(
        int $statusCode = 200,
        $data = null,
        $meta = null,
        ?ActionError $error = null
    ) {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->meta = $meta;
        $this->error = $error;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array|null|object
     */
    public function getData()
    {
        return $this->data;
    }
    /**
     * @return array|null|object
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * @return ActionError|null
     */
    public function getError(): ?ActionError
    {
        return $this->error;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $payload = [
            'statusCode' => $this->statusCode,
        ];

        $payload['meta'] = $this->meta;
        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
