<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Texto seguro para JSON UTF-8 (sin sustitutos globales en json_encode). */
final class Utf8Text
{
    public static function isValid(string $s): bool
    {
        return $s === '' || (bool) preg_match('//u', $s);
    }

    /**
     * Repara cadenas legacy (p. ej. Latin-1 en saves antiguos) a UTF-8 válido.
     */
    public static function repair(string $s): string
    {
        if ($s === '' || self::isValid($s)) {
            return $s;
        }
        if (function_exists('iconv')) {
            $latin = @iconv('ISO-8859-1', 'UTF-8//TRANSLIT', $s);
            if (is_string($latin) && self::isValid($latin)) {
                return $latin;
            }
            $utf8 = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if (is_string($utf8) && self::isValid($utf8)) {
                return $utf8;
            }
        }
        $ascii = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $s);

        return is_string($ascii) ? $ascii : '';
    }

    public static function paraJson(string $s): string
    {
        return self::repair($s);
    }

    /** Primera letra Unicode segura (evita substr byte que rompe UTF-8). */
    public static function primeraLetra(string $s): string
    {
        $s = self::repair($s);
        if ($s === '') {
            return '?';
        }
        if (function_exists('mb_substr')) {
            $ch = mb_substr($s, 0, 1, 'UTF-8');
            return is_string($ch) && $ch !== '' ? $ch : '?';
        }
        if (preg_match('/^./u', $s, $m)) {
            return $m[0];
        }

        return self::paraJson(substr($s, 0, 1));
    }

    public static function mayusculas(string $s): string
    {
        $s = self::paraJson($s);
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($s, 'UTF-8');
        }

        return strtoupper($s);
    }

    /**
     * @return list<array{path: string, hex: string}>
     */
    public static function rutasInvalidas(mixed $value, string $path = ''): array
    {
        $bad = [];
        if (is_string($value)) {
            if (!self::isValid($value)) {
                $bad[] = ['path' => $path, 'hex' => bin2hex(substr($value, 0, 32))];
            }
            return $bad;
        }
        if (is_array($value)) {
            foreach ($value as $k => $item) {
                $p = $path === '' ? (string) $k : $path . '.' . $k;
                $bad = array_merge($bad, self::rutasInvalidas($item, $p));
            }
        }
        return $bad;
    }
}
