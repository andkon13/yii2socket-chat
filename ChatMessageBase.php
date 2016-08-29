<?php

namespace andkon\yii2SocketChat;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "chat_message".
 *
 * @property integer  $id
 * @property integer  $chat_room_id
 * @property integer  $user_id
 * @property integer  $shop_id
 * @property string   $user_name
 * @property string   $text
 * @property string   $created
 * @property ChatRoom $chatRoom
 */
class ChatMessage extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'chat_message';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['chat_room_id', 'user_id', 'shop_id'], 'integer'],
            [['text'], 'string'],
            [['created'], 'safe'],
            [['user_name'], 'string', 'max' => 255],
            [
                ['chat_room_id'],
                'exist',
                'skipOnError'     => true,
                'targetClass'     => ChatRoom::className(),
                'targetAttribute' => ['chat_room_id' => 'id']
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'chat_room_id' => 'Chat Room ID',
            'user_id'      => 'User ID',
            'shop_id'      => 'Shop ID',
            'user_name'    => 'User Name',
            'text'         => 'Text',
            'created'      => 'Created',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChatRoom()
    {
        return $this->hasOne(ChatRoom::className(), ['id' => 'chat_room_id']);
    }
}
