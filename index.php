<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__DIR__);

$context->classes
        ->add('IvoPetkov\BearFrameworkAddons\Notifications', 'classes/Notifications.php')
        ->add('IvoPetkov\BearFrameworkAddons\Notifications\*', 'classes/Notifications/*.php');

$app->shortcuts
        ->add('notifications', function() {
            return new \IvoPetkov\BearFrameworkAddons\Notifications();
        });
