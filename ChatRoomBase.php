<?php

namespace andkon\yii2SocketChat;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "chat_room".
 *
 * @property integer       $id
 * @property string        $hash
 * @property integer       $user_id
 * @property integer       $shop_id
 * @property integer       $seller_id
 * @property string        $messages
 * @property string        $last_update
 * @property ChatMessage[] $chatMessages
 */
class ChatRoomBase extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'chat_room';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'shop_id', 'seller_id'], 'integer'],
            [['messages'], 'string'],
            [['last_update'], 'safe'],
            [['hash'], 'string', 'max' => 50],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'hash'        => 'Hash',
            'user_id'     => 'User ID',
            'shop_id'     => 'Shop ID',
            'seller_id'   => 'Seller ID',
            'messages'    => 'Messages',
            'last_update' => 'Last Update',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChatMessages()
    {
        return $this->hasMany(ChatMessage::className(), ['chat_room_id' => 'id']);
    }
}
