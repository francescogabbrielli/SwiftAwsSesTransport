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


//Create the message
$message = Swift_Message::newInstance()
        ->setSubject('Testing Swiftmailer SES')
        ->setFrom(array(FROM_ADDRESS => FROM_NAME))
        ->setTo(array(TO_ADDRESS))
        //->setCc(array(CC_ADDRESS))
        //->setBcc(array(BCC_ADDRESS))
        ->setBody("<p>Dude, I'm <b>totally</b> sending you email via AWS</p>", 'text/html')
        ->addPart("Dude, I'm _totally_ sending you email via AWS Formatted", 'text/plain');


if (defined('ATTACHMENT')) 
{
    $transport = Swift_AwsSesTransport::newInstance(
        Swift_AwsSesTransport::newClient(AWSSESEndpoint, AWSProfile, AWSConfigSet));
    
    // if there is an attachment send raw (uses sendRawEmail)
    $attachment = Swift_Attachment::newInstance(file_get_contents(ATTACHMENT), ATTACHMENT);
    $message->attach($attachment);
} 
else 
{
    // otherwise send formatted (uses sendEmail)
    $transport = Swift_AwsSesTransport::newFormattedInstance(
        Swift_AwsSesTransport::newClient(AWSSESEndpoint, AWSProfile, AWSConfigSet));
}
    
// Print the response from AWS to the error log for debugging.
$transport->setDebug(true); 

//Create the Mailer using your created Transport
$mailer = Swift_Mailer::newInstance($transport);


echo "Sending\n";
try
{
    echo "Sent: " . $mailer->send($message) . "\n";
} 
catch (Exception $e)
{
    echo $e . "\n";
}
