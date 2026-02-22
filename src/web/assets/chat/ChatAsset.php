<?php

namespace samuelreichor\coPilot\web\assets\chat;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ChatAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $css = [
        'copilot-chat.css',
    ];

    public $js = [
        'copilot-chat.js',
    ];
}
