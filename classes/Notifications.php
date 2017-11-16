<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;
use IvoPetkov\BearFrameworkAddons\Notifications\Notification;

/**
 * Notifications utilities.
 */
class Notifications
{

    /**
     *
     */
    private static $newNotificationCache = null;

    /**
     * Constructs a new notification and returns it.
     * 
     * @param ?string $id The notification ID.
     * @param ?string $text The notification text.
     * @return \BearFramework\Notifications\Notification
     */
    public function make(string $id = null, string $text = null): Notification
    {
        if (self::$newNotificationCache === null) {
            self::$newNotificationCache = new Notification();
        }
        $notification = clone(self::$newNotificationCache);
        if ($id !== null) {
            $notification->id = $id;
        }
        if ($text !== null) {
            $notification->text = $text;
        }
        return $notification;
    }

    /**
     * Sends a notification.
     * 
     * @param string $recipientID The recipient ID.
     * @param \BearFramework\Notifications\Notification $notification The notification to send.
     * @return void No value is returned.
     * @throws \Exception
     */
    public function send(string $recipientID, Notification $notification): void
    {
        $app = App::get();

        if ($notification->id === null) {
            $notification->id = 'n' . uniqid() . 'x' . base_convert(rand(0, 999999999), 10, 16);
        }

        if ($notification->dateCreated === null) {
            $notification->dateCreated = time();
        }

        if ($notification->maxAge === null) {
            $notification->maxAge = 30 * 86400;
        }

        if ($app->hooks->exists('notificationSend')) {
            $preventDefault = false;
            $app->hooks->execute('notificationSend', $notification, $preventDefault);
            if ($preventDefault) {
                return;
            }
        }

        $this->set($recipientID, $notification);

        $app->hooks->execute('notificationSent', $notification);
    }

    private function set(string $recipientID, Notification $notification): void
    {
        $app = App::get();

        $notificationID = $notification->id;
        $dataItem = $app->data->make($this->getNotificationDataKey($recipientID, $notificationID), $notification->toJSON());
        $app->data->set($dataItem);
    }

    public function get($recipientID, $notificationID)//: ?Notification
    {
        $app = App::get();

        $notificationRawData = $app->data->getValue($this->getNotificationDataKey($recipientID, $notificationID));
        if ($notificationRawData === null) {
            return null;
        }
        return $this->constructNotificationFromRawData($notificationRawData);
    }

    private function constructNotificationFromRawData($rawData)
    {
        $notification = $this->make();
        $data = json_decode($rawData, true);
        $notification->id = isset($data['id']) ? $data['id'] : null;
        $notification->title = isset($data['title']) ? $data['title'] : null;
        $notification->text = isset($data['text']) ? $data['text'] : null;
        $notification->priority = isset($data['priority']) ? $data['priority'] : 3;
        $notification->status = isset($data['status']) ? $data['status'] : 'unread';
        $notification->dateCreated = isset($data['dateCreated']) ? $data['dateCreated'] : null;
        $notification->maxAge = isset($data['maxAge']) ? $data['maxAge'] : 30 * 86400;
        return $notification;
    }

    public function markAsRead($recipientID, $notificationID)
    {
        $this->setStatus($recipientID, $notificationID, 'read');
    }

    public function markAsUnread($recipientID, $notificationID)
    {
        $this->setStatus($recipientID, $notificationID, 'unread');
    }

    private function setStatus($recipientID, $notificationID, $status)
    {
        $notification = $this->get($recipientID, $notificationID);
        if ($notification instanceof Notification) {
            $notification->status = $status;
            $this->set($recipientID, $notification);
        }
    }

    public function delete($recipientID, $notificationID)
    {
        $app = App::get();

        $app->data->delete($this->getNotificationDataKey($recipientID, $notificationID));
    }

    public function deleteAll($recipientID)
    {
        $app = App::get();
        $notificationDataItems = $app->data->getList()->filterBy('key', $this->getRecipientDataKeyPrefix($recipientID), 'startWith');
        foreach ($notificationDataItems as $notificationDataItem) {
            $app->data->delete($notificationDataItem->key);
        }
    }

    public function getList($recipientID)
    {
        $app = App::get();

        return new \IvoPetkov\DataList(function($context) use ($app, $recipientID) {
            $result = [];
            $notificationDataItems = $app->data->getList()->filterBy('key', $this->getRecipientDataKeyPrefix($recipientID), 'startWith');
            foreach ($notificationDataItems as $notificationDataItem) {
                $notification = $this->constructNotificationFromRawData($notificationDataItem->value);
                if (!$this->deleteIfOld($recipientID, $notification)) {
                    $result[] = $notification;
                }
            }
            return $result;
        });
    }

    public function getUnreadCount($recipientID)
    {
        $list = $this->getList($recipientID);
        $count = 0;
        foreach ($list as $notification) {
            if ($notification->status === 'unread') {
                $count++;
            }
        }
        return $count;
    }

    private function deleteIfOld(string $recipientID, Notification $notification)
    {
        if ($notification->dateCreated + $notification->maxAge < time()) {
            $this->delete($recipientID, $notification->id);
            return true;
        }
        return false;
    }

    private function getRecipientDataKeyPrefix($recipientID)
    {
        $recipientIDMD5 = md5($recipientID);
        return 'notifications/recipients/recipient/' . substr($recipientIDMD5, 0, 2) . '/' . substr($recipientIDMD5, 2, 2) . '/' . $recipientIDMD5 . '/';
    }

    private function getNotificationDataKey($recipientID, $notificationID)
    {
        $recipientIDMD5 = md5($recipientID);
        $notificationIDMD5 = md5($notificationID);
        return 'notifications/recipients/recipient/' . substr($recipientIDMD5, 0, 2) . '/' . substr($recipientIDMD5, 2, 2) . '/' . $recipientIDMD5 . '/notifications/notification/' . substr($notificationIDMD5, 0, 2) . '/' . substr($notificationIDMD5, 2, 2) . '/' . $notificationIDMD5 . '.json';
    }

}
