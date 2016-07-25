<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 20.07.16
 * Time: 12:11
 */

namespace andkon\yii2SocketChat;

/**
 * Class Event
 *
 * @package console\controllers
 */
class Event extends \yii\base\Event
{
    public $connect;
    public $message;
    public $context;
}