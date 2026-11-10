<?php

namespace App\Domain\Inventory;

abstract class Immutable extends Record
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Registro consolidado não pode ser alterado.'));
        static::deleting(fn () => throw new \LogicException('Registro consolidado não pode ser excluído.'));
    }
}
