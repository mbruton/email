<?php

namespace adapt\email{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class model_email_account extends \adapt\model{
        
        public function __construct($id = null, $data_source = null){
            parent::__construct('email_account', $id, $data_source);
        }
        
        public function initialise(){
            parent::initialise();
            
            $this->_auto_load_only_tables = array(
                'email_folder'
            );
            
            $this->_auto_load_children = true;
        }
        
        /* Load default account */
        public function load_default(){
            $sql = $this->data_source->sql
                ->select('*')
                ->from('email_account')
                ->where(
                    new sql_and(
                        new sql_cond(
                            'date_deleted',
                            sql::IS,
                            new sql_null()
                        ),
                        new sql_cond(
                            'name',
                            sql::EQUALS,
                            sql::q("default")
                        )
                    )
                )
                ->order_by('priority')
                ->limit(1);
            
            $result = $sql
                ->execute(0)
                ->results();
            
            if (is_array($result) && count($result) == 1){
                $return = $this->load_by_data($result[0]);
                if ($return){
                    
                    /* Update based on settings */
                    $this->name = "default";
                    $this->label = "Default account";
                    $this->description = "Default account for sending email";
                    $this->priority = 1;
                    $this->imap_hostname = $this->setting("email.imap_host");
                    $this->imap_port = $this->setting("email.imap_port");
                    $this->imap_username = $this->setting("email.imap_username");
                    $this->imap_password = $this->setting("email.imap_password");
                    $this->imap_options = $this->setting("email.imap_options");
                    $this->smtp_hostname = $this->setting("email.smtp_host");
                    $this->smtp_port = $this->setting("email.smtp_port");
                    $this->smtp_security = $this->setting("email.smtp_security_layer");
                    $this->smtp_username = $this->setting("email.smtp_username");
                    $this->smtp_password = $this->setting("email.smtp_password");
                    $this->account_email = $this->setting("email.smtp_sender_email");
                    
                    $this->save();    
                }
                
                return $return;
            }elseif(is_array($result) && count($result) == 0){
                
                /* Create an account from settings */
                $this->name = "default";
                $this->label = "Default account";
                $this->description = "Default account for sending email";
                $this->priority = 1;
                $this->imap_hostname = $this->setting("email.imap_host");
                $this->imap_port = $this->setting("email.imap_port");
                $this->imap_username = $this->setting("email.imap_username");
                $this->imap_password = $this->setting("email.imap_password");
                $this->imap_options = $this->setting("email.imap_options");
                $this->smtp_hostname = $this->setting("email.smtp_host");
                $this->smtp_port = $this->setting("email.smtp_port");
                $this->smtp_security = $this->setting("email.smtp_security_layer");
                $this->smtp_username = $this->setting("email.smtp_username");
                $this->smtp_password = $this->setting("email.smtp_password");
                $this->account_email = $this->setting("email.smtp_sender_email");
                
                return $this->save();
            }
            
            return false;
        }
        
        public function new_email(){
            $email = new model_email();
            $folder = $this->get_drafts();
            $email->add($folder);
            return $email;
        }
        
        public function new_email_from_template($template_name){
            $templates = $this->get_templates();
            if ($templates && $templates instanceof \adapt\model && $templates->table_name == 'email_folder'){
                
                $sql = $this
                    ->data_source
                    ->sql
                    ->select('*')
                    ->from('email')
                    ->where(
                        new sql_and(
                            new sql_cond(
                                'date_deleted',
                                sql::IS,
                                new sql_null()
                            ),
                            new sql_cond(
                                'email_folder_id',
                                sql::EQUALS,
                                sql::q($templates->email_folder_id)
                            ),
                            new sql_cond(
                                'name',
                                sql::EQUALS,
                                sql::q($template_name)
                            )
                        )
                    );
                
                $result = $sql
                    ->execute()
                    ->results();
                
                if (is_array($result) && count($result) == 1){
                    $template = new model_email();
                    if ($template->load_by_data($result[0])){
                        
                        $email = $template->copy();
                        $email->name = guid();
                        
                        /* Remove the folder */
                        for($i = 0; $i < $email->count(); $i++){
                            $child = $email->get($i);
                            if ($child && $child instanceof \adapt\model && $child->table_name == 'email_folder'){
                                $email->remove($i);
                            }
                        }
                        
                        $email->email_folder_id = null;
                        
                        /* Add the drafts folder */
                        $folder = $this->get_drafts();
                        $email->add($folder);
                        //print $email;
                        /* Set the flags */
                        $email->template = 'No';
                        $email->draft = 'Yes';
                        $email->sent = 'No';
                        $email->queued_to_send = 'No';
                        $email->received = 'No';
                        $email->seen = 'No';
                        $email->flagged = 'No';
                        $email->answered = 'No';
                        
                        return $email;
                    }
                }else{
                    $this->error("Unable to find template '{$template_name}' in this account");
                }
            }
            
            return null;
        }
        
