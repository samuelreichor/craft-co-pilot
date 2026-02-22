<?php

namespace samuelreichor\coPilot\web\assets\chat;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SlideoutAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $css = [
        'copilot-chat.css',
    ];

    public $js = [
        'copilot-slideout.js',
    ];
}
