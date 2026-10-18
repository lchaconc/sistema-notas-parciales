<?php

namespace App\Support;

use App\Exceptions\ApiError;
use DateTimeImmutable;

final class Input
{
    public static function text(array $data, string $key, ?int $max = null): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new ApiError("El campo {$key} es obligatorio.");
        }
        $value = trim($value);
        if ($max !== null && mb_strlen($value) > $max) {
            throw new ApiError("El campo {$key} supera {$max} caracteres.");
        }
        return $value;
    }

    public static function optionalText(array $data, string $key, int $max): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }
        return self::text($data, $key, $max);
    }

    public static function integer(array $data, string $key, ?int $min = null, ?int $max = null): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/D', $value))) {
            throw new ApiError("El campo {$key} debe ser entero.");
        }
        $number = (int) $value;
        if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
            throw new ApiError("El campo {$key} está fuera de rango.");
        }
        return $number;
    }

    public static function cents(array $data, string $key, ?int $min = null, ?int $max = null): int
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new ApiError("El campo {$key} debe ser decimal.");
        }
        $string = str_replace(',', '.', (string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $string)) {
            throw new ApiError("El campo {$key} admite hasta dos decimales.");
        }
        [$whole, $fraction] = array_pad(explode('.', $string, 2), 2, '');
        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        if (($min !== null && $cents < $min) || ($max !== null && $cents > $max)) {
            throw new ApiError("El campo {$key} está fuera de rango.");
        }
        return $cents;
    }

    public static function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public static function date(array $data, string $key): string
    {
        $value = self::text($data, $key);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ApiError("El campo {$key} debe ser una fecha válida AAAA-MM-DD.");
        }
        return $value;
    }

    public static function email(array $data, string $key = 'correo'): string
    {
        $email = self::text($data, $key, 254);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiError('El correo no es válido.');
        }
        return $email;
    }
}
