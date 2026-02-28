<?php

namespace samuelreichor\coPilot\migrations;

use craft\db\Migration;
use samuelreichor\coPilot\constants\Constants;

class m260227_000000_add_debug_log_column extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Constants::TABLE_CONVERSATIONS, 'debugLog')) {
            $this->addColumn(
                Constants::TABLE_CONVERSATIONS,
                'debugLog',
                $this->text()->null()->after('messages'),
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn(Constants::TABLE_CONVERSATIONS, 'debugLog');

        return true;
    }
}
