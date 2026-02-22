<?php

namespace samuelreichor\coPilot\migrations;

use craft\db\Migration;
use samuelreichor\coPilot\constants\Constants;

class m260222_000000_add_conversation_id_to_audit_log extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn(
            Constants::TABLE_AUDIT_LOG,
            'conversationId',
            $this->integer()->null()->after('userId'),
        );

        $this->createIndex(null, Constants::TABLE_AUDIT_LOG, ['conversationId']);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn(Constants::TABLE_AUDIT_LOG, 'conversationId');

        return true;
    }
}
