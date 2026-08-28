<?php

namespace Webkul\Warehouse\Services;

use InvalidArgumentException;

/**
 * Minimal QR Code Model 2 SVG generator for internal asset labels.
 *
 * Design scope:
 * - Byte mode
 * - Error correction level L
 * - Version 1 for payload <= 17 ASCII bytes
 * - Version 2 for payload <= 32 ASCII bytes
 *
 * Asset codes such as CAM-0001, PRN-0001, LGT-0002 and LAP-451-002
 * fit comfortably in this range.
 */
class QrCodeService
{
    protected const ECC_LEVEL_BITS = 0b01; // L

    protected const VERSION_CONFIG = [
        1 => [
            'size'           => 21,
            'data_codewords' => 19,
            'ecc_codewords'  => 7,
            'capacity_bytes' => 17,
        ],

        2 => [
            'size'           => 25,
            'data_codewords' => 34,
            'ecc_codewords'  => 10,
            'capacity_bytes' => 32,
        ],
    ];

    /**
     * Create an SVG QR image.
     */
    public function svg(
        string $value,
        int $scale = 8,
        int $quietZone = 4
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                'QR payload tidak boleh kosong.'
            );
        }

        if (preg_match('/[^\x20-\x7E]/', $value)) {
            throw new InvalidArgumentException(
                'QR asset label hanya mendukung kode ASCII printable.'
            );
        }

        $length = strlen($value);

        $version = match (true) {
            $length <= self::VERSION_CONFIG[1]['capacity_bytes'] => 1,
            $length <= self::VERSION_CONFIG[2]['capacity_bytes'] => 2,

            default => throw new InvalidArgumentException(
                'Asset Code terlalu panjang untuk QR label internal. Maksimal 32 karakter ASCII.'
            ),
        };

        $matrix = $this->matrix(
            $value,
            $version
        );

        $size = count($matrix);
        $fullSize = $size + ($quietZone * 2);
        $pixelSize = $fullSize * max($scale, 1);

        $rects = [];

        foreach ($matrix as $row => $columns) {
            foreach ($columns as $column => $dark) {
                if (! $dark) {
                    continue;
                }

                $rects[] = sprintf(
                    '<rect x="%d" y="%d" width="1" height="1"/>',
                    $column + $quietZone,
                    $row + $quietZone
                );
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" role="img" aria-label="%3$s" shape-rendering="crispEdges"><rect width="100%%" height="100%%" fill="white"/><g fill="black">%4$s</g></svg>',
            $fullSize,
            $pixelSize,
            htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_XML1,
                'UTF-8'
            ),
            implode('', $rects)
        );
    }

    /**
     * Generate the QR module matrix.
     */
    public function matrix(
        string $value,
        int $version
    ): array {
        if (! isset(self::VERSION_CONFIG[$version])) {
            throw new InvalidArgumentException(
                'QR version tidak didukung.'
            );
        }

        $config = self::VERSION_CONFIG[$version];

        if (strlen($value) > $config['capacity_bytes']) {
            throw new InvalidArgumentException(
                'Payload melebihi kapasitas QR version.'
            );
        }

        $dataCodewords = $this->dataCodewords(
            $value,
            $version,
            $config['data_codewords']
        );

        $eccCodewords = $this->reedSolomonRemainder(
            $dataCodewords,
            $config['ecc_codewords']
        );

        $allCodewords = array_merge(
            $dataCodewords,
            $eccCodewords
        );

        $bits = [];

        foreach ($allCodewords as $codeword) {
            $this->appendBits(
                $bits,
                $codeword,
                8
            );
        }

        /*
         * QR Model 2 versions 1-2 have 0 remainder bits.
         */
        $bestMatrix = null;
        $bestPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            [$modules, $reserved] = $this->baseMatrix(
                $version
            );

            $this->placeData(
                $modules,
                $reserved,
                $bits,
                $mask
            );

            $this->placeFormatBits(
                $modules,
                $reserved,
                $mask
            );

            $penalty = $this->penaltyScore(
                $modules
            );

            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMatrix = $modules;
            }
        }

        return $bestMatrix;
    }

    /**
     * Byte mode QR payload.
     */
    protected function dataCodewords(
        string $value,
        int $version,
        int $dataCodewordCount
    ): array {
        $bits = [];

        // Mode indicator: 0100 = byte mode.
        $this->appendBits(
            $bits,
            0b0100,
            4
        );

        // Version 1-9 byte-mode character count uses 8 bits.
        $this->appendBits(
            $bits,
            strlen($value),
            8
        );

        foreach (str_split($value) as $character) {
            $this->appendBits(
                $bits,
                ord($character),
                8
            );
        }

        $capacityBits = $dataCodewordCount * 8;

        $terminator = min(
            4,
            $capacityBits - count($bits)
        );

        for ($i = 0; $i < $terminator; $i++) {
            $bits[] = 0;
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];

        foreach (array_chunk($bits, 8) as $chunk) {
            $valueByte = 0;

            foreach ($chunk as $bit) {
                $valueByte = ($valueByte << 1) | $bit;
            }

            $codewords[] = $valueByte;
        }

        $pads = [
            0xEC,
            0x11,
        ];

        $padIndex = 0;

        while (count($codewords) < $dataCodewordCount) {
            $codewords[] = $pads[$padIndex % 2];
            $padIndex++;
        }

        return $codewords;
    }

    /**
     * Reed-Solomon error correction over GF(256), primitive polynomial 0x11D.
     */
    protected function reedSolomonRemainder(
        array $data,
        int $degree
    ): array {
        $generator = $this->reedSolomonGenerator(
            $degree
        );

        $result = array_fill(
            0,
            $degree,
            0
        );

        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];

            array_shift($result);
            $result[] = 0;

            foreach ($generator as $index => $coefficient) {
                $result[$index] ^= $this->gfMultiply(
                    $coefficient,
                    $factor
                );
            }
        }

        return $result;
    }

    protected function reedSolomonGenerator(
        int $degree
    ): array {
        $result = array_fill(
            0,
            $degree,
            0
        );

        $result[$degree - 1] = 1;

        $root = 1;

        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->gfMultiply(
                    $result[$j],
                    $root
                );

                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }

            $root = $this->gfMultiply(
                $root,
                0x02
            );
        }

        return $result;
    }

    protected function gfMultiply(
        int $x,
        int $y
    ): int {
        $z = 0;

        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1)
                ^ (($z >> 7) * 0x11D);

            if ((($y >> $i) & 1) !== 0) {
                $z ^= $x;
            }
        }

        return $z & 0xFF;
    }

    /**
     * Create function patterns and reserve non-data modules.
     */
    protected function baseMatrix(
        int $version
    ): array {
        $size = self::VERSION_CONFIG[$version]['size'];

        $modules = array_fill(
            0,
            $size,
            array_fill(
                0,
                $size,
                false
            )
        );

        $reserved = array_fill(
            0,
            $size,
            array_fill(
                0,
                $size,
                false
            )
        );

        $this->drawFinder(
            $modules,
            $reserved,
            3,
            3
        );

        $this->drawFinder(
            $modules,
            $reserved,
            $size - 4,
            3
        );

        $this->drawFinder(
            $modules,
            $reserved,
            3,
            $size - 4
        );

        // Timing patterns.
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;

            $this->setFunction(
                $modules,
                $reserved,
                6,
                $i,
                $dark
            );

            $this->setFunction(
                $modules,
                $reserved,
                $i,
                6,
                $dark
            );
        }

        // Alignment pattern for version 2.
        if ($version === 2) {
            $this->drawAlignment(
                $modules,
                $reserved,
                18,
                18
            );
        }

        // Reserve format info modules.
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // Dark module at row (4*version + 9), column 8.
        $this->setFunction(
            $modules,
            $reserved,
            8,
            4 * $version + 9,
            true
        );

        return [
            $modules,
            $reserved,
        ];
    }

    /**
     * Finder pattern including its separator.
     *
     * Coordinates are finder center.
     */
    protected function drawFinder(
        array &$modules,
        array &$reserved,
        int $centerX,
        int $centerY
    ): void {
        $size = count($modules);

        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $centerX + $dx;
                $y = $centerY + $dy;

                if (
                    $x < 0
                    || $y < 0
                    || $x >= $size
                    || $y >= $size
                ) {
                    continue;
                }

                $distance = max(
                    abs($dx),
                    abs($dy)
                );

                $dark = (
                    $distance !== 2
                    && $distance !== 4
                );

                $this->setFunction(
                    $modules,
                    $reserved,
                    $x,
                    $y,
                    $dark
                );
            }
        }
    }

    protected function drawAlignment(
        array &$modules,
        array &$reserved,
        int $centerX,
        int $centerY
    ): void {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $distance = max(
                    abs($dx),
                    abs($dy)
                );

                $this->setFunction(
                    $modules,
                    $reserved,
                    $centerX + $dx,
                    $centerY + $dy,
                    $distance !== 1
                );
            }
        }
    }

    protected function setFunction(
        array &$modules,
        array &$reserved,
        int $x,
        int $y,
        bool $dark
    ): void {
        $modules[$y][$x] = $dark;
        $reserved[$y][$x] = true;
    }

    /**
     * Zig-zag QR data placement with mask.
     */
    protected function placeData(
        array &$modules,
        array $reserved,
        array $bits,
        int $mask
    ): void {
        $size = count($modules);
        $bitIndex = 0;
        $upward = true;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($vertical = 0; $vertical < $size; $vertical++) {
                $y = $upward
                    ? $size - 1 - $vertical
                    : $vertical;

                for ($columnOffset = 0; $columnOffset < 2; $columnOffset++) {
                    $x = $right - $columnOffset;

                    if ($reserved[$y][$x]) {
                        continue;
                    }

                    $bit = $bits[$bitIndex] ?? 0;
                    $bitIndex++;

                    if ($this->maskCondition(
                        $mask,
                        $x,
                        $y
                    )) {
                        $bit ^= 1;
                    }

                    $modules[$y][$x] = $bit === 1;
                }
            }

            $upward = ! $upward;
        }
    }

    /**
     * QR mask patterns.
     */
    protected function maskCondition(
        int $mask,
        int $x,
        int $y
    ): bool {
        return match ($mask) {
            0 => (($x + $y) % 2) === 0,

            1 => ($y % 2) === 0,

            2 => ($x % 3) === 0,

            3 => (($x + $y) % 3) === 0,

            4 => (
                (
                    intdiv($y, 2)
                    + intdiv($x, 3)
                ) % 2
            ) === 0,

            5 => (
                (
                    ($x * $y) % 2
                    + ($x * $y) % 3
                ) === 0
            ),

            6 => (
                (
                    (
                        ($x * $y) % 2
                        + ($x * $y) % 3
                    ) % 2
                ) === 0
            ),

            7 => (
                (
                    (
                        ($x + $y) % 2
                        + ($x * $y) % 3
                    ) % 2
                ) === 0
            ),

            default => false,
        };
    }

    /**
     * Write 15-bit format information.
     */
    protected function placeFormatBits(
        array &$modules,
        array &$reserved,
        int $mask
    ): void {
        $size = count($modules);
        $data = (self::ECC_LEVEL_BITS << 3)
            | $mask;

        $bits = $this->formatBits(
            $data
        );

        $getBit = static fn (
            int $index
        ): bool => (($bits >> $index) & 1) !== 0;

        /*
         * First copy around top-left finder.
         */
        for ($i = 0; $i <= 5; $i++) {
            $modules[$i][8] = $getBit($i);
        }

        $modules[7][8] = $getBit(6);
        $modules[8][8] = $getBit(7);
        $modules[8][7] = $getBit(8);

        for ($i = 9; $i < 15; $i++) {
            $modules[8][14 - $i] = $getBit($i);
        }

        /*
         * Second copy.
         */
        for ($i = 0; $i < 8; $i++) {
            $modules[8][$size - 1 - $i] = $getBit($i);
        }

        for ($i = 8; $i < 15; $i++) {
            $modules[$size - 15 + $i][8] = $getBit($i);
        }

        /*
         * Fixed dark module is always dark.
         */
        $modules[$size - 8][8] = true;
    }

    protected function formatBits(
        int $data
    ): int {
        $remainder = $data << 10;
        $generator = 0x537;

        while ($this->bitLength($remainder)
            >= $this->bitLength($generator)
        ) {
            $remainder ^= $generator
                << (
                    $this->bitLength($remainder)
                    - $this->bitLength($generator)
                );
        }

        return (
            (($data << 10) | $remainder)
            ^ 0x5412
        ) & 0x7FFF;
    }

    protected function bitLength(
        int $value
    ): int {
        $length = 0;

        while ($value > 0) {
            $length++;
            $value >>= 1;
        }

        return $length;
    }

    /**
     * Standard QR mask penalty score.
     */
    protected function penaltyScore(
        array $modules
    ): int {
        $size = count($modules);
        $score = 0;

        // Rule 1: runs in rows and columns.
        for ($axis = 0; $axis < 2; $axis++) {
            for ($line = 0; $line < $size; $line++) {
                $runColor = null;
                $runLength = 0;

                for ($position = 0; $position < $size; $position++) {
                    $color = $axis === 0
                        ? $modules[$line][$position]
                        : $modules[$position][$line];

                    if ($color === $runColor) {
                        $runLength++;

                        if ($runLength === 5) {
                            $score += 3;
                        } elseif ($runLength > 5) {
                            $score++;
                        }
                    } else {
                        $runColor = $color;
                        $runLength = 1;
                    }
                }
            }
        }

        // Rule 2: 2x2 blocks.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $color = $modules[$y][$x];

                if (
                    $modules[$y][$x + 1] === $color
                    && $modules[$y + 1][$x] === $color
                    && $modules[$y + 1][$x + 1] === $color
                ) {
                    $score += 3;
                }
            }
        }

        // Rule 3: finder-like patterns in rows and columns.
        $patternA = [
            true,
            false,
            true,
            true,
            true,
            false,
            true,
            false,
            false,
            false,
            false,
        ];

        $patternB = [
            false,
            false,
            false,
            false,
            true,
            false,
            true,
            true,
            true,
            false,
            true,
        ];

        for ($axis = 0; $axis < 2; $axis++) {
            for ($line = 0; $line < $size; $line++) {
                $sequence = [];

                for ($position = 0; $position < $size; $position++) {
                    $sequence[] = $axis === 0
                        ? $modules[$line][$position]
                        : $modules[$position][$line];
                }

                for ($start = 0; $start <= $size - 11; $start++) {
                    $slice = array_slice(
                        $sequence,
                        $start,
                        11
                    );

                    if (
                        $slice === $patternA
                        || $slice === $patternB
                    ) {
                        $score += 40;
                    }
                }
            }
        }

        // Rule 4: dark/light balance.
        $darkModules = 0;

        foreach ($modules as $row) {
            foreach ($row as $dark) {
                if ($dark) {
                    $darkModules++;
                }
            }
        }

        $total = $size * $size;
        $darkPercent = ($darkModules * 100)
            / $total;

        $score += (int) (
            floor(
                abs($darkPercent - 50) / 5
            ) * 10
        );

        return $score;
    }

    protected function appendBits(
        array &$bits,
        int $value,
        int $length
    ): void {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = (
                ($value >> $i) & 1
            );
        }
    }
}
