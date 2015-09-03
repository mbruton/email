<?php

namespace extensions\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class model_email extends \frameworks\adapt\model{
        
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
                    
                    $sql->select(new \frameworks\adapt\sql('*'))
                        ->from($this->table_name);
                    
                    /* Do we have a date_deleted field? */
                    if (in_array('date_deleted', $fields)){
                        
                        $id_condition = new \frameworks\adapt\sql_condition(new \frameworks\adapt\sql('imap_message_id'), '=', $imap_message_id);
                        $folder_condition = new \frameworks\adapt\sql_condition(new \frameworks\adapt\sql('email_folder_id'), '=', $email_folder_id);
                        $date_deleted_condition = new \frameworks\adapt\sql_condition(new \frameworks\adapt\sql('date_deleted'), 'is', new \frameworks\adapt\sql('null'));
                        
                        $sql->where(new \frameworks\adapt\sql_and($id_condition, $folder_condition, $date_deleted_condition));
                    }else{
                        
                        $id_condition = new \frameworks\adapt\sql_condition(new \frameworks\adapt\sql('imap_message_id'), '=', $imap_message_id);
                        $folder_condition = new \frameworks\adapt\sql_condition(new \frameworks\adapt\sql('email_folder_id'), '=', $email_folder_id);
                        $sql->where(new \frameworks\adapt\sql_and($id_condition, $folder_condition));
                    }
                    print new html_pre($sql);
                    /* Get the results */
                    $results = $sql->execute()->results();
                    print new html_pre(print_r($results, true));
                    
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
                    if ($child instanceof \frameworks\adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'to'){
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
                    if ($child instanceof \frameworks\adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'cc'){
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
                    if ($child instanceof \frameworks\adapt\model && $child->table_name == 'email_recipient' && $child->recipient_type == 'bcc'){
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
                    if ($child instanceof \frameworks\adapt\model && $child->table_name == 'email_part' && $child->content_type == $content_type){
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
                    if ($child instanceof \frameworks\adapt\model && $child->table_name == 'email_part' && $child->content_encoding == "base64" && is_null($child->content_id)){
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
                    $this->error("Unable to attach file with key: {$key}, file was not found.");
                    
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
                    if ($child instanceof \frameworks\adapt\model && $child->table_name == 'email_part' && $child->content_encoding == "base64" && !is_null($child->content_id)){
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
                    $this->error("Unable to embed file with key: {$key}, file was not found.");
                    
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
            
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \frameworks\adapt\model && $child->table_name == 'email_folder'){
                    $email_account = new model_email_account($child->email_account_id);
                    
                    if ($email_account->is_loaded) break;
                }
            }
            
            if (is_null($email_account)){
                /* We haven't found one so we are going to use the system default (if there is one) */
                
            }
        }
        
    }
    
}

?>