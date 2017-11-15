<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->context->get(__FILE__);

$context->classes
        ->add('\IvoPetkov\BearFrameworkAddons\Notifications', 'classes/Notifications.php')
        ->add('\IvoPetkov\BearFrameworkAddons\Notifications\Notification', 'classes/Notifications/Notification.php');

$app->shortcuts
        ->add('notifications', function() {
            return new \IvoPetkov\BearFrameworkAddons\Notifications();
        });
