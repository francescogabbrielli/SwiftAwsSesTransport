# SwiftAwsSesTransport
Swift Mailer Transport for using AWS SES Php Api v2/v3.

## What is it?
It's a transport for use with Swiftmailer to send mail over AWS SES.
An updated version of the transport by jmhobbs using AWS SesClient v2 / v3.

## Where do I put it?
[comment]: < The best way to use it is through [composer](https://getcomposer.org/). >

[comment]: <    $ composer require francescogabbrielli/swiftmailer-aws-ses-transport>

[comment]: < Which will bring in Swiftmailer if you don't already have it installed. >
[comment]: < Otherwise >
Swift can autoload it if you put the files in this directory:

    [swift library root]/classes/Swift/AwsSesTransport.php

## How do I use it?

Like any other Swiftmailer transport:

    //Create the Transport with the client and the specific AWS SES sending method 
    $transport = SwiftAwsSesTransport::newInstance($ses_client, $ses_client->sendEmail);
  
    //Create the Mailer using your created Transport
    $mailer = Swift_Mailer::newInstance($transport);
    
    $mailer->send($message);

## Swiftmailer Version

Not working in version 6 yet

## Acknowledgments
* @jmhobbs - Original work on AWS SES rest API: https://github.com/jmhobbs/Swiftmailer-Transport--AWS-SES
* @laravel - Updated Swift Transport implementation for AWS SES: https://github.com/laravel/framework/tree/5.7/src/Illuminate/Mail/Transport
