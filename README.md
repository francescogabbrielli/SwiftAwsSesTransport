# SwiftAwsSesTransport

## What is it?
It's a transport for use with Swiftmailer to send mail over AWS SES.
An updated version of the transport by jmhobbs using AWS SesClient v2/v3.

## Where do I put it?
[comment]: < The best way to use it is through [composer](https://getcomposer.org/). >

[comment]: <    $ composer require francescogabbrielli/swiftmailer-aws-ses-transport>

[comment]: < Which will bring in Swiftmailer if you don't already have it installed. >
[comment]: < Otherwise >
Swift can autoload it if you put the files in this directory:

    [swift library root]/classes/Swift/AwsSesTransport.php

## How do I use it?

Like any other Swiftmailer transport:

    //Create the desired AWS Transport with the client (for Api v2 do not specify $config_set)
    $transport = Swift_AwsSesTransport::newInstance($ses_client, $config_set);
    //$transport = Swift_AwsSesFormattedTransport::newInstance($ses_client, $config_set);
    //$transport = Swift_AwsSesTemplatedTransport::newInstance($ses_client, $config_set, $template);
    //$transport = Swift_AwsSesBulkTransport::newInstance($ses_client, $config_set, $template);
  
    //Create the Mailer using your created Transport
    $mailer = Swift_Mailer::newInstance($transport);
    
    $mailer->send($message);

## Swiftmailer Version

Tested on versions 5 and 6. For version 5 change method signature inside [AwsSesTransport](classes/Swift/AwsSesTransport.php):

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null) 

to

    public function send(Swift_Mime_Message $message, &$failedRecipients = null) 
    

## Acknowledgments
* @jmhobbs - Original work on AWS SES rest API: https://github.com/jmhobbs/Swiftmailer-Transport--AWS-SES
* @laravel - Updated Swift Transport implementation for AWS SES: https://github.com/laravel/framework/tree/5.7/src/Illuminate/Mail/Transport
