<?php

/**
 * This file declare the Swist_Transport_AwsSesTransport class.
 *
 * @author Francesco Gabbrielli
 */

require_once './AwsSesClient.php';

/**
 * The base class for aws transport
 */
abstract class Swift_Transport_AwsSesTransport implements Swift_Transport 
{

    /**
     * Aws Ses client (wrapper)
     * 
     * @var AwsSesClient 
     */
    protected $ses_client;
    
    /**
     *
     * @var type 
     */
    protected $version2;
        
    /**
     * @var AwsResult 
     */
    protected $response;
    
    /* 
     * Total recipients sent 
     * 
     * @var int
     */
    protected $send_count;
    
    /**
     * Swift send status
     * 
     * @var int
     */
    protected $send_status;

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
     * @param AwsSesClient $ses_client
     */
    public function __construct($ses_client, $catch_exception, $debug) 
    {
        $this->ses_client = $ses_client;
        try {
            $this->version2 = $ses_client->isVersion2();
        } catch(Exception $e) {
            $this->version2 = true;
        }
        $this->debug = $debug;
        $this->catch_exception = $catch_exception;        
    }
    
    public static function newClient($region, $profile, $configuration, $from="", $charset="UTF-8") {
        return AwsSesClient::factory($region, $profile, $configuration, $from, $charset);
    }


    /**
     * Set an argument value for AWS message request
     * 
     * @param string $name argument name
     * @param mixed $value argument value (string or array)
     */
    public function setArg($name, $value) 
    {
        $this->msg_request[$name] = $value;
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
    }
    
    /**
     * 
     * @param boolean $enable_catch
     */
    public function setCatchException($enable_catch) 
    {
        $this->catch_exception = $enable_catch;
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
     * Get the AwsResult response object
     *
     * @return AwsResult
     */
    public function getResponse() 
    {
        return $this->response;
    }
        
    /**
     * Get the total messages sent
     * 
     * @return int
     */
    public function getSendCount() 
    {
        return $this->send_count;
    }
    
    /**
     * Get the swift send status
     * 
     * @return int
     */
    public function getSendStatus() 
    {
        return $this->send_status;
    }
    
    /**
     * Register an "internal" plug-in with the transport.
     *
     * @param  \Swift_Events_EventListener  $plugin
     * @return void
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        array_push($this->plugins, $plugin);
    }
    
    

    /**
     * Iterate through registered plugins and execute plugins' methods.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return void
     */
    protected function beforeSendPerformed(Swift_Mime_SimpleMessage $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'beforeSendPerformed')) {
                $plugin->beforeSendPerformed($event);
            }
        }
    }
    
    /**
     * Iterate through registered plugins and execute plugins' methods.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return void
     */
    protected function sendPerformed(Swift_Mime_SimpleMessage $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'sendPerformed')) {
                $plugin->sendPerformed($event);
            }
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
        $dest = [$to => join(",", array_keys($message->getTo()))];
        if ($message->getCc())
            $dest[$cc] = join(",", array_keys($message->getCc()));
        if ($message->getBcc())
            $dest[$bcc] = join(",", array_keys($message->getBcc()));
        return $dest;
    }
    
}
