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
    
    /**
     * Create a new AwsSesTransport.
     * 
     * @param AwsSesClient $ses_client 
     * @param boolean $catch_exception 
     * @param boolean $debug Set to true to enable debug messages in error log.
     */
    public function __construct($ses_client, $catch_exception=false, $debug = false) 
    {
        parent::__construct($ses_client, $catch_exception, $debug);
    }

    /**
     * Create a new AwsSesTransport.
     * 
     * @param AwsSesClient $ses_client 
     * @param boolean $catch_exception 
     * @param boolean $debug Set to true to enable debug messages in error log.
     */
    public static function newInstance($ses_client, $catch_exception=false, $debug = false) 
    {
        return new Swift_AwsSesTransport($ses_client, $catch_exception, $debug);
    }

    /**
     * Send the given Message.
     * 
     * <p>
     * Recipient/sender data will be retrieved from the Message API is necessary
     *
     * @param Swift_Mime_Message $message
     * @param string[] &$failedRecipients to collect failures by-reference
     * @return int number of recipients who were accepted for delivery
     * @throws Exception on any errors if $catch_exception is false
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null) 
    {

        $failedRecipients = (array) $failedRecipients;

//        if ($evt = $this->_eventDispatcher->createSendEvent($this, $message)) {
//            $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
//            if ($evt->bubbleCancelled())
//                return 0;
//        }
        
        $this->beforeSendPerformed($message);

        $this->send_count = 0;
        $this->send_status = Swift_Events_SendEvent::RESULT_TENTATIVE;

        try 
        {
            
            // enforce from 
            $from = $message->getSender() ?: $message->getFrom();
            $fromEmail = key($from);
            $this->ses_client->setFrom("$from[$fromEmail] <$fromEmail>");
            
            $this->do_send($message);
                        
            $this->sendPerformed($message);
            
        } catch (\Exception $e) {
            
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
//        // Send SwiftMailer Event
//        if ($evt) {
//            $evt->setResult($send_status);
//            $evt->setFailedRecipients($failedRecipients);
//            $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
//        }
        

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
        
        $dest = $this->ses_client->isVersion2() ? $this->getDestinations($message) : [];
        $this->response = $this->ses_client->sendRawEmail($message->toString(), $dest);
        
        // report message ID and headers
        $headers = $message->getHeaders();
        $headers->addTextHeader('X-SES-Message-ID', $this->response->get('MessageId'));
        $this->send_count = $this->numberOfRecipients($message);
        
    }

}
