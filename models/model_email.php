<?php

namespace adapt\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class model_email extends \adapt\model{
        
        const EVENT_ON_LOAD_BY_IMAP_MESSAGE_ID = 'model_email.on_load_by_imap_message_id';
        
        protected $_variables = array();
        
        public function __construct($id = null, $data_source = null){
            parent::__construct('email', $id, $data_source);
        }
        
        public function initialise(){
            parent::initialise();
            
            $this->_auto_load_only_tables = array(
                'email_recipient',
                'email_part',
                'email_folder'
            );
            
            $this->_auto_load_children = true;
        }
        
        public function load_by_imap_message_id($imap_message_id, $email_folder_id){
            $imap_message_id = $this->data_source->escape(trim($imap_message_id));
            $email_folder_id = $this->data_source->escape(trim($email_folder_id));
            $this->initialise();
            
            /* Make sure name is set */
            if (isset($imap_message_id) && isset($email_folder_id)){
                
                /* We need to check this table has a name field */
                $fields = array_keys($this->_data);
                
                if (in_array('imap_message_id', $fields)){
                    $sql = $this->data_source->sql;
                    
                    $sql->select('*')
                        ->from($this->table_name);
                    
                    /* Do we have a date_deleted field? */
                    if (in_array('date_deleted', $fields)){
                        
                        $id_condition = new sql_cond('imap_message_id', sql::EQUALS, $imap_message_id);
                        $folder_condition = new sql_cond('email_folder_id', sql::EQUALS, $email_folder_id);
                        $date_deleted_condition = new sql_cond('date_deleted', sql::IS, new sql_null());
                        
                        $sql->where(new sql_and($id_condition, $folder_condition, $date_deleted_condition));
                    }else{
                        
                        $id_condition = new sql_cond('imap_message_id', sql::EQUALS, $imap_message_id);
                        $folder_condition = new sql_cond('email_folder_id', sql::EQUALS, $email_folder_id);
                        $sql->where(new sql_and($id_condition, $folder_condition));
                    }
                    //print new html_pre($sql);
                    /* Get the results */
                    $results = $sql->execute()->results();
                    //print new html_pre(print_r($results, true));
                    
                    if (count($results) == 1){
                        $this->trigger(self::EVENT_ON_LOAD_BY_IMAP_MESSAGE_ID);
                        return $this->load_by_data($results[0]);
                    }elseif(count($results) == 0){
                        $this->error("Unable to find a record with an imap message id of {$imap_message_id}");
                    }elseif(count($results) > 1){
                        $this->error(count($results) . " records found with an imap message id '{$imap_message_id}'.");
                    }
                    
                }else{
                    $this->error('Unable to load by imap message id by name, this table has no \'imap_message_id\' field.');
                }
            }else{
                $this->error('Unable to load by imap message id, no id supplied');
            }
            
            return false;
        }
        
        public function to($email_address = null, $name = null){
            if (is_null($email_address) && is_null($name)){
                $children = $this->get();
                $output = array();
                foreach($children as $child){
                    if ($child instanceof \adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'to'){
                        $output[] = $child;
                    }
                }
                
                return $output;
            }else{
                $recipient = new model_email_recipient();
                $recipient->recipient_type = 'to';
                $recipient->recipient_name = $name;
                $recipient->recipient_email = $email_address;
                $this->add($recipient);
                
                return $this;
            }
            
        }
        
        public function cc($email_address = null, $name = null){
            if (is_null($email_address) && is_null($name)){
                $children = $this->get();
                $output = array();
                foreach($children as $child){
                    if ($child instanceof \adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'cc'){
                        $output[] = $child;
                    }
                }
                
                return $output;
            }else{
                $recipient = new model_email_recipient();
                $recipient->recipient_type = 'cc';
                $recipient->recipient_name = $name;
                $recipient->recipient_email = $email_address;
                $this->add($recipient);
                
                return $this;
            }
            
        }
        
        public function bcc($email_address = null, $name = null){
            if (is_null($email_address) && is_null($name)){
                $children = $this->get();
                $output = array();
                foreach($children as $child){
                    if ($child instanceof \adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'bcc'){
                        $output[] = $child;
                    }
                }
                
                return $output;
            }else{
                $recipient = new model_email_recipient();
                $recipient->recipient_type = 'bcc';
                $recipient->recipient_name = $name;
                $recipient->recipient_email = $email_address;
                $this->add($recipient);
                
                return $this;
            }
        }
        
        public function subject($subject = null){
            if (is_null($subject)){
                return $this->subject;
            }else{
                $this->subject = $subject;
                return $this;
            }
        }
        
        public function from($email_address = null, $name = null, $host = null){
            /* When not specified the account default is used */
            
            if (is_null($email_address)){
                return $this->sender_email;
            }else{
                $this->sender_email = $email_address;
                $this->sender_name = $name;
                $this->sender_host = $host;
                
                return $this;
            }
        }
        
