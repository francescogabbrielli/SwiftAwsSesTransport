<?php

use Aws\Ses\SesClient;

/**
 * Aws Client Wrapper.
 * 
 * Utility class to simplify Aws Ses API access
 * 
 * NOTE: API V3 require PHP 5.5
 */
class AwsSesClient
{
    /**
     * The actual Aws Ses Client
     * 
     * @var SesClient
     */
    private $ses_client;
    
    /**
     * Flag for v2 Api (versus v3)
     * 
     * @var boolean
     */
    private $version2;
    
    /**
     * Sender
     * 
     * @var string
     */
    private $from;
    
    /**
     * Tags
     * 
     * @var array
     */
    private $tags;
    
    /**
     * Template data
     * 
     * @var array
     */
    private $data;
    
    /**
     * Charset (default UTF-8)
     * 
     * @var string
     */
    private $charset;
    
    
    /** 
     * AWS message request (to specify all the desired AWS arguments).
     * 
     * Check Aws Sws documentation for arguments usage:
     * https://docs.aws.amazon.com/it_it/ses/latest/APIReference/Welcome.html
     * 
     * @var array 
     * @seealso Swift_AwsSesTransport::setArg()
     */
    private $msg_request;

    
    /**
     * Costruisce un nuovo client SES e lo inizializza con la configurazione
     * presente nel sistema.
     * 
     * @param SesClient $client the actual Aws Ses client
     * @param string $configuration ConfiugrationSet su AWS SES (or null if using v2 API)
     * @param string $from Mittente (opzionale)
     * @param string $charset Charset (opzionale)
     */
    public function __construct($client, $configuration, $from="", $charset="UTF-8") 
    {
        if (!getenv("HOME"))
            putenv('HOME='. getenv("USERPROFILE"));
        
        $this->ses_client = $client;
        $this->version2 = is_null($configuration);
        $this->msg_request =  $this->version2 ? [] : ["ConfigurationSetName" => $configuration];  

        $this->tags = array();
        $this->data = array();

        $this->charset = $charset;
    }
    
    public static function factory($region, $profile, $configuration, $from="", $charset="UTF-8") {
        
        $config = [
            'region' => $region,
            'profile' => $profile, 
            'http' => [
                'verify' => false//TODO: per produzione -> risolvere questo workaround usato per i test
            ]            
        ];
        
        if (!is_null($configuration))
            $config['version'] = '2010-12-01';
        
        $client = SesClient::factory($config);
        
        return new AwsSesClient($client, $configuration, $from, $charset);
    }
    
    /**
     * The actual SesClient from AWS SDK
     * 
     * @return SesClient
     */
    public function getSesClient() 
    {
        return $this->ses_client;
    }
    
    /**
     * @return boolean
     */
    public function isVersion2() 
    {
        return $this->version2;
    }
    
    /**
     * Set sender 
     * 
     * @param string $from
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }
    
    public function getCharset()
    {
        return $this->charset;
    }
    
    /**
     * Imposta il charset
     * 
     * @param string $charset
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }
    
    /**
     * Default replacement data for the template (only v3).
     * 
     * Each destination specifc data are to be passed inside the sendBulk destinations 
     * 
     * @param array $data array
     * @see sendBulkTemplatedEmail.
     */
    public function setData($data) 
    {
        $this->data = $data;
    }
    
    /**
     * Default tags (for v3).
     * 
     * Each destination tags are to be passed inside the sendBulk destinations
     * 
     * @param array $tags array [name1 => value1, etc]
     * @see sendBulkTemplatedEmail
     */
    public function setTags($tags) 
    {
        $this->tags = $tags;
    }
    
    /**
     * Set the full message request
     * 
     * @param array $request
     */
    public function setMsgRquest($request) 
    {
        $this->msg_request = $request;
    }
        
    /**
     * Create a template on AWS (only v3)
     * 
     * @param array $json_array template json array
     * @param string $name template name (to force it)
     * @return AwsResult AWS response
     * @throws AwsException if template already exists or wrong syntax
     */
    public function createTemplate($json_array, $name="") 
    {
        if ($this->isVersion2())
            throw new Exception ("Templates are not allowed in version 2");
        $template = $json_array;
        if ($name)
            $template["TemplateName"] = $name;
        return $this->ses_client->createTemplate(["Template" => $json_array]);
    }

