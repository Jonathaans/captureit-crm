<?php

namespace Webkul\Admin\Services;

class Rfc822EmailParser
{
    public function parse(
        string $raw
    ): array {
        [
            $headerText,
            $body
        ] =
            $this->splitHeaderBody(
                $raw
            );

        $headers =
            $this->parseHeaders(
                $headerText
            );

        $contentType =
            $headers['content-type'][0]
            ?? 'text/plain';

        $transferEncoding =
            strtolower(
                trim(
                    $headers[
                        'content-transfer-encoding'
                    ][0]
                    ?? ''
                )
            );

        $mime =
            $this->parseContentType(
                $contentType
            );

        $textBody = null;
        $htmlBody = null;

        $this->extractContent(
            $mime,
            $transferEncoding,
            $body,
            $textBody,
            $htmlBody
        );

        $from =
            $this->parseAddressList(
                $headers['from'][0]
                ?? ''
            );

        $to =
            $this->parseAddressList(
                implode(
                    ', ',
                    $headers['to']
                    ?? []
                )
            );

        $cc =
            $this->parseAddressList(
                implode(
                    ', ',
                    $headers['cc']
                    ?? []
                )
            );

        return [
            'message_id' =>
                $this->cleanMessageId(
                    $headers[
                        'message-id'
                    ][0]
                    ?? null
                ),

            'subject' =>
                $this->decodeHeader(
                    $headers['subject'][0]
                    ?? ''
                ),

            'date' =>
                $headers['date'][0]
                ?? null,

            'from_name' =>
                $from[0]['name']
                ?? null,

            'from_email' =>
                $from[0]['email']
                ?? null,

            'to' =>
                $to,

            'cc' =>
                $cc,

            'text_body' =>
                $textBody,

            'html_body' =>
                $htmlBody,
        ];
    }

    private function splitHeaderBody(
        string $raw
    ): array {
        $position =
            strpos(
                $raw,
                "\r\n\r\n"
            );

        $separatorLength = 4;

        if ($position === false) {
            $position =
                strpos(
                    $raw,
                    "\n\n"
                );

            $separatorLength = 2;
        }

        if ($position === false) {
            return [
                $raw,
                '',
            ];
        }

        return [
            substr(
                $raw,
                0,
                $position
            ),

            substr(
                $raw,
                $position
                + $separatorLength
            ),
        ];
    }

    private function parseHeaders(
        string $headerText
    ): array {
        $normalized =
            preg_replace(
                "/\r?\n[ \t]+/",
                ' ',
                $headerText
            )
            ?? $headerText;

        $headers = [];

        foreach (
            preg_split(
                "/\r?\n/",
                $normalized
            )
            ?: []
            as $line
        ) {
            if (
                ! str_contains(
                    $line,
                    ':'
                )
            ) {
                continue;
            }

            [
                $name,
                $value,
            ] =
                explode(
                    ':',
                    $line,
                    2
                );

            $name =
                strtolower(
                    trim(
                        $name
                    )
                );

            $value =
                trim(
                    $value
                );

            $headers[$name][] =
                $value;
        }

        return $headers;
    }

    private function parseContentType(
        string $value
    ): array {
        $parts =
            preg_split(
                '/;\s*/',
                $value
            )
            ?: [];

        $type =
            strtolower(
                trim(
                    array_shift(
                        $parts
                    )
                    ?: 'text/plain'
                )
            );

        $parameters = [];

        foreach ($parts as $part) {
            if (
                ! str_contains(
                    $part,
                    '='
                )
            ) {
                continue;
            }

            [
                $key,
                $parameterValue,
            ] =
                explode(
                    '=',
                    $part,
                    2
                );

            $parameters[
                strtolower(
                    trim(
                        $key
                    )
                )
            ] =
                trim(
                    $parameterValue,
                    " \t\n\r\0\x0B\"'"
                );
        }

        return [
            'type' =>
                $type,

            'boundary' =>
                $parameters[
                    'boundary'
                ]
                ?? null,

            'charset' =>
                $parameters[
                    'charset'
                ]
                ?? null,
        ];
    }

