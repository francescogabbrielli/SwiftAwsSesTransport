<?php

/*
 * This file requires SwiftMailer and Aws Ses PHP Api V2 and V3
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Sends Messages over AWS SES using sendRawEmail API
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesTransport extends Swift_Transport_AwsSesTransport 
{
    
    public function __construct($client, $catch_exception, $debug)
    {
        parent::__construct(
                Swift_DependencyContainer::getInstance()->createDependenciesFor('transport.aws_ses'),
                $client, $catch_exception, $debug);
    }
    
    /**
     * Send the given Message.
     * 
     * Recipient/sender data will be retrieved from the Message API if necessary
     *
     * @param Swift_Mime_Message $message
     * @param string[] &$failedRecipients to collect failures by-reference
     * @return int number of recipients who were accepted for delivery
     * @throws Exception on any errors if $catch_exception is false
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null) 
    {

        $failedRecipients = (array) $failedRecipients;

        if ($evt = $this->_eventDispatcher->createSendEvent($this, $message)) {
            $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled())
                return 0;
        }
        
        $this->beforeSendPerformed($message);

        $this->send_count = 0;
        $this->send_status = Swift_Events_SendEvent::RESULT_TENTATIVE;

        try 
        {
            
            // enforce from 
            $from = $message->getSender() ?: $message->getFrom();
            $this->client->setFrom($this->mail_string($from)[0]);
            
            $this->do_send($message);
                        
            $this->sendPerformed($message);
            
        } catch (Exception $e) {
            
            $failedRecipients = $this->getDestinations($message);
            $this->send_count = 0;
            $this->send_status = Swift_Events_SendEvent::RESULT_FAILED;
            if (!$this->catch_exception)
                throw $e;
        }
        
        $this->send_status = Swift_Events_SendEvent::RESULT_SUCCESS;

        $this->_debug("=== Start AWS Response ===");
        $this->_debug($this->response);
        $this->_debug("=== End AWS Response ===");

//        if ($respEvent = $this->_eventDispatcher->createResponseEvent(
//                $this, $this->response, 
//                resultStatus == Swift_Events_SendEvent::RESULT_SUCCESS)) {
//            $this->_eventDispatcher->dispatchEvent($respEvent, 'awsResponse');
//        }
//
        // Send SwiftMailer Event
        if ($evt) {
            $evt->setResult($send_status);
            $evt->setFailedRecipients($failedRecipients);
            $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
        }
        
        return $this->send_count;
        
    }
    
    /**
     * Send via Aws sendRawEmail and report the result
     * 
     * @param Swift_Mime_Message $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message)
    {
        
        $dest = $this->client->isVersion2() ? $this->getDestinations($message) : [];
        $this->response = $this->client->sendRawEmail($message->toString(), $dest);
        
        // report message ID and headers
        $headers = $message->getHeaders();
        $headers->addTextHeader('X-SES-Message-ID', $this->response->get('MessageId'));
        $this->send_count = $this->numberOfRecipients($message);
        
    }
    
    /**
     * Default tags (for v3).
     * 
     * @param array $tags array [name1 => value1, etc]
     */
    public function setTags($tags) {
        $this->client->setTags($tags);
        return $this;
    }

}
