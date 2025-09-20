<?php

namespace JavidFazaeli\JSubscriberX\Services;

class Crypto
{
    private const HEADER   = "JX2";   // versioned envelope
    private const IV_LEN   = 16;      // AES-256-CBC IV length
    private const MAC_LEN  = 32;      // HMAC-SHA256 length (raw)

    private string $encKey;
    private string $macKey;

    public function __construct(?string $masterKey = null)
    {
        // Prefer a dedicated master key; fallback to EE's encryption_key
        $master = $masterKey
            ?: (string) (ee()->config->item('jsubx_master_key') ?: ee()->config->item('encryption_key'));

        if ($master === '') {
            throw new \RuntimeException('Missing crypto master key (set jsubx_master_key or encryption_key).');
        }

        // Derive separate keys (HKDF if available, else salted hashes)
        if (function_exists('hash_hkdf')) {
            $this->encKey = hash_hkdf('sha256', $master, 32, 'jsubx-enc');
            $this->macKey = hash_hkdf('sha256', $master, 32, 'jsubx-mac');
        } else {
            $this->encKey = hash('sha256', 'enc|'.$master, true);
            $this->macKey = hash('sha256', 'mac|'.$master, true);
        }
    }

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_LEN);
        $ct = openssl_encrypt($plain, 'AES-256-CBC', $this->encKey, OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            throw new \RuntimeException('Encrypt failed');
        }

        // MAC over iv || ct
        $mac = hash_hmac('sha256', $iv . $ct, $this->macKey, true);

        // Envelope: HEADER || IV || MAC || CT (base64)
        return base64_encode(self::HEADER . $iv . $mac . $ct);
    }

    public function decrypt(string $b64): ?string
    {
        if (!$b64) return null;
        $raw = base64_decode($b64, true);
        if ($raw === false) return null;

        // New format: HEADER + IV + MAC + CT
        $hdr = substr($raw, 0, strlen(self::HEADER));
        if ($hdr === self::HEADER) {
            $rest = substr($raw, strlen(self::HEADER));
            if (strlen($rest) < self::IV_LEN + self::MAC_LEN + 1) return null;

            $iv  = substr($rest, 0, self::IV_LEN);
            $mac = substr($rest, self::IV_LEN, self::MAC_LEN);
            $ct  = substr($rest, self::IV_LEN + self::MAC_LEN);

            $calc = hash_hmac('sha256', $iv . $ct, $this->macKey, true);
            if (!hash_equals($mac, $calc)) return null;

            $pt = openssl_decrypt($ct, 'AES-256-CBC', $this->encKey, OPENSSL_RAW_DATA, $iv);
            return ($pt === false) ? null : $pt;
        }

        // Legacy fallback: IV + CT (no MAC)
        if (strlen($raw) >= self::IV_LEN + 1) {
            $iv = substr($raw, 0, self::IV_LEN);
            $ct = substr($raw, self::IV_LEN);
            $pt = openssl_decrypt($ct, 'AES-256-CBC', $this->encKey, OPENSSL_RAW_DATA, $iv);
            return ($pt === false) ? null : $pt;
        }

        return null;
    }

    // Convenience helpers
    public function encryptArray(array $data): string
    {
        return $this->encrypt(json_encode($data));
    }

    public function decryptToArray(?string $blob): array
    {
        if (!$blob) return [];
        $json = $this->decrypt($blob);
        return $json ? (json_decode($json, true) ?: []) : [];
    }
}