    private function extractContent(
        array $mime,
        string $transferEncoding,
        string $body,
        ?string &$textBody,
        ?string &$htmlBody
    ): void {
        if (
            str_starts_with(
                $mime['type'],
                'multipart/'
            )
            && ! empty(
                $mime['boundary']
            )
        ) {
            foreach (
                $this->splitMultipart(
                    $body,
                    $mime['boundary']
                )
                as $partRaw
            ) {
                [
                    $partHeaderText,
                    $partBody,
                ] =
                    $this->splitHeaderBody(
                        $partRaw
                    );

                $partHeaders =
                    $this->parseHeaders(
                        $partHeaderText
                    );

                $partMime =
                    $this->parseContentType(
                        $partHeaders[
                            'content-type'
                        ][0]
                        ?? 'text/plain'
                    );

                $partEncoding =
                    strtolower(
                        trim(
                            $partHeaders[
                                'content-transfer-encoding'
                            ][0]
                            ?? ''
                        )
                    );

                $disposition =
                    strtolower(
                        $partHeaders[
                            'content-disposition'
                        ][0]
                        ?? ''
                    );

                /*
                 * V1.1 ignores attachments.
                 */
                if (
                    str_contains(
                        $disposition,
                        'attachment'
                    )
                ) {
                    continue;
                }

                $this->extractContent(
                    $partMime,
                    $partEncoding,
                    $partBody,
                    $textBody,
                    $htmlBody
                );
            }

            return;
        }

        if (
            ! str_starts_with(
                $mime['type'],
                'text/'
            )
        ) {
            return;
        }

        $decoded =
            $this->decodeTransfer(
                $body,
                $transferEncoding
            );

        $decoded =
            $this->convertCharset(
                $decoded,
                $mime['charset']
            );

        if (
            $mime['type']
            === 'text/html'
        ) {
            if ($htmlBody === null) {
                $htmlBody =
                    $decoded;
            }

            return;
        }

        if ($textBody === null) {
            $textBody =
                $decoded;
        }
    }

    private function splitMultipart(
        string $body,
        string $boundary
    ): array {
        $delimiter =
            '--'
            .$boundary;

        $chunks =
            explode(
                $delimiter,
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

            if (
                trim(
                    $chunk
                ) !== ''
            ) {
                $parts[] =
                    $chunk;
            }
        }

        return $parts;
    }

    private function decodeTransfer(
        string $body,
        string $encoding
    ): string {
        return match (
            strtolower(
                $encoding
            )
        ) {
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
                quoted_printable_decode(
                    $body
                ),

            default =>
                $body,
        };
    }

    private function convertCharset(
        string $value,
        ?string $charset
    ): string {
        $charset =
            trim(
                (string) (
                    $charset
                    ?? ''
                )
            );

        if (
            $charset === ''
            || strcasecmp(
                $charset,
                'UTF-8'
            ) === 0
        ) {
            return $value;
        }

        if (
            function_exists(
                'mb_convert_encoding'
            )
        ) {
            try {
                return mb_convert_encoding(
                    $value,
                    'UTF-8',
                    $charset
                );
            } catch (\Throwable) {
                return $value;
            }
        }

        if (
            function_exists(
                'iconv'
            )
        ) {
            $converted =
                @iconv(
                    $charset,
                    'UTF-8//IGNORE',
                    $value
                );

            if ($converted !== false) {
                return $converted;
            }
        }

        return $value;
    }

    private function parseAddressList(
        string $value
    ): array {
        $value =
            $this->decodeHeader(
                $value
            );

        if (
            trim(
                $value
            ) === ''
        ) {
            return [];
        }

        $addresses = [];

        /*
         * Good practical coverage for:
         * Name <email@example.com>
         * "Name, Company" <email@example.com>
         * email@example.com
         */
        if (
            preg_match_all(
                '/(?:"([^"]*)"|([^,<"]+))?\s*<([^<>\s@]+@[^<>\s@]+)>|([^,\s<>]+@[^,\s<>]+)/u',
                $value,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            foreach ($matches as $match) {
                $email =
                    trim(
                        (string) (
                            $match[3]
                            ?? ''
                        )
                    );

                if ($email === '') {
                    $email =
                        trim(
                            (string) (
                                $match[4]
                                ?? ''
                            )
                        );
                }

                if (
                    $email === ''
                    || ! filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {
                    continue;
                }

                $name =
                    trim(
                        (string) (
                            $match[1]
                            ?? $match[2]
                            ?? ''
                        )
                    );

                $addresses[] = [
                    'name' =>
                        $name !== ''
                            ? $name
                            : null,

                    'email' =>
                        $email,
                ];
            }
        }

        return $addresses;
    }

    private function decodeHeader(
        string $value
    ): string {
        if ($value === '') {
            return '';
        }

        if (
            function_exists(
                'mb_decode_mimeheader'
            )
        ) {
            try {
                $decoded =
                    mb_decode_mimeheader(
                        $value
                    );

                if (
                    is_string(
                        $decoded
                    )
                    && $decoded !== ''
                ) {
                    return $decoded;
                }
            } catch (\Throwable) {
                // Continue.
            }
        }

        if (
            function_exists(
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

    private function cleanMessageId(
        ?string $messageId
    ): ?string {
        $messageId =
            trim(
                (string) (
                    $messageId
                    ?? ''
                )
            );

        if ($messageId === '') {
            return null;
        }

        return trim(
            $messageId,
            '<>'
        );
    }
}