        public function message($content_type, $message = null){
            $content_type = trim(strtolower($content_type));
            if (is_null($message)){
                $children = $this->get();
                foreach($children as $child){
                    $output = array();
                    if ($child instanceof \adapt\model && $child->table_name == 'email_part' && $child->content_type == $content_type){
                        $output[] = $child;
                    }
                    return $output;
                }
            }else{
                $part = new model_email_part();
                $part->content_type = $content_type;
                $part->content_encoding = 'quoted-printable';
                $part->content = quoted_printable_encode($message);
                $this->add($part);
            }
            
            return $this;
        }
        
        public function attach($file_key = null, $filename = null){
            if (is_null($file_key)){
                $children = $this->get();
                foreach($children as $child){
                    $output = array();
                    if ($child instanceof \adapt\model && $child->table_name == 'email_part' && $child->content_encoding == "base64" && is_null($child->content_id)){
                        $output[] = $child;
                    }
                    return $output;
                }
            }else{
                $file = $this->file_store->get($file_key);
                if ($file){
                    $part = new model_email_part();
                    $part->content_type = $this->file_store->get_content_type($file_key);
                    $part->content_encoding = 'base64';
                    $part->content = base64_encode($file);
                    $part->filename = $filename;
                    $this->add($part);
                }else{
                    $this->error("Unable to attach file with key: {$file_key}, file was not found.");
                    
                }
                
                return $this;
            }
            
            return null;
        }
        
        public function embed($file_key = null, $content_id = null){
            if (is_null($file_key)){
                $children = $this->get();
                foreach($children as $child){
                    $output = array();
                    if ($child instanceof \adapt\model && $child->table_name == 'email_part' && $child->content_encoding == "base64" && !is_null($child->content_id)){
                        $output[] = $child;
                    }
                    return $output;
                }
            }else{
                $file = $this->file_store->get($file_key);
                if ($file){
                    $part = new model_email_part();
                    $part->content_type = $this->file_store->get_content_type($file_key);
                    $part->content_encoding = 'base64';
                    $part->content = base64_encode($file);
                    $part->content_id = $content_id;
                    $this->add($part);
                }else{
                    $this->error("Unable to embed file with key: {$file_key}, file was not found.");
                    
                }
                
                return $this;
            }
            
            return null;
        }
        
        public function variables($variables){
            if (is_array($variables)){
                $this->_variables = $variables;
                return $this;
            }else{
                return $this->_variables;
            }
        }
        
        public function send($queue = true){
            /* We need to find the folder we are in so we can find the email_account */
            $email_account = null;
            
            /* Ensure variables are an array */
            if (!is_array($this->_variables)){
                $this->_variables = array();
            }
            
            /* Add settings to variables */
            $settings = $this->get_settings();
            foreach($settings as $name => $value){
                if (is_string($value) || is_numeric($value)){
                    $key = "setting[{$name}]";
                    $this->_variables[$key] = $value;
                }
            }
            
            /* Set the variables in the subject */
            foreach($this->_variables as $key => $value){
                $this->subject = str_replace("{{" . $key . "}}", $value, $this->subject);
            }
            
            $this->subject = preg_replace("/\{\{[^}]+\}\}/", "", $this->subject);
            
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder'){
                    $email_account = new model_email_account($child->email_account_id);
                    
                    if ($email_account->is_loaded){
                        break;
                    }
                }
                
