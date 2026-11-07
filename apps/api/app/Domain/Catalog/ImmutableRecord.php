<?php

namespace App\Domain\Catalog;

abstract class ImmutableRecord extends ScopedRecord
{
    protected $attributes = [];

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Histórico não pode ser alterado.'));
        static::deleting(fn () => throw new \LogicException('Histórico não pode ser excluído.'));
    }
}
