<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 20.07.16
 * Time: 12:30
 */

namespace vendor\andkon\yii2SocketChat;

use andkon\yii2SocketChat\Server;
use React\Socket\Connection;
use yii\base\Model;

/**
 * Class Room
 *
 * @package vendor\andkon\yii2SocketChat
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
    public static function findById($id)
    {
        if (null === $id) {
            return null;
        }

        $data = \Yii::$app->getCache()->get($id);
        if (!$data) {
            $model = ChatRoomBase::findOne(['hash' => $id]);
            if (!$model) {
                return null;
            }

            $data            = $model->toArray();
            $data['id']      = $model->hash;
            $data['message'] = json_decode($model->messages);
        } else {
            $data['id'] = $id;
        }

        $shop   = (array_key_exists('shop', $data)) ? Server::getConnectById($data['shop']) : null;
        $client = (array_key_exists('client', $data)) ? Server::getConnectById($data['client']) : null;
        $model  = new self($data, $shop, $client);

        return $model;
    }

    /**
     * @param $data
     */
    public static function initRoom($data)
    {
        \Yii::$app->getCache()->set($data['id'], $data);
    }

    /**
     * @param $message
     */
    public function addMessage($message)
    {
        if (!empty($message)) {
            $message          = [
                'user_id' => $this->user_id,
                'user'    => (string)\common\models\User::findOne($this->user_id),
                'text'    => $message,
                'date'    => date('Y-m-d H:i:s'),
                'room'    => $this->id,
                'shop_id' => $this->shop_id
            ];
            $this->messages[] = $message;
            $this->save(true);
            if (!$this->clientConnect && $this->clientConnectId) {
                $this->clientConnect = Server::getConnectByUser($this->clientConnectId);
            }
            if ($this->clientConnect) {
                Server::write(json_encode([$message]), $this->clientConnect);
            }

            if (!$this->shopConnect && $this->seller_id) {
                $this->shopConnect = Server::getConnectByUser($this->seller_id);
            }
            if ($this->shopConnect) {
                Server::write(json_encode([$message]), $this->shopConnect);
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
            $model = ChatRoomBase::findOne(['hash' => $data['id']]);
            if (!$model) {
                $model       = new ChatRoomBase();
                $model->hash = $data['id'];
            }

            $model->setAttributes($data);
            $model->messages    = json_encode($model->messages);
            $model->last_update = date('Y-m-d H:i:s');
            $model->save();
            $data['last_update'] = date('Y-m-d H:i:s');
        }

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
}
