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
class Swift_AwsSesTemplatedTransport extends Swift_AwsSesTransport 
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
        
        $this->response = $this->client->sendTemplatedEmail(
            $this->getDestinations($message, "to", "cc", "bcc"),
            $this->assuredTemplateName()
        );

        // report message ID and count
        $headers = $message->getHeaders();
        $headers->addTextHeader('X-SES-Message-ID', $this->response->get('MessageId'));
        $this->send_count = $this->numberOfRecipients($message);
        
    }

    /**
     * Return the template name. If the provided template is a json, it will
     * assure that template will be present online.
     * 
     * NOTE: this won't overwrite the online template if already present!
     * 
     * @return string the template name
     */
    protected function assuredTemplateName()
    {
        if (is_array($this->template))
        {
            $this->client->getTemplate($template_name, $this->template);
            return $this->template["TemplateName"];
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