        /**
         * Returns a list of templates on the account
         */
        public function list_templates(){
            $template_names = [];
            $templates = $this->get_templates();
            if ($templates && $templates instanceof \adapt\model && $templates->table_name == 'email_folder'){
                
                $sql = $this
                    ->data_source
                    ->sql
                    ->select('name')
                    ->from('email')
                    ->where(
                        new sql_and(
                            new sql_cond(
                                'date_deleted',
                                sql::IS,
                                new sql_null()
                            ),
                            new sql_cond(
                                'email_folder_id',
                                sql::EQUALS,
                                sql::q($templates->email_folder_id)
                            )
                        )
                    );
                
                $results = $sql
                    ->execute()
                    ->results();
                
                foreach($results as $result) $template_names[] = $result['name'];
            }
            
            return $template_names;
        }
        
        /*
         * Override save to create new mail folders
         */
        public function save($create_folders_if_needed = true){
            if (!$this->is_loaded && $create_folders_if_needed){
                /* Inbox */
                $folder = new model_email_folder();
                $folder->type = 'Inbox';
                $folder->label = 'Inbox';
                $this->add($folder);

                /* Outbox */
                $folder = new model_email_folder();
                $folder->type = 'Outbox';
                $folder->label = 'Outbox';
                $this->add($folder);

                /* Drafts */
                $folder = new model_email_folder();
                $folder->type = 'Drafts';
                $folder->label = 'Drafts';
                $this->add($folder);

                /* Sent items */
                $folder = new model_email_folder();
                $folder->type = 'Sent items';
                $folder->label = 'Sent items';
                $this->add($folder);

                /* Trash */
                $folder = new model_email_folder();
                $folder->type = 'Trash';
                $folder->label = 'Trash';
                $this->add($folder);

                /* Templates */
                $folder = new model_email_folder();
                $folder->type = 'Templates';
                $folder->label = 'Templates';
                $this->add($folder);
            }
            
            return parent::save();
        }
        
        /*
         * Override delete to cascade to all folders and emails
         */
        public function delete(){
            // TODO: make work more better
            parent::delete();
        }
        
