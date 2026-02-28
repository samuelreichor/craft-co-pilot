<?php

namespace samuelreichor\coPilot\migrations;

use craft\db\Migration;
use samuelreichor\coPilot\constants\Constants;

class m260228_000000_change_debug_log_to_mediumtext extends Migration
{
    public function safeUp(): bool
    {
        $this->alterColumn(
            Constants::TABLE_CONVERSATIONS,
            'debugLog',
            $this->mediumText()->null(),
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->alterColumn(
            Constants::TABLE_CONVERSATIONS,
            'debugLog',
            $this->text()->null(),
        );

        return true;
    }
}
