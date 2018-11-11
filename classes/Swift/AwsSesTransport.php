<?php

/*
 * This file requires SwiftMailer and Aws Ses PHP Api (V2 or V3)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Build transports for the various AWS SES API.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Francesco Gabbrielli
 */
abstract class Swift_AwsSesTransport extends Swift_Transport_AwsSesTransport 
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
    }
    
    private static function wrapClient($ses_client, $configuration_set) {
        return $ses_client instanceof AwsSesWrapper
                ? $ses_client
                : AwsSesWrapper($ses_client, $configuration_set);
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
        return AwsSesWrapper::factory($region, $profile, $configuration_set);
    }
    
    /**
     * Create a new sendRawEmail transport.
     * 
     * This is the only one to allow attachments.
     * 
     * @param SesClient $ses_client Aws SesClient or its wrapper
     * @param string $configuration_set Configuration Set on AWS SES (or null for v2 api)
     * @return \Swift_AwsSesTransport the transport
     */
    public static function newRawInstance($ses_client, $configuration_set=null) 
    {
        return new Swift_AwsSesRawTransport(
                Swift_AwsSesTransport::wrapClient($ses_client, $configuration_set));
    }
    
    /**
     * Create a new sendEmail transport. The easiest type. Requires Html2Text.
     * 
     * @param SesClient $ses_client Aws SesClient or its wrapper
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
     * @param SesClient $ses_client Aws SesClient or its wrapper
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
     * @param SesClient $ses_client Aws SesClient or its wrapper
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
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {

        $failedRecipients = (array) $failedRecipients;

        $this->beforeSendPerformed($message);
        $count = 0;

        try 
        {
            // enforce from 
            $from = $message->getSender() ?: $message->getFrom();
            $this->client->setFrom(join(",", $this->mail_string($from)));
            
            $result = $this->do_send($message, $failedRecipients);
            if ($this->client->isAsync()) 
                $result->then(function($result) use ($message) {
                    onResponse($message, $result, $failedRecipients);
                });
            else
                $count = onResponse($message, $result, $failedRecipients);
            
        } catch (Exception $e) {
            
            $failedRecipients = $this->getDestinations($message);
            if (!$this->catch_exception)
                throw $e;
        }
        
        return $count;
        
    }

    /**
     * Executed when the response from AWS is received
     * 
     * @param Swift_Mime_SimpleMessage $message the message
     * @param AwsResult $response the AWS response
     * @param array $failedRecipients failed recipients
     * @return int the total number of recipients
     */
    protected function onResponse($message, $response, &$failedRecipients) 
    {
        $this->_debug("=== Start AWS Response ===");
        $this->_debug($response);
        $this->_debug("=== End AWS Response ===");
        $ret = $this->do_sent($message, $response, $failedRecipients);
        $this->sendPerformed($message, $response);
        return $ret;
    }

}
