<?php

use Modules\User\Models\User;
use Modules\Log\jobs\LogActionJob;

class UserObserver
{
    public function created(User $User)
    {
        $this->log('create', $User);
    }

    public function updated(User $User)
    {
        $this->log('update', $User);
    }

    public function deleting(User $User)
    {
        $this->log('delete', $User); //قبل از حذف دیتا در لاگ ذخیره شود
    }
    public function deleted(User $User)
    {
        // $this->log('delete', $User);
    }

    protected function log($type, $model)
    {
        $changes = $model->getChanges();
        if ($type === 'update') {
            if (empty($changes)) return;

            if (isset($changes['password'])) $type = 'password_change';
        }

        $old = $type === 'create' ? null : $model->getOriginal();
        $new = $model->getChanges();

        LogActionJob::dispatch(
            auth()->id() ?? null,
            $model->getTable(),
            $model->id,
            $type,
            [
                'old' => $old,
                'new' => $new
            ]
        );
    }
}
