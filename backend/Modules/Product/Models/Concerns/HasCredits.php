<?php

namespace Modules\Product\Models\Concerns;

use Modules\Product\Models\Credit;
use Modules\Product\Models\Relations\CreditAmountSumRelation;
use Modules\Product\Models\Relations\CreditForPartyRelation;

trait HasCredits
{
    abstract protected function creditEntityTypes(): array;

    public function creditRecords(): CreditForPartyRelation
    {
        return new CreditForPartyRelation(
            Credit::query(),
            $this,
            $this->creditEntityTypes()
        );
    }

    public function creditAmountSum(): CreditAmountSumRelation
    {
        return new CreditAmountSumRelation(
            Credit::query(),
            $this,
            $this->creditEntityTypes()
        );
    }
}
