<?php

namespace samuelreichor\coPilot\migrations;

use craft\db\Migration;
use samuelreichor\coPilot\constants\Constants;

class m260322_000000_rename_entry_id_to_element_id extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists(Constants::TABLE_AUDIT_LOG, 'entryId')) {
            $this->renameColumn(Constants::TABLE_AUDIT_LOG, 'entryId', 'elementId');
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(Constants::TABLE_AUDIT_LOG, 'elementId')) {
            $this->renameColumn(Constants::TABLE_AUDIT_LOG, 'elementId', 'entryId');
        }

        return true;
    }
}
