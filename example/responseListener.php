<?php

/*
 * Example event plugin. Still deciding what to do with it. Probably useful with
 * async calls, because the response is aready available in the transport 
 * after the call.
 *
 * 1. Run composer.phar install in the root (next to compser.json)
 * 2. Copy config.php.example to config.php and add your AWS credentials
 * 3. Run this script!
 */
require_once(__DIR__ . '/../vendor/autoload.php');
require_once('./config.php');

class ResponseListener implements Swift_Events_ResponseListener {
    public function responseReceived(\Swift_Events_ResponseEvent $evt) {
        echo sprintf("Message sent by SES with Message-ID %s.\n", 
                $response->get("MessageId"));
    }
}

$transport = Swift_AwsSesTransport::newInstance(
    Swift_AwsSesTransport::newClient(AWSSESEndpoint, AWSProfile, AWSConfigSet))
        ->setDebug(true)
        ->registerPlugin(new ResponseListener());

//Create the Mailer using your created Transport
$mailer = Swift_Mailer::newInstance($transport);

//Create the message
$message = Swift_Message::newInstance()
        ->setSubject('Testing Swiftmailer SES')
        ->setFrom(array(FROM_ADDRESS => FROM_NAME))
        ->setTo(array(TO_ADDRESS))
        ->setBody("<p>Dude, I'm <b>totally</b> sending you email via AWS.</p>", 'text/html')
        ->addPart("Dude, I'm _totally_ sending you email via AWS.", 'text/plain');

echo "Sending\n";
try
{
    echo "Sent: " . $mailer->send($message) . "\n";
} 
catch (Exception $e)
{
    echo $e . "\n";
}