<?php

/*
 * This file requires SwiftMailer and Aws Ses PHP Api V2 and V3
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require 'AwsSesClient.php';

/**
 * Sends Messages over AWS SES using sendRawEmail API, or build another transport
 * to use different AWS SES Api.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
class Swift_AwsSesTransport extends Swift_Transport_AwsSesTransport 
{
    
    public function __construct($client, $catch_exception=false, $debug=false)
    {
        call_user_func_array(
                array($this, 'Swift_Transport_AwsSesTransport::__construct'),
                array_merge(
                    Swift_DependencyContainer::getInstance()
                            ->createDependenciesFor('transport.aws_ses'),
                    [$client, $catch_exception, $debug]
                ));
//        parent::__construct(
//                Swift_DependencyContainer::getInstance()->createDependenciesFor('transport.aws_ses'),
//                $client, $catch_exception, $debug);
    }
    
    private static function wrapClient($ses_client, $configuration_set) {
        return $ses_client instanceof AwsSesClient 
                ? $ses_client
                : AwsSesClient($client, $configuration_set);
    }
    
    /**
     * Utility method to directly build an Aws SesClient wrapper
     * 
     * @param string $region Set the correct endpoint region. 
     *      http://docs.aws.amazon.com/general/latest/gr/rande.html#ses_region
     * @param string $profile AWS IAM profile
     * @param string $configuration_set Configuration Set on AWS SES (or null for v2 api)
     * @return AwsSesClient
     */
    public static function newClient($region, $profile, $configuration_set) {
        return AwsSesClient::factory($region, $profile, $configuration_set);
    }
    
    /**
     * Create a new sendRawEmail transport.
     * 
     * This is the only one to allow attachments.
     * 
     * @param SesClient $ses_client Aws Ses Client or its wrapper
     * @param string $configuration_set Configuration Set on AWS SES (or null for v2 api)
     * @return \Swift_AwsSesTransport the transport
     */
    public static function newInstance($ses_client, $configuration_set=null) 
    {
        return new Swift_AwsSesTransport(
                Swift_AwsSesTransport::wrapClient($ses_client, $configuration_set));
    }
    
    /**
     * Create a new sendEmail transport. The easiest type. Requires Html2Text.
     * 
     * @param SesClient $ses_client Aws Ses Client or its wrapper
     * @param string $configuration_set Configuration Set on AWS SES (or null for v2 api)
     * @return \Swift_AwsSesTransport the transport
     */
    public static function newFormattedInstance($ses_client, $configuration_set = null) 
    {
        return new Swift_AwsSesFormattedTransportTransport(
                Swift_AwsSesTransport::wrapClient($ses_client, $configuration_set));
    }

    /**
     * Create a new sendTemplatedEmail transport
     * 
     * @param SesClient $ses_client Aws Ses Client or its wrapper
     * @param string $configuration_set Configuration Set on AWS SES (not null)
     * @param mixed $template The name of the template or its json definition 
     *      (only the contents of the 'Template' element) to force creation 
     *      if template does not exists.
     * @return \Swift_AwsSesTemplatedTransport
     */
    public static function newTemplatedInstance($ses_client, $configuration_set, $template)
    {
        return new Swift_AwsSesTemplatedTransport(
                Swift_AwsSesTransport::wrapClient($ses_client, $configuration_set),
                $template);
    }
    
    /**
     * Create a new sendBulkTemplatedEmail transport
     * 
     * @param SesClient $ses_client Aws Ses Client or its wrapper
     * @param string $configuration_set Configuration Set on AWS SES
     *      (only the contents of the 'Template' element) to force creation 
     *      if template does not exists.
     * @param mixed $template The name of the template or its json definition 
     * @return \Swift_AwsSesTemplatedTransport
     */
    public static function newBulkInstance($ses_client, $configuration_set, $template)
    {
        return new Swift_AwsSesBulkTransport(
                Swift_AwsSesTransport::wrapClient($ses_client, $configuration_set),
                $template);
    }
    
    /**
     * Send the given Message.
     * 
     * Recipient/sender data will be retrieved from the Message API if necessary
     *
     * @param Swift_Mime_SimpleMessage $message
     * @param string[] &$failedRecipients to collect failures by-reference
     * @return int number of recipients who were accepted for delivery
     * @throws Exception on any errors if $catch_exception is false
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {

        $failedRecipients = (array) $failedRecipients;

        $this->beforeSendPerformed($message);

        $this->send_count = 0;
        $this->send_status = Swift_Events_SendEvent::RESULT_TENTATIVE;

        try 
        {
            // enforce from 
            $from = $message->getSender() ?: $message->getFrom();
            $this->client->setFrom(join(",", $this->mail_string($from)));
            
            $this->response = null;
            $result = $this->do_send($message, $failedRecipients);
            if ($this->client->isAsync()) 
            {
                //TODO: async management
                //$result->onSuccess(function() {
                    
                //});
                
            } else {
                
                $this->response = $result;
                $this->send_status = Swift_Events_SendEvent::RESULT_SUCCESS;
                $this->sendPerformed($message);
            }
            
        } catch (Exception $e) {
            
            $failedRecipients = $this->getDestinations($message);
            $this->send_count = 0;
            $this->send_status = Swift_Events_SendEvent::RESULT_FAILED;
            if (!$this->catch_exception)
                throw $e;
        }

        $this->_debug("=== Start AWS Response ===");
        $this->_debug($this->response);
        $this->_debug("=== End AWS Response ===");
        
        return $this->send_count;
        
    }
    
    /**
     * Send via Aws sendRawEmail and report the result
     * 
     * @param Swift_Mime_SimpleMessage $message the message
     * @throws Exception is sending method is wrong or \AwsException if request is wrong
     */
    protected function do_send($message, &$failedRecipients)
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
    public function setTags($tags) 
    {
        $this->client->setTags($tags);
        return $this;
    }

}
