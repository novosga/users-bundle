<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\UsersBundle;

use Novosga\Module\BaseModule;

class NovosgaUsersBundle extends BaseModule
{
    public function getIconName()
    {
        return 'users';
    }

    public function getDisplayName()
    {
        return 'module.name';
    }

    public function getHomeRoute()
    {
        return 'novosga_users_index';
    }
}
