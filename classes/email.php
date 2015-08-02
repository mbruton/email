<?php

namespace extensions\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class email extends \frameworks\adapt\base{
        
        protected $email_addresses;
        protected $from;
        
        public $body;
        public $subject;
        public $return_path;
        
        public function __construct(){
            parent::__construct();
            //$this->data = array();
            $this->email_addresses = array();
            $this->body = null;
        }
        
        public function add_to($email_address, $name = null){
            $this->email_addresses[] = array(
                'address' => $email_address,
                'name' => $name,
                'type' => 'to',
                'formatted' => $name ? "\"{$name}\" <{$email_address}>" : $email_address
            );
        }
        
        public function add_cc($email_address, $name = null){
            $this->email_addresses[] = array(
                'address' => $email_address,
                'name' => $name,
                'type' => 'cc',
                'formatted' => $name ? "\"{$name}\" <{$email_address}>" : $email_address
            );
        }
        
        public function add_bcc($email_address, $name = null){
            $this->email_addresses[] = array(
                'address' => $email_address,
                'name' => $name,
                'type' => 'bcc',
                'formatted' => $name ? "\"{$name}\" <{$email_address}>" : $email_address
            );
        }
        
        public function get_email_addresses($filter = null){
            if (isset($filter)){
                $addresses = array();
                foreach($this->email_addresses as $e){
                    if (strtolower($filter) == $e['type']){
                        $addresses[] = $e;
                    }
                }
                
                return $addresses;
            }
            return $this->email_addresses;
        }
        
        public function get_formatted_email_addresses($filter = null){
            $list = $this->get_email_addresses($filter);
            $out = array();
            foreach($list as $e) $out[] = $e['formatted'];
            return $out;
        }
        
        
        public function from($email_address, $name = null){
            $this->email_addresses[] = array(
                'address' => $email_address,
                'name' => $name,
                'type' => 'from',
                'formatted' => $name ? "\"{$name}\" <{$email_address}>" : $email_address
            );
        }
        
        public function header(){
            $header = "From: " . implode("; ", $this->get_formatted_email_addresses('from')) . "\r\n";
            $header .= "To: " . implode("; ", $this->get_formatted_email_addresses('to')) . "\r\n";
            $cc = $this->get_formatted_email_addresses('cc');
            if (count($cc) > 0) $header .= "Cc: " . implode("; ", $cc) . "\r\n";
            $header .= "Date: " . date("r") . "\r\n";
            $header .= "Subject: {$this->subject}\r\n";
            if ($this->body instanceof mime) $header .= "MIME-Version: 1.0\r\n";
            //$header .= "\r\n";
            return $header;
        }
        
        public function render(){
            $output = $this->header();
            if ($this->body instanceof mime){
                $output .= $this->body->render();
            }elseif(is_string($this->body)){
                $output .= $this->body;
            }
            
            return $output;
        }
        
        public function send($smtp_object){
            $return = false;
            $from = $this->get_email_addresses('from');
            if (is_array($from) && count($from) >= 1){
                $from = $from[0]['address'];
            }else{
                $from = "";
            }
            $to_list = array_merge($this->get_email_addresses('to'), $this->get_email_addresses('cc'), $this->get_email_addresses('bcc'));
            $to = array();
            
            foreach($to_list as $t){
                $to[] = $t['address'];
            }
            
            $return = false;
            
            if ($smtp_object->open_connection()){
                if ($smtp_object->handshake()){
                    if ($smtp_object->login()){
                        if ($smtp_object->from($from)){
                            if ($smtp_object->to($to)){
                                $smtp_object->data($this->render());
                                $return = true;
                            }
                        }
                    }
                }
                $smtp_object->close_connection();
            }
            
            return $return;
        }
        
    }
}

?>