        /*
         * Fetch email from the imap server
         */
        public function fetch($mailbox = null){
            
            if ($this->is_loaded && !is_null($this->imap_username) && !is_null($this->imap_password) && !is_null($this->imap_hostname)){
                $imap = new imap($this->imap_username, $this->imap_password, $this->imap_hostname, $this->imap_port, $this->imap_options);
                
                if ($imap){
                    
                    /* Lets get the mailbox structure from the imap server */
                    $structure = $imap->list_mailboxes();
                    
                    $children =  $this->get();
                    
                    $inbox_found = false;
                    $sent_items_found = false;
                    $deleted_items_found = false;
                    $drafts_found = false;
                    
                    foreach($structure as $folder){
                        
                        //print new html_pre(print_r($structure, true));
                        //exit(1);
                        
                        $found = false;
                        foreach($children as $child){
                            if ($child instanceof \adapt\model && $child->table_name == 'email_folder'){
                                if ($child->imap_name == $folder){
                                    $found = true;
                                    
                                    switch($child->type){
                                    case "Inbox":
                                        $inbox_found = true;
                                        break;
                                    case "Sent items":
                                        $sent_items_found = true;
                                        break;
                                    case "Drafts":
                                        $drafts_found = true;
                                        break;
                                    case "Trash":
                                        $deleted_items_found = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        //if (!$found){
                        //    
                        //    if ($folder == "INBOX"){
                        //        $inbox = $this->get_inbox();
                        //        if ($inbox){
                        //            $inbox->imap_name = $folder;
                        //            $inbox->save();
                        //        }else{
                        //            $inbox = new model_email_folder();
                        //            $inbox->type = 'Inbox';
                        //            $inbox->label = 'Inbox';
                        //            $inbox->imap_name = $folder;
                        //            $this->add($inbox);
                        //        }
                        //        
                        //        $found = true;
                        //    }
                        //    
                        //    if (preg_match("/Sent/i", $folder) && !preg_match("/Sent[^\\]*\\/", $folder)){
                        //        $ef = $this->get_sent_items();
                        //        
                        //        if ($ef){
                        //            if (!is_null($ef->imap_name)){
                        //                $ef = new model_email_folder();
                        //                $ef->type = 'Folder';
                        //                $ef->imap_name = $folder;
                        //                $ef->name = $folder;
                        //                
                        //            }else{
                        //                $ef->imap_name = $folder;
                        //            }
                        //            
                        //            $this->add($ef);
                        //        }else{
                        //            $ef = new model_email_folder();
                        //            $ef->type = 'Sent items';
                        //            $ef->label = $folder;
                        //            $ef->imap_name = $folder;
                        //            $this->add($ef);
                        //        }
                        //    }
                        //    
                        //    if (!$found){
                        //        
                        //    }
                        //    
                        //}
                        
                        if (!$found){
                            //print new html_pre($folder);
                            //exit(1);
                            if ($folder == "INBOX" && !$inbox_found){
                                $inbox = $this->get_inbox();
                                $inbox_found = true;
                                if ($inbox){
                                    $inbox->imap_name = $folder;
                                    $inbox->save();
                                }else{
                                    $inbox = new model_email_folder();
                                    $inbox->type = 'Inbox';
                                    $inbox->label = 'Inbox';
                                    $inbox->imap_name = $folder;
                                    $this->add($inbox);
                                }
                            }elseif(preg_match("/[^\/]*Sent[^\/]*/i", $folder) && !$sent_items_found){
                                $ef = $this->get_sent_items();
                                
                                if ($ef){
                                    if (!is_null($ef->imap_name)){
                                        $ef = new model_email_folder();
                                        $ef->type = 'Folder';
                                        $ef->imap_name = $folder;
                                        $ef->name = $folder;
                                        
                                    }else{
                                        $ef->imap_name = $folder;
                                    }
                                    
                                    $ef->save();
                                }else{
                                    $ef = new model_email_folder();
                                    $ef->type = 'Sent items';
                                    $ef->label = $folder;
                                    $ef->imap_name = $folder;
                                    $this->add($ef);
                                }
                                $sent_items_found = true;
                            }elseif(preg_match("/[^\/]*(Trash|Deleted)[^\/]*/i", $folder) && !$deleted_items_found){
                                $ef = $this->get_trash();
                                if ($ef){
                                    if (!is_null($ef->imap_name)){
                                        $ef = new model_email_folder();
                                        $ef->type = 'Folder';
                                        $ef->imap_name = $folder;
                                        $ef->name = $folder;
                                    }else{
                                        $ef->imap_name = $folder;
                                    }
                                    
                                    $ef->save();
                                }else{
                                    $ef = new model_email_folder();
                                    $ef->type = 'Trash';
                                    $ef->label = $folder;
                                    $ef->imap_name = $folder;
                                    $this->add($ef);
                                }
                                $deleted_items_found = true;
                            }elseif(preg_match("/[^\/]*Drafts[^\/]*/i", $folder) && !$drafts_found){
                                $ef = $this->get_drafts();
                                if ($ef){
                                    if (!is_null($ef->imap_name)){
                                        $ef = new model_email_folder();
                                        $ef->type = 'Folder';
                                        $ef->imap_name = $folder;
                                        $ef->name = $folder;
                                    }else{
                                        $ef->imap_name = $folder;
                                    }
                                    
                                    $ef->save();
                                }else{
                                    $ef = new model_email_folder();
                                    $ef->type = 'Drafts';
                                    $ef->label = $folder;
                                    $ef->imap_name = $folder;
                                    $this->add($ef);
                                }
                                $drafts_found = true;
                            }else{
                                $this->create_folder($folder);
                            }
                        }
                    }
                    
                    /* Lets pull the headers from the server */
                    if ($mailbox){
                        print new html_pre("Fetching: {$mailbox}");
                        
                        $folder = $this->get_folder($mailbox);
                        
                        /* Get the message ID's */
                        $search = "ALL";
                        if (!is_null($folder->date_synced)){
                            $date = new \adapt\date($folder->date_synced);
                            $search = " SINCE " . $date->date('d-M-Y');
                        }
                        
                        $ids = $imap->search($mailbox, $search); //TODO: Folder by last sync date
                        //print new html_pre(print_r($ids));
                        //print $folder->email_folder_id . "-" . $folder->label;
                        //exit(1);
                        foreach($ids as $id){
                            print $id;
                            $header = $imap->get_header($id);
                            print new html_pre(print_r($header, true));
                            
                            print new html_pre("Message ID: {$header->Msgno}");
                            /* Have me already recieved this message? */
                            $email = new model_email();
                            $email->load_by_imap_message_id(trim($header->Msgno), $folder->email_folder_id);
                            //print new html_pre(print_r($email->errors(), true));
                            //exit(1);
                            if ($email->is_loaded){
                                /* Update */
                                //print "{$header['message_id']} Found";
                                print new html_pre("Updating existing record");
                                
                                /* Create new */
                                $date = new \adapt\date();
                                $date->set_date($header->date, "D, d M Y H:i:s O");
                                
                                $email->email_folder_id = $folder->email_folder_id;
                                $email->imap_date = $date->date('Y-m-d H:i:s');
                                $email->imap_message_id = trim($header->Msgno);
                                $email->imap_message_fetched = 'No';
                                $email->template = 'No';
                                $email->draft = is_null($header->Draft) || trim($header->Draft) ? "No" : "Yes";
                                $email->sent = $folder->type == 'Sent items' ? 'Yes' : 'No';
                                $email->queued_to_send = 'No';
                                $email->received = 'Yes';
                                $email->seen = is_null($header->Unseen) || trim($header->Unseen) == '' ? 'Yes' : 'No';
                                $email->flagged = is_null($header->Flagged) || trim($header->Flagged) == '' ? 'No' : 'Yes';
                                $email->answered = is_null($header->Answered) || trim($header->Answered) == '' ? 'No' : 'Yes';
                                if (!is_null($header->Deleted) && trim($header->Deleted) != ''){
                                    $email->date_deleted = $this->data_source->sql('now()');
                                }
                                $from = $header->from[0];
                                $email->sender_name = $from->personal;
                                if (!is_null($from->mailbox) && !is_null($from->host)){
                                    $email->sender_email = $from->mailbox . "@" . $from->host;
                                }
                                
                                $email->subject = $header->subject;
                                $email->date_sent = $date->date('Y-m-d H:i:s');
                                
                                $email->save();
                            }else{
                                /* Clear errors */
                                $email->errors(true);
                                
                                /* Create new */
                                $date = new \adapt\date();
                                $date->set_date($header->date, "D, d M Y H:i:s O");
                                
                                $email->email_folder_id = $folder->email_folder_id;
                                $email->imap_date = $date->date('Y-m-d H:i:s');
                                $email->imap_message_id = trim($header->Msgno);
                                $email->imap_message_fetched = 'No';
                                $email->template = 'No';
                                $email->draft = is_null($header->Draft) || trim($header->Draft) ? "No" : "Yes";
                                $email->sent = $folder->type == 'Sent items' ? 'Yes' : 'No';
                                $email->queued_to_send = 'No';
                                $email->received = 'Yes';
                                $email->seen = is_null($header->Unseen) || trim($header->Unseen) == '' ? 'Yes' : 'No';
                                $email->flagged = is_null($header->Flagged) || trim($header->Flagged) == '' ? 'No' : 'Yes';
                                $email->answered = is_null($header->Answered) || trim($header->Answered) == '' ? 'No' : 'Yes';
                                if (!is_null($header->Deleted) && trim($header->Deleted) != ''){
                                    $email->date_deleted = new sql_now();
                                }
                                $from = $header->from[0];
                                $email->sender_name = $from->personal;
                                if (!is_null($from->mailbox) && !is_null($from->host)){
                                    $email->sender_email = $from->mailbox . "@" . $from->host;
                                }
                                
                                $email->subject = $header->subject;
                                $email->date_sent = $date->date('Y-m-d H:i:s');
                                
                                $recipients = $header->to;
                                if (is_array($recipients) && count($recipients)){
                                    foreach($recipients as $to){
                                        $model = new model_email_recipient();
                                        $model->recipient_type = 'to';
                                        $model->recipient_name = trim($to->personal);
                                        $model->recipient_email = trim($to->mailbox) . "@" . trim($to->host);
                                        $email->add($model);
                                    }
                                }
                                
                                $recipients = $header->cc;
                                if (is_array($recipients) && count($recipients)){
                                    foreach($recipients as $cc){
                                        $model = new model_email_recipient();
                                        $model->recipient_type = 'cc';
                                        $model->recipient_name = trim($cc->personal);
                                        $model->recipient_email = trim($cc->mailbox) . "@" . trim($cc->host);
                                        $email->add($model);
                                    }
                                }
                                
                                print new html_pre($email);
                                print new html_pre(print_r($email->errors(), true));
                                
                                $email->save();
                                //exit(1);
                                //print "{$header['message_id']} not found";
                            }
                            
                            
                        }
                        
                        $folder->date_synced = date("Y-m-d H:i:s");
                        $folder->save();
                        
                    }else{
                        /* Fetch all */
                        foreach($children as $child){
                            $imap_path = $this->get_imap_path($child);
                            print new html_h1("Fetching path: {$imap_path}");
                            $this->fetch($imap_path);
                            print new html_h3("Ending ok");
                        }
                    }
                    
                    
                    $this->date_synced = date("Y-m-d H:i:s");
                    $this->save();
                    
                }else{
                    //Handle error here
                }
                
                
                
            }
        }
        
        public function get_imap_path($model_email_folder){
            if ($model_email_folder instanceof \adapt\model && $model_email_folder->table_name == 'email_folder'){
                $name = $model_email_folder->imap_name;
                if (is_null($model_email_folder->parent_email_folder_id)){
                    return $name;
                }else{
                    $model = $this->get_folder_by_id($model_email_folder->parent_email_folder_id);
                    if ($model){
                        $name = $this->get_imap_path($model) . "/" . $name;
                    }
                    
                    return $name;
                }
            }
            
            return "";
        }
        
        public function get_folder_by_id($id){
            $children = $this->get();
            
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder' && $child->email_folder_id == $id){
                    return $child;
                }
            }
            
            return null;
        }
        
        public function get_folder($imap_path, $parent_id = null){
            //print "<h1>Seeking '{$imap_path}'</h1>";
            $path = explode("/", $imap_path);
            $path = array_reverse($path);
            $base_folder = array_pop($path);
            $path = array_reverse($path);
            $path = implode("/", $path);
            
            $children = $this->get();
            
            foreach($children as $child){
                
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder'){
                    //print new html_pre(print_r($child->to_hash(), true));
                    if ((is_null($parent_id) && is_null($child->parent_email_folder_id) && $child->imap_name == $base_folder) || ($parent_id == $child->parent_email_folder_id && $child->imap_name == $base_folder)){   
                        if ((is_null($parent_id) && is_null($child->parent_email_folder_id)) || ($child->imap_name == $base_folder)){
                            if ($path != ""){
                                return $this->get_folder($path, $child->email_folder_id);
                            }else{
                                return $child;
                            }
                        }
                    }
                }
            }
            return null;
        }
        
        public function create_folder($imap_path, $parent_id = null){
            //print new html_pre("IMAP PATH: {$imap_path}");
            $path = explode("/", $imap_path);
            $path = array_reverse($path);
            $base_folder = array_pop($path);
            $path = array_reverse($path);
            $path = implode("/", $path);
            
            
            //print "<pre>BASE: {$base_folder}</pre>";
            //print "<pre>PATH: {$path}</pre>";
            //exit(1);
            $children = $this->get();
            
            $found = false;
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder'){
                    if ((is_null($parent_id) && is_null($child->parent_email_folder_id) && $child->imap_name == $base_folder) || ($parent_id == $child->parent_email_folder_id && $child->imap_name == $base_folder)){
                        
                        $found = true;
                        if ($path != ""){
                            $this->create_folder($path, $child->email_folder_id);
                        }else{
                            return $child;
                        }
                    }
                }
            }
            
