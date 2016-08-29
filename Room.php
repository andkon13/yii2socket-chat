<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 20.07.16
 * Time: 12:30
 */

namespace andkon\yii2SocketChat;

use common\modules\user\models\User;
use React\Socket\Connection;
use yii\base\Model;

/**
 * Class Room
 *
 * @package andkon\yii2SocketChat
 */
class Room extends Model
{
    /**
     * @var
     */
    public $id;
    /**
     * @var null
     */
    public $shopConnect;
    /**
     * @var null
     */
    public $clientConnect;
    /**
     * @var
     */
    public $clientConnectId;
    /**
     * @var array|mixed
     */
    public $messages = [];
    /**
     * @var
     */
    public $user_id;
    /**
     * @var
     */
    public $shop_id;
    /**
     * @var
     */
    public $seller_id;
    /**
     * @var
     */
    public $isShop;
    /**
     * @var
     */
    public $last_update;

    public $user_avatar;

    public $seller_avatar;

    /**
     * Room constructor.
     *
     * @param array $config
     * @param null  $shopConnect
     * @param null  $clientConnect
     */
    public function __construct(array $config, $shopConnect = null, $clientConnect = null)
    {
        $vars   = array_keys(get_object_vars($this));
        $config = array_filter($config, function ($key) use ($vars) {
            return in_array($key, $vars);
        }, ARRAY_FILTER_USE_KEY);
        parent::__construct($config);
        if (!is_array($this->messages)) {
            $this->messages = json_decode($this->messages, true);
        }

        $this->shopConnect   = $shopConnect;
        $this->clientConnect = $clientConnect;
    }

    /**
     * @param $id
     *
     * @return null|Room|static
     */
    public static function findById($id, $model = null)
    {
        if (null === $id) {
            return null;
        }

        $data = \Yii::$app->getCache()->get($id);
        if (!$data) {
            /** @var ChatRoomBase $model */
            if (!$model) {
                $model = ChatRoomBase::findOne(['hash' => $id]);
            }

            if (!$model) {
                return null;
            }

            $data             = $model->toArray();
            $data['messages'] = $model->chatMessages;

            $data['id'] = $model->hash;
        } elseif (is_string($data)) {
            $data       = json_decode($data, true);
            $data['id'] = $id;
        }

        if (!array_key_exists('messages', $data)) {
            $data['messages'] = [];
        }

        $shop   = (array_key_exists('shop', $data)) ? Server::getConnectById($data['shop']) : null;
        $client = (array_key_exists('client', $data)) ? Server::getConnectById($data['client']) : null;

        $model = new self($data, $shop, $client);

        return $model;
    }

    /**
     * @param $data
     */
    public static function initRoom($data)
    {
        $room = self::findById($data['id']);
        if ($room) {
            $data = array_merge($room->toArray(), $data);
        }

        \Yii::$app->getCache()->set($data['id'], $data);
    }

    /**
     * @param string $message
     * @param int    $sellerId
     */
    public function addMessage($message, $sellerId)
    {
        if (!empty($message)) {
            $user_id          = $sellerId ?? $this->user_id;
            $user             = \common\models\User::findOne($user_id);
            $message          = [
                'user_id' => $user_id,
                'user'    => (string)$user,
                'text'    => $message,
                'date'    => date('Y-m-d H:i:s'),
                'room'    => $this->id,
                'shop_id' => $this->shop_id,
            ];
            $this->messages[] = $message;
            $this->save(true);
            $data = $this->prepareData([$message]);

            if ($this->clientConnect) {
                Server::write(json_encode($data), $this->clientConnect);
            } else {
                \Yii::$app->trigger(
                    Server::EVENT_MESSAGE_REQUEST_TO_SEND_OFFLINE,
                    (new Event(['message' => $message, 'context' => 'toClient']))
                );
            }

            if ($this->shopConnect) {
                Server::write(json_encode($data), $this->shopConnect);
            } else {
                \Yii::$app->trigger(
                    Server::EVENT_MESSAGE_REQUEST_TO_SEND_OFFLINE,
                    (new Event(['message' => $message, 'context' => 'toShop']))
                );
            }
        }
    }

    /**
     * @param bool $dbSave
     *
     * @return bool
     */
    public function save($dbSave = false)
    {
        $data = get_object_vars($this);
        $data = array_filter($data, function ($key) {
            return !in_array($key, ['shopConnect', 'clientConnect']);
        }, ARRAY_FILTER_USE_KEY);

        if (strtotime($data['last_update']) < strtotime('-10 MINUTES') || $dbSave) {
            $model = \Yii::$app->getDb()->cache(function () use ($id) {
                return ChatRoomBase::findOne(['hash' => $data['id']]);
            }, 60);
            if (!$model) {
                $model       = new ChatRoomBase();
                $model->hash = $data['id'];
            }

            $model->setAttributes($data);
            $model->last_update = date('Y-m-d H:i:s');
            $model->save();

            $message = new ChatMessage();


            $data['last_update'] = date('Y-m-d H:i:s');
        }

        $data = json_encode($data);

        return \Yii::$app->getCache()->set($this->id, $data);
    }

    /**
     * @param Connection $connect
     */
    public function setClientConnect($connect)
    {
        $this->clientConnectId = Server::getConnectId($connect);
        $this->save();
        $this->clientConnect = $connect;
    }

    /**
     * @param $messages
     *
     * @return array
     */
    public function prepareData($messages):array
    {
        if (!$this->user_avatar && $this->user_id) {
            $this->user_avatar = User::findOne($this->user_id)->getAvatarUrl();
        }

        if (!$this->seller_avatar && $this->seller_id) {
            $this->seller_avatar = User::findOne($this->seller_id)->getAvatarUrl();
        }

        $data             = [];
        $data['user']     = ['id' => $this->user_id, 'avatar' => $this->user_avatar, 'online' => false, 'typing' => false];
        $data['seller']   = ['id' => $this->seller_id, 'avatar' => $this->seller_avatar, 'online' => false, 'typing' => false];
        $data['messages'] = $messages;
        if (!$this->clientConnect && $this->clientConnectId) {
            $this->clientConnect    = Server::getConnectByUser($this->clientConnectId);
            $data['user']['online'] = $this->clientConnect !== null;
        }

        if (!$this->shopConnect && $this->seller_id) {
            $this->shopConnect        = Server::getConnectByUser($this->seller_id);
            $data['seller']['online'] = $this->shopConnect !== null;
        }

        return $data;
    }
}
