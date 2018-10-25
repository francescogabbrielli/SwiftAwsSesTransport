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
    public function __construct($ses_client, $template,
            $catch_exception=false, $debug = false) 
    {    
        if ($ses_client->isVersion2())
            throw new Exception ("Cannot use templates on version 2 API");
        
        parent::__construct($ses_client, $catch_exception, $debug);
        $this->template = $template;
    }
  
    /**
     * Send via Aws sendTemplatedEmail and report the result
     * 
     * The name of the template is taken from the template
     * 
     * @param Swift_Mime_Message $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message) 
    {
        
        $template_name = $this->template;
        
        if (is_array($this->template))
        {
            $template_name = $this->template["TemplateName"];
            $this->client->getTemplate($template_name, $this->template);
        }

        $this->response = $this->client->sendTemplatedEmail(
            $this->getDestinations($message, "to", "cc", "bcc"),
            $template_name
        );

        // report message ID and count
        $headers = $message->getHeaders();
        $headers->addTextHeader('X-SES-Message-ID', $this->response->get('MessageId'));
        $this->send_count = $this->numberOfRecipients($message);
        
    }
    
    public function setReplacementData($data) {
        $this->client->setData($data);
        return $this;
    }
    
    public function setTags($tags) {
        $this->client->setTags($tags);
        return $this;
    }
    
}