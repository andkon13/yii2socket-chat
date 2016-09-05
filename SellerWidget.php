<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 22.07.16
 * Time: 15:12
 */

namespace andkon\yii2SocketChat;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\Cookie;
use yii\web\JsExpression;

/**
 * Class SellerWidget
 *
 * @package andkon\yii2SocketChat
 */
class SellerWidget extends AbstractWidget
{
    public $tabTemplate = '<div onclick=\"chat.setCurrentChat(\'{room}\')\" class=\"tab chat\"><b>{name}</b><div id=\"{room}\"></div></div>';

    public function init()
    {
        parent::init();
        SellerAssets::register($this->getView());
        $this->getView()->registerJs(new JsExpression('chat.tabTemplate="' . $this->tabTemplate . '";'));
    }

    /**
     * @return bool
     */
    public function run()
    {
        echo Html::button('Онлайн-чат', ['onclick' => 'chat.open()']);
        echo Html::beginTag($this->containerTag, $this->containerOptions);
        echo Html::tag('div', 'Загрузка', ['class' => 'list']);
        echo Html::beginTag('div', ['style' => 'display: none;', 'class' => 'response']);
        echo Html::textarea('chat', '', ['class' => 'message']);
        echo Html::button('Отправить', ['class' => 'messSend']);
        echo Html::endTag('div');
        echo Html::endTag($this->containerTag);
    }

    /** @inheritdoc */
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
        $cacheKey = 'sessionKeySeller';
        $key      = \Yii::$app->getRequest()->getCookies()->getValue($cacheKey);
        if (!$key) {
            $key = \Yii::$app->getSecurity()->generateRandomString(10);
            \Yii::$app->getResponse()->getCookies()->add(new Cookie(['name' => $cacheKey, 'value' => $key]));
        }

        return $key;
    }
}
