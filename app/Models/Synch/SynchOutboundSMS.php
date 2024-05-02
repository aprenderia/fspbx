<?php

namespace App\Models\Synch;

use App\Models\Messages;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $domain_setting_value
 * @property string|null $to_did
 * @property string|null $from_did
 * @property string|null $message
 */
class SynchOutboundSMS extends Model
{

    public $from;
    public $to;
    public $text;
    public $message_uuid;

    /**
     * Send the outbound SMS message.
     *
     * @return bool
     */
    public function send()
    {
        $message = Messages::find($this->message_uuid);

        if (!$message) {
            logger("Could not find sms entity from " . $this->from_did . " to " . $this->to_did);
        }

        // Logic to send the SMS message using a third-party Synch API,
        // This method should return a boolean indicating whether the message was sent successfully.

        $data = array(
            'from' => $this->from,
            'to' => $this->to,
            "text" => $this->text,
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('synch.api_key'),
            'Content-Type' => 'application/json'
        ])
            ->asJson()
            ->post(config('synch.message_broker_url') . "/publishMessages", $data);

        // Get result
        if (isset($response)) {
            $result = json_decode($response->body());
            logger($response->body());

            // Determine if the operation was successful
            if ($response->successful() && isset($result->success) && $result->success) {
                $message->status = 'success';
                if (isset($result->result->referenceId)) {
                    $message->reference_id = $result->result->referenceId;
                }
            } else {
                if (isset($result->reason, $result->detail)) {
                    $message->status = $result->detail;
                } elseif (isset($result->response) && !$result->response->success) {
                    $message->status = $result->response->detail;
                } else {
                    $message->status = 'unknown error';
                }
    
                if (isset($result->errors)) {
                    logger()->error("Error details:", $result->errors);
                }
            }
    
            $message->save();
        } else {
            logger()->error('SMS error. No response received from the server.');
            $message->status = 'failed';
            $message->save();
        }

        return true; // Change this to reflect the result of the API call.
    }

    /**
     * Determine if the outbound SMS message was sent successfully.
     *
     * @return bool
     */
    public function wasSent()
    {
        // Logic to determine if the message was sent successfully using a third-party API.

        return true; // Change this to reflect the result of the API call.
    }
}
