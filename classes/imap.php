<?php

namespace extensions\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class imap extends \frameworks\adapt\base{
        
        protected $mailbox;
        
        public function __construct($username, $password, $host, $port = "", $options = "", $folder = "INBOX"){
            if ($port) $host .= ":{$port}";
            $host .= $options;
            $connection = '{' . $host . '}' . $folder;
            
            $this->mailbox = imap_open($connection, $username, $password);
        }
        
        public function search($filter = "ALL"){
            $emails = imap_search($this->mailbox, $filter);
            
            $data = array();
            if (is_array($emails)){
                foreach($emails as $email){
                    $d = imap_fetch_overview($this->mailbox, $email);
                    
                    $pairs = array();
                    foreach($d[0] as $key => $value){
                        $pairs[$key] = $value;
                    }
                    $pairs['date_sent'] = date("d/m/Y H:i:s", strtotime($pairs['date']));
                    $data[] = $pairs;
                }
            }
            
            return $data;
        }
        
        public function get_message_header($message_number){
            return imap_fetchheader($this->mailbox, $message_number);
        }
        
        public function get_message_structure($message_number){
            return imap_fetchstructure($this->mailbox, $message_number);
        }
        
        public function get_message_parts($message_number){
            return $this->decode_message_parts($this->get_message_structure($message_number));
        }
        
        public function get_message_part($message_number, $part_id){
            return imap_fetchbody($this->mailbox, $message_number, $part_id);
        }
        
        private function get_mime_type($type, $subtype){
            $mime = "";
            switch($type){
            case 0:
                $mime = "text";
                break;
            case 1:
                $mime = "multipart";
                break;
            case 2:
                $mime = "message";
                break;
            case 3:
                $mime = "application";
                break;
            case 4:
                $mime = "audio";
                break;
            case 5:
                $mime = "image";
                break;
            case 6:
                $mime = "video";
                break;
            case 7:
            default:
                $mime = "other";
                break;
            }
            
            if ($subtype) $mime .= "/" . strtolower($subtype);
            return $mime;
        }
        
        private function get_encoding($encoding){
            switch($encoding){
            case 0:
                return "7bit";
            case 1:
                return "8bit";
            case 2:
                return "binary";
            case 3:
                return "base64";
            case 4:
                return "quoted-printable";
            case 5:
                return "other";
            }
            
            return "other";
        }
        
        private function decode_message_parts($structure, $current_part = ""){
            $parts = array();
            
            $part = array(
                'part_id' => $current_part,
                'mime' => $this->get_mime_type($structure->type, $structure->subtype),
                'encoding' => $this->get_encoding($structure->encoding)
            );
            
            if ($structure->disposition){
                $part['disposition'] = strtolower($structure->disposition);
            }
            
            $params = array();
            if (is_array($structure->dparameters)) $params = $structure->dparameters;
            if (is_array($structure->parameters)) $params = array_merge($params, $structure->parameters);
            
            foreach($params as $param){
                $attrib = strtolower($param->attribute);
                $part[$attrib] = $param->value;
            }
            
            $parts[] = $part;
            
            if (is_array($structure->parts)){
                $i = 1;
                foreach($structure->parts as $p){
                    $id = $i;
                    if ($current_part) $id = "{$current_part}.{$id}";
                    $parts = array_merge($parts, $this->decode_message_parts($p, $id));
                    $i++;
                }
            }
            
            return $parts;
        }
        
    }
}

?>