<?php
declare(strict_types=1);

namespace Ttp;

/** What a route produced. The front controller is the only thing that emits it. */
final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
        public bool $cacheable = true,
    ) {
    }

    /** @param array<string,string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $body, string $type, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => $type]);
    }

    public static function redirect(string $location, int $status = 301): self
    {
        return new self($status, '', [
            'Location'      => $location,
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ], false);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        echo $this->body;
    }
}
