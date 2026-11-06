<?php
/**
 * QRIS Helper - EMVCo Tag-Length-Value (TLV) Parser & Dynamic Converter
 * Sesuai Standar Bank Indonesia (PADG No. 21/18/PADG/2019) & EMV QRCPS
 */
class QrisHelper
{
    /**
     * Kamus Definisi Tag EMVCo QRIS Standar Bank Indonesia
     */
    public static array $tagNames = [
        '00' => 'Payload Format Indicator',
        '01' => 'Point of Initiation Method (11=Statis, 12=Dinamis)',
        '26' => 'Merchant Account Info (Acquirer / DANA)',
        '27' => 'Merchant Account Info (Acquirer 2)',
        '28' => 'Merchant Account Info (Acquirer 3)',
        '29' => 'Merchant Account Info (Acquirer 4)',
        '30' => 'Merchant Account Info (Acquirer 5)',
        '51' => 'Merchant Account Info (NMID QRIS Nasional / ASPI)',
        '52' => 'Merchant Category Code (MCC)',
        '53' => 'Transaction Currency (360 = IDR)',
        '54' => 'Transaction Amount (Nominal Tagihan)',
        '55' => 'Tip or Convenience Indicator',
        '56' => 'Value of Convenience Fee Fixed',
        '57' => 'Value of Convenience Fee Percentage',
        '58' => 'Country Code (ID)',
        '59' => 'Merchant Name (Nama Toko/Merchant)',
        '60' => 'Merchant City (Kota/Kabupaten)',
        '61' => 'Postal Code (Kode Pos)',
        '62' => 'Additional Data Field Template',
        '63' => 'CRC16 Checksum'
    ];

    /**
     * Parse raw QRIS string into associative array of tag => value
     */
    public static function parse(string $qris): array
    {
        $tags = [];
        $i = 0;
        $len = strlen($qris);

        while ($i < $len) {
            if ($i + 4 > $len) break;
            $tag = substr($qris, $i, 2);
            $tagLen = intval(substr($qris, $i + 2, 2));
            $i += 4;
            $val = substr($qris, $i, $tagLen);
            $i += $tagLen;
            $tags[$tag] = $val;
        }

        return $tags;
    }

    /**
     * Hitung CRC16-CCITT (Polinomial 0x1021, Init 0xFFFF)
     * Standar EMVCo QRCPS
     */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return strtoupper(sprintf('%04X', $crc));
    }

    /**
     * Validasi apakah CRC Tag 63 pada QRIS valid
     */
    public static function validateCrc(string $qris): bool
    {
        if (strlen($qris) < 8) return false;
        $dataWithoutCrc = substr($qris, 0, -4);
        $expectedCrc = strtoupper(substr($qris, -4));
        $calculatedCrc = self::crc16($dataWithoutCrc);
        return $expectedCrc === $calculatedCrc;
    }

    /**
     * Ubah QRIS Statis menjadi QRIS Dinamis dengan nominal dan fee opsional
     */
    public static function toDynamic(
        string $rawQris,
        int|float $amount,
        ?string $feeType = null,
        int|float $feeValue = 0
    ): array {
        $tags = self::parse($rawQris);

        // Ambil info merchant terlebih dahulu sebelum tags diubah/unset
        $merchantName = $tags['59'] ?? 'Merchant QRIS';
        $merchantCity = $tags['60'] ?? '';
        $postalCode   = $tags['61'] ?? '';
        $nmid = '';
        if (isset($tags['51'])) {
            $tag51Sub = self::parse($tags['51']);
            if (isset($tag51Sub['02'])) {
                $nmid = $tag51Sub['02'];
            }
        }

        // 1. Ubah Tag 01 (Point of Initiation Method) dari 11 (Statis) jadi 12 (Dinamis)
        $tags['01'] = '12';

        // 2. Format Nominal Tag 54
        $amountInt = (int) round($amount);
        $tags['54'] = (string) $amountInt;

        // 3. Tangani Fee / Biaya Layanan jika ada
        unset($tags['55'], $tags['56'], $tags['57']);
        if ($feeType === 'fixed' && $feeValue > 0) {
            $tags['55'] = '02'; // Fixed convenience fee
            $tags['56'] = (string) ((int) round($feeValue));
        } elseif ($feeType === 'percentage' && $feeValue > 0) {
            $tags['55'] = '03'; // Percentage convenience fee
            $tags['57'] = number_format($feeValue, 2, '.', '');
        }

        // Hapus CRC lama jika ada
        unset($tags['63']);

        // Urutan standar tag EMVCo untuk memastikan kompatibilitas semua e-wallet & m-banking
        $orderedKeys = [
            '00', '01',
            '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39',
            '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51',
            '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62'
        ];

        $payload = '';
        foreach ($orderedKeys as $k) {
            if (isset($tags[$k])) {
                $val = $tags[$k];
                $len = sprintf('%02d', strlen($val));
                $payload .= $k . $len . $val;
                unset($tags[$k]);
            }
        }

        // Sisipkan tag sisa lainnya jika ada yang belum ter-cover
        foreach ($tags as $k => $val) {
            $len = sprintf('%02d', strlen($val));
            $payload .= $k . $len . $val;
        }

        // Tambahkan Tag 63 (CRC16)
        $payloadWithCrcTag = $payload . '6304';
        $crc = self::crc16($payloadWithCrcTag);
        $finalQris = $payloadWithCrcTag . $crc;

        return [
            'success' => true,
            'qris_string' => $finalQris,
            'amount' => $amountInt,
            'formatted_amount' => 'Rp ' . number_format($amountInt, 0, ',', '.'),
            'merchant_name' => $merchantName,
            'merchant_city' => $merchantCity,
            'postal_code' => $postalCode,
            'nmid' => $nmid,
            'crc' => $crc
        ];
    }

    /**
     * Inspeksi mendalam struktur QRIS (Tag-Length-Value)
     */
    public static function inspect(string $qris): array
    {
        $tags = self::parse($qris);
        $isValidCrc = self::validateCrc($qris);
        $expectedCrc = substr($qris, -4);
        $calculatedCrc = self::crc16(substr($qris, 0, -4));

        $details = [];
        foreach ($tags as $tag => $val) {
            $name = self::$tagNames[$tag] ?? "Tag Custom ($tag)";
            $subTags = [];
            // Parse sub-tags for 26-51 and 62
            if ((intval($tag) >= 26 && intval($tag) <= 51) || $tag === '62') {
                $parsedSub = self::parse($val);
                foreach ($parsedSub as $subKey => $subVal) {
                    $subTags[] = [
                        'tag' => $subKey,
                        'length' => strlen($subVal),
                        'value' => $subVal
                    ];
                }
            }

            $details[] = [
                'tag' => $tag,
                'name' => $name,
                'length' => strlen($val),
                'value' => $val,
                'sub_tags' => $subTags
            ];
        }

        $nmid = '';
        if (isset($tags['51'])) {
            $tag51Sub = self::parse($tags['51']);
            $nmid = $tag51Sub['02'] ?? '';
        }

        return [
            'is_valid_crc' => $isValidCrc,
            'expected_crc' => $expectedCrc,
            'calculated_crc' => $calculatedCrc,
            'is_dynamic' => ($tags['01'] ?? '') === '12',
            'amount' => isset($tags['54']) ? (int)$tags['54'] : null,
            'merchant_name' => $tags['59'] ?? '',
            'merchant_city' => $tags['60'] ?? '',
            'postal_code' => $tags['61'] ?? '',
            'nmid' => $nmid,
            'tags' => $details
        ];
    }
}
