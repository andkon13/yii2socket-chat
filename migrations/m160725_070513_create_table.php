<?php

/**
 * Class m160725_070513_create_table
 */
class m160725_070513_create_table extends \yii\db\Migration
{
    /**
     * @return bool
     */
    public function safeUp()
    {
        $this->createTable(
            'chat_room',
            [
                'id'          => $this->primaryKey(),
                'hash'        => $this->string(50),
                'user_id'     => $this->integer(),
                'shop_id'     => $this->integer(),
                'seller_id'   => $this->integer(),
                'messages'    => 'jsonb',
                'last_update' => $this->dateTime(),
            ]
        );

        return true;
    }

    /**
     * @return bool
     */
    public function safeDown()
    {
        $this->dropTable('chat_room');

        return true;
    }
}
