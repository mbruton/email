<?php

namespace extensions\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;

    class smtp extends \frameworks\adapt\base{
        
        const NO_SECURITY = 0;
        const SSL = 1;
        const TLS = 2;
        
        protected $_local_hostname;
        protected $_relay = array();
        protected $_from;
        protected $_to = array();
        protected $_data;
        
        public function __construct($local_hostname = 'localhost'){
            parent::__construct();
        }
        
        public function relay_via($smtp_hostname, $smtp_port = 25, $smtp_security = 0, $smtp_username = null, $smtp_password = null){
            $this->_relay = array(
                'hostname' => $smtp_hostname,
                'port' => $smtp_port,
                'security' => $smtp_security,
                'username' => $smtp_username,
                'password' => $smtp_password
            );
            return $this;
        }
        
        public function from($sender_email){
            $this->_from = $sender_email;
            return $this;
        }
        
        public function to($recipient_email){ /* $recipient_email can be an array */
            if (is_array($recipient_email)){
                $this->_to = array_merge($this->_to, $recipient_email);
            }else{
                $this->_to[] = $recipient_email;
            }
            return $this;
        }
        
        public function data($message_to_send){
            $this->_data = $message_to_send;
            return $this;
        }
        
        public function send(){
            /*
             * Are we sending directly or are we
             * relaying?
             */
            if (isset($this->_relay['hostname']) && isset($this->_relay['port']) && is_int($this->_relay['port'])){
                $connection = $this->open_connection($this->_relay['hostname'], $this->_relay['port'], $this->_relay['security'], $this->_relay['username'], $this->_relay['password']);
                if ($connection){
                    return $this->send_via_connection($connection);
                }else{
                    $this->error("Unable to relay via {$this->_relay['hostname']}:{$this->_relay['port']}, a connection could not be made.");
                }
            }else{
                /*
                 * We need to get a list of domains and email addresses
                 * that we are sending to.
                 */
                $domains = array();
                foreach($this->_to as $email){
                    list($recipient, $domain) = explode("@", $email);
                    if ($domain && $recipient){
                        if (!is_array($domains[$domain])){
                            $domains[$domain] = array($recipient);
                        }else{
                            if (!in_array($recipient, $domains[$domain])){
                                $domains[$domain][] = $recipient;
                            }
                        }
                    }
                }
                
                /*
                 * We need to get a list of mail servers
                 * for each domain
                 */
                $hosts = array();
                
                foreach(array_keys($domains) as $domain){
                    $records = array();
                    if (getmxrr($domain, $records)){
                        $hosts[$domain] = $records;
                    }else{
                        $this->error("Unable to find any MX records for '{$domain}', sending mail to '{$domain}' as per RFC2821.");
                        $hosts[$domain] = array($domain);
                    }
                }
                
                /*
                 * Lets open a connection to each
                 * domain and send the mail
                 */
                $valid = true;
                foreach($hosts as $domain => $servers){
                    $connection = null;
                    foreach($servers as $host){
                        if ($connection = $this->open_connection($host, 25)){
                            break;
                        }
                    }
                    
                    if (!is_null($connection)){
                        $this->send_via_connection($connection, $domain);
                    }else{
                        $this->error("Unable to send mail to '{$domain}' - no valid mail host could be found.");
                        $valid = false;
                    }
                }
                
                return $valid;
            }
            
            return false;
        }
        
        protected function send_via_connection($connection, $limit_to_domain = null){
            /* $domain = 'adaptframework.com' is used to limit sending to a single domain, else we'd be using others to relay our message  */
            
            $code = null;
            $response = null;
            
            $output = true;
            
            /* Lets get a list of recipients */
            $recipients = array();
            
            foreach($this->_to as $email){
                list($recipient, $domain) = explode("@", $email);
                if ($domain && $recipient){
                    if (is_null($limit_to_domain)){
                        if (!in_array($email, $recipients)){
                            $recipients[] = $email;
                        }
                    }else{
                        if ($domain == $limit_to_domain){
                            if (!in_array($email, $recipients)){
                                $recipients[] = $email;
                            }
                        }
                    }
                }
            }
            
            /* Do we have any recipients? */
            if (count($recipients)){
                /*
                 * Set the sender
                 */
                if ($this->request($connection, "MAIL FROM: <{$this->_from}>", $code, $connection)){
                    if ($code == "250"){
                        
                        /*
                         * Set the recipients
                         */
                        
                        foreach($recipients as $email){
                            if ($this->request($connection, "RCPT TO: <{$mail}>", $code, $response)){
                                if (!in_array($code, array("250", "251"))){
                                    $this->error("The smtp server rejected the recipient '{$email}' with the error {$code}: {$response}");
                                    $output = false;
                                }
                            }
                        }
                        
                        if ($output == true){
                           /*
                            * Only send the message if we are error free
                            */
                            if ($this->request($connection, "DATA", $code, $response)){
                                
                                if ($code == "354"){
                                    /* The smtp server is allowing us to send the message */
                                    if ($this->request($connection, $this->_data . "\r\n.", $code, $response)){
                                        if ($code != 250){
                                            $this->error("The smtp server rejected the message with the error {$code}: {$response}");
                                            $output = false;
                                        }
                                    }
                                }else{
                                    $this->error("The smtp server refused the message with the error {$code}: {$response}");
                                    $output = false;
                                }
                            }
                        }
                        
                        
                    }else{
                        $this->error("The smtp server rejected the sender '{$this->_from}' with the error {$code}: {$response}");
                        $output = false;
                    }
                }
            }else{
                $output = false;
            }
            
            /* Close the connection */
            $this->request($connection, "QUIT", $code, $response);
            fclose($connection);
            
            return $output;
        }
        
        protected function open_connection($host, $port, $security, $username = null, $password = null){
            $error = null;
            $error_string = null;
            if ($security == self::SSL){
                $host = "ssl://{$host}";
            }
            
            $connection = fsockopen($host, $port, $error, $error_string, 30); //TODO: Make the timeout a setting
            
            if ($connection){
                $code = null;
                $response = null;
                $this->parse_response(fgets($connection), $code, $response);
                
                if ($code == '220'){
                    if (preg_match("/esmtp/i", $response)){
                        /* ESMTP Host so lets handshake with EHLO */
                        
                        if ($this->request($connection, "EHLO" . is_null($this->_local_hostname) ? "localhost" : $this->_local_hostname, $code, $response)){
                            if ($code != "250"){
                                /* Unexpected response */
                                $this->error("{$code}: {$error}");
                                fclose($connection);
                                return null;
                            }
                        }else{
                            /* Handshake failed */
                            $this->error("Unable to connect to esmtp '{$host}': Handshake failed");
                            fclose($connection);
                            return null;
                        }
                        
                    }else{
                        /* Old school smtp server */
                        if ($this->request($connection, "HELO" . is_null($this->_local_hostname) ? "localhost" : $this->_local_hostname, $code, $response)){
                            if ($code != "250"){
                                /* Unexpected response */
                                $this->error("{$code}: {$error}");
                                fclose($connection);
                                return null;
                            }
                        }else{
                            /* Handshake failed */
                            $this->error("Unable to connect to smtp '{$host}': Handshake failed");
                            fclose($connection);
                            return null;
                        }
                    }
                    
                    /*
                     * Do we need to enable TLS?
                     */
                    if ($security == self::TLS){
                        if ($this->request($connection, "STARTTLS", $code, $response)){
                            
                            /* Lets enable TLS */
                            if (false == stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)){
                                /* TLS Failed */
                                $this->error('Unable to start TLS for ' . $host);
                                fclose($connection);
                                return null;
                            }
                            
                        }else{
                            /* Something went wrong :/ */
                            $this->error('Unable to start TLS for ' . $host);
                            fclose($connection);
                            return null;
                        }
                    }
                    
                    /*
                     * Do we need to login?
                     */
                    if ($username && $password){
                        if ($this->request($connection, "AUTH LOGIN", $code, $response)){
                            if ($this->request($connection, base64_encode($this->username), $code, $response)){
                                if ($this->request(base64_encode($this->password), $code, $response)){
                                    if ($code != "235"){
                                        $this->error("Unable to authenticate againist {$host} - Server responded with {$code}: {$response}");
                                        fclose($connection);
                                        return null;
                                    }
                                }
                            }
                        }else{
                            $this->error("Unable to authenticate against '{$host}' - Authentication may not be supported");
                            fclose($connection);
                            return null;
                        }
                    }
                    
                    return $connection;
                    
                }else{
                    /* The host didn't respond with what we were looking for :( */
                    $this->error("{$code}: {$error}");
                    fclose($connection);
                }
            }else{
                $this->error("Unable to connect to '{$host}': {$error} - {$error_string}");
            }
            
            return null;
        }
        
        protected function parse_response($response, &$code, &$data){
            if (strlen($response) >= 3){
                $code = substr($response, 0, 3);
                if (strlen($response) > 3){
                    $data = trim(substr($response, 3));
                }
            }
        }
        
        protected function request($connection, $command, &$response_code, &$response_description){
            if ($connection){
                $response_code = "";
                $response_description = "-";
                fputs($connection, $command . "\r\n");
                
                while (substr($response_description, 0, 1) == "-"){ //To work around a PHP bug on debian
                    $this->parse_response(fgets($connection), $response_code, $response_description);
                }
                
                return true;
            }
            
            return false;
        }
    }
    
    class smtp_old extends \frameworks\adapt\base{
        
        const NO_SECURITY = 0;
        const SSL = 1;
        const TLS = 2;
        
        protected $connection;
        protected $host;
        protected $port;
        protected $security;
        protected $localhost;
        
        protected $username;
        protected $password;
        
        protected $esmtp_support;
        protected $last_error;
        
        public function __construct($localhost_name = null, $host = null, $port = 25, $security = 0, $username = null, $password = null){ //Should also construct with username / password
            $this->localhost = $localhost_name;
            $this->host = $host;
            $this->port = $port;
            $this->security = $security;
            $this->esmtp_support = false;
            $this->username = $username;
            $this->password = $password;
            
            
        }
        
        public function open_connection(){
            $err = null;
            $err_string = null;
            $host = $this->host;
            
            switch($this->security){
            case self::SSL:
                $host = "ssl://" . $host;
                break;
            case self::TLS:
                //$host = "tls://" . $host;
                break;
            }
            
            $this->connection = fsockopen($host, $this->port, $err, $err_string, 30);
            //$this->connection = fopen("{$host}:{$port}", "rw");
            
            if (empty($this->connection)){
                $this->last_error = array(
                    'number' => $err,
                    'string' => $err_string
                );
                
                return false;
            }else{
                $code = null;
                $response = null;
                $this->parse_response(fgets($this->connection, 4096), $code, $response);
                
                if ($code == "220"){
                    if (preg_match("/esmtp/i", $response)){
                        $this->esmtp_support = true;
                    }
                }else{
                    $this->connection = null;
                    $this->last_error = array(
                        'number' => $code,
                        'string' => $response
                    );
                    
                    return false;
                }
                
                return true;
            }
        }
        
        
        public function parse_response($response, &$code, &$data){
            //print "S=" . $response;
            if (strlen($response) >= 3){
                $code = substr($response, 0, 3);
                if (strlen($response) > 3){
                    $data = trim(substr($response, 3));
                }
            }
        }
        
        public function handshake(){
            $code = "";
            $data = "";
            $command = "HELO {$this->localhost}";
            
            if ($this->esmtp_support){
                $command = "EHLO {$this->localhost}";
            }
            
            if ($this->send($command, $code, $data)){
                if ($code == "250"){
                    return true;
                }
            }
            
            return false;
        }
        
        public function login($username = null, $password = null){
            
            if (isset($username)) $this->username = $username;
            if (isset($password)) $this->password = $password;
            
            if ($this->security == self::TLS){
                $code = "";
                $data = "";
                if ($this->send("STARTTLS", $code, $data)){
                    if (false == stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)){
                        //print "Failed to start TLS\n";
                        return false;
                    }
                }else{
                    return false;
                }
            }
            
            $code = "";
            $data = "";
            $command = "AUTH LOGIN";
            
            if ($this->send($command, $code, $data)){
                if ($this->send(base64_encode($this->username), $code, $data)){
                    if ($this->send(base64_encode($this->password), $code, $data)){
                        if ($code == "235") return true;
                    }
                }
            }
            
            return false;
        }
        
        public function from($email_address){
            $code = "";
            $data = "";
            $command = "MAIL FROM: <{$email_address}>";
            
            if ($this->send($command, $code, $data)){
                if ($code == "250") return true;
            }
            
            return false;
        }
        
        public function to($addresses){
            if (!is_array($addresses)) $addresses = array($addresses);
            
            $code = "";
            $data = "";
            
            foreach($addresses as $a){
                $command = "RCPT TO: <{$a}>";
                if ($this->send($command, $code, $data)){
                    if (!in_array($code, array("250", "251"))) return false;
                }
            }
            
            return true;
        }
        
        public function data($mail_message){
            $code = "";
            $data = "";
            $command = "DATA";
            
            if ($this->send($command, $code, $data)){
                if ($code == "354"){
                    if ($this->send($mail_message . "\r\n.", $code, $data)){
                        if ($code == "250"){
                            return true;
                        }
                    }
                }
            }
            
            return false;
        }
        
        public function close_connection(){
            $code = "";
            $data = "";
            $command = "QUIT";
            
            if ($this->send($command, $code, $data)){
                if ($code == "221"){
                    
                }
            }
            
            if ($this->connection) fclose($this->connection);
            
            return true;
        }
        
        public function send($command, &$response_code, &$response_description){
            //print "C=" . $command . "\n";
            if ($this->connection){
                $response_code = "";
                $response_description = "-";
                fputs($this->connection, $command . "\r\n");
                
                while (substr($response_description, 0, 1) == "-"){ //To work around a PHP bug on debian
                    $this->parse_response(fgets($this->connection), $response_code, $response_description);
                }
                
                return true;
            }else{
                $this->last_error = array(
                    'number' => -1,
                    'string' => 'Not connected'
                );
            }
            
            return false;
        }
        
    }
}

?>