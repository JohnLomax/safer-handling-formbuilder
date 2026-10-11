<?php
declare(strict_types=1);

require_once __DIR__ . '/database_bridge.php';

/**
 * Normalise voucher codes for lookup (uppercase, no spaces).
 */
function voucherNormalizeCode(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/\s+/', '', $code);

    return is_string($code) ? $code : '';
}

function voucherPdo(): ?PDO
{
    return appDatabasePdo();
}

/**
 * Format a net amount for quoteValue fields.
 */
function voucherFormatQuoteValue(float $amount): string
{
    return number_format(max(0, $amount), 2, '.', '');
}

/**
 * Look up an active, in-date voucher by code.
 *
 * @return array{id:int,code:string,name:string,amount:float,is_active:bool,valid_from:?string,valid_until:?string,max_redemptions:?int}|null
 */
function voucherFindByCode(string $code): ?array
{
    $code = voucherNormalizeCode($code);
    if ($code === '') {
        return null;
    }

    try {
        $pdo = voucherPdo();
        if (! $pdo instanceof PDO) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT id, code, name, amount, is_active, valid_from, valid_until, max_redemptions
             FROM vouchers
             WHERE UPPER(REPLACE(code, \' \', \'\')) = :code
             LIMIT 1'
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'amount' => (float) $row['amount'],
            'is_active' => (bool) $row['is_active'],
            'valid_from' => $row['valid_from'] !== null ? (string) $row['valid_from'] : null,
            'valid_until' => $row['valid_until'] !== null ? (string) $row['valid_until'] : null,
            'max_redemptions' => $row['max_redemptions'] !== null ? (int) $row['max_redemptions'] : null,
        ];
    } catch (Throwable) {
        return null;
    }
}

function voucherRedemptionCount(int $voucherId): int
{
    try {
        $pdo = voucherPdo();
        if (! $pdo instanceof PDO) {
            return 0;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM enquiries WHERE voucher_id = :id');
        $stmt->execute([':id' => $voucherId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

/**
 * @param array{id:int,code:string,name:string,amount:float,is_active:bool,valid_from:?string,valid_until:?string,max_redemptions:?int} $voucher
 */
function voucherIsCurrentlyValid(array $voucher, ?int $excludeEnquiryId = null): bool
{
    if (empty($voucher['is_active'])) {
        return false;
    }

    $now = time();
    if (! empty($voucher['valid_from'])) {
        $from = strtotime((string) $voucher['valid_from']);
        if ($from !== false && $now < $from) {
            return false;
        }
    }
    if (! empty($voucher['valid_until'])) {
        $until = strtotime((string) $voucher['valid_until']);
        if ($until !== false && $now > $until) {
            return false;
        }
    }

    if (isset($voucher['max_redemptions']) && $voucher['max_redemptions'] !== null) {
        $count = voucherRedemptionCount((int) $voucher['id']);
        if ($excludeEnquiryId !== null) {
            try {
                $pdo = voucherPdo();
                if ($pdo instanceof PDO) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM enquiries WHERE voucher_id = :vid AND id = :eid');
                    $stmt->execute([':vid' => (int) $voucher['id'], ':eid' => $excludeEnquiryId]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        $count = max(0, $count - 1);
                    }
                }
            } catch (Throwable) {
                // keep count
            }
        }
        if ($count >= (int) $voucher['max_redemptions']) {
            return false;
        }
    }

    return true;
}

/**
 * Apply a fixed-amount voucher to a quote net total.
 *
 * @return array{
 *   ok:bool,
 *   error:?string,
 *   voucher:?array{id:int,code:string,name:string,amount:float},
 *   original:float,
 *   discount:float,
 *   final:float,
 *   original_formatted:string,
 *   final_formatted:string
 * }
 */
function voucherApplyToQuoteNet(float $originalNet, string $code, ?int $excludeEnquiryId = null): array
{
    $originalNet = round(max(0, $originalNet), 2);
    $empty = [
        'ok' => false,
        'error' => null,
        'voucher' => null,
        'original' => $originalNet,
        'discount' => 0.0,
        'final' => $originalNet,
        'original_formatted' => voucherFormatQuoteValue($originalNet),
        'final_formatted' => voucherFormatQuoteValue($originalNet),
    ];

    $code = voucherNormalizeCode($code);
    if ($code === '') {
        $empty['error'] = 'Enter a voucher code.';

        return $empty;
    }

    $voucher = voucherFindByCode($code);
    if ($voucher === null) {
        $empty['error'] = 'That voucher code was not found.';

        return $empty;
    }

    if (! voucherIsCurrentlyValid($voucher, $excludeEnquiryId)) {
        $empty['error'] = 'That voucher is not currently valid.';

        return $empty;
    }

    $discount = round(min((float) $voucher['amount'], $originalNet), 2);
    $final = round(max(0, $originalNet - $discount), 2);

    return [
        'ok' => true,
        'error' => null,
        'voucher' => [
            'id' => (int) $voucher['id'],
            'code' => (string) $voucher['code'],
            'name' => (string) $voucher['name'],
            'amount' => (float) $voucher['amount'],
        ],
        'original' => $originalNet,
        'discount' => $discount,
        'final' => $final,
        'original_formatted' => voucherFormatQuoteValue($originalNet),
        'final_formatted' => voucherFormatQuoteValue($final),
    ];
}

/**
 * Persist voucher fields on an enquiry and merge into form_data_json.
 *
 * @param array{id:int,code:string,name:string,amount:float} $voucher
 */
function voucherAttachToEnquiry(int $enquiryId, array $voucher, float $originalNet, float $finalNet, float $discount): void
{
    require_once __DIR__ . '/enquiry_logger.php';

    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'voucher_id', 'INTEGER');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'voucher_code', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'voucher_amount', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'quote_net_original', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'quote_net_final', 'TEXT');

    $stmt = $pdo->prepare('SELECT form_data_json FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $formData = [];
    if (is_array($row)) {
        $raw = trim((string) ($row['form_data_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $formData = $decoded;
            }
        }
    }

    $formData['voucherCode'] = (string) $voucher['code'];
    $formData['voucherName'] = (string) $voucher['name'];
    $formData['voucherAmount'] = voucherFormatQuoteValue($discount);
    $formData['quoteValueOriginal'] = voucherFormatQuoteValue($originalNet);
    $formData['quoteValue'] = voucherFormatQuoteValue($finalNet);

    $update = $pdo->prepare(
        'UPDATE enquiries SET
            voucher_id = :voucher_id,
            voucher_code = :voucher_code,
            voucher_amount = :voucher_amount,
            quote_net_original = :quote_net_original,
            quote_net_final = :quote_net_final,
            form_data_json = :form_data_json
         WHERE id = :id'
    );
    $update->execute([
        ':voucher_id' => (int) $voucher['id'],
        ':voucher_code' => (string) $voucher['code'],
        ':voucher_amount' => voucherFormatQuoteValue($discount),
        ':quote_net_original' => voucherFormatQuoteValue($originalNet),
        ':quote_net_final' => voucherFormatQuoteValue($finalNet),
        ':form_data_json' => json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':id' => $enquiryId,
    ]);
}

function voucherClearFromEnquiry(int $enquiryId): void
{
    require_once __DIR__ . '/enquiry_logger.php';

    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'voucher_id', 'INTEGER');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'voucher_code', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'voucher_amount', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'quote_net_original', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'quote_net_final', 'TEXT');

    $stmt = $pdo->prepare('SELECT form_data_json, quote_net_original FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $formData = [];
    $restore = null;
    if (is_array($row)) {
        $raw = trim((string) ($row['form_data_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $formData = $decoded;
            }
        }
        $orig = trim((string) ($row['quote_net_original'] ?? ''));
        if ($orig !== '') {
            $restore = $orig;
        } elseif (! empty($formData['quoteValueOriginal'])) {
            $restore = (string) $formData['quoteValueOriginal'];
        }
    }

    unset($formData['voucherCode'], $formData['voucherName'], $formData['voucherAmount'], $formData['quoteValueOriginal']);
    if ($restore !== null) {
        $formData['quoteValue'] = $restore;
    }

    $update = $pdo->prepare(
        'UPDATE enquiries SET
            voucher_id = NULL,
            voucher_code = NULL,
            voucher_amount = NULL,
            quote_net_original = NULL,
            quote_net_final = NULL,
            form_data_json = :form_data_json
         WHERE id = :id'
    );
    $update->execute([
        ':form_data_json' => json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':id' => $enquiryId,
    ]);
}
