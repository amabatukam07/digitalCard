<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class PixQrCodeSvgGenerator
{
    /**
     * Capacidade e estrutura de blocos Reed-Solomon para Error Correction Level M (ISO/IEC 18004), versões 1 a 12.
     * Formato: [maxBytesModeByte, totalCodewords, ecPerBlock, g1Blocks, g1Data, g2Blocks, g2Data].
     *
     * @var array<int, array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int}>
     */
    private const VERSION_TABLE_M = [
        1 => [14, 26, 10, 1, 16, 0, 0],
        2 => [26, 44, 16, 1, 28, 0, 0],
        3 => [42, 70, 26, 1, 44, 0, 0],
        4 => [62, 100, 18, 2, 32, 0, 0],
        5 => [84, 134, 24, 2, 43, 0, 0],
        6 => [106, 172, 16, 4, 27, 0, 0],
        7 => [122, 196, 18, 4, 31, 0, 0],
        8 => [152, 242, 22, 2, 38, 2, 39],
        9 => [180, 292, 22, 3, 36, 2, 37],
        10 => [213, 346, 26, 4, 43, 1, 44],
        11 => [251, 404, 30, 1, 50, 4, 51],
        12 => [287, 466, 22, 6, 36, 2, 37],
    ];

    /**
     * Coordenadas dos padrões de alinhamento por versão.
     *
     * @var array<int, list<int>>
     */
    private const ALIGNMENT_POSITIONS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
        11 => [6, 30, 54],
        12 => [6, 32, 58],
    ];

    /**
     * Gera um SVG de QR Code ISO/IEC 18004 (Byte mode, EC Level M) determinístico e legível por leitores reais.
     */
    public static function renderSvg(string $brCodePayload, int $size = 180): string
    {
        $matrix = self::encodeMatrix($brCodePayload);
        $gridSize = count($matrix);
        $quietZone = 4;
        $totalModules = $gridSize + ($quietZone * 2);
        $rects = [];

        for ($r = 0; $r < $gridSize; $r++) {
            for ($c = 0; $c < $gridSize; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quietZone;
                    $y = $r + $quietZone;
                    $rects[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"1\" height=\"1\"/>";
                }
            }
        }

        $rectsHtml = implode('', $rects);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$totalModules} {$totalModules}" width="{$size}" height="{$size}" shape-rendering="crispEdges" role="img" aria-label="QR Code PIX">
  <rect width="{$totalModules}" height="{$totalModules}" fill="#ffffff"/>
  <g fill="#0B1120">{$rectsHtml}</g>
