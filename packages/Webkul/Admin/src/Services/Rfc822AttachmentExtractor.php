<?php

namespace Webkul\Admin\Services;

class Rfc822AttachmentExtractor
{
    private int $maxAttachmentBytes = 10485760; // 10 MB each

    private int $maxAttachmentCount = 20;

    public function extract(string $raw): array
    {
        [$headerText, $body] = $this->splitHeaderBody($raw);

        $headers = $this->parseHeaders($headerText);

        $contentType =
            $headers['content-type'][0]
            ?? 'text/plain';

        $mime =
            $this->parseContentType($contentType);

        $attachments = [];

        $this->walk(
            $mime,
            $headers,
            $body,
            $attachments
        );

        return array_slice(
            $attachments,
            0,
            $this->maxAttachmentCount
        );
    }

    private function walk(
        array $mime,
        array $headers,
        string $body,
        array &$attachments
    ): void {
        if (
            str_starts_with($mime['type'], 'multipart/')
            && ! empty($mime['boundary'])
        ) {
            foreach (
                $this->splitMultipart(
                    $body,
                    $mime['boundary']
                )
                as $partRaw
            ) {
                [$partHeaderText, $partBody] =
                    $this->splitHeaderBody($partRaw);

                $partHeaders =
                    $this->parseHeaders($partHeaderText);

                $partMime =
                    $this->parseContentType(
                        $partHeaders['content-type'][0]
                        ?? 'application/octet-stream'
                    );

                $this->walk(
                    $partMime,
                    $partHeaders,
                    $partBody,
                    $attachments
                );

                if (
                    count($attachments)
                    >= $this->maxAttachmentCount
                ) {
                    return;
                }
            }

            return;
        }

        $dispositionHeader =
            $headers['content-disposition'][0]
            ?? '';

        $disposition =
            strtolower(
                trim(
                    explode(
                        ';',
                        $dispositionHeader,
                        2
                    )[0]
                    ?? ''
                )
            );

        $dispositionParams =
            $this->parseParameters(
                $dispositionHeader
            );

        $contentTypeParams =
            $mime['parameters'];

        $filename =
            $dispositionParams['filename']
            ?? $contentTypeParams['name']
            ?? null;

        $contentId =
            trim(
                (string) (
                    $headers['content-id'][0]
                    ?? ''
                ),
                " \t\n\r\0\x0B<>"
            );

        $isAttachment =
            $filename !== null
            || $disposition === 'attachment'
            || (
                $disposition === 'inline'
                && $contentId !== ''
            );

        if (! $isAttachment) {
            return;
        }

        $encoding =
            strtolower(
                trim(
                    (string) (
                        $headers['content-transfer-encoding'][0]
                        ?? ''
                    )
                )
            );

        $data =
            $this->decodeTransfer(
                $body,
                $encoding
            );

        if ($data === '') {
            return;
        }

        if (
            strlen($data)
            > $this->maxAttachmentBytes
        ) {
            return;
        }

        $filename =
            $this->decodeHeader(
                (string) (
                    $filename
                    ?: 'attachment.bin'
                )
            );

        $filename =
            $this->safeFilename(
                $filename
            );

        $attachments[] = [
            'filename' => $filename,
            'mime_type' => $mime['type'],
            'size' => strlen($data),
            'disposition' =>
                $disposition !== ''
                    ? $disposition
                    : 'attachment',
            'content_id' =>
                $contentId !== ''
                    ? $contentId
                    : null,
            'data' => $data,
        ];
    }

    private function splitHeaderBody(string $raw): array
    {
        $position = strpos($raw, "\r\n\r\n");
        $separatorLength = 4;

        if ($position === false) {
            $position = strpos($raw, "\n\n");
            $separatorLength = 2;
        }

        if ($position === false) {
            return [$raw, ''];
        }

        return [
            substr($raw, 0, $position),
            substr($raw, $position + $separatorLength),
        ];
    }

