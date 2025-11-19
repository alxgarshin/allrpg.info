<?php

declare(strict_types=1);

namespace App\Migrations;

use Fraym\BaseObject\BaseMigration;
use Fraym\Helper\AuthHelper;

class Migration20251118152435 extends BaseMigration
{
    public function up(): bool
    {
        MIGRATE_DB->query("ALTER TABLE `user` ADD `password_hashed` VARCHAR(255) DEFAULT NULL;", []);
        MIGRATE_DB->query("ALTER TABLE `user` ADD `hash_version` enum('wrapped_v1', 'final_v2') DEFAULT NULL;", []);

        $users = MIGRATE_DB->select(
            tableName: 'user',
            fieldsSet: ['id', 'pass'],
        );

        foreach ($users as $u) {
            $old = $u['pass'];

            if (!$old) {
                continue;
            }

            $wrapped = AuthHelper::hashPassword($old, false);

            MIGRATE_DB->update(
                tableName: 'user',
                data: [
                    'password_hashed' => $wrapped,
                    'hash_version'    => 'wrapped_v1',
                ],
                criteria: [
                    'id' => $u['id'],
                ],
            );
        }

        return true;
    }

    public function down(): bool
    {
        return true;
    }
}
