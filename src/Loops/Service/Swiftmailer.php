<?php
/**
 * This file is part of the Loops framework.
 *
 * @author Lukas <lukas@loopsframework.com>
 * @license https://raw.githubusercontent.com/loopsframework/base/master/LICENSE
 * @link https://github.com/loopsframework/base
 * @link https://loopsframework.com/
 * @package extra
 * @version 0.1
 */

namespace Loops\Service;

use ArrayIterator;
use Loops;
use Loops\ArrayObject;
use Loops\ServiceInterface;
use Loops\Exception;
use Loops\Misc;
use Loops\Renderer\CustomizedRenderInterface;
use IteratorAggregate;
use ReflectionClass;
use Swift_Mailer;
use Swift_Message;
use Swift_Mime_Message;
use Swift_SendmailTransport;
use Swift_MailTransport;
use Swift_SmtpTransport;

/**
 * Magic functionality to create swift mailer classes and send emails more conviniently.
 * 
 * This serviceclass provides many shortcuts to conveniently create swiftmailer objects.
 * When using these shortcuts, many swiftmailer objects are configured automatically
 * based on the values in a passed Loops\Config object.
 *
 * (examples are all within an Loops\Object method)
 *
 * 1. Creating swiftmailer objects
 * 
 * Invoking __call on the MagicSwiftFactory object will result into a newInstance call of a swiftmailer class.
 * The called function is
 * Swift_{camelized accessed methodname}::newInstance({passed arguments});
 *
 * Therefore swiftmailer objects (with a newInstance function) can easily created as follows:
 * 
 * <code>
 *     //create a swiftmailer transport - Swift_SmtpTransport::newInstance('mail.example.com', 587, "tls");
 *     $transport = $this->email->smtp_transport('mail.example.com', 587, "tls");
 *     
 *     //create a swiftmailer object - Swift_Mailer::newInstance($transport);
 *     $this->email->mailer($transport);
 *
 *     //create a swiftmailer message - Swift_Message::newInstance("subject");
 *     $this->email->message("subject");
 * </code>
 *
 * 2. Creating swiftmailer objects with other factory functions
 *
 * Some swiftmailer object provide factory functions other than newInstance.
 * These can be called by accessing a virtual property (__get) and then invoking __call on the
 * resulting object.
 * The called function is
 * Swift_{camelized accessed property name}::{accessed methodname}({passed arguments});
 * 
 * This enables the possibility to create swiftmailer objects as follows:
 * <code>
 *     //create a swiftmailer attachment from file - Swift_Attachment::fromPath('my-document.pdf');
 *     $controller->email->attachment->fromPath('my-document.pdf');
 * </code>
 * 
 * 3. Automatic configuration of swiftmailer objects.
 *
 * Additionally, some objects will be setup based on values from the Loops\Config for this service if created.
 * 
 * The following objects will be autoconfigured:
 * 
 * Swift_Message:
 * Keys
 *     -returnto
 *     -from
 *     -from_name
 *     -sender
 *     -replyto
 *     -replyto_name
 *     -bcc
 *     -bcc_name
 * from the config are read and the messages headers will be setup accordingly.
 * <code>
 *     $controller->email->message(); //some headers may are already set depending on config file
 * </code>
 *
 * Swift_Mailer:
 * Key
 *     -transport
 * is read from the config and used to create a transport object that is passed to the swiftmailer.
 * 
 * The transport class will be: Swift_{capitalized value}Transport
 * 
 * Example values: null (Swift_NullTransport), sendmail (Swift_SendmailTransport), smtp (Swift_SmtpTransport), etc )
 * 
 * This transport class is also configured by the Loops\Config/ (see below for details on each transport class)
 * 
 * Swift_SmtpTransport:
 * Keys
 *     -host
 *     -port
 *     -ssl
 *     -username
 *     -password
 *     -authmode
 * are read from the config and used to create a smtp transport object that is passed to the swiftmailer.
 * <code>
 *     $this->email->smtp_transport(); //object is already configured depending on config file
 * </code>
 *
 * Swift_MailTransport:
 * Key
 *     -extra
 * is read from the config and used to create a mail transport object that is passed to the swiftmailer.
 * <code>
 *     $this->email->mail_transport(); //object is already configured depending on config file
 * </code>
 * 
 * Swift_SendmailTransport:
 *     sendmail
 * is read from the config and used to create a sendmail transport object that is passed to the swiftmailer.
 * <code>
 *     $this->email->sendmail_transport(); //object is already configured depending on config file
 * </code>
 *
 * 4. Shortcuts are provided to quickly send mails.
 * see methods send, messageFromTemplate and sendFromTemplate in the MagicSwiftFactory class.
 * These can be used for quick message sending.
 *
 * Example section in config (ini format)
 * <code>
 *     [email]
 *     from      = "someone@example.com"
 *     replyto   = "someoneelse@example.com"
 *     bcc       = "nsa@example.com"
 *     
 *     transport = smtp
 *     host      = "mail.example.com"
 *     port      = 587
 *     username  = "user"
 *     password  = "secret"
 *     ssl       = "tls"
 * </code>
 *
 * @todo Maybe extend from Service (loopsify)
 */
