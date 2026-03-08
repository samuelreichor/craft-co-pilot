<?php

namespace samuelreichor\coPilot\migrations;

use craft\db\Migration;
use samuelreichor\coPilot\constants\Constants;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createConversationsTable();
        $this->createAuditLogTable();
        $this->createBrandVoiceTable();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Constants::TABLE_BRAND_VOICE);
        $this->dropTableIfExists(Constants::TABLE_AUDIT_LOG);
        $this->dropTableIfExists(Constants::TABLE_CONVERSATIONS);

        return true;
    }

    private function createConversationsTable(): void
    {
        $this->createTable(Constants::TABLE_CONVERSATIONS, [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'title' => $this->string(255)->defaultValue('New conversation'),
            'contextType' => $this->string(50)->null(),
            'contextId' => $this->integer()->null(),
            'messages' => $this->json(),
            'debugLog' => $this->mediumText()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            null,
            Constants::TABLE_CONVERSATIONS,
            'userId',
            '{{%users}}',
            'id',
            'CASCADE',
            null,
        );

        $this->createIndex(null, Constants::TABLE_CONVERSATIONS, ['userId']);
        $this->createIndex(null, Constants::TABLE_CONVERSATIONS, ['contextType', 'contextId']);
    }

    private function createAuditLogTable(): void
    {
        $this->createTable(Constants::TABLE_AUDIT_LOG, [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'conversationId' => $this->integer()->null(),
            'toolName' => $this->string(100)->notNull(),
            'entryId' => $this->integer()->null(),
            'fieldHandle' => $this->string(255)->null(),
            'action' => $this->string(50)->notNull(),
            'status' => $this->string(20)->notNull(),
            'details' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);

        $this->addForeignKey(
            null,
            Constants::TABLE_AUDIT_LOG,
            'userId',
            '{{%users}}',
            'id',
            'CASCADE',
            null,
        );

        $this->createIndex(null, Constants::TABLE_AUDIT_LOG, ['userId']);
        $this->createIndex(null, Constants::TABLE_AUDIT_LOG, ['conversationId']);
        $this->createIndex(null, Constants::TABLE_AUDIT_LOG, ['toolName']);
        $this->createIndex(null, Constants::TABLE_AUDIT_LOG, ['dateCreated']);
    }

    private function createBrandVoiceTable(): void
    {
        $this->createTable(Constants::TABLE_BRAND_VOICE, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'brandVoice' => $this->text()->null(),
            'glossary' => $this->text()->null(),
            'forbiddenWords' => $this->text()->null(),
            'languageInstructions' => $this->text()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            null,
            Constants::TABLE_BRAND_VOICE,
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            null,
        );

        $this->createIndex(null, Constants::TABLE_BRAND_VOICE, ['siteId'], true);
    }
}
