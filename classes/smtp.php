<?php

namespace extensions\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;

    class smtp extends \frameworks\adapt\base{
        
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
        
        public function __construct($localhost, $host, $port, $security = 0, $username = null, $passowrd = null){ //Should also construct with username / password
            $this->localhost = $localhost;
            $this->host = $host;
            $this->port = $port;
            $this->security = $security;
            $this->esmtp_support = false;
            $this->username = $username;
            $this->password = $passowrd;
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