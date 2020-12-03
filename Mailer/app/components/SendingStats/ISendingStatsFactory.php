<?php
declare(strict_types=1);

namespace Remp\MailerModule\Components;

interface ISendingStatsFactory
{
    /** @return SendingStats */
    public function create();
}
