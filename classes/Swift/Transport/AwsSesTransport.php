<?php

/**
 * This file declare the Swist_Transport_AwsSesTransport class.
 *
 * @author Francesco Gabbrielli
 */

use AwsSesWrapper\AwsSesWrapper;

/**
 * The base class for aws transport
 */
abstract class Swift_Transport_AwsSesTransport implements Swift_Transport 
{
    /** The event dispatcher from the plugin API */
    protected $_eventDispatcher;
    
    /**
     * Aws Ses client (wrapper)
     * 
     * @var AwsSesWrapper
     */
    protected $client;

    /**
     * Don't throw any exception and just return a result in SwiftMailer send.
     * (default is false, ie exceptions are thrown)
     * 
     * @var boolean
     */
    protected $catch_exception;

    /**
     * @var mixed boolean or callable
     * @seealso setDebug()
     */
    protected $debug;
    
    /**
     * The "internal" plug-ins registered with the transport.
     *
     * @var array
     */
    public $plugins = [];
    
    /**
     *
     * @var Swift_Event
     */
    private $evt;
    
    /**
     * @param Swift_Events_EventDispatcher $eventDispatcher
     * @param AwsSesWrapper $client client wrapper
     */
    public function __construct(Swift_Events_EventDispatcher $eventDispatcher, 
            $client, $catch_exception=false, $debug=false) 
    {
        
        $this->_eventDispatcher = $eventDispatcher;
        
        $this->client = $client;
        $this->debug = $debug;
        $this->catch_exception = $catch_exception;        
    }
    
    /**
     * Debugging helper.
     *
     * If false, no debugging will be done.
     * If true, debugging will be done with error_log.
     * Otherwise, this should be a callable, and will receive the debug message as the first argument.
     * 
     * @param mixed $val boolean or callable
     */
    public function setDebug($val) 
    {
        $this->debug = $val;
        $this->client->setDebug($val);
        return $this;
    }
    
    /**
     * 
     * @param boolean $enable_catch
     */
    public function setCatchException($enable_catch) 
    {
        $this->catch_exception = $enable_catch;
        return $this;
    }

    /**
     * 
     * @param boolean $async
     */
    public function setAsync($async) {
        $this->client->setAsync($async);
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return true;
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function ping()
    {
        return true;
    }
    
    /**
     * Register an "internal" plug-in with the transport, and binds it to 
     * the swift event dispatcher
     *
     * @param  \Swift_Events_EventListener  $plugin
     * @return Swift_AwsSesTransport
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        array_push($this->plugins, $plugin);
        $this->_eventDispatcher->bindEventListener($plugin);
        return $this;
    }
    
    /**
     * Iterate through registered plugins and execute plugins' methods.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     */
    protected function beforeSendPerformed(Swift_Mime_SimpleMessage $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'beforeSendPerformed')) {
                $plugin->beforeSendPerformed($event);
            }
        }
        
        if ($this->evt = $this->_eventDispatcher->createSendEvent($this, $message)) {
            $this->_eventDispatcher->dispatchEvent($this->evt, 'beforeSendPerformed');
            if ($this->evt->bubbleCancelled())
                return 0;
        }        
    }
    
    /**
     * Iterate through registered plugins and execute plugins' methods.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @param mixed $response Aws\Response
     * @param array $failedRecipients
     */
    protected function sendPerformed(Swift_Mime_SimpleMessage $message, $response, $failedRecipients)
    {
        $event = new Swift_Events_SendEvent($this, $message);
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'sendPerformed')) {
                $plugin->sendPerformed($event);
            }
        }
        
        $statusCode = $this->client->isVersion2() ? 200 : $response->get("@metadata")["statusCode"];
        $this->_debug("STATUS CODE: $statusCode ");

        // aws response event
        if ($respEvent = $this->_eventDispatcher->createResponseEvent(
                $this, $response, 
                $statusCode === 200)) {
            $this->_eventDispatcher->dispatchEvent($respEvent, 'responseReceived');
        }

        // Send SwiftMailer Event
        if ($this->evt) {
            $this->evt->setResult($statusCode===200 
                    ? Swift_Events_SendEvent::RESULT_SUCCESS 
                    : Swift_Events_SendEvent::RESULT_FAILED);//TODO: implement more...
            $this->evt->setFailedRecipients($failedRecipients);
            $this->_eventDispatcher->dispatchEvent($this->evt, 'sendPerformed');
        }        
    }
    
    /**
     * Get the number of recipients.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return int
     */
    protected function numberOfRecipients(Swift_Mime_SimpleMessage $message)
    {
        return count(array_merge(
            (array) $message->getTo(), (array) $message->getCc(), (array) $message->getBcc()
        ));
    }
    
    protected function _debug($message) 
    {
        if (true === $this->debug) {
            error_log($message);
        } elseif (is_callable($this->debug)) {
            call_user_func($this->debug, $message);
        }
    }

    /**
     * Retrieve destinations from Message API
     * @param type $message
     */
    protected function getDestinations($message, $to="To", $cc="CC", $bcc="BCC") 
    {
        $dest = array();
        if ($message->getTo())
            $dest[$to] = $this->mail_string($message->getTo());
        if ($message->getCc())
            $dest[$cc] = $this->mail_string($message->getCc());
        if ($message->getBcc())
            $dest[$bcc] = $this->mail_string($message->getBcc());
        return $dest;
    }

    /**
     * Make a mail string from an array of emails (for sender)
     * 
     * @param array $array
     * @return array 
     */
    protected function mail_string($array) {
        return array_map(function ($el) use ($array) {
            return ($array[$el] ? "$array[$el] ": "") ."<$el>";
        }, array_keys($array));
    }
    
}

// now register dependancies
Swift_DependencyContainer::getInstance()
	-> register('transport.aws_ses')
	-> withDependencies(array('transport.eventdispatcher'));