class Swiftmailer implements ServiceInterface, CustomizedRenderInterface, IteratorAggregate {
    private $config;
    private $swiftclassname;
    
    private $template;
    private $parameter = [];
    
    public static function isShared(Loops $loops) {
        return TRUE;
    }
    
    public static function hasService(Loops $loops) {
        return class_exists("Swift");
    }
    
    public static function getService(ArrayObject $config, Loops $loops) {
        $reflection = new ReflectionClass("Swift");
        require_once(dirname(dirname($reflection->getFileName()))."/swift_init.php");
        return new Swiftmailer($config);
    }
    
    public function getIterator() {
        return new ArrayIterator($this->parameter);
    }
    
    public function delegateRender() {
    }
    
    public function getTemplateName() {
        return $this->template;
    }
    
    public function modifyAppearances(&$appearances, &$forced_appearances) {
    }
    
    public function __construct($config, $swiftclassname = NULL) {
        $this->config           = $config;
        $this->swiftclassname   = $swiftclassname;
    }
    
    public function __call($name, $arguments) {
        if($this->swiftclassname) {
            return call_user_func_array([$this->swiftclassname, $name], $arguments);
        }
        
        $name = Misc::camelize($name);
        
        if(method_exists($this, "newInstance_$name")) {
            $result = call_user_func_array([$this, "newInstance_$name"], $arguments);
        }
        else {
            if(!class_exists("Swift_$name")) {
                throw new Exception("Unknown swift class [Swift_$name].");
            }
            $result = call_user_func_array(["Swift_$name", "newInstance"], $arguments);
        }
        
        return $result;
    }
    
    public function __get($key) {
        if(!$this->swiftclassname) {
            $key = Misc::camelize($key);
            return new Swiftmailer($this->config, "Swift_$key");
        }
    }
    
    /**
     * A mailer is automatically created and the passed message will be sent.
     * The config file must provide enough information for the mailer class to autoconfigure itself
     * or message sending will fail.
     * 
     * <code>
     *     //create message
     *     $message = $this->email->message("subject")
     *                                  ->setTo('somebody@example.com')
     *                                  ->setBody("Hello!");
     *
     *     //shortcut to $this->email->mailer()->send($message)
     *     $controller->email->send($message);
     * </code>
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = NULL) {
        return $this->mailer()->send($message, $failedRecipients);
    }
    
    /**
     * Creates a swift message from a phalcon template with the help of the phalcon view engine.
     * The swift message object and this magic factory class can be accessed within the template.
     * The first line of the rendered result will be used as the subject and the rest as the body.
     *
     * @param string $template The template name that should be used (without appearances and file extension)
     * @param array $parameter Parameters that are passed to template. Addionaly 'swift' will be set to the swift message that is created.
     * @param array|string $appearances The appearance parameter that is passed to the render engine.
     * @param string $prefix This string will be prepended to the template name. Defaults to 'email/'.
     */
    public function messageFromTemplate($template, $parameter = [], $appearances = [], $prefix = "email/") {
        $loops = Loops::getCurrentLoops();
        
        $renderer = $loops->getService("renderer");
        
        $message = $this->message();
        
        $this->template = $prefix.$template;
        $this->parameter = $parameter;
        $this->parameter['swift'] = $message;

        if(!$output = $renderer->render($this, $appearances)) {
            return FALSE;
        }
        
        if(strpos($output, "\n") === FALSE) {
            $output .= "\n";
        }
        
        list($subject, $body) = explode("\n", $output, 2);
        
        $message->setSubject($subject);
        $message->setBody($body);
        return $message;
    }
    
