<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * Kétfaktoros hitelesítés (2FA / TOTP) szolgáltatás.
 *
 * Közös helyen kezeli:
 *  - TOTP secret generálás
 *  - otpauth:// URL + QR kód (SVG) létrehozás
 *  - TOTP kód ellenőrzés (legutóbbi és előző 30 mp-es ablakban)
 *  - Recovery kódok generálás + használat (egyszer használható)
 */
class TwoFactorService
{
    public function __construct(
        private Google2FA $google2fa,
    ) {}

    /**
     * Új secret kulcs (32 char base32).
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * otpauth:// URL az authenticator app számára.
     */
    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );
    }

    /**
     * QR kód SVG formátumban (inline SVG string, data URI nélkül).
     */
    public function qrCodeSvg(string $otpauthUrl, int $size = 240): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);

        return $writer->writeString($otpauthUrl);
    }

    /**
     * TOTP kód validálás. 1 window = ±30 mp tolerancia óraszinkron eltérésekre.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Aktuális TOTP kód (tesztekhez / CLI-hez). Éles kódban csak a verify-t használjuk.
     */
    public function generateCurrentCode(string $secret): string
    {
        return $this->google2fa->getCurrentOtp($secret);
    }

    /**
     * 8 db recovery kód generálása (XXXXX-XXXXX formátum).
     * Minden kód egyszer használható.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->randomCodeSegment() . '-' . $this->randomCodeSegment();
        }
        return $codes;
    }

    /**
     * Egy 5 karakteres szegmens (nagybetűk + számok; tévesztés-veszélyes karakterek kihagyva).
     */
    private function randomCodeSegment(int $length = 5): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // I, O, 0, 1 kihagyva
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $result;
    }

    /**
     * Recovery kód ellenőrzés + fogyasztás. Ha találat van, visszaadja az új (csökkentett) kód-listát.
     * Ha nincs találat, null.
     */
    public function consumeRecoveryCode(array $codes, string $candidate): ?array
    {
        $normalized = strtoupper(trim(str_replace(' ', '', $candidate)));
        $filtered = array_values(array_filter($codes, fn ($c) => strtoupper($c) !== $normalized));

        if (count($filtered) === count($codes)) {
            return null; // nincs találat
        }

        return $filtered;
    }
}
