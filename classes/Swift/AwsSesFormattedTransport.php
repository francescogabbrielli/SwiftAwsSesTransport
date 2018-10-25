<?php
/*
 * This file requires SwiftMailer and Aws Ses PHP Api V2 and V3
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Sends Messages over AWS SES using simple formatted sendEmail API.
 * 
 * NOTE: Does not allow attachements. Requires Html2Text.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesFormattedTransport extends Swift_AwsSesTransport
{

    /**
     * Send via Aws sendEmail and report the result
     * 
     * @param Swift_Mime_Message $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message) 
    {
        
        $this->response = $this->ses_client->sendEmail(
            $this->getDestinations($message, "to", "cc", "bcc"),
            $message->getSubject(),
            $message->getBody(),
            (new Html2Text\Html2Text($message->getBody()))->getText() 
        );

        // report message ID and count
        $headers = $message->getHeaders();
        $headers->addTextHeader('X-SES-Message-ID', $this->response->get('MessageId'));
        $this->send_count = $this->numberOfRecipients($message);
        
    }
    
}