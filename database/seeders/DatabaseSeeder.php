<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Database\UniqueConstraintViolationException;

class DatabaseSeeder extends Seeder
{
    /**
     * Наполнение базы данных начальными данными.
     */
    public function run(): void
    {
        // Seed tasks if needed
        if (Task::count() === 0) {
            $this->call(TaskSeeder::class);
            return; // Skip other seeders if we're just seeding tasks
        }

        // Вспомогательная функция: безопасное создание пользователя по email
        $getOrCreateUser = function (string $email, array $attrs = []) {
            $existing = User::where('email', $email)->first();
            if ($existing) return $existing;
            try {
                $user = new User(array_merge(['email' => $email], $attrs));
                if (empty($user->password)) {
                    $user->password = bcrypt(Str::password(12));
                }
                if (empty($user->name)) {
                    $user->name = Str::before($email, '@');
                }
                $user->save();
                return $user;
            } catch (UniqueConstraintViolationException $e) {
                // Если другой процесс/посев создал запись параллельно — просто вернуть существующую
                return User::where('email', $email)->first();
            }
        };
        // 1) Создать роли при необходимости (Администратор, Менеджер, Исполнитель)
        $roles = [
            'Administrator',
            'Manager',
            'Performer',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName);
        }

        // 1.1) Опционально создать пользователя-администратора, если заданы ENV-переменные
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        if ($adminEmail && $adminPassword) {
            $admin = User::firstOrNew(['email' => $adminEmail]);
            if (! $admin->exists) {
                $admin->name = 'Administrator';
                $admin->password = bcrypt($adminPassword);
                $admin->save();
            }
            if (! $admin->hasRole('Administrator')) {
                $admin->assignRole('Administrator');
            }
        }

        // 2) Создать или получить тестового менеджера
        $manager = $getOrCreateUser('manager@example.com', [
            'name' => 'Manager One',
            'password' => bcrypt(Str::password(12)),
        ]);
        if (! $manager->hasRole('Manager')) {
            $manager->assignRole('Manager');
        }

        // 3) Создать или получить исполнителей
        $performerA = $getOrCreateUser('performer.a@example.com', [
            'name' => 'Performer A',
            'password' => bcrypt(Str::password(12)),
        ]);
        if (! $performerA->hasRole('Performer')) {
            $performerA->assignRole('Performer');
        }

        $performerB = $getOrCreateUser('performer.b@example.com', [
            'name' => 'Performer B',
            'password' => bcrypt(Str::password(12)),
        ]);
        if (! $performerB->hasRole('Performer')) {
            $performerB->assignRole('Performer');
        }

        // 4) Привязать пользователей к менеджеру (связь многие-ко-многим, пересечения разрешены)
        $manager->managedUsers()->syncWithoutDetaching([
            $performerA->id,
            $performerB->id,
        ]);

        // 5) Демонстрация пересечения: закрепить одного пользователя и за вторым менеджером
        $manager2 = $getOrCreateUser('manager2@example.com', [
            'name' => 'Manager Two',
            'password' => bcrypt(Str::password(12)),
        ]);
        if (! $manager2->hasRole('Manager')) {
            $manager2->assignRole('Manager');
        }
        $manager2->managedUsers()->syncWithoutDetaching([
            $performerA->id, // пересечение с первым менеджером
        ]);
    }
}
