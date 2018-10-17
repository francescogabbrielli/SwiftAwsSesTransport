<?php
	/*
	* This file requires SwiftMailer and Aws Ses PHP Api v3
	*
	* For the full copyright and license information, please view the LICENSE
	* file that was distributed with this source code.
	*/
    
	
	/**
	* Sends Messages over AWS SES.
	* @package Swift
	* @subpackage Transport
	* @author Francesco Gabbrielli
	*/
	class Swift_AwsSesTransport extends Swift_Transport_AwsSesTransport {

		/** the Aws SesClient */
		private $ses_client;
        
		/** the send method */
		private $send_method;
		
		/** AWSResult */
		private $response
		
		/** AWS message request (to specify all the desired AWS arguments)*/
		private $msgRequest;
		
		/**
		 * Debugging helper.
		 *
		 * If false, no debugging will be done.
		 * If true, debugging will be done with error_log.
		 * Otherwise, this should be a callable, and will recieve the debug message as the first argument.
		 *
		 * @seealso Swift_AwsSesTransport::setDebug()
		 */
		private $debug;

		/**
		* Create a new SesAwsTransport.
		* @param SESClient $ses_client The AWS SES Client.
		* @param string $config_set ConfigurationSetName argument (mandatory, to receive notifications through SNS)
		* @param string $send_method the method that client uses to send message (default is sendRawEmail)
		* @param boolean $debug Set to true to enable debug messages in error log.
		*/
		public function __construct($ses_client, $config_set, $send_method="sendRawEmail", $debug = false) {
			call_user_func_array(
				array($this, 'Swift_Transport_AwsSesTransport::__construct'),
				Swift_DependencyContainer::getInstance()
					->createDependenciesFor('transport.aws')
				);

			$this->ses_client = $ses_client;
			$this->send_method = $send_method;
			$this->debug = $debug;
			$this->msgRequest = ["ConfigurationSetName" => $config_set];
		}

		/**
		* Create a new AwsSesTransport.
		* @param SESClient $ses_client The AWS SES Client.
		* @param string $send_method the method that client uses to send message (default is sendRawEmail)
		*/
		public static function newInstance( $ses_client, $send_method ) {
			return new Swift_AwsSesTransport( $ses_client, $send_method );
		}

		public function setDebug($val) {
			$this->debug = $val;
		}

		public function setSesClient($ses_client) {
			$this->ses_client = $ses_client;
		}
		
		public function setSendMethod($method) {
			$this->send_method = $method;
		}
		/**
		 * Set an argument value for AWS message
		 * 
		 * @param string $param argument name
		 * @param mixed $value argument value (string or array)
		 */
		public function setArg($name, $value) {
			$this->msgRequest[$name] => $value;
		}
		
		protected function _debug ( $message ) {
			if ( true === $this->debug ) {
				error_log( $message );
			} elseif ( is_callable($this->debug) ) {
				call_user_func( $this->debug, $message );
			}
		}

		/**
		* Send the given Message.
		*
		* Recipient/sender data will be retreived from the Message API.
		* The return value is the number of recipients who were accepted for delivery.
		*
		* @param Swift_Mime_Message $message
		* @param string[] &$failedRecipients to collect failures by-reference
		* @return int
		* @throws AwsException on any errors
		*/
		public function send( Swift_Mime_Message $message, &$failedRecipients = null ) {
			
			$failedRecipients = (array) $failedRecipients;
			
			if ($evt = $this->_eventDispatcher->createSendEvent($this, $message))
			{
				$this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
				if ($evt->bubbleCancelled())
					return 0;
			}
			
			$sendCount = count((array) $message->getTo());
			$resultStatus = Swift_Events_SendEvent::RESULT_TENTATIVE;
			
			try 
			{
				$callable = $this->ses_client->{$send_method};
				$length = strlen($send_method);
				if ($this->send_method=="sendRawEmail")
					$this->msgRequest['RawMessage'] = ['Data' => $message->toString()];
				else if (is_callable($callable) && substr($this->send_method, 0, $length)=="send")
					$this->response = $callable($this->msgRequest);
				else
					throw new Exception("Method not allowed: $this->send_method");

				$resultStatus = Swift_Events_SendEvent::RESULT_SUCCESS;
				
				$this->_debug("=== Start AWS Response ===");
				$this->_debug($this->response);
				$this->_debug("=== End AWS Response ===");
			} 
			catch (\Exception $e) 
			{				
				$failedRecipients = $message->getTo();
				$sendCount = 0;
				$resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
			}
			
			// Send SwiftMailer Event
			if ($evt) 
			{
				$evt->setResult($resultStatus);
				$evt->setFailedRecipients($failedRecipients);
				$this->eventDispatcher->dispatchEvent($event, 'sendPerformed');
			}
			
			return $sendCount;
		}
		
		/**
		 * Get the AwsResult object
		 *
		 * @return AwsResult
		 */
		public function getResponse() {
			return $this->response;
		}

		public function isStarted() {}
		public function start() {}
		public function stop() {}

		/**
		 * Register a plugin.
		 *
		 * @param Swift_Events_EventListener $plugin
		 */
		public function registerPlugin(Swift_Events_EventListener $plugin)
		{
			$this->_eventDispatcher->bindEventListener($plugin);
		}

	} // AWSTransport
