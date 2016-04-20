<?php

namespace adapt\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class mime extends \adapt\base{
        
        protected $children;
        protected $type;
        protected $boundary;
        
        public $transfer_encoding;
        public $charset;
        public $content_id;
        public $content_disposition;
        public $filename;
        
        public function __construct($mime_type, $charset = null, $transfer_encoding = null, $content_id = null, $content_disposition = null, $filename = null){
            parent::__construct();
            $this->children = array();
            $this->boundary = md5("adapt email " . date("Ymdhms") . rand(0, 99999));
            $this->type = $mime_type;
            $this->transfer_encoding = $transfer_encoding;
            $this->charset = $charset;
            $this->content_id = $content_id;
            $this->content_disposition = $content_disposition;
            $this->filename = $filename;
        }
        
        public function dget_mime_type(){
            return $this->type;
        }
        
        public function dset_mime_type($value){
            $this->type = $value;
        }
        
        public function add($data){
            if (is_array($data)){
                $this->children = array_merge($this->children, $data);
            }elseif($data instanceof \adapt\html){
                $this->children[] = $data->render();
            }else{
                $this->children[] = $data;
            }
        }
        
        public function has_mime_children(){
            foreach($this->children as $child){
                if ($child instanceof mime) return true;
            }
            
            return false;
        }
        
        public function render(){
            $output = "Content-Type: {$this->mime_type};";
            if (isset($this->content_disposition)){
                $output .= " name=\"{$this->filename}\"";
            }
            if ($this->has_mime_children()){
                $output .=  " boundary={$this->boundary}\r\n";
            }elseif(isset($this->charset)){
                $output .= " charset={$this->charset}\r\n";
            }else{
                $output .= "\r\n";
            }
            
            if (isset($this->transfer_encoding)){
                $output .= "Content-Transfer-Encoding: {$this->transfer_encoding}\r\n";
            }
            
            if (isset($this->content_id)){
                $output .= "Content-ID: {$this->content_id}\r\n";
            }
            
            if (isset($this->content_disposition)){
                $output .= "Content-Disposition: {$this->content_disposition};";
                if (isset($this->filename)){
                    $output .= " filename=\"{$this->filename}\";\r\n";
                }else{
                    $output .= "\r\n";
                }
            }
            
            $output .= "\r\n";
            
            
            foreach($this->children as $child){
                if ($child instanceof mime){
                    if ($this->has_mime_children()) $output .= "\r\n--{$this->boundary}\r\n";
                    $output .= $child->render();
                }elseif(is_string($child)){
                    if (preg_match("/base64/i", $this->transfer_encoding)){
                        //$output .= chunk_split($child, 76, "\r\n");
                        $output .= $child . "\r\n";
                    }elseif(preg_match("/quoted-printable/i", $this->transfer_encoding)){
                        //$output .= quoted_printable_encode($child);
                        $output .= $child . "\r\n";
                    }else{
                        $output .= $child . "\r\n";
                    }
                }
            }
            
            if ($this->has_mime_children()) $output .= "\r\n--{$this->boundary}--\r\n";
            
            return $output;
        }
        
        public function __toString(){
            return $this->render();
        }
    }
}

?>