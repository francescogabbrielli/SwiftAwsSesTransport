<?php

/*
 * Example sending email with AWS.
 *
 * 1. Run composer.phar install in the root (next to compser.json)
 * 2. Copy config.php.example to config.php and add your AWS credentials
 * 3. Run this script!
 */
require_once(__DIR__ . '/../vendor/autoload.php');
require_once('./config.php');

$transport = new Swift_AwsSesTransport(
    Swift_AwsSesTransport::newClient(AWSSESEndpoint, AWSProfile, AWSConfigSet)
);
//$transport = new Swift_AwsSesFormattedTransport(
//    Swift_AwsSesTransport::newClient(AWSSESEndpoint, AWSProfile, AWSConfigSet)
//);
$transport->setDebug(true); // Print the response from AWS to the error log for debugging.

//Create the Mailer using your created Transport
$mailer = Swift_Mailer::newInstance($transport);

//Create the message
$message = Swift_Message::newInstance()
        ->setSubject('Testing Swiftmailer SES')
        ->setFrom(array(FROM_ADDRESS=>FROM_NAME))
        ->setTo(array(TO_ADDRESS))
        ->setBody("<p>Dude, I'm <b>totally</b> sending you email via AWS Formatted</p>", 'text/html')
        ->addPart("Dude, I'm _totally_ sending you email via AWS Formatted", 'text/plain');

echo "Sending\n";
try
{
    echo "Sent: " . $mailer->send($message) . "\n";
} 
catch (Exception $e)
{
    echo $e . "\n";
}
