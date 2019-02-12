<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) Ivo Petkov
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

    use \BearFramework\App\EventsTrait;

    /**
     *
     */
    private static $newNotificationCache = null;

    /**
     * Constructs a new notification and returns it.
     * 
     * @param ?string $title The notification title.
     * @param ?string $text The notification text.
     * @return \BearFramework\Notifications\Notification
     */
    public function make(string $title = null, string $text = null): Notification
    {
        if (self::$newNotificationCache === null) {
            self::$newNotificationCache = new Notification();
        }
        $notification = clone(self::$newNotificationCache);
        if ($title !== null) {
            $notification->title = $title;
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

        if ($this->hasEventListeners('beforeSendNotification')) {
            $eventDetails = new \IvoPetkov\BearFrameworkAddons\Notifications\BeforeSendNotificationEventDetails($recipientID, $notification);
            $this->dispatchEvent('beforeSendNotification', $eventDetails);
            if ($eventDetails->preventDefault) {
                return;
            }
        }

        if (strlen($notification->type) > 0) {
            $otherNotifications = $this->getList($recipientID);
            foreach ($otherNotifications as $otherNotification) {
                if ((string) $notification->type === (string) $otherNotification->type) {
                    $this->delete($recipientID, $otherNotification->id);
                }
            }
        }

        $this->set($recipientID, $notification);

        if ($this->hasEventListeners('sendNotification')) {
            $eventDetails = new \IvoPetkov\BearFrameworkAddons\Notifications\SendNotificationEventDetails($recipientID, $notification);
            $this->dispatchEvent('sendNotification', $eventDetails);
        }
    }

    /**
     * 
     * @param string $recipientID
     * @param Notification $notification
     */
    private function set(string $recipientID, Notification $notification): void
    {
        $app = App::get();

        $notificationID = $notification->id;
        $dataItem = $app->data->make($this->getNotificationDataKey($recipientID, $notificationID), $notification->toJSON());
        $app->data->set($dataItem);
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     * @return Notification|null
     */
    public function get(string $recipientID, string $notificationID): ?Notification
    {
        $app = App::get();

        $notificationRawData = $app->data->getValue($this->getNotificationDataKey($recipientID, $notificationID));
        if ($notificationRawData === null) {
            return null;
        }
        return $this->constructNotificationFromRawData($notificationRawData);
    }

    /**
     * 
     * @param string $rawData
     * @return Notification
     */
    private function constructNotificationFromRawData(string $rawData): Notification
    {
        $notification = $this->make();
        $data = json_decode($rawData, true);
        $notification->id = isset($data['id']) ? $data['id'] : null;
        $notification->type = isset($data['type']) ? $data['type'] : null;
        $notification->title = isset($data['title']) ? $data['title'] : null;
        $notification->text = isset($data['text']) ? $data['text'] : null;
        $notification->priority = isset($data['priority']) ? $data['priority'] : 3;
        $notification->status = isset($data['status']) ? $data['status'] : 'unread';
        $notification->dateCreated = isset($data['dateCreated']) ? $data['dateCreated'] : null;
        $notification->maxAge = isset($data['maxAge']) ? $data['maxAge'] : 40 * 86400;
        $notification->data = isset($data['data']) ? $data['data'] : [];
        $notification->clickUrl = isset($data['clickUrl']) ? $data['clickUrl'] : null;
        return $notification;
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     */
    public function markAsRead(string $recipientID, string $notificationID): void
    {
        $this->setReadStatus($recipientID, $notificationID, 'read');
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     */
    public function markAsUnread(string $recipientID, string $notificationID): void
    {
        $this->setReadStatus($recipientID, $notificationID, 'unread');
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     * @param string $status
     */
    private function setReadStatus(string $recipientID, string $notificationID, string $status): void
    {
        $notification = $this->get($recipientID, $notificationID);
        if ($notification instanceof Notification) {
            if ($notification->status !== $status) {
                $notification->status = $status;
                $this->set($recipientID, $notification);
            }
        }
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     */
    public function delete(string $recipientID, string $notificationID): void
    {
        $app = App::get();

        $app->data->delete($this->getNotificationDataKey($recipientID, $notificationID));
    }

    /**
     * 
     * @param string $recipientID
     */
    public function deleteAll(string $recipientID): void
    {
        $app = App::get();
        $notificationDataItems = $app->data->getList()->filterBy('key', $this->getRecipientDataKeyPrefix($recipientID), 'startWith');
        foreach ($notificationDataItems as $notificationDataItem) {
            $app->data->delete($notificationDataItem->key);
        }
    }

    /**
     * 
     * @param string $recipientID
     * @return \IvoPetkov\DataList
     */
    public function getList(string $recipientID): \IvoPetkov\DataList
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

    /**
     * 
     * @param string $recipientID
     * @return int
     */
    public function getUnreadCount(string $recipientID): int
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

    /**
     * 
     * @param string $recipientID
     * @param Notification $notification
     * @return bool
     */
    private function deleteIfOld(string $recipientID, Notification $notification): bool
    {
        if ($notification->dateCreated + $notification->maxAge < time()) {
            $this->delete($recipientID, $notification->id);
            return true;
        }
        return false;
    }

    /**
     * 
     * @param string $recipientID
     * @return string
     */
    private function getRecipientDataKeyPrefix(string $recipientID): string
    {
        $recipientIDMD5 = md5($recipientID);
        return 'notifications/recipients/recipient/' . substr($recipientIDMD5, 0, 2) . '/' . substr($recipientIDMD5, 2, 2) . '/' . $recipientIDMD5 . '/';
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     * @return string
     */
    private function getNotificationDataKey(string $recipientID, string $notificationID): string
    {
        $recipientIDMD5 = md5($recipientID);
        $notificationIDMD5 = md5($notificationID);
        return 'notifications/recipients/recipient/' . substr($recipientIDMD5, 0, 2) . '/' . substr($recipientIDMD5, 2, 2) . '/' . $recipientIDMD5 . '/notifications/notification/' . substr($notificationIDMD5, 0, 2) . '/' . substr($notificationIDMD5, 2, 2) . '/' . $notificationIDMD5 . '.json';
    }

}
