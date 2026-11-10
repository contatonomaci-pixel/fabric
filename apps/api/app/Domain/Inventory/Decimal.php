<?php

namespace App\Domain\Inventory;

use Illuminate\Validation\ValidationException;

final class Decimal
{
    public static function quantity(mixed $value, string $field, bool $positive = true): string
    {
        if (! is_string($value) || ! preg_match('/^\d{1,12}(\.\d{1,6})?$/', $value) || bccomp($value, '0', 6) < ($positive ? 1 : 0)) {
            self::fail($field, $positive ? 'Informe quantidade positiva em texto decimal, até 6 casas.' : 'Informe quantidade não negativa em texto decimal, até 6 casas.');
        }

        return bcadd($value, '0', 6);
    }

    public static function bounded(string $value, string $field): string
    {
        if (bccomp($value, '999999999999.999999', 6) > 0 || bccomp($value, '0', 6) < 0) {
            self::fail($field, 'Quantidade fora da precisão ou saldo disponível.');
        }

        return bcadd($value, '0', 6);
    }

    public static function cost(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{1,14}(\.\d{1,4})?$/', $value)) {
            self::fail('unit_cost', 'Informe custo não negativo em texto decimal, até 4 casas.');
        }

        return bcadd($value, '0', 4);
    }

    public static function total(string $quantity, string $cost): string
    {
        $total = bcadd(bcmul($quantity, $cost, 10), '0.00005', 4);
        if (bccomp($total, '99999999999999.9999', 4) > 0) {
            self::fail('quantity', 'O custo total excede a precisão permitida.');
        }

        return $total;
    }

    public static function base(string $quantity, string $ratio): string
    {
        $exact = bcmul($quantity, $ratio, 12);
        $result = self::bounded(bcadd($exact, '0', 6), 'quantity');
        if (bccomp($exact, $result, 12) !== 0) {
            self::fail('quantity', 'A conversão exige mais de 6 casas decimais; informe quantidade representável.');
        }

        return $result;
    }

    public static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
