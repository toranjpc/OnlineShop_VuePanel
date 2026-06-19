<?php

namespace Modules\Product\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Product\Models\Credit;

class CreditAmountSumRelation extends Relation
{
    public function __construct(
        Builder $query,
        Model $parent,
        protected array $types,
    ) {
        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->forParty($this->types, (int) $this->parent->getKey());
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $ids = $this->getKeys($models);

        $this->query->where(function (Builder $query) use ($ids) {
            $query->where(function (Builder $query) use ($ids) {
                $query->whereIn('receive_type', $this->types)
                    ->whereIn('receive_id', $ids);
            })->orWhere(function (Builder $query) use ($ids) {
                $query->whereIn('payment_type', $this->types)
                    ->whereIn('payment_id', $ids);
            });
        });
    }

    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, 0);
        }

        return $models;
    }

    public function match(array $models, Collection $results, $relation): array
    {
        $grouped = [];

        foreach ($results as $credit) {
            if (in_array($credit->receive_type, $this->types, true)) {
                $grouped[$credit->receive_id] = ($grouped[$credit->receive_id] ?? 0) + (int) $credit->amount;
            }

            if (in_array($credit->payment_type, $this->types, true)) {
                $grouped[$credit->payment_id] = ($grouped[$credit->payment_id] ?? 0) + (int) $credit->amount;
            }
        }

        foreach ($models as $model) {
            $model->setRelation($relation, (int) ($grouped[$model->getKey()] ?? 0));
        }

        return $models;
    }

    public function getResults(): int
    {
        return (int) $this->query->sum('amount');
    }
}
