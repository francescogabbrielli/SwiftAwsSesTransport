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
 * NOTE: Does not allow attachments. Requires Html2Text.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesFormattedTransport extends Swift_AwsSesRawTransport
{
    
    /**
     * Send via Aws sendEmail and report the result
     * 
     * @param Swift_Mime_Message $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message, &$failedRecipients) 
    {
        return $this->client->sendEmail(
            $this->getDestinations($message, "to", "cc", "bcc"),
            $message->getSubject(),
            $message->getBody(),
            (new Html2Text\Html2Text($message->getBody()))->getText() 
        );
    }
    
}