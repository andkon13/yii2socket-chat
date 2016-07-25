<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 19.07.16
 * Time: 16:24
 */

namespace andkon\yii2SocketChat;

use yii\web\AssetBundle;

/**
 * Class ClientAssets
 *
 * @package andkon\yii2SocketChat\assets
 */
class SellerAssets extends AssetBundle
{
    public $css = [];
    public $js = [
        'js/seller.js'
    ];

    public $depends = [
        'yii\web\YiiAsset',
        'yii\web\JqueryAsset',
    ];

    /** @inheritdoc */
    public function init()
    {
        $this->sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
        parent::init();
    }
}
