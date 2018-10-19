<?php

/*
 * This file requires SwiftMailer and Aws Ses PHP Api V2 and V3
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Sends Messages over AWS SES.
 * 
 * <p>
 * This Transport is meant to fully exploit Aws Ses API beyond sendRawMessage,
 * that is implemented as default.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesTransport extends Swift_Transport_AwsSesTransport {

    /**
     * The Aws SesClient 
     * 
     * @var \SesClient from api V2 or V3
     */
    private $ses_client;

    /**
     * The send method 
     * 
     * @var string
     */
    private $send_method;

    /**
     * @var \AwsResult 
     */
    private $response;
    
    /* 
     * Total recipients sent 
     * 
     * @var int
     */
    private $send_count;

    /** 
     * AWS message request (to specify all the desired AWS arguments).
     * 
     * Check Aws Sws documentation for arguments usage:
     * https://docs.aws.amazon.com/it_it/ses/latest/APIReference/Welcome.html
     * 
     * @var array 
     * @seealso Swift_AwsSesTransport::setArg()
     */
    private $msg_request;
    

    /**
     * Debugging helper.
     *
     * If false, no debugging will be done.
     * If true, debugging will be done with error_log.
     * Otherwise, this should be a callable, and will recieve the debug message as the first argument.
     *
     * @var mixed boolean or callable
     * @seealso Swift_AwsSesTransport::setDebug()
     */
    private $debug;
    
    /**
     * Catch exception and just return a result in SwiftMailer send
     * 
     * @var boolean
     */
    private $catch_exception;

    /**
     * Create a new AwsSesTransport.
     * 
     * @param \SesClient $ses_client The AWS SES Client V2 or V3.
     * @param string $config_set ConfigurationSetName argument or null for V2 Api
     * @param string $send_method the method that client uses to send message (default is sendRawEmail)
     * @param boolean $debug Set to true to enable debug messages in error log.
     */
    public function __construct($ses_client, $config_set, $send_method = "sendRawEmail", $catch_exception=false, $debug = false) {
        call_user_func_array(
                array($this, 'Swift_Transport_AwsSesTransport::__construct'), Swift_DependencyContainer::getInstance()
                        ->createDependenciesFor('transport.aws')
        );

        $this->ses_client = $ses_client;
        $this->send_method = $send_method;
        $this->debug = $debug;
        $this->catch_exception = $catch_exception;
        $this->version2 = is_null($config_set);
        $this->msg_request =  $this->version2 ? [] : ["ConfigurationSetName" => $config_set];
    }

    /**
     * Create a new AwsSesTransport.
     * 
     * @param \SesClient $ses_client The AWS SES Client.
     * @param string $send_method the method that client uses to send message (default is sendRawEmail)
     */
    public static function newInstance($ses_client, $send_method) {
        return new Swift_AwsSesTransport($ses_client, $send_method);
    }

    public function setDebug($val) {
        $this->debug = $val;
    }

    public function setSesClient($ses_client) {
        $this->ses_client = $ses_client;
    }

    public function setSendMethod($method) {
        $this->send_method = $method;
    }
    
    public function setCatchException($enable_catch) {
        $this->catch_exception = $enable_catch;
    }

    /**
     * Set an argument value for AWS message request
     * 
     * @param string $name argument name
     * @param mixed $value argument value (string or array)
     */
    public function setArg($name, $value) {
        $this->msg_request[$name] = $value;
    }

    protected function _debug($message) {
        if (true === $this->debug) {
            error_log($message);
        } elseif (is_callable($this->debug)) {
            call_user_func($this->debug, $message);
        }
    }

    /**
     * Send the given Message.
     * 
     * <p>
     * Recipient/sender data will be retreived from the Message API is necessary
     *
     * @param Swift_Mime_Message $message
     * @param string[] &$failedRecipients to collect failures by-reference
     * @return int number of recipients who were accepted for delivery
     * @throws Exception on any errors if $catch_exception is false
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null) {

        $failedRecipients = (array) $failedRecipients;

        if ($evt = $this->_eventDispatcher->createSendEvent($this, $message)) {
            $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled())
                return 0;
        }

        $this->send_count = 0;
        $send_status = Swift_Events_SendEvent::RESULT_TENTATIVE;

        try {
            
            // enforce from 
            $from = $message->getSender() ?: $message->getFrom();
            $fromEmail = key($from);
            $this->msg_request['Source'] = "$from[$fromEmail] <$fromEmail>";
            
            $this->do_send($message);
            
            // report message ID
            $headers = $message->getHeaders();
            $headers->addTextHeader('X-SES-Message-ID', $this->response->get('MessageId'));
            
        } catch (\Exception $e) {
            
            $failedRecipients = $this->getDestinations($message);
            $this->send_count = 0;
            $send_status = Swift_Events_SendEvent::RESULT_FAILED;
            if (!$this->catch_exception)
                throw $e;
        }
        
        $send_status = Swift_Events_SendEvent::RESULT_SUCCESS;

        $this->_debug("=== Start AWS Response ===");
        $this->_debug($this->response);
        $this->_debug("=== End AWS Response ===");

        if ($respEvent = $this->_eventDispatcher->createResponseEvent(
                $this, $this->response, 
                resultStatus == Swift_Events_SendEvent::RESULT_SUCCESS)) {
            $this->_eventDispatcher->dispatchEvent($respEvent, 'awsResponse');
        }

        // Send SwiftMailer Event
        if ($evt) {
            $evt->setResult($send_status);
            $evt->setFailedRecipients($failedRecipients);
            $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
        }

        return $this->send_count;
        
    }
    
    /**
     * Do the actual send via API.
     * 
     * @param type $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    private function doSend($message) {
        
        $callable = $this->ses_client->{$send_method};
        $length = strlen($send_method);
        
        if ($this->send_method == "sendRawEmail")
        {
            $raw_data = $message->toString();
            if (base64_decode($raw_data, true) === false)
                $raw_data = base64_encode($raw_data);
            $this->msg_request['RawMessage'] = ['Data' => $raw_data];
            if ($this->version2)
                $this->msg_request['Destinations'] = $this->getDestinations($message);
            $this->response = $this->ses_client->sendRawEmail($this->msg_request);
            $this->send_count = count(array_merge(
                        array_keys((array) $message->getTo()),
                        array_keys((array) $message->getCc()),
                        array_keys((array) $message->getBcc())
                    ));   
        }
        else if (is_callable($callable) && substr($this->send_method, 0, $length) === "send")
        {
            $this->response = $callable($this->msg_request);
            //$this->send_count = TODO
        }
        else
            throw new Exception("Method not allowed: $this->send_method");        
    }
    
    /**
     * Retrieve destinations from Message API
     * @param type $message
     */
    private function getDestinations($message) {
        $dest = ["To" => join(",", array_keys($message->getTo()))];
        if (!empty($message->getCc()))
            $dest["CC"] = join(",", array_keys($message->getCc()));
        if (!empty($message->getBcc()))
            $dest["BCC"] = join(",", array_keys($message->getBcc()));
    }
    
    /**
     * Get the AwsResult object
     *
     * @return AwsResult
     */
    public function getResponse() {
        return $this->response;
    }
    
    /**
     * Get the total messages sent
     * 
     * @return int
     */
    public function getSendCount() {
        return $this->send_count;
    }

    public function isStarted() {
        return true;
    }

    public function start() {
        return true;
    }

    public function stop() {
        return true;
    }

    /**
     * Register a plugin.
     *
     * @param Swift_Events_EventListener $plugin
     */
    public function registerPlugin(Swift_Events_EventListener $plugin) {
        $this->_eventDispatcher->bindEventListener($plugin);
    }

}
