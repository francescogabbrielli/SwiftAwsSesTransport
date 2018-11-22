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
     * Successful sent messages IDs
     * 
     * @var array
     */
    private $messageIds;

    

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
     * Send via Aws sendBulkTemplatedEmail and report the result
     * 
     * @param Swift_SimpleMime_Message $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message, &$failedRecipients) 
    {
        return $this->client->sendBulkTemplatedEmail(
            $this->destinations,
            $this->assuredTemplateName()
        );
    }
    
    protected function do_sent($message, $response, &$failedRecipients) 
    {
        $status_array = $response->get("Status");
        $this->messageIds = array();
        $this->count = 0;
        for ($i=0; $i < count($status_array) ; $i++)
        {
            $recipients = $this->getRecipients($i);
            if (isset($status_array[$i]["MessageId"])) 
            {
                $this->count += count($recipients);
                $this->messageIds[] = $status_array[$i]["MessageId"];
            }
            else
                $failedRecipients = array_merge($failedRecipients, $recipients);
        }
        return $this->count;
    }
    
    /**
     * Add a bulk destination with its specific template data and tags
     *  
     * @param array $recipients recipients
     * @param array $data specific replacement data
     * @param array $tags specific tags
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
    
    /**
     * Reset bulk destinations to start again
     */
    public function resetDestinations() 
    {
        $this->destinations = [];
    }

    protected function getDestinations($message, $to = "To", $cc = "CC", $bcc = "BCC") 
    {
        $array = [];
        for ($i=0; $i<count($this->destinations); $i++)
            $array = array_merge($array, $this->getRecipients($n));
        return $array;
    }
    
    private function getRecipients($n) 
    {
        $recipients = $this->destinations[$n]["dest"];
        return array_merge(
            (array) $recipients["to"], 
            isset($recipients["cc"]) ? (array) $recipients["cc"] : [], 
            isset($recipients["bcc"]) ? (array) $recipients["bcc"] : []
        );
    }
    
    /**
     * Get successfully sent messages IDs
     * 
     * @return array
     */
    public function getSentMessageIds()
    {
        return $this->messageIds;
    }
    
}