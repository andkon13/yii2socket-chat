<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 19.07.16
 * Time: 16:20
 */

namespace andkon\yii2SocketChat;

use common\models\User;
use common\modules\shop\models\Shop;
use andkon\yii2SocketChat\Room;
use yii\helpers\Html;

/**
 * Class ClientWidget
 *
 * @package andkon\yii2SocketChat
 */
class ClientWidget extends AbstractWidget
{
    /**
     * @return bool
     */
    public function init()
    {
        if (!$this->check()) {
            return false;
        }
        parent::init();
        ClientAssets::register($this->getView());
    }

    /**
     * @return bool
     */
    protected function check()
    {
        try {
            Shop::getCurrent();
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function run()
    {
        if (!$this->check()) {
            return false;
        }

        echo Html::button('Онлайн-чат', ['onclick' => 'chat.open()']);
        echo Html::beginTag($this->containerTag, $this->containerOptions);
        echo Html::tag('div', 'Загрузка', ['class' => 'list']);
        echo Html::textarea('chat', '', ['class' => 'message']);
        echo Html::button('Отправить', ['class' => 'messSend']);
        echo Html::endTag($this->containerTag);
    }

    /** @inheritdoc */
    protected function setCache()
    {
        $data = [
            'id'        => $this->id,
            'user_id'   => \Yii::$app->getUser()->getId(),
            'shop_id'   => Shop::getCurrent()->id,
            'seller_id' => Shop::getCurrent()->user_id,
            'isShop'    => false,
        ];
        Room::initRoom($data);
    }
}