    /**
     * Retrieve a template from AWS (only V3).
     * 
     * Template creation can be forced by specifying its json definition
     * 
     * @param string $name template name
     * @param array $force_creation template json definition to force creation
     *      if template does not exists
     * @return AwsResult AWS response
     * @throws AwsException
     */
    public function getTemplate($name, $force_creation=null)
    {
        if ($this->isVersion2())
            throw new Exception ("Templates are not allowed in version 2");
        try {
            $res = $this->ses_client->getTemplate(['TemplateName' => $name]);
        } catch(Exception $e) {
            if (!$force_creation)
                throw $e;
            $res = $this->createTemplate($force_creation, $name);
        }
        return $res;
    }
    
    /**
     * Delete a template on AWS (only V3)
     * 
     * @param string $name template name
     * @return AwsResult AWS response
     */
    public function deleteTemplate($name) 
    {
       if ($this->isVersion2())
            throw new Exception ("Templates are not allowed in version 2");
        return $this->ses_client->deleteTemplate(['TemplateName' => $name]);
    }
    
    /**
     * Send simple formatted email. No attachments.
     * 
     * @param array $dest destinations as a simple array or associative in the form:
     *      ['to' => [email1, email2, ...], 'cc' => [ etc..], bcc => [etc...]]
     * @param string $subject email subject
     * @param string $html email in HTML format
     * @param string $text email in text format
     * @return AwsResult AWS response
     * @throws AwsException 
     */
    public function sendEmail($dest, $subject, $html, $text)
    {
        
        $mail = [
            'Destination'           => $this->buildDestination($dest),
            //'FromArn' => '<string>',
            'Message' => [ // REQUIRED
                'Body' => [ // REQUIRED
                    'Html' => ['Charset' => $this->charset,'Data' => $html],
                    'Text' => ['Charset' => $this->charset,'Data' => $text],
                ],
                'Subject' => ['Charset' => $this->charset,'Data' => $subject],
            ],
            //'ReplyToAddresses' => [],
            //'ReturnPath' => '',
            //'ReturnPathArn' => '<string>',
            'Source'                => $this->from, // REQUIRED
            //'SourceArn' => '<string>',
        ];
        
        if ($this->tags)
            $mail['Tags'] = $this->buildTags();
            
        $req = $this->buildRequest($mail);              
        return $this->ses_client->sendEmail($req);
        
    }
    
    /**
     * Send raw email. Beware of differences between Api v2 and v3
     * 
     * @param string $raw_data mail in raw format (string|resource|Psr\Http\Message\StreamInterface)
     * @param array $dest destinations in this format: 
     *      ["To => [...], "Cc => [...], "Bcc" => [...]]
     *      - Mandatory in v2!
     *      - DO NOT SPECIFIY in v3 unless you want to override raw headers!
     * @return AwsResult AWS response
     * @throws AwsException 
     */
    public function sendRawEmail($raw_data, $dest=[]) 
    {
        //force base64 encoding on v2 Api
        if ($this->isVersion2() && base64_decode($raw_data, true)===false)
            $raw_data = base64_encode($raw_data);
        
        $mail = [
            //'FromArn' => '<string>',
            'Source'                => $this->from, // REQUIRED
            //'SourceArn' => '<string>',
            'RawMessage'            => ['Data' => $raw_data],
            //'ReturnPathArn' => '<string>',
        ];

        // override destinations
        if ($dest)
            $mail['Destinations'] = $dest;
        
        if ($this->tags)
            $mail['Tags'] = $this->buildTags();

        $req = $this->buildRequest($mail);
        return $this->ses_client->sendRawEmail($req);
    }
    
