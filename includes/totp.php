<?php
/**
 * Totp — RFC 6238 TOTP (Google Authenticator compatible), no external library.
 */
class Totp {
    private const ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a random base32-encoded secret (20 bytes = 160 bits). */
    public static function generateSecret(): string {
        return self::b32enc(random_bytes(20));
    }

    /**
     * Verify a 6-digit code against a secret.
     * Accepts ±1 time step (90-second window) to tolerate clock skew.
     */
    public static function verify(string $secret, string $code): bool {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return false;
        $t = (int)floor(time() / 30);
        foreach ([-1, 0, 1] as $d) {
            if (hash_equals(self::compute($secret, $t + $d), $code)) return true;
        }
        return false;
    }

    /** Build the otpauth:// URI for QR code generation. */
    public static function otpauth(string $secret, string $user, string $issuer = 'LiveGig'): string {
        return 'otpauth://totp/'
            . rawurlencode($issuer) . ':' . rawurlencode($user)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&digits=6&period=30';
    }

    /**
     * @deprecated Lekte de TOTP-secret naar api.qrserver.com.
     * Niet meer gebruikt — render QR client-side of toon de secret/otpauth-URI als tekst.
     */
    public static function qrUrl(string $secret, string $user, string $issuer = 'LiveGig'): string {
        return '';
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private static function compute(string $secret, int $counter): string {
        $key  = self::b32dec(strtoupper($secret));
        $msg  = pack('J', $counter);           // 8-byte big-endian unsigned int
        $hash = hash_hmac('sha1', $msg, $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $otp  = (
            ((ord($hash[$off])     & 0x7F) << 24) |
            ((ord($hash[$off + 1]) & 0xFF) << 16) |
            ((ord($hash[$off + 2]) & 0xFF) <<  8) |
             (ord($hash[$off + 3]) & 0xFF)
        ) % 1_000_000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    private static function b32enc(string $raw): string {
        $out = '';
        $v = 0; $bits = 0;
        for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
            $v = ($v << 8) | ord($raw[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHA[($v >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) $out .= self::ALPHA[($v << (5 - $bits)) & 0x1F];
        return $out;
    }

    private static function b32dec(string $enc): string {
        $out = '';
        $v = 0; $bits = 0;
        for ($i = 0, $n = strlen($enc); $i < $n; $i++) {
            $pos = strpos(self::ALPHA, $enc[$i]);
            if ($pos === false) continue;
            $v = ($v << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($v >> $bits) & 0xFF);
            }
        }
        return $out;
    }
}
