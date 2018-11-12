# SwiftAwsSesTransport

## What is it?
It's a transport for use with Swiftmailer to send mail over AWS SES.
An updated version of the transport by jmhobbs using AWS SesClient v2/v3.

## Where do I put it?
The best way to use it is through [composer](https://getcomposer.org/).
```bash
composer require francescogabbrielli/swiftmailer-aws-ses-transport
```
Which will bring in Swiftmailer if you don't already have it installed.
Otherwise Swift can autoload it if you put the files in this directory:

    [swift library root]/classes/Swift/AwsSesTransport.php

## How do I use it?

Like any other Swiftmailer transport:
```php
//Create the desired AWS Transport with the client (for Api v2 do not specify $config_set)
//Standard raw email send
$transport = Swift_AwsSesTransport::newRawInstance($ses_client, $config_set);

//Simple email send (no attachments)
$transport = Swift_AwsSesTransport::newFormattedInstance($ses_client, $config_set);

//Template email send
$transport = Swift_AwsSesTransport::newTemplatedInstance($ses_client, $config_set, $template);
    ->setReplacementData(TEMPLATE_DATA);
    
//Bulk template email send 
$transport = Swift_AwsSesTransport::newBulkInstance($ses_client, $config_set, $template)
    ->setReplacementData(TEMPLATE_DATA)
    ->addDestination(...)
    ->addDestination(...)
    ...
    ->addDestination(...);

//Create the Mailer using your created Transport
$mailer = Swift_Mailer::newInstance($transport);
...
$mailer->send($message);
```

## Symfony1.X configuration

    ```yaml
    # app/frontend/config/factories.yml

    all:
      mailer:
        class: sfMailer
        param:
          transport:
            class:          SwiftAwsSesTransport
    ```

## How do I get the message ID on send?

You may register a Swift_Events_ResponseListener plugin with a callback.  
See example/responseListener.php for details. 
It is especially useful in combination with async calls.
```php
$transport
    ->setAsync(true)
    ->registerPlugin(
        new Swift_Events_ResponseReceivedListener() {
            public function responseReceived( \Swift_Events_ResponseEvent $evt ) {
                $response = $evt->getResponse();//Aws\Result
                $sent = $evt->getSource()->getCount();
                echo sprintf( "Message-ID %s.\n", $response->get("MessageId"));
            }
        });
```

For sync calls, the message ID is available in the header of the message:
```php
$message->getHeaders()->get("X-SES-Message-ID");
```

For bulk send, there is an utility method to read all the sent message IDs:
```php
$transport->getSentMessageIds();
```

## Swiftmailer Version

Tested on versions 5 and 6. For version 5 change method signature inside [AwsSesTransport](classes/Swift/AwsSesTransport.php):
```php
public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null) 
```
to
```php
public function send(Swift_Mime_Message $message, &$failedRecipients = null) 
```

## Acknowledgments
* @jmhobbs - Original work on AWS SES rest API: https://github.com/jmhobbs/Swiftmailer-Transport--AWS-SES
* @laravel - Updated Swift Transport implementation for AWS SES: https://github.com/laravel/framework/tree/5.7/src/Illuminate/Mail/Transport