    /**
     * Creates a swift message from a template with the help of the renderer engine and sends it immediately
     *
     * Note: Because the swift message can be accessed in the template (by key 'email'), it is possible to add the receivers from within
     *       that template. (Warning: swiftmailer methods return its object which may result in template output that should be surpressed)
     *       This class is also available (by key 'this'), which enables the use to add attachments and other manipulation.
     * 
     * <code>
     *     //shortcut for $this->email->send($this->email->messageFromTemplate('test'));
     *     $controller->email->sendFromTemplate('test');
     *
     *     // -- template {viewdir}/email/test.smarty
     *     // {$_x=$email->addTo('somebody@example.com', 'Somebody')}Hello World!
     *     // Dear Somebody.
     *     //
     *     // I greet the world.
     *     // 
     *     // Sincerly,
     *     // Someone.
     * </code>
     * 
     * @see method messageFromTemplate
     * @see method send
     */
    public function sendFromTemplate($template, $parameter = [], $appearances = [], $prefix = "email/") {
        if(!$message = $this->messageFromTemplate($template, $parameter, $appearances, $prefix)) {
            return FALSE;
        }

        return $this->send($message);
    }
    
    private function newInstance_SmtpTransport() {
        $transport = Swift_SmtpTransport::newInstance();
        
        $arguments = func_get_args();
        
        $host = empty($arguments[0]) ? (empty($this->config->host) ? NULL : $this->config->host) : $arguments[0];
        $port = empty($arguments[1]) ? (empty($this->config->port) ? NULL : $this->config->port) : $arguments[1];
        $ssl  = empty($arguments[2]) ? (empty($this->config->ssl ) ? NULL : $this->config->ssl ) : $arguments[2];
        
        if(!empty($host)) $transport->setHost($host);
        if(!empty($port)) $transport->setPort($port);
        if(!empty($ssl )) $transport->setEncryption($ssl);
        
        if(!empty($this->config->username)) $transport->setUsername($this->config->username);
        if(!empty($this->config->password)) $transport->setPassword($this->config->password);
        if(!empty($this->config->authmode)) $transport->setAuthMode($this->config->authmode);
        
        return $transport;
    }
    
    private function newInstance_SendmailTransport() {
        $arguments = func_get_args();
        
        if(!empty($arguments[0])) {
            return Swift_SendmailTransport::newInstance($arguments[0]);
        }
        
        if(!empty($this->config->sendmail)) {
            return Swift_SendmailTransport::newInstance((array)$this->config->sendmail);
        }
        
        return Swift_SendmailTransport::newInstance();
    }
    
    private function newInstance_MailTransport() {
        $arguments = func_get_args();
        
        if(!empty($arguments[0])) {
            return Swift_SendmailTransport::newInstance($arguments[0]);
        }
        
        if(!empty($this->config->extra)) {
            return Swift_SendmailTransport::newInstance((array)$this->config->extra);
        }

        return Swift_SendmailTransport::newInstance();
    }
    
    private function newInstance_Message() {
        $message = call_user_func_array(["Swift_Message", "newInstance"], func_get_args());

        $this->add($message, 'returnto', 'setReturnTo', FALSE);
        $this->add($message, 'from', 'setFrom');
        $this->add($message, 'sender', 'setSender', FALSE);
        $this->add($message, 'replyto', 'setReplyTo');
        $this->add($message, 'bcc', 'setBcc');

        return $message;
    }
    
    private function add(Swift_Message $message, $key, $func, $withname = TRUE) {
        if(empty($this->config->$key)) return;
        $email = $this->config->$key;
        $key = $key."_name";
        if(!$withname || empty($this->config->$key)) {
            $message->$func($email);
        }
        else {
            $message->$func($email, $this->config->$key);
        }
    }

    private function newInstance_Mailer() {
        $transportclass = empty($this->config->transport) ? "mailTransport" : $this->config->transport."Transport";

        if(!$transport = $this->__call($transportclass, func_get_args())) {
            throw new Exception("Failed to create the Transport Class");
        }
        
        return Swift_Mailer::newInstance($transport);
    }
}