<?php

namespace App\Console\Commands;

use App\Services\InactiveMemberService;
use Illuminate\Console\Command;

class ProcessInactiveMembers extends Command
{
    protected $signature = 'moderation:process-inactivity';

    protected $description = 'Warn inactive group members and suspend repeat offenders for a configured duration';

    public function handle(InactiveMemberService $inactiveMembers): int
    {
        $result = $inactiveMembers->processAll();

        $this->info(sprintf(
            'Inactivity check complete: %d reinstated, %d warned, %d suspended.',
            $result['released'],
            $result['warned'],
            $result['suspended'],
        ));

        return self::SUCCESS;
    }
}
