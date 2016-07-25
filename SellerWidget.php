<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 22.07.16
 * Time: 15:12
 */

namespace andkon\yii2SocketChat;

use common\helpers\ArrayHelper;
use common\modules\shop\models\Shop;
use vendor\andkon\yii2SocketChat\Room;
use yii\helpers\Html;

/**
 * Class SellerWidget
 *
 * @package vendor\andkon\yii2SocketChat
 */
class SellerWidget extends AbstractWidget
{
    public function init()
    {
        parent::init();
        SellerAssets::register($this->getView());
    }

    /**
     * @return bool
     */
    public function run()
    {
        echo Html::button('Онлайн-чат', ['onclick' => 'chat.open()']);
        echo Html::beginTag($this->containerTag, $this->containerOptions);
        echo Html::tag('div', 'Загрузка', ['class' => 'list']);
        echo Html::textarea('chat', '', ['class' => 'message']);
        echo Html::button('Отправить', ['class' => 'messSend']);
        echo Html::endTag($this->containerTag);
    }

    protected function setCache()
    {
        $shopsIds = ArrayHelper::getColumn($this->user->shops, 'id');
        $data     = [
            'id'        => $this->id,
            'user_id'   => null,
            'shop_id'   => $shopsIds,
            'seller_id' => $this->user->id,
            'isShop'    => true,
        ];
        Room::initRoom($data);
    }

    /**
     * @return mixed|string
     */
    protected function getChatId()
    {
        $cacheKey = __CLASS__ . 'sessionKeySeller';
        $key      = \Yii::$app->getSession()->get($cacheKey);
        if (!$key) {
            $key = \Yii::$app->getSecurity()->generateRandomString(10);
        }

        \Yii::$app->getSession()->set($cacheKey, $key);

        return $key;
    }
}
