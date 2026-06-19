<?php

namespace Modules\Product\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Product\Models\Credit;

class CreditForPartyRelation extends Relation
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
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    public function match(array $models, Collection $results, $relation): array
    {
        foreach ($models as $model) {
            $partyId = (int) $model->getKey();

            $records = $results->filter(
                fn (Credit $credit) => $this->creditBelongsToParty($credit, $partyId)
            )->values();

            $model->setRelation($relation, $records);
        }

        return $models;
    }

    public function getResults(): Collection
    {
        return $this->query->get();
    }

    protected function creditBelongsToParty(Credit $credit, int $partyId): bool
    {
        return (in_array($credit->receive_type, $this->types, true) && (int) $credit->receive_id === $partyId)
            || (in_array($credit->payment_type, $this->types, true) && (int) $credit->payment_id === $partyId);
    }
}
