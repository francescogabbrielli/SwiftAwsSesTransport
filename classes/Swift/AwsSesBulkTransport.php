<?php
/*
 * This file requires SwiftMailer and Aws Ses PHP Api V2 and V3
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Sends bulk email messages over AWS SES using sendBulkTemplatedEmail API.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesBulkTransport extends Swift_AwsSesTemplatedTransport 
{

    /**
     * Bulk destinations
     * 
     * @var array
     */    
    private $destinations;
    

    /**
     * {@inheritdoc}
     */
    public function __construct($ses_client, $template,
            $catch_exception=false, $debug = false) 
    {    
        parent::__construct($ses_client, $template, $catch_exception, $debug);
        $this->destinations = array();
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

        $this->response = $this->client->sendBulkTemplatedEmail(
            $this->destinations,
            $template_name
        );

        // TODO: report messages IDs and total count
        $headers = $message->getHeaders();
        $headers->addTextHeader('X-SES-Message-ID', $this->response->get('MessageId'));
        $this->send_count = $this->numberOfRecipients($message);
        
    }
    
    /**
     * 
     * @param array $recipients
     * @param array $data
     * @param array $tags
     */
    public function addDestination($recipients, $data=[], $tags=[])
    {
        $this->destinations[] = [
            "dest" => $recipients,
            "data" => $data,
            "tags" => $tags
        ];
        return $this;
    }
    
}