    private function parseHeaders(string $headerText): array
    {
        $normalized =
            preg_replace(
                "/\r?\n[ \t]+/",
                ' ',
                $headerText
            )
            ?? $headerText;

        $headers = [];

        foreach (
            preg_split("/\r?\n/", $normalized)
            ?: []
            as $line
        ) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] =
                explode(':', $line, 2);

            $headers[
                strtolower(trim($name))
            ][] =
                trim($value);
        }

        return $headers;
    }

    private function parseContentType(string $value): array
    {
        $parts =
            preg_split('/;\s*/', $value)
            ?: [];

        $type =
            strtolower(
                trim(
                    array_shift($parts)
                    ?: 'application/octet-stream'
                )
            );

        return [
            'type' => $type,
            'parameters' =>
                $this->parseParameters($value),
            'boundary' =>
                $this->parseParameters($value)['boundary']
                ?? null,
        ];
    }

    private function parseParameters(string $value): array
    {
        $parameters = [];

        if (
            preg_match_all(
                '/;\s*([A-Za-z0-9_\-*]+)\s*=\s*(?:"([^"]*)"|([^;]*))/',
                $value,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $key =
                    strtolower(
                        trim($match[1])
                    );

                $rawValue =
                    trim(
                        (string) (
                            $match[2] !== ''
                                ? $match[2]
                                : $match[3]
                        )
                    );

                if (str_ends_with($key, '*')) {
                    $key =
                        rtrim($key, '*');

                    if (
                        preg_match(
                            "/^[^']*'[^']*'(.*)$/",
                            $rawValue,
                            $encoded
                        )
                    ) {
                        $rawValue =
                            rawurldecode(
                                $encoded[1]
                            );
                    }
                }

                $parameters[$key] =
                    trim(
                        $rawValue,
                        " \t\n\r\0\x0B\"'"
                    );
            }
        }

        return $parameters;
    }

    private function splitMultipart(
        string $body,
        string $boundary
    ): array {
        $chunks =
            explode(
                '--'.$boundary,
                $body
            );

        $parts = [];

        foreach ($chunks as $chunk) {
            $chunk =
                ltrim(
                    $chunk,
                    "\r\n"
                );

            if (
                $chunk === ''
                || str_starts_with(
                    $chunk,
                    '--'
                )
            ) {
                continue;
            }

            $chunk =
                preg_replace(
                    "/\r?\n$/",
                    '',
                    $chunk
                )
                ?? $chunk;

            if (trim($chunk) !== '') {
                $parts[] = $chunk;
            }
        }

        return $parts;
    }

    private function decodeTransfer(
        string $body,
        string $encoding
    ): string {
        return match ($encoding) {
            'base64' =>
                (
                    base64_decode(
                        preg_replace(
                            '/\s+/',
                            '',
                            $body
                        )
                        ?? $body,
                        true
                    )
                    ?: ''
                ),

            'quoted-printable' =>
                quoted_printable_decode($body),

            default =>
                $body,
        };
    }

    private function decodeHeader(string $value): string
    {
        if (
            $value !== ''
            && function_exists(
                'mb_decode_mimeheader'
            )
        ) {
            try {
                $decoded =
                    mb_decode_mimeheader($value);

                if (
                    is_string($decoded)
                    && $decoded !== ''
                ) {
                    return $decoded;
                }
            } catch (\Throwable) {
                // Continue.
            }
        }

        if (
            $value !== ''
            && function_exists(
                'iconv_mime_decode'
            )
        ) {
            $decoded =
                @iconv_mime_decode(
                    $value,
                    ICONV_MIME_DECODE_CONTINUE_ON_ERROR,
                    'UTF-8'
                );

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $value;
    }

    private function safeFilename(string $name): string
    {
        $name =
            str_replace(
                ['\\', '/', "\0"],
                '_',
                trim($name)
            );

        $name =
            preg_replace(
                '/[\x00-\x1F\x7F]/u',
                '',
                $name
            )
            ?? $name;

        if ($name === '') {
            return 'attachment.bin';
        }

        return mb_substr(
            $name,
            0,
            240
        );
    }
}
