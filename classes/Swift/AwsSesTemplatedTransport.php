<?php
/*
 * This file requires SwiftMailer and Aws Ses PHP Api V2 and V3
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Sends template email messages over AWS SES using sendTemplatedEmail API.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesTemplatedTransport extends Swift_AwsSesRawTransport 
{
    
    /**
     * Template (force creation if not present on AWS when specified as array)
     * 
     * @var mixed string template name or array full template to force template creation
     */
    protected $template;
    
    /**
     * {@inheritdoc}
     */
    public function __construct($client, $template,
            $catch_exception=false, $debug = false) 
    {    
        if ($client->isVersion2())
            throw new Exception ("Cannot use templates on version 2 API");
        
        parent::__construct($client, $catch_exception, $debug);
        $this->template = $template;
    }
  
    /**
     * Send via Aws sendTemplatedEmail and report the result
     * 
     * @param Swift_Mime_Message $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message, &$failedRecipients) 
    {     
        return $this->client->sendTemplatedEmail(
            $this->getDestinations($message, "to", "cc", "bcc"),
            $this->assuredTemplateName()
        );
    }
    

    /**
     * Return the template name. If the provided template is a json, it will
     * assure that template will be present online, and wait for creation.
     * 
     * NOTE 1: this won't overwrite the online template if already present!
     * 
     * NOTE 2: async version is not provided here. Implement your own solution before
     * invoking the send.
     * 
     * @return string the template name
     * @throws Exception if the template does not exist or cannot (force) create it
     */
    protected function assuredTemplateName()
    {
        if (is_array($this->template))
        {
            $template_name = $this->template["TemplateName"];
            $res = $this->client->getTemplate($template_name, $this->template);
            if ($this->client->isAsync())
                $res->wait();
            return $template_name;
        }
        return $this->template;
    }

    
    /**
     * Default replacement data for the template (only v3).
     * 
     * @param array $data array
     */
    public function setReplacementData($data) {
        $this->client->setData($data);
        return $this;
    }
    
}