                /* While we are in this loop we may as well do the variable swap out */
                if ($this->_variables && is_array($this->_variables)){
                    if ($child instanceof \adapt\model && $child->table_name == 'email_part' && in_array($child->content_encoding, array('quoted-printable'))){
                        $content = quoted_printable_decode($child->content);
                        foreach($this->_variables as $key => $value){
                            //$child->content = quoted_printable_encode(preg_replace("/\{\{" . $key . "\}\}/", $value, quoted_printable_decode($child->content)));
                            $content = str_replace("{{" . $key . "}}", $value, $content);
                        }
                        
                        /* Remove any empty variables */
                        $content = preg_replace("/\{\{[^}]+\}\}/", "", $content);
                        $child->content = quoted_printable_encode($content);
                        
                        ///* Dot stuffing, fix the soon to be missing '.' */
                        //$child->content = preg_replace("/^\./m", "..", $child->content);
                    }
                }
            }
            
            if (is_null($email_account)){
                /* We haven't found one so we are going to use the system default (if there is one) */
                $account = new model_email_account();
                if ($account->load_default()){
                    $email_account = $account;
                }
            }
            
            if ($email_account && $email_account instanceof model_email_account){
                if ($queue){
                    return $email_account->queue($this);
                }else{
                    return $email_account->send($this);
                }
            }
            
            return false;
        }
        
        public function render(){
            $raw = "";
            if ($this->sender_email){
                if ($this->sender_name){
                    $raw .= "From: {$this->sender_name} <$this->sender_email>\r\n";
                }else{
                    $raw .= "From: $this->sender_email\r\n";
                }
            }
            
            $to = "";
            $cc = "";
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'to' && $child->recipient_email){
                    if ($to == ""){
                        if ($child->recipient_name){
                            $to .= "To: {$child->recipient_name} <{$child->recipient_email}>";
                        }else{
                            $to .= "To: {$child->recipient_email}";
                        }
                    }else{
                        if ($child->recipient_name){
                            $to .= ",{$child->recipient_name} <{$child->recipient_email}>";
                        }else{
                            $to .= ",{$child->recipient_email}";
                        }
                    }
                }elseif ($child instanceof \adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'cc' && $child->recipient_email){
                    if ($cc == ""){
                        if ($child->recipient_name){
                            $cc .= "Cc: {$child->recipient_name} <{$child->recipient_email}>";
                        }else{
                            $cc .= "Cc: {$child->recipient_email}";
                        }
                    }else{
                        if ($child->recipient_name){
                            $cc .= ",{$child->recipient_name} <{$child->recipient_email}>";
                        }else{
                            $cc .= ",{$child->recipient_email}";
                        }
                    }
                }
            }
            
            if ($to != ""){
                $raw .= $to . "\r\n";
            }
            
            if ($cc != ""){
                $raw .= $cc . "\r\n";
            }
            
            $raw .= "MIME-Version: 1.0\r\n";
            
            if ($this->date_sent){
                $date = new \adapt\date($this->date_sent);
                $raw .= "Date: " . $date->date('r') . "\r\n";
            }else{
                $date = new \adapt\date();
                $raw .= "Date: " . $date->date('r') . "\r\n";
            }
            
            if ($this->subject){
                $raw .= "Subject: {$this->subject}\r\n";
            }
            
            /* Lets build the body */
            $printables = array();
            $non_prinatables = array();
            
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_part'){
                    if (in_array($child->content_encoding, array('quoted-printable'))){
                        $printables[] = $child;
                    }else{
                        $non_prinatables[] = $child;
                    }
                }
            }
            
            
            
            if (count($printables) && count($non_prinatables)){
                
                $body = new mime("multipart/mixed");
                
                if (count($printables) > 1){
                    $alternatives = new mime("multipart/alternative");
                    foreach($printables as $child){
                        $mime = new mime($child->content_type, null, $child->content_encoding);
                        $mime->add($child->content);
                        $alternatives->add($mime);
                    }
                    $body->add($alternatives);
                }else{
                    $mime = new mime($printables[0]->content_type, null, $printables[0]->content_encoding);
                    $mime->add($printables[0]->content);
                    $body->add($mime);
                }
                
                foreach($non_prinatables as $child){
                    if ($child->filename){
                        $mime = new mime($child->content_type, null, $child->content_encoding, null, 'attachment', $child->filename);
                        $mime->add($child->content);
                        $body->add($mime);
                    }else{
                        $mime = new mime($child->content_type, null, $child->content_encoding, $this->content_id, 'inline');
                        $mime->add($child->content);
                        $body->add($mime);
                    }
                }
                
                $raw .= $body->render();
                
            }elseif(count($printables)){
                
                if (count($printables) > 1){
                    $body = new mime("multipart/alternative");
                    foreach($printables as $child){
                        $mime = new mime($child->content_type, null, $child->content_encoding);
                        $mime->add($child->content);
                        $body->add($mime);
                    }
                    
                    $raw .= $body->render();
                    
                }else{
                    $body = new mime($printables[0]->content_type, null, $printables[0]->content_encoding);
                    $body->add($printables[0]->content);
                    $raw .= $body->render();
                }
                
            }elseif(count($non_prinatables)){
                
                if (count($non_prinatables) > 1){
                    $body = new mime("multipart/mixed");
                    foreach($non_prinatables as $child){
                        if ($child->filename){
                            $mime = new mime($child->content_type, null, $child->content_encoding, null, 'attachment', $child->filename);
                            $mime->add($child->content);
                            $body->add($mime);
                        }else{
                            $mime = new mime($child->content_type, null, $child->content_encoding, $this->content_id, 'inline');
                            $mime->add($child->content);
                            $body->add($mime);
                        }
                    }
                    
                    $raw .= $body->render();
                    
                }else{
                    if ($non_prinatables[0]->filename){
                        $body = new mime($non_prinatables[0]->content_type, null, $non_prinatables[0]->content_encoding, null, 'attachment', $non_prinatables[0]->filename);
                        $body->add($non_prinatables[0]->content);
                        $raw .= $body->render();
                    }else{
                        $body = new mime($non_prinatables[0]->content_type, null, $non_prinatables[0]->content_encoding, $non_prinatables[0]->content_id, 'inline');
                        $body->add($non_prinatables[0]->content);
                        $raw .= $body->render();    
                    }
                    
                }
                
            }else{
                $raw .= "\r\n";
            }
            
            return $raw;
        }
        
    }
    
}

