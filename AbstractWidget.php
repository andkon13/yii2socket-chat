<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 22.07.16
 * Time: 15:12
 */

namespace andkon\yii2SocketChat;

use common\modules\shop\models\Shop;
use common\modules\user\models\User;
use yii\base\Widget;
use yii\web\View;

/**
 * Class AbstractWidget
 *
 * @package andkon\yii2SocketChat
 */
abstract class AbstractWidget extends Widget
{
    /**
     * @var string
     */
    public $classUserMessage = 'current';
    /**
     * @var array
     */
    public $containerOptions = [];
    /**
     * @var User
     */
    public $user;
    /**
     * @var string
     */
    public $containerTag = 'div';
    /**
     * @var string
     */
    public $messageTemplate = '<div class="{class}">{message} <span>{date}</span></div>';

    /**
     *
     */
    public function init()
    {
        parent::init();
        $this->id = $this->getChatId();
        $url      = \Yii::$app->getHomeUrl() . ':' . \Yii::$app->getComponents()['chat']['port'] ?? 1337;
        $url .= '/' . $this->id;
        $apponent = \Yii::t('app', $this->messageTemplate);
        $current  = \Yii::t('app', $this->messageTemplate, ['class' => $this->classUserMessage]);
        $user_id  = (\Yii::$app->getUser()->getIsGuest()) ? $this->id : \Yii::$app->getUser()->id;
        $js       = <<<JS
var clientChat = {url:'{$url}', chatId: '{$this->id}', messageTemplate: {current: '{$current}', apponent: '{$apponent}'}, user_id: '{$user_id}'}  
JS;
        $this->setCache();
        $this->getView()->registerJs($js, View::POS_HEAD);
        $this->getView()->registerCss('#container_' . $this->id . '{display: none;}');
        $this->containerOptions = array_merge($this->containerOptions, ['id' => 'container_' . $this->id]);
    }

    /**
     * Возвращает уникальный идентичикатор чата
     *
     * @return mixed|string
     */
    protected function getChatId()
    {
        $cacheKey = __CLASS__ . 'sessionKey' . Shop::getCurrent()->id;
        $key      = \Yii::$app->getSession()->get($cacheKey);
        if (!$key) {
            $key = \Yii::$app->getSecurity()->generateRandomString(10);
        }

        \Yii::$app->getSession()->set($cacheKey, $key);

        return $key;
    }

    /**
     * Пишет в кэш данные для соединения
     *
     * @return mixed
     */
    abstract protected function setCache();
}