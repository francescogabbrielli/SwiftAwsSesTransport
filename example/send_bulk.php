<?php

/*
 * Example sending bulk email from template with AWS Api v3.
 *
 * 1. Run composer.phar install in the root (next to compser.json)
 * 2. Copy config.php.example to config.php and add your AWS credentials
 * 3. Run this script!
 */
require_once(__DIR__ . '/../vendor/autoload.php');
require_once('./config.php');


$transport = Swift_AwsSesTransport::newBulkInstance(
    Swift_AwsSesTransport::newClient(AWSSESEndpoint, AWSProfile, AWSConfigSet),
    AWSConfigSet,
    json_decode(file_get_contents("template.json"), true))//or just TEMPLATE
        ->setReplacementData(TEMPLATE_DATA) // default replacement data
        ->setDebug(true); // Print the response from AWS to the error log for debugging.

//Create the Mailer using your created Transport
$mailer = Swift_Mailer::newInstance($transport);

//Create the message
$message = Swift_Message::newInstance()
        ->setSubject('Testing Swiftmailer SES')
        ->setFrom(array(FROM_ADDRESS => FROM_NAME));

//Add bulk destinations
$transport
        ->addDestination(DEST_1, DEST_1_DATA);

echo "Sending\n";
try
{
    echo "Sent: " . $mailer->send($message) . "\n";
} 
catch (Exception $e)
{
    echo $e . "\n";
}
