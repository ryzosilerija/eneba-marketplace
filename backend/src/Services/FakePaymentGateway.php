<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Simulated card payment — no real money, no external service.
 *
 * Test cards:
 *   4242 4242 4242 4242  → success
 *   4000 0000 0000 0002  → declined ("Your card was declined.")
 *   4000 0000 0000 9995  → declined ("Insufficient funds.")
 * Any other number that passes the Luhn checksum succeeds.
 */
final class FakePaymentGateway
{
    /** @return array{success: bool, reference: ?string, error: ?string} */
    public function charge(int $amountCents, string $cardNumber): array
    {
        $number = preg_replace('/\D/', '', $cardNumber);

        return match (true) {
            $number === '4000000000000002' => ['success' => false, 'reference' => null, 'error' => 'Your card was declined.'],
            $number === '4000000000009995' =>['success' => false, 'reference' => null, 'error' => 'Insufficient funds.'],
            default => ['success' => true, 'reference' => 'PAY-' . strtoupper(bin2hex(random_bytes(6))), 'error' => null],
        };
    }

    /** Luhn checksum — the same check real card forms use to catch typos. */
    public static function luhnValid(string $cardNumber): bool
    {
        $digits = preg_replace('/\D/', '', $cardNumber);
        if (strlen($digits) < 12 || strlen($digits) > 19) {
            return false;
        }
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $d) {
            $d = (int) $d;
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }
        return $sum % 10 === 0;
    }
}
