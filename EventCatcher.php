<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 20.07.16
 * Time: 11:59
 */

namespace andkon\yii2SocketChat;

use vendor\andkon\yii2SocketChat\Room;
use yii\base\Object;

/**
 * Class EventCatcher
 *
 * @package andkon\yii2SocketChat
 */
class EventCatcher extends Object
{
    /**
     * Инициализация
     */
    public static function initLiseners()
    {
        \Yii::$app->on(Server::EVENT_ON_CONNECT, [self::className(), 'onConnect']);
        \Yii::$app->on(Server::EVENT_ON_MESSAGE, [self::className(), 'onMessage']);
        \Yii::$app->on(Server::EVENT_ON_EOF, [self::className(), 'onEof']);
        \Yii::$app->on(Server::EVENT_ON_CLOSED, [self::className(), 'onClosed']);
        \Yii::$app->on(Server::EVENT_ON_ERROR, [self::className(), 'onError']);
    }

    /**
     * @param Event $event
     */
    public function onConnect(Event $event)
    {
        /** @var Room $room */
        $room = $event->context;
        if ($room && $room->isShop) {
            if (!is_array($room->shop_id)) {
                $room->shop_id = [$room->shop_id];
            }

            foreach ($room->shop_id as $shop_id) {
                if (array_key_exists($shop_id, Server::$roomByShop)) {
                    foreach (Server::$roomByShop[$shop_id] as $item) {
                        $item              = Room::findById($item);
                        $item->shopConnect = $event->connect;
                        $room->messages    = array_merge($room->messages, $item->messages);
                    }
                }
            }
        }

        if ($room && count($room->messages)) {
            Server::write(json_encode($room->messages), $event->connect);
        }
    }

    /**
     * @param Event $event
     *
     * @return bool
     */
    public function onMessage(Event $event)
    {
        if (!is_array($event->message)) {
            return false;
        }

        if (!array_key_exists('chatId', $event->message) || !array_key_exists('message', $event->message) || empty($event->message['message'])) {
            return false;
        }

        $room = Room::findById($event->message['chatId']);
        if (!$room) {
            return false;
        }

        if ($room->isShop && $event->message['room']) {
            $room = Room::findById($event->message['room']);
        }

        $room->addMessage($event->message['message']);
    }

    /**
     * @param Event $event
     */
    public function onEof(Event $event)
    {
    }

    /**
     * @param Event $event
     */
    public function onClosed(Event $event)
    {
    }

    /**
     * @param Event $event
     */
    public function onError(Event $event)
    {
    }
}