            if (!$found){
                $folder = new model_email_folder();
                $folder->parent_email_folder_id = $parent_id;
                $folder->imap_name = $base_folder;
                $folder->label = $base_folder;
                $folder->type = 'Folder';
                $folder->email_account_id = $this->email_account_id;
                $folder->save();
                $this->add($folder);
                $this->save();
                return $folder;
            }
            
            
            return null;
        }
        
        public function get_inbox(){
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder' && $child->type == 'Inbox'){
                    return $child;
                }
            }
            
            return null;
        }
        
        public function get_outbox(){
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder' && $child->type == 'Outbox'){
                    return $child;
                }
            }
            
            return null;
        }
        
        public function get_sent_items(){
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder' && $child->type == 'Sent items'){
                    return $child;
                }
            }
            
            return null;
        }
        
        public function get_trash(){
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder' && $child->type == 'Trash'){
                    return $child;
                }
            }
            
            return null;
        }
        
        public function get_drafts(){
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder' && $child->type == 'Drafts'){
                    return $child;
                }
            }
            
            return null;
        }
        
        public function get_templates(){
            $children = $this->get();
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'email_folder' && $child->type == 'Templates'){
                    return $child;
                }
            }
            
            return null;
        }
        
        
        /*
         * Send an email
         * Use only when you need to send an email immediately,
         * this function *is* blocking.
         */
        public function send($model_email){
            if ($this->save_to_outbox($model_email)){
                
                $email = $model_email->render();
                
                /* Handle dot stuffing */
                $email = preg_replace("/^\./m", "..", $email);
                
                if ($email && $email != ""){
                    
                    /* Create a new SMTP instance */
                    $smtp = new smtp();
                    
                    $smtp_security = smtp::NO_SECURITY;
                    switch($this->smtp_security){
                    case "TLS":
                        $smtp_security = smtp::TLS;
                        break;
                    case "SSL":
                        $smtp_security = smtp::SSL;
                        break;
                        
                    }
                    
                    /* Do we have relay information? */
                    if ($this->smtp_hostname){
                        $smtp->relay_via($this->smtp_hostname, $this->smtp_port, $smtp_security, $this->smtp_username, $this->smtp_password);
                    }
                    
                    $sender_email = $model_email->sender_email;
                    if (is_null($sender_email) || $sender_email == ""){
                        $sender_email = $this->account_email;
                    }
                    
                    $smtp->from($sender_email);
                    
                    $children = $model_email->get();
                    
                    foreach($children as $child){
                        if ($child instanceof \adapt\model && $child->table_name == 'email_recipient'){
                            $smtp->to($child->recipient_email);
                        }
                    }
                    
                    $smtp->data($email);
                    
                    $success = $smtp->send();
                    if ($success){
                        return $this->save_to_sent_items($model_email);
                        
                    }else{
                        $errors = $smtp->errors(true);
                        foreach($errors as $error){
                            //print "<pre>{$error}</pre>";
                            $this->error($error);
                        }
                    }
                    
                }else{
                    $this->error("Email is empty");
                }
                
            }
            
            return false;
        }
        
        /*
         * Queue the email so it gets sent out
         * in the next scheduled cycle.
         */
        public function queue($model_email){
            if ($this->save_to_outbox($model_email)){
                /* Set the queued_to_send flag */
                $model_email->queued_to_send = 'Yes';
                
                $out = $model_email->save();
                
                return $out;
            }
            
            return false;
        }
        
        public function get_queued_email(){
            if ($this->is_loaded){
                $sql = $this->data_source->sql
                    ->select('e.*')
                    ->from('email', 'e')
                    ->join('email_folder', 'f', 'email_folder_id')
                    ->join('email_account', 'a', 'email_account_id')
                    ->where(
                        new sql_and(
                            new sql_cond('e.queued_to_send', sql::EQUALS, sql::q('Yes')),
                            new sql_cond('e.date_deleted', sql::IS, new sql_null()),
                            new sql_cond('f.date_deleted', sql::IS, new sql_null()),
                            new sql_cond('a.date_deleted', sql::IS, new sql_null())
                        )
                    );
                
                $results = $sql->execute()->results();
                
                $emails = array();
                foreach($results as $result_data){
                    $email = new model_email();
                    $email->load_by_data($result_data);
                    $emails[] = $email;
                }
                
                return $emails;
            }
            
            return array();
        }
        
        /*
         * Helper functions
         */
        public function save_to_sent_items($model_email){
            $output = false;
            if ($this->is_loaded){
                $sent_items = $this->get_sent_items();
                if ($sent_items && $sent_items instanceof \adapt\model && $sent_items->table_name == 'email_folder'){
                    $model_email->email_folder_id = $sent_items->email_folder_id;
                    $model_email->template = 'No';
                    $model_email->draft = 'No';
                    $model_email->sent = 'Yes';
                    $model_email->queued_to_send = 'No';
                    $model_email->received = 'No';
                    $model_email->seen = 'No';
                    $model_email->flagged = 'No';
                    $model_email->answered = 'No';
                    $output = $model_email->save();
                }else{
                    $this->error("Unable to find the sent items folder");
                }
            }else{
                $this->error("Unable to save email to sent items, email account not loaded.");
            }
            
            return $output;
        }
        
        public function save_to_dafts($model_email){
            $output = false;
            if ($this->is_loaded){
                $drafts = $this->get_drafts();
                if ($drafts && $drafts instanceof \adapt\model && $drafts->table_name == 'email_folder'){
                    $model_email->email_folder_id = $drafts->email_folder_id;
                    $model_email->template = 'No';
                    $model_email->draft = 'Yes';
                    $model_email->sent = 'No';
                    $model_email->queued_to_send = 'No';
                    $model_email->received = 'No';
                    $model_email->seen = 'No';
                    $model_email->flagged = 'No';
                    $model_email->answered = 'No';
                    $output = $model_email->save();
                }else{
                    $this->error("Unable to find the drafts folder");
                }
            }else{
                $this->error("Unable to save email to drafts folder, email account not loaded.");
            }
            
            return $output;
        }
        
        public function save_to_outbox($model_email){
            $output = false;
            if ($this->is_loaded){
                $outbox = $this->get_outbox();
                if ($outbox && $outbox instanceof \adapt\model && $outbox->table_name == 'email_folder'){
                    $model_email->email_folder_id = $outbox->email_folder_id;
                    $model_email->template = 'No';
                    $model_email->draft = 'No';
                    $model_email->sent = 'No';
                    $model_email->queued_to_send = 'No';
                    $model_email->received = 'No';
                    $model_email->seen = 'No';
                    $model_email->flagged = 'No';
                    $model_email->answered = 'No';
                    $output = $model_email->save();
                }else{
                    $this->error("Unable to find the outbox folder");
                }
            }else{
                $this->error("Unable to save email to the outbox, email account not loaded.");
            }
            
            return $output;
        }
        
        public function save_to_templates($model_email){
            $output = false;
            if ($this->is_loaded){
                $templates = $this->get_templates();
                if ($templates && $templates instanceof \adapt\model && $templates->table_name == 'email_folder'){
                    $model_email->email_folder_id = $templates->email_folder_id;
                    $model_email->template = 'Yes';
                    $model_email->draft = 'No';
                    $model_email->sent = 'No';
                    $model_email->queued_to_send = 'No';
                    $model_email->received = 'No';
                    $model_email->seen = 'No';
                    $model_email->flagged = 'No';
                    $model_email->answered = 'No';
                    if (is_null($model_email->sender_email)){
                        $model_email->sender_email = $this->account_email;
                    }
                    $output = $model_email->save();
                }else{
                    $this->error("Unable to find the template folder");
                }
            }else{
                $this->error("Unable to save email to the template, email account not loaded.");
            }
            
            return $output;
        }
        
    }
    
}

?>