<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 20.07.16
 * Time: 12:11
 */

namespace andkon\yii2SocketChat;
use React\Socket\Connection;

/**
 * Class Event
 *
 * @package console\controllers
 */
class Event extends \yii\base\Event
{
    /** @var  Connection */
    public $connect;
    public $message;
    public $context;
}