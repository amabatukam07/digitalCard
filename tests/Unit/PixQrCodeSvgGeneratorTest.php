<?php

namespace Tests\Unit;

use App\Services\Payment\PixQrCodeSvgGenerator;
use App\Services\Payment\SimulatedPixGateway;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PixQrCodeSvgGeneratorTest extends TestCase
{
    public function test_encodes_versions_and_svg_with_iso18004_structure(): void
    {
        // Versão 1 (<= 14 bytes -> 21x21)
        $matrixV1 = PixQrCodeSvgGenerator::encodeMatrix('HELLO WORLD');
        $this->assertCount(21, $matrixV1);
        $this->assertCount(21, $matrixV1[0]);

        // Versão 4 (~50 bytes -> 33x33)
        $matrixV4 = PixQrCodeSvgGenerator::encodeMatrix(str_repeat('A', 50));
        $this->assertCount(33, $matrixV4);

        // BR Code PIX real NexoSupri (~145 bytes -> Versão 8 ou 9)
        $brCode = SimulatedPixGateway::generateBrCode(
            pixKey: 'pix@nexosupri.com.br',
            merchantName: 'NEXOSUPRI SUPRIMENTOS',
            merchantCity: 'SAO PAULO',
            amount: 1000.00,
            txid: 'NSPX123456789012345678901'
        );

        $matrixPix = PixQrCodeSvgGenerator::encodeMatrix($brCode);
        $size = count($matrixPix);
        $this->assertGreaterThanOrEqual(49, $size);

        // Valida os 3 Finder Patterns 7x7 + dark module + timing patterns
        $this->assertFinderPatternAt($matrixPix, 0, 0);
        $this->assertFinderPatternAt($matrixPix, 0, $size - 7);
        $this->assertFinderPatternAt($matrixPix, $size - 7, 0);
        $this->assertTrue($matrixPix[$size - 8][8]);

        for ($i = 8; $i < $size - 8; $i++) {
            $expected = ($i % 2) === 0;
            $this->assertSame($expected, $matrixPix[6][$i]);
            $this->assertSame($expected, $matrixPix[$i][6]);
        }

        // Valida consistência entre as duas cópias da Format Information
        $copy1 = [
            $matrixPix[8][0], $matrixPix[8][1], $matrixPix[8][2], $matrixPix[8][3],
            $matrixPix[8][4], $matrixPix[8][5], $matrixPix[8][7], $matrixPix[8][8],
            $matrixPix[7][8], $matrixPix[5][8], $matrixPix[4][8], $matrixPix[3][8],
            $matrixPix[2][8], $matrixPix[1][8], $matrixPix[0][8],
        ];
        $copy2 = [
            $matrixPix[$size - 1][8], $matrixPix[$size - 2][8], $matrixPix[$size - 3][8], $matrixPix[$size - 4][8],
            $matrixPix[$size - 5][8], $matrixPix[$size - 6][8], $matrixPix[$size - 7][8], $matrixPix[8][$size - 8],
            $matrixPix[8][$size - 7], $matrixPix[8][$size - 6], $matrixPix[8][$size - 5], $matrixPix[8][$size - 4],
            $matrixPix[8][$size - 3], $matrixPix[8][$size - 2], $matrixPix[8][$size - 1],
        ];
        $this->assertSame($copy1, $copy2);

        // SVG determinístico com quiet zone de 4 módulos
        $expectedViewBox = $size + 8;
        $svg1 = PixQrCodeSvgGenerator::renderSvg($brCode, 176);
        $svg2 = PixQrCodeSvgGenerator::renderSvg($brCode, 176);

        $this->assertSame($svg1, $svg2);
        $this->assertStringContainsString("viewBox=\"0 0 {$expectedViewBox} {$expectedViewBox}\"", $svg1);
        $this->assertStringContainsString('width="176" height="176"', $svg1);
    }

    public function test_throws_exception_when_payload_exceeds_supported_capacity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PixQrCodeSvgGenerator::encodeMatrix(str_repeat('X', 300));
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private function assertFinderPatternAt(array $matrix, int $startRow, int $startCol): void
    {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $isOuter = $r === 0 || $r === 6 || $c === 0 || $c === 6;
                $isInner = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                $this->assertSame($isOuter || $isInner, $matrix[$startRow + $r][$startCol + $c]);
            }
        }
    }
}
