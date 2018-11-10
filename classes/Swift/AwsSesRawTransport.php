<?php

/*
 * This file requires SwiftMailer and Aws Ses PHP Api (V2 or V3)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require 'AwsSesClient.php';

/**
 * Sends Messages over AWS SES using sendRawEmail API.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesRawTransport extends Swift_AwsSesTransport 
{
    /**
     * Successful recipients number
     * 
     * @var int 
     */
    protected $count;
    
    /**
     * Implement send via Aws sendRawEmail and report the result
     * 
     * @param Swift_Mime_SimpleMessage $message the message
     * @param array $failedRecipients
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message, &$failedRecipients)
    {
        $dest = $this->client->isVersion2() ? $this->getDestinations($message) : [];
        return $this->client->sendRawEmail($message->toString(), $dest);
    }
           
    /**
     * Implement optional operations after message is sent
     * 
     * @param Swift_Mime_SimpleMessage $message the message
     * @param AwsResult $response the AWS response
     * @param array $failedRecipients
     * @return int the total number of recipients
     */
    protected function do_sent($message, $response, &$failedRecipients)
    {
        // report message ID in headers
        $headers = $message->getHeaders();
        $headers->addTextHeader('X-SES-Message-ID', $response->get('MessageId'));
        $this->count = $this->numberOfRecipients($message);
        return $this->count;
    }
    
    /**
     * Default tags (for v3).
     * 
     * @param array $tags array [name1 => value1, etc]
     */
    public function setTags($tags) 
    {
        $this->client->setTags($tags);
        return $this;
    }
    
    /**
     * Count successful recipients
     * 
     * @return int
     */
    public function getCount() {
        return $this->count;
    }
}
