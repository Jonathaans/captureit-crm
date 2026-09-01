<?php

namespace Webkul\Admin\Services;

use RuntimeException;
use Webkul\Admin\Models\UserEmailAccount;

class PurePhpImapClient
{
    private $stream = null;

    private int $tagCounter = 0;

    private int $maxLiteralBytes = 15728640; // 15 MB per message

    public function connect(
        UserEmailAccount $account,
        string $folder = 'INBOX'
    ): self {
        $this->disconnect();

        $host =
            trim(
                (string) $account->imap_host
            );

        $port =
            (int) $account->imap_port;

        $encryption =
            strtolower(
                trim(
                    (string) $account->imap_encryption
                )
            );

        if (
            $host === ''
            || $port < 1
            || $port > 65535
        ) {
            throw new RuntimeException(
                'IMAP host / port tidak valid.'
            );
        }

        if (
            ! in_array(
                $encryption,
                [
                    'ssl',
                    'tls',
                    'none',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'IMAP encryption harus ssl, tls, atau none.'
            );
        }

        $validateCertificate =
            (bool) $account->imap_validate_certificate;

        $context =
            stream_context_create([
                'ssl' => [
                    'verify_peer' =>
                        $validateCertificate,

                    'verify_peer_name' =>
                        $validateCertificate,

                    'allow_self_signed' =>
                        ! $validateCertificate,

                    'peer_name' =>
                        $host,

                    'SNI_enabled' =>
                        true,
                ],
            ]);

        $scheme =
            $encryption === 'ssl'
                ? 'ssl'
                : 'tcp';

        $target =
            sprintf(
                '%s://%s:%d',
                $scheme,
                $host,
                $port
            );

        $errno = 0;
        $error = '';

        $stream =
            @stream_socket_client(
                $target,
                $errno,
                $error,
                20,
                STREAM_CLIENT_CONNECT,
                $context
            );

        if (! is_resource($stream)) {
            throw new RuntimeException(
                'Tidak dapat terhubung ke IMAP server: '
                .(
                    $error !== ''
                        ? $error
                        : 'connection failed'
                )
                .' ('
                .$errno
                .')'
            );
        }

        stream_set_timeout(
            $stream,
            30
        );

        $this->stream =
            $stream;

        $greeting =
            $this->readLine();

        if (
            ! str_starts_with(
                strtoupper(
                    ltrim(
                        $greeting
                    )
                ),
                '* OK'
            )
            && ! str_starts_with(
                strtoupper(
                    ltrim(
                        $greeting
                    )
                ),
                '* PREAUTH'
            )
        ) {
            $this->disconnect();

            throw new RuntimeException(
                'IMAP greeting tidak valid: '
                .trim(
                    $greeting
                )
            );
        }

        if ($encryption === 'tls') {
            $this->command(
                'STARTTLS'
            );

            $cryptoMethod =
                defined(
                    'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT'
                )
                    ? (
                        STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                        | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                    )
                    : STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

            $enabled =
                @stream_socket_enable_crypto(
                    $this->stream,
                    true,
                    $cryptoMethod
                );

            if ($enabled !== true) {
                $this->disconnect();

                throw new RuntimeException(
                    'IMAP STARTTLS negotiation gagal.'
                );
            }
        }

        $this->command(
            'LOGIN '
            .$this->quote(
                (string) $account->imap_username
            )
            .' '
            .$this->quote(
                (string) $account->imap_password
            )
        );

        $this->select(
            $folder
        );

        return $this;
    }

    public function select(
        string $folder = 'INBOX'
    ): array {
        return $this->command(
            'SELECT '
            .$this->quote(
                $folder
            )
        );
    }

    public function searchUids(
        int $afterUid = 0
    ): array {
        $criteria =
            $afterUid > 0
                ? 'UID '
                    .(
                        $afterUid + 1
                    )
                    .':*'
                : 'ALL';

        $response =
            $this->command(
                'UID SEARCH '
                .$criteria
            );

        $uids = [];

        foreach (
            $response['lines']
            as $line
        ) {
            if (
                preg_match(
                    '/^\*\s+SEARCH(?:\s+(.*))?$/i',
                    trim(
                        $line
                    ),
                    $matches
                )
            ) {
                $tail =
                    trim(
                        (string) (
                            $matches[1]
                            ?? ''
                        )
                    );

                if ($tail === '') {
                    continue;
                }

                foreach (
                    preg_split(
                        '/\s+/',
                        $tail
                    )
                    ?: []
                    as $uid
                ) {
                    if (
                        ctype_digit(
                            $uid
                        )
                    ) {
                        $uids[] =
                            (int) $uid;
                    }
                }
            }
        }

        $uids =
            array_values(
                array_unique(
                    array_filter(
                        $uids,
                        fn ($uid) =>
                            $uid > $afterUid
                    )
                )
            );

        sort(
            $uids,
            SORT_NUMERIC
        );

        return $uids;
    }

    public function fetchRawMessage(
        int $uid
    ): string {
        if ($uid < 1) {
            throw new RuntimeException(
                'IMAP UID tidak valid.'
            );
        }

        $response =
            $this->command(
                'UID FETCH '
                .$uid
                .' (BODY.PEEK[])'
            );

        if (
            empty(
                $response['literals']
            )
        ) {
            throw new RuntimeException(
                'IMAP message UID '
                .$uid
                .' tidak mengembalikan body literal.'
            );
        }

        /*
         * BODY.PEEK[] produces one full RFC822 literal.
         */
        return (string) $response[
            'literals'
        ][0];
    }

    public function disconnect(): void
    {
        if (
            is_resource(
                $this->stream
            )
        ) {
            try {
                $this->command(
                    'LOGOUT',
                    false
                );
            } catch (\Throwable) {
                // Ignore disconnect errors.
            }

            @fclose(
                $this->stream
            );
        }

        $this->stream =
            null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function command(
        string $command,
        bool $expectOk = true
    ): array {
        $this->assertConnected();

        $tag =
            'A'
            .str_pad(
                (string) ++$this->tagCounter,
                5,
                '0',
                STR_PAD_LEFT
            );

        $written =
            @fwrite(
                $this->stream,
                $tag
                .' '
                .$command
                ."\r\n"
            );

        if ($written === false) {
            throw new RuntimeException(
                'Gagal menulis command ke IMAP server.'
            );
        }

        $response =
            $this->readTaggedResponse(
                $tag
            );

        if (
            $expectOk
            && strtoupper(
                $response['status']
            ) !== 'OK'
        ) {
            throw new RuntimeException(
                'IMAP command gagal: '
                .$response['status']
                .' '
                .$response['message']
            );
        }

        return $response;
    }

    private function readTaggedResponse(
        string $tag
    ): array {
        $lines = [];
        $literals = [];
        $status = '';
        $message = '';

        while (true) {
            $line =
                $this->readLine();

            $lines[] =
                $line;

            if (
                preg_match(
                    '/\{(\d+)\+?\}\r?\n$/',
                    $line,
                    $literalMatch
                )
            ) {
                $length =
                    (int) $literalMatch[1];

                if (
                    $length >
                    $this->maxLiteralBytes
                ) {
                    throw new RuntimeException(
                        'Email terlalu besar untuk V1.1 IMAP sync: '
                        .$length
                        .' bytes.'
                    );
                }

                $literals[] =
                    $this->readBytes(
                        $length
                    );
            }

            if (
                preg_match(
                    '/^'
                    .preg_quote(
                        $tag,
                        '/'
                    )
                    .'\s+([A-Z]+)(?:\s+(.*))?$/i',
                    trim(
                        $line
                    ),
                    $matches
                )
            ) {
                $status =
                    strtoupper(
                        $matches[1]
                    );

                $message =
                    trim(
                        (string) (
                            $matches[2]
                            ?? ''
                        )
                    );

                break;
            }
        }

        return [
            'lines' => $lines,
            'literals' => $literals,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function readLine(): string
    {
        $this->assertConnected();

        $line =
            @fgets(
                $this->stream,
                65536
            );

        if ($line === false) {
            $meta =
                stream_get_meta_data(
                    $this->stream
                );

            if (
                ! empty(
                    $meta['timed_out']
                )
            ) {
                throw new RuntimeException(
                    'IMAP server timeout.'
                );
            }

            throw new RuntimeException(
                'Koneksi IMAP ditutup oleh server.'
            );
        }

        return $line;
    }

    private function readBytes(
        int $length
    ): string {
        $data = '';

        while (
            strlen(
                $data
            ) < $length
        ) {
            $remaining =
                $length
                - strlen(
                    $data
                );

            $chunk =
                @fread(
                    $this->stream,
                    min(
                        8192,
                        $remaining
                    )
                );

            if (
                $chunk === false
                || $chunk === ''
            ) {
                $meta =
                    stream_get_meta_data(
                        $this->stream
                    );

                if (
                    ! empty(
                        $meta['timed_out']
                    )
                ) {
                    throw new RuntimeException(
                        'Timeout saat membaca email IMAP.'
                    );
                }

                throw new RuntimeException(
                    'IMAP literal terputus sebelum selesai.'
                );
            }

            $data .=
                $chunk;
        }

        return $data;
    }

    private function quote(
        string $value
    ): string {
        return '"'
            .str_replace(
                [
                    '\\',
                    '"',
                    "\r",
                    "\n",
                ],
                [
                    '\\\\',
                    '\\"',
                    '',
                    '',
                ],
                $value
            )
            .'"';
    }

    private function assertConnected(): void
    {
        if (
            ! is_resource(
                $this->stream
            )
        ) {
            throw new RuntimeException(
                'IMAP client belum terhubung.'
            );
        }
    }
}