    /**
     * Send templated email (only V3)
     * 
     * @param array $dest destinations as a simple array or associative in the form:
     *      ['to' => [email1, email2, ...], 'cc' => [ etc..], bcc => [etc...]]
     * @param string $template_name template name on AWS SES
     * @param array $template_data template replacement data
     * @return AwsResult AWS response
     * @throws AwsException
     */
    function sendTemplatedEmail($dest, $template_name, $template_data=null) 
    {
        if ($this->isVersion2())
            throw new Exception ("Templates are not allowed in version 2");
        
        $mail = [
            'Destination'       => $this->buildDestination($dest), // REQUIRED
            'Source'            => $this->from, // REQUIRED
        //    'SourceArn' => '<string>',
            'Template'          => $template_name, // REQUIRED
        //    'TemplateArn' => '<string>',
            'TemplateData'      => $this->buildReplacements($template_data)
        ];
                        
        if ($this->tags)
            $mail['Tags'] = $this->buildTags();
        
        $req = $this->buildRequest($mail);
        return $this->ses_client->sendTemplatedEmail($req);
    }
    
    /**
     * Send bulk templated email (only V3)
     * 
     * <p>
     * Destinations array format:
     * <pre>
     * [
     *      "dest"   => destination emails (array ['to' => [...], 'cc' => [...], 'bcc' => [...]])
     *      "data"   => replacement data (array)
     *      "tags"   => tags (array [name1 => value1, ...])
     * ]
     * </pre>
     * 
     * @param array $destinations destinations
     * @param string $template_name template name on AWS SES 
     * @return AwsResult AWS response
     * @throws AwsException
     */
    function sendBulkTemplatedEmail( $destinations, $template_name) 
    {
        if ($this->isVersion2())
            throw new Exception ("Templates are not allowed in version 2");
        
        $mail = [
            'Destinations'          => $this->buildDestinations($destinations), // REQUIRED
            'Source'                => $this->from, // REQUIRED
        //    'SourceArn' => '<string>',
            'Template'              => $template_name, // REQUIRED
        //    'TemplateArn' => '<string>',
            'DefaultTemplateData'   => $this->buildReplacements(),// REQUIRED
        ];
        
                
        if ($this->tags)
            $mail['DefaultTags'] = $this->buildTags();

        $req = $this->buildRequest($mail);
        return $this->ses_client->sendBulkTemplatedEmail($req);
    }
    
    private function buildRequest($mail_req) 
    {
        return array_merge($this->msg_request, $mail_req);
    }
    
    /**
     * Create an array mapped with 'ToAddesses', 'CcAddresses', 'BccAddresses'
     * 
     * @param array $emails destinations as a simple array or associative in the form:
     *      ['to' => [email1, email2, ...], 'cc' => [ etc..], bcc => [etc...]]
     * @return array destinations in AWS format
     */
    private function buildDestination($emails) 
    {
        $ret = ['ToAddresses' => isset($emails['to']) ? $emails['to'] : array_values($emails)];
        if (isset($emails['cc']) && $emails['cc'])
            $ret['CcAddresses'] = $emails['cc'];
        if (isset($emails['bcc']) && $emails['cc'])
            $ret['BccAddresses'] = $emails['bcc'];
        return $ret;
    }
    
    private function buildTags($tags=null)
    {
        if (!$tags)
            $tags = $this->tags;
        $tag_array = array();
        if (is_array($tags))    
            foreach ($tags as $key => $value)
                $tag_array[] = ["Name" => $key, "Value" => $value];
        return $tag_array;
    }
    
    /**
     * Create a string replacement data
     * (only v3)
     * 
     * @param array $data
     * @return string
     */
    private function buildReplacements($data=null)
    {
        return json_encode($data?:$this->data);
    }

    /**
     * Create an array of destinations for bulk mail send
     * 
     * @param array $destinations in the required format
     * <pre>
     * [
     *      "dest"   => destination emails (array ['to' => [...], 'cc' => [...], 'bcc' => [...]])
     *      "data"   => replacement data (array)
     *      "tags"   => tags (array [name1 => value1, ...])
     * ]
     * </pre>
     * @return array
     */
    private function buildDestinations($destinations) 
    {
        $ret = array();
        foreach ($destinations as $dest) 
        {
            $d = [
                "Destination" => $this->buildDestination($dest["dest"]),
                "ReplacementTemplateData" => $this->buildReplacements($dest["data"])
            ];
            if (isset($dest["tags"]) && $dest["tags"])
                $d['ReplacementTags'] = $this->buildTags($dest["tags"]);
            $ret[] = $d;
        } 
        return $ret;
    }
    
}
