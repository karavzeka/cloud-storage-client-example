<?php
declare(strict_types=1);

namespace Kluatr\ClientSelectelStorage\Http;

/**
 * Simple response class
 */
class Response
{
    /**
     * @var int Status code
     */
    private $statusCode = 200;

    /**
     * @var array Array of headers
     */
    private $headers = [];

    /**
     * @var string Response body
     */
    private $body = '';

    /**
     * Returns the value of header
     *
     * @param string $key
     * @return string
     */
    public function getHeader(string $key): string
    {
        return $this->headers[$key];
    }

    /**
     * Sets the header
     *
     * @param string $key
     * @param string $value
     * @return Response
     */
    public function setHeader(string $key, string $value): Response
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Checks if the header exists
     *
     * @param string $key
     * @return bool
     */
    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    /**
     * Returns the status of response
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Sets the status of response
     *
     * @param int $statusCode
     * @return Response
     */
    public function setStatusCode(int $statusCode): Response
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Returns the body of response
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Sets the body of response
     *
     * @param string $body
     * @return Response
     */
    public function setBody(string $body): Response
    {
        $this->body = $body;
        return $this;
    }
}