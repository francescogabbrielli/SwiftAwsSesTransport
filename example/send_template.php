<?php

/*
 * Example sending email template with AWS.
 *
 * 1. Run composer.phar install in the root (next to compser.json)
 * 2. Copy config.php.example to config.php and add your AWS credentials
 * 3. Run this script!
 */
require_once(__DIR__ . '/../vendor/autoload.php');
require_once('./config.php');


$transport = (new Swift_AwsSesTemplatedTransport(
    Swift_Transport_AwsSesTransport::newClient(AWSSESEndpoint, AWSProfile, AWSConfigSet),
    json_decode(file_get_contents("template.json"), true)))
        ->setReplacementData([
            "name" => "Maria", 
            "animal" => "dog"
        ])
        ->setDebug(true); // Print the response from AWS to the error log for debugging.

//Create the Mailer using your created Transport
$mailer = Swift_Mailer::newInstance($transport);

//Create the message
$message = Swift_Message::newInstance()
        ->setSubject('Testing Swiftmailer SES')
        ->setFrom(array(FROM_ADDRESS))
        ->setTo(array(TO_ADDRESS))
        ->setBody("");

echo "Sending\n";
try
{
    echo "Sent: " . $mailer->send($message) . "\n";
} catch (AWSEmptyResponseException $e)
{
    echo $e . "\n";
}
