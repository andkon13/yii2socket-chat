<?php

use yii\db\Schema;

/**
 * Class m160826_081222_create_messages_table
 */
class m160826_081222_create_messages_table extends \yii\db\Migration
{
    /** @inheritdoc */
    public function safeUp()
    {
        $this->createTable(
            'chat_message',
            [
                'id'           => $this->primaryKey(),
                'chat_room_id' => $this->integer(),
                'user_id'      => $this->integer(),
                'shop_id'      => $this->integer(),
                'user_name'    => $this->string(255),
                'text'         => $this->text(),
                'created'      => $this->dateTime(),
            ]
        );

        $this->createIndex('chat_message_user_id_idx', 'chat_message', ['user_id']);
        $this->createIndex('chat_message_shop_id_idx', 'chat_message', ['shop_id']);
        $this->createIndex('chat_message_user_id_shop_id_idx', 'chat_message', ['user_id', 'shop_id']);
        $this->createIndex('chat_message_user_id_shop_id_created_idx', 'chat_message', ['user_id', 'shop_id', 'created']);

        $this->addForeignKey('chat_message_chat_room_chat_room_id_id', 'chat_message', ['chat_room_id'], 'chat_room', ['id']);

        $this->dropColumn('chat_room', 'messages');

        return true;
    }

    /** @inheritdoc */
    public function safeDown()
    {
        $this->dropTable('chat_message');
        $this->addColumn('chat_room', 'messages', 'jsonb');

        return true;
    }
}
