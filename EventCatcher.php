<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 20.07.16
 * Time: 11:59
 */

namespace andkon\yii2SocketChat;

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
                $clientRooms = ChatRoomBase::find()->where(['shop_id' => $shop_id])
                    ->andWhere('user_id is not null')
                    ->all();
                foreach ($clientRooms as $item) {
                    $item              = Room::findById($item->hash, $item);
                    $item->shopConnect = $event->connect;
                    if (isset(Server::$connectsByRoom[$item->id]['user'])) {
                        $data                     = $item->prepareData([]);
                        $data['seller']['online'] = true;
                        // Отправляем данные что б сменить статус продавца
                        Server::write(json_encode($data), Server::$connectsByRoom[$item->id]['user']);
                    }

                    $room->messages = array_merge($room->messages, $item->messages);
                }
            }
        } elseif ($room) {
            $oldRoom = ChatRoomBase::findOne(
                [
                    'user_id'   => $room->user_id,
                    'seller_id' => $room->seller_id,
                    'shop_id'   => $room->shop_id,
                ]
            );
            if ($oldRoom && $oldRoom->hash != $room->id) {
                $oldRoom->hash = $room->id;
                $oldRoom->save(true, ['hash']);
                $room->messages = json_decode($oldRoom->messages, true);
                $room->save();
            }
        }

        if ($room && count($room->messages)) {
            $data = $room->prepareData($room->messages);

            Server::write(json_encode($data), $event->connect);
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

        if (!array_key_exists('chatId', $event->message)) {
            return false;
        }

        if (
            !(array_key_exists('message', $event->message) && !empty($event->message['message']))
            &&
            !array_key_exists('event', $event->message)
        ) {

            return false;
        }

        $room = Room::findById($event->message['chatId']);
        if (!$room) {
            return false;
        }

        $sellerId = null;
        $isShop   = ($room->isShop && isset($event->message['room']));
        if ($isShop) {
            $sellerId = $room->seller_id;
            $room     = Room::findById($event->message['room']);
        }

        if (array_key_exists('event', $event->message)) {
            switch ($event->message['event']) {
                case 'typing':
                case 'typingOff':
                    $data = $room->prepareData([]);
                    if ($isShop) {
                        $conn = $room->clientConnect;
                    } else {
                        $conn = $room->shopConnect;
                    }

                    if ($event->message['event'] === 'typing') {
                        $data['user']['typing']   = true;
                        $data['seller']['typing'] = true;
                    }

                    Server::write(json_encode($data), $conn);
                    break;
                default:
                    return false;
            }
        } else {
            $room->addMessage($event->message['message'], $sellerId);
        }
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
        if ($event->context instanceof Room) {
            $room = $event->context;
            if ($room->isShop) {
                foreach ($room->shop_id as $shopId) {
                    if (array_key_exists($shopId, Server::$roomByShop)) {
                        foreach (Server::$roomByShop[$shopId] as $roomId) {
                            if (isset(Server::$connectsByRoom[$roomId]['user'])) {
                                $clientRoom               = Room::findById($roomId);
                                $data                     = $clientRoom->prepareData([]);
                                $data['seller']['online'] = false;
                                Server::write(json_encode($data), Server::$connectsByRoom[$roomId]['user']);
                                unset(Server::$connectsByRoom[$roomId]['seller']);
                            }
                        }
                    }
                }
            } else {
                $data                   = $room->prepareData([]);
                $data['user']['online'] = false;
                if (Server::$connectsByRoom[$room->id]['seller']) {
                    Server::write(json_encode($data), Server::$connectsByRoom[$room->id]['seller']);
                    unset(Server::$connectsByRoom[$room->id]['user']);
                }
            }
        }
    }

    /**
     * @param Event $event
     */
    public function onError(Event $event)
    {
    }
}
