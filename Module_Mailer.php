<?php
namespace GDO\Mailer;

use GDO\Core\GDO_Module;
use GDO\Core\GDT_Secret;

final class Module_Mailer extends GDO_Module
{
    public int $priority = 80;

    public function getDependencies(): array
    {
        return [
            'Mail',
        ];
    }

}
