<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\User\Models\ExtData;
use Modules\User\Models\Option;
use Modules\User\Models\User;
use Illuminate\Support\Facades\Artisan;

class UserSeeder extends Seeder
{

    public function run(): void
    {
        DB::transaction(function () {
            // پایه‌ی گزینه‌ها (مشاغل، دسته، پلن‌ها)
            $adminJob = Option::updateOrCreate(
                ['title' => 'ادمین کل', 'f_id' => null, 'kind' => 'job'],
                ['option' => ['form' => null, 'permissions' => ['*']], 'status' => 1]
            );
            $developerJob = Option::updateOrCreate(
                ['title' => 'دولوپر', 'f_id' => null, 'kind' => 'job'],
                ['option' => ['form' => null, 'permissions' => ['*']], 'status' => 1]
            );
            $userJob = Option::updateOrCreate(
                ['title' => 'کاربر عادی', 'f_id' => null, 'kind' => 'job'],
                ['option' => ['form' => null, 'permissions' => []], 'status' => 0]
            );

            $users = [
                ['username' => 'admin', 'name' => 'مدیر', 'lastname' => 'سیستم', 'password' => Hash::make('admin1234'), 'mobile' => 9121111111, 'sex' => 1, 'job' => $adminJob->id, 'per' => ['*'], 'status' => 1, 'is_accountable' => 0],
                ['username' => 'developer', 'name' => 'Developer', 'lastname' => 'User', 'password' => Hash::make('developer1234'), 'mobile' => 9120703611, 'sex' => 1, 'job' => $developerJob->id, 'per' => ['*'], 'status' => 1, 'is_accountable' => 0],
                ['username' => 'user1', 'name' => 'کاربر', 'lastname' => '1', 'password' => Hash::make('user1234'), 'mobile' => 9123456789, 'sex' => 1, 'job' => $userJob->id, 'per' => [], 'status' => 1, 'is_accountable' => 1],
                ['username' => 'user2', 'name' => 'کاربر', 'lastname' => '2', 'password' => Hash::make('user1234'), 'mobile' => 9123456710, 'sex' => 1, 'job' => $userJob->id, 'per' => [], 'status' => 1, 'is_accountable' => 1],
                ['username' => 'user3', 'name' => 'کاربر', 'lastname' => '3', 'password' => Hash::make('user1234'), 'mobile' => 9123456711, 'sex' => 1, 'job' => $userJob->id, 'per' => [], 'status' => 1, 'is_accountable' => 1],
                ['username' => 'user4', 'name' => 'کاربر', 'lastname' => '4', 'password' => Hash::make('user1234'), 'mobile' => 9123456712, 'sex' => 1, 'job' => $userJob->id, 'per' => [], 'status' => 1, 'is_accountable' => 1],
                ['username' => 'user5', 'name' => 'کاربر', 'lastname' => '5', 'password' => Hash::make('user1234'), 'mobile' => 9123456713, 'sex' => 1, 'job' => $userJob->id, 'per' => [], 'status' => 1, 'is_accountable' => 1],
            ];
            $users = array_map(function (array $user) {
                $user['per'] = json_encode($user['per']);

                return $user;
            }, $users);
            User::upsert($users, ['username'], ['name', 'lastname', 'password', 'mobile', 'sex', 'job', 'per', 'status', 'is_accountable']);
        });
        Artisan::call('storage:link');
        Artisan::call('cache:clear');
    }
}
