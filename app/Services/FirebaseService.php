<?php

namespace App\Services;

use App\Constants\Columns;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    public function sendNotification($deviceToken, $title, $body)
    {
        $message = CloudMessage::withTarget(Columns::token, $deviceToken)
            ->withNotification(Notification::create($title, $body));

        return $this->messaging->send($message);
    }
}