</svg>
SVG;
    }

    /**
     * Codifica um payload ASCII/UTF-8 na matriz binária do QR Code ISO/IEC 18004 (EC Level M).
     *
     * @return array<int, array<int, bool>>
     */
    public static function encodeMatrix(string $payload): array
    {
        $byteLength = strlen($payload);
        $version = self::selectVersion($byteLength);
        $spec = self::VERSION_TABLE_M[$version];
        $gridSize = ($version * 4) + 17;

        $dataCapacityBytes = ($spec[3] * $spec[4]) + ($spec[5] * $spec[6]);
        $dataCodewords = self::encodeDataCodewords($payload, $version, $dataCapacityBytes);
        $allCodewords = self::appendEccAndInterleave($dataCodewords, $spec);

        $bitStream = [];
        foreach ($allCodewords as $byte) {
            for ($bit = 7; $bit >= 0; $bit--) {
                $bitStream[] = (($byte >> $bit) & 1) === 1;
            }
        }

        $matrix = array_fill(0, $gridSize, array_fill(0, $gridSize, false));
        $isFunction = array_fill(0, $gridSize, array_fill(0, $gridSize, false));

        self::drawFunctionPatterns($matrix, $isFunction, $version, $gridSize);
        self::placeDataBits($matrix, $isFunction, $bitStream, $gridSize);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $isFunction, $mask, $gridSize);
            self::drawFormatInfo($candidate, $mask, $gridSize);
            if ($version >= 7) {
                self::drawVersionInfo($candidate, $version, $gridSize);
            }

            $penalty = self::calculatePenalty($candidate, $gridSize);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
        }

        $finalMatrix = self::applyMask($matrix, $isFunction, $bestMask, $gridSize);
        self::drawFormatInfo($finalMatrix, $bestMask, $gridSize);
        if ($version >= 7) {
            self::drawVersionInfo($finalMatrix, $version, $gridSize);
        }

        return $finalMatrix;
    }

    private static function selectVersion(int $byteLength): int
    {
        foreach (self::VERSION_TABLE_M as $version => $spec) {
            if ($byteLength <= $spec[0]) {
                return $version;
            }
        }

        throw new InvalidArgumentException("Payload excede a capacidade máxima suportada (287 bytes): {$byteLength} bytes.");
    }

    /**
     * @return list<int>
     */
    private static function encodeDataCodewords(string $payload, int $version, int $dataCapacityBytes): array
    {
        $bits = [];
        $pushBits = static function (int $value, int $count) use (&$bits): void {
            for ($i = $count - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };

        $byteLength = strlen($payload);
        $charCountBits = $version >= 10 ? 16 : 8;

        // Mode indicator 0100 (Byte mode) + character count
        $pushBits(0b0100, 4);
        $pushBits($byteLength, $charCountBits);

        for ($i = 0; $i < $byteLength; $i++) {
            $pushBits(ord($payload[$i]), 8);
        }

        $capacityBits = $dataCapacityBytes * 8;
        $terminatorBits = min(4, $capacityBits - count($bits));
        for ($i = 0; $i < $terminatorBits; $i++) {
            $bits[] = 0;
        }

        while ((count($bits) % 8) !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        $bitCount = count($bits);
        for ($i = 0; $i < $bitCount; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $codewords[] = $byte;
        }

        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($codewords) < $dataCapacityBytes) {
            $codewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        return $codewords;
    }

    /**
     * @param  list<int>  $dataCodewords
     * @param  array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int}  $spec
     * @return list<int>
     */
    private static function appendEccAndInterleave(array $dataCodewords, array $spec): array
    {
        [, , $ecPerBlock, $g1Blocks, $g1Data, $g2Blocks, $g2Data] = $spec;

        [$exp, $log] = self::buildGaloisTables();
        $generator = self::buildRsGenerator($ecPerBlock, $exp, $log);

        $dataBlocks = [];
        $eccBlocks = [];
        $offset = 0;

        for ($b = 0; $b < $g1Blocks; $b++) {
            $block = array_slice($dataCodewords, $offset, $g1Data);
            $offset += $g1Data;
            $dataBlocks[] = $block;
            $eccBlocks[] = self::computeRsRemainder($block, $generator, $ecPerBlock, $exp, $log);
        }

        for ($b = 0; $b < $g2Blocks; $b++) {
            $block = array_slice($dataCodewords, $offset, $g2Data);
            $offset += $g2Data;
            $dataBlocks[] = $block;
            $eccBlocks[] = self::computeRsRemainder($block, $generator, $ecPerBlock, $exp, $log);
        }

        $result = [];
        $maxDataLen = max($g1Data, $g2Data);

        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($eccBlocks as $ecc) {
                $result[] = $ecc[$i];
            }
        }

        return $result;
    }

    /**
     * @return array{0: list<int>, 1: array<int, int>}
     */
    private static function buildGaloisTables(): array
    {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if (($x & 0x100) !== 0) {
                $x ^= 0x11D;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        return [$exp, $log];
    }

    /**
     * @param  list<int>  $exp
     * @param  array<int, int>  $log
     * @return list<int>
     */
    private static function buildRsGenerator(int $degree, array $exp, array $log): array
    {
        $gen = [1];

        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                if ($coef !== 0) {
                    $next[$j + 1] ^= $exp[$log[$coef] + $i];
                }
            }
            $gen = $next;
        }

        return $gen;
    }

    /**
     * @param  list<int>  $data
     * @param  list<int>  $generator
     * @param  list<int>  $exp
     * @param  array<int, int>  $log
     * @return list<int>
     */
    private static function computeRsRemainder(array $data, array $generator, int $ecCount, array $exp, array $log): array
    {
        $msg = array_merge($data, array_fill(0, $ecCount, 0));
        $dataLen = count($data);
        $genLen = count($generator);

        for ($i = 0; $i < $dataLen; $i++) {
            $factor = $msg[$i];
            if ($factor === 0) {
                continue;
            }

            $logFactor = $log[$factor];
            for ($j = 0; $j < $genLen; $j++) {
                if ($generator[$j] !== 0) {
                    $msg[$i + $j] ^= $exp[$log[$generator[$j]] + $logFactor];
                }
            }
        }

        return array_values(array_slice($msg, $dataLen));
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     * @param  array<int, array<int, bool>>  $isFunction
     */
    private static function drawFunctionPatterns(array &$matrix, array &$isFunction, int $version, int $gridSize): void
    {
        // Finder patterns + 1px white separators
        self::placeFinderPattern($matrix, $isFunction, 0, 0, $gridSize);
        self::placeFinderPattern($matrix, $isFunction, 0, $gridSize - 7, $gridSize);
        self::placeFinderPattern($matrix, $isFunction, $gridSize - 7, 0, $gridSize);

        // Timing patterns
        for ($i = 8; $i < $gridSize - 8; $i++) {
            $dark = ($i % 2) === 0;
            $matrix[6][$i] = $dark;
            $isFunction[6][$i] = true;
            $matrix[$i][6] = $dark;
            $isFunction[$i][6] = true;
        }

        // Alignment patterns
        $positions = self::ALIGNMENT_POSITIONS[$version] ?? [];
        foreach ($positions as $rCenter) {
            foreach ($positions as $cCenter) {
                if ($isFunction[$rCenter][$cCenter]) {
                    continue;
                }
                self::placeAlignmentPattern($matrix, $isFunction, $rCenter, $cCenter);
            }
        }

        // Dark module
        $matrix[$gridSize - 8][8] = true;
        $isFunction[$gridSize - 8][8] = true;

        // Reserve Format Info cells
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $isFunction[8][$i] = true;
                $isFunction[$i][8] = true;
            }
        }
        for ($i = $gridSize - 8; $i < $gridSize; $i++) {
            $isFunction[8][$i] = true;
        }
        for ($i = $gridSize - 7; $i < $gridSize; $i++) {
            $isFunction[$i][8] = true;
        }

        // Reserve Version Info cells (version >= 7)
        if ($version >= 7) {
            for ($r = 0; $r < 6; $r++) {
                for ($c = $gridSize - 11; $c < $gridSize - 8; $c++) {
                    $isFunction[$r][$c] = true;
                    $isFunction[$c][$r] = true;
                }
            }
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     * @param  array<int, array<int, bool>>  $isFunction
     */
    private static function placeFinderPattern(array &$matrix, array &$isFunction, int $startRow, int $startCol, int $gridSize): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $startRow + $r;
                $cc = $startCol + $c;
                if ($rr < 0 || $rr >= $gridSize || $cc < 0 || $cc >= $gridSize) {
                    continue;
                }

                $isSeparator = $r === -1 || $r === 7 || $c === -1 || $c === 7;
                if ($isSeparator) {
                    $matrix[$rr][$cc] = false;
                } else {
                    $isOuterBorder = $r === 0 || $r === 6 || $c === 0 || $c === 6;
                    $isInnerCore = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                    $matrix[$rr][$cc] = $isOuterBorder || $isInnerCore;
                }

                $isFunction[$rr][$cc] = true;
            }
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     * @param  array<int, array<int, bool>>  $isFunction
     */
    private static function placeAlignmentPattern(array &$matrix, array &$isFunction, int $centerRow, int $centerCol): void
    {
        for ($dr = -2; $dr <= 2; $dr++) {
            for ($dc = -2; $dc <= 2; $dc++) {
                $dist = max(abs($dr), abs($dc));
                $matrix[$centerRow + $dr][$centerCol + $dc] = ($dist !== 1);
                $isFunction[$centerRow + $dr][$centerCol + $dc] = true;
            }
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     * @param  array<int, array<int, bool>>  $isFunction
     * @param  list<bool>  $bitStream
     */
    private static function placeDataBits(array &$matrix, array $isFunction, array $bitStream, int $gridSize): void
    {
        $bitIndex = 0;
        $totalBits = count($bitStream);
        $col = $gridSize - 1;
        $dir = -1;

        while ($col > 0) {
            if ($col === 6) {
                $col--;
            }

            $row = $dir === -1 ? $gridSize - 1 : 0;
            while ($row >= 0 && $row < $gridSize) {
                for ($offset = 0; $offset < 2; $offset++) {
                    $c = $col - $offset;
                    if (! $isFunction[$row][$c]) {
                        $matrix[$row][$c] = $bitIndex < $totalBits ? $bitStream[$bitIndex] : false;
                        $bitIndex++;
                    }
                }
                $row += $dir;
            }

            $col -= 2;
            $dir = -$dir;
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     * @param  array<int, array<int, bool>>  $isFunction
     * @return array<int, array<int, bool>>
     */
    private static function applyMask(array $matrix, array $isFunction, int $mask, int $gridSize): array
    {
        $masked = $matrix;

        for ($r = 0; $r < $gridSize; $r++) {
            for ($c = 0; $c < $gridSize; $c++) {
                if ($isFunction[$r][$c]) {
                    continue;
                }

                $invert = match ($mask) {
                    0 => (($r + $c) % 2) === 0,
                    1 => ($r % 2) === 0,
                    2 => ($c % 3) === 0,
                    3 => (($r + $c) % 3) === 0,
                    4 => ((intdiv($r, 2) + intdiv($c, 3)) % 2) === 0,
                    5 => ((($r * $c) % 2) + (($r * $c) % 3)) === 0,
                    6 => (((($r * $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    default => false,
                };

                if ($invert) {
                    $masked[$r][$c] = ! $masked[$r][$c];
                }
            }
        }

        return $masked;
    }

    /**
     * Grava os 15 bits de Format Information (EC Level M = 00 + Mask 3 bits + BCH(15,5) XOR 0x5412).
     *
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function drawFormatInfo(array &$matrix, int $mask, int $gridSize): void
    {
        // EC Level M = 0b00
        $data = $mask & 0x07;
        $rem = $data << 10;

        for ($i = 14; $i >= 10; $i--) {
            if ((($rem >> $i) & 1) !== 0) {
                $rem ^= (0x537 << ($i - 10));
            }
        }

        $formatBits = (($data << 10) | $rem) ^ 0x5412;

        $topLeftCoords = [
            14 => [8, 0],
            13 => [8, 1],
            12 => [8, 2],
            11 => [8, 3],
            10 => [8, 4],
            9 => [8, 5],
            8 => [8, 7],
            7 => [8, 8],
            6 => [7, 8],
            5 => [5, 8],
            4 => [4, 8],
            3 => [3, 8],
            2 => [2, 8],
            1 => [1, 8],
            0 => [0, 8],
        ];

        $secondCoords = [
            14 => [$gridSize - 1, 8],
            13 => [$gridSize - 2, 8],
            12 => [$gridSize - 3, 8],
            11 => [$gridSize - 4, 8],
            10 => [$gridSize - 5, 8],
            9 => [$gridSize - 6, 8],
            8 => [$gridSize - 7, 8],
            7 => [8, $gridSize - 8],
            6 => [8, $gridSize - 7],
            5 => [8, $gridSize - 6],
            4 => [8, $gridSize - 5],
            3 => [8, $gridSize - 4],
            2 => [8, $gridSize - 3],
            1 => [8, $gridSize - 2],
            0 => [8, $gridSize - 1],
        ];

        for ($bit = 0; $bit < 15; $bit++) {
            $val = (($formatBits >> $bit) & 1) === 1;
            [$r1, $c1] = $topLeftCoords[$bit];
            [$r2, $c2] = $secondCoords[$bit];
            $matrix[$r1][$c1] = $val;
            $matrix[$r2][$c2] = $val;
        }
    }

    /**
     * Grava os 18 bits de Version Information (BCH(18,6)) para versões >= 7.
     *
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function drawVersionInfo(array &$matrix, int $version, int $gridSize): void
    {
        $rem = $version << 12;

        for ($i = 17; $i >= 12; $i--) {
            if ((($rem >> $i) & 1) !== 0) {
                $rem ^= (0x1F25 << ($i - 12));
            }
        }

        $versionBits = ($version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $val = (($versionBits >> $i) & 1) === 1;
            $a = intdiv($i, 3);
            $b = $gridSize - 11 + ($i % 3);
            $matrix[$b][$a] = $val;
            $matrix[$a][$b] = $val;
        }
    }

    /**
     * Avalia penalidade ISO/IEC 18004 (N1..N4) para seleção da melhor máscara.
     *
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function calculatePenalty(array $matrix, int $gridSize): int
    {
        $penalty = 0;

        // N1: sequências horizontais e verticais >= 5 módulos da mesma cor
        for ($r = 0; $r < $gridSize; $r++) {
            $runColor = $matrix[$r][0];
            $runLen = 1;
            for ($c = 1; $c < $gridSize; $c++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                } else {
                    if ($runLen >= 5) {
                        $penalty += 3 + ($runLen - 5);
                    }
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
            if ($runLen >= 5) {
                $penalty += 3 + ($runLen - 5);
            }
        }

        for ($c = 0; $c < $gridSize; $c++) {
            $runColor = $matrix[0][$c];
            $runLen = 1;
            for ($r = 1; $r < $gridSize; $r++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                } else {
                    if ($runLen >= 5) {
                        $penalty += 3 + ($runLen - 5);
                    }
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
            if ($runLen >= 5) {
                $penalty += 3 + ($runLen - 5);
            }
        }

        // N2: blocos 2x2 da mesma cor
        for ($r = 0; $r < $gridSize - 1; $r++) {
            for ($c = 0; $c < $gridSize - 1; $c++) {
                $color = $matrix[$r][$c];
                if (
                    $matrix[$r][$c + 1] === $color
                    && $matrix[$r + 1][$c] === $color
                    && $matrix[$r + 1][$c + 1] === $color
                ) {
                    $penalty += 3;
                }
            }
        }

        // N3: padrão finder-like 1:1:3:1:1 com 4 brancos adjacentes
        $pattern1 = [true, false, true, true, true, false, true, false, false, false, false];
        $pattern2 = [false, false, false, false, true, false, true, true, true, false, true];

        for ($r = 0; $r < $gridSize; $r++) {
            for ($c = 0; $c <= $gridSize - 11; $c++) {
                $match1 = true;
                $match2 = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($matrix[$r][$c + $k] !== $pattern1[$k]) {
                        $match1 = false;
                    }
                    if ($matrix[$r][$c + $k] !== $pattern2[$k]) {
                        $match2 = false;
                    }
                    if (! $match1 && ! $match2) {
                        break;
                    }
                }
                if ($match1 || $match2) {
                    $penalty += 40;
                }
            }
        }

        for ($c = 0; $c < $gridSize; $c++) {
            for ($r = 0; $r <= $gridSize - 11; $r++) {
                $match1 = true;
                $match2 = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($matrix[$r + $k][$c] !== $pattern1[$k]) {
                        $match1 = false;
                    }
                    if ($matrix[$r + $k][$c] !== $pattern2[$k]) {
                        $match2 = false;
                    }
                    if (! $match1 && ! $match2) {
                        break;
                    }
                }
                if ($match1 || $match2) {
                    $penalty += 40;
                }
            }
        }

        // N4: proporção de módulos escuros
        $darkCount = 0;
        for ($r = 0; $r < $gridSize; $r++) {
            for ($c = 0; $c < $gridSize; $c++) {
                if ($matrix[$r][$c]) {
                    $darkCount++;
                }
            }
        }

        $totalModules = $gridSize * $gridSize;
        $k = intdiv(abs(($darkCount * 20) - ($totalModules * 10)), $totalModules);
        $penalty += $k * 10;

        return $penalty;
    }
}
