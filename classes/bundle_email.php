<?php

namespace adapt\email{
    
    /* Prevent Direct Access */
    defined('ADAPT_STARTED') or die;
    
    class bundle_email extends \adapt\bundle{
        
        protected $_templates;
        
        public function __construct($data){
            parent::__construct('email', $data);
            
            $this->_templates = array();
            
            $this->register_config_handler('email', 'email', 'process_email_tag');
        }
        
        public function boot(){
            if (parent::boot()){
                                
                /* Lets extend base and make the default email account available */
                \adapt\base::extend('pget_email_account', function($_this){
                    $account = $_this->store('email.account');
                    if (is_null($account)){
                        $account = new \adapt\email\model_email_account();
                        if ($account->load_default()){
                            $_this->store('email.account', $account);
                        }
                    }
                    
                    return $account;
                });
                
                /* Allow changing of the default */
                \adapt\base::extend('pset_email_account', function($_this, $account){
                    $_this->store('email.account', $account);
                });
                
                
                return true;
            }
            
            return false;
        }
        
        
        public function process_email_tag($bundle, $tag_data){
            //print "<h1>BAR</h1>";
            if ($bundle instanceof \adapt\bundle && $tag_data instanceof \adapt\xml){
                //print "<h1>FUBAR</h1>";
                $this->register_install_handler($this->name, $bundle->name, 'install_emails');
                
                $template_nodes = $tag_data->get();
                $this->_templates[$bundle->name] = array();
                
                foreach($template_nodes as $template_node){
                    if ($template_node instanceof \adapt\xml && $template_node->tag == "template"){
                        $template = array(
                            'name' => $template_node->attr('name'),
                            'subject' => '',
                            'sender_name' => null,
                            'sender_email' => null,
                            'parts' => array()
                        );
                        
                        $template_children = $template_node->get();
                        
                        foreach($template_children as $template_child){
                            if ($template_child instanceof \adapt\xml){
                                switch($template_child->tag){
                                case "subject":
                                    $template['subject'] = $template_child->get(0);
                                    break;
                                case "part":
                                    
                                    $get_from_file = $template_child->attr('get-from-file');
                                    
                                    if ($get_from_file && file_exists(ADAPT_PATH . $bundle->name . "/" . $bundle->name . "-" . $bundle->version . "/" . $get_from_file)){
                                        $part = file_get_contents(ADAPT_PATH . $bundle->name . "/" . $bundle->name . "-" . $bundle->version . "/" . $get_from_file);
                                    } else {
                                        $part = $template_child->get(0)->get(0);
                                    }
                                    $type = $template_child->attr('content-type');
                                    $encoding = $template_child->attr('content-encoding');
                                    $content_id = $template_child->attr('content-id');
                                    $filename = $template_child->attr('filename');
                                    
                                    $template['parts'][] = array(
                                        'content_type' => $type,
                                        'part' => $part,
                                        'content_encoding' => $encoding,
                                        'content_id' => $content_id,
                                        'filename' => $filename
                                    );
                                    
                                    print "<pre>PARTS: " . print_r($template['parts'], true) . "</pre>";
                                    break;
                                case "from":
                                    $template['sender_email'] = $template_child->get(0);
                                    $template['sender_name'] = $template_child->attr('name');
                                    break;
                                }
                            }
                        }
                        
                        $this->_templates[$bundle->name][] = $template;
                    }
                }
            }
            
        }
        
        public function install_emails($bundle){
            /* Ensure the default account is created */
            $account = new \adapt\email\model_email_account();
            $account->load_default();
            
            /* And is available */
            $this->store('email.account', $account);
            
            if ($bundle instanceof \adapt\bundle){
                if (is_array($this->_templates[$bundle->name])){
                    $templates = $this->_templates[$bundle->name];
                    
                    foreach($templates as $template){
                        $model_email = new model_email();
                        $account = $this->store('email.account');
                        
                        if ($account instanceof model_email_account){
                            $model_email->name = $template['name'];
                            $model_email->subject($template['subject']);
                            $model_email->from($template['sender_email'], $template['sender_name']);
                            foreach($template['parts'] as $part){
                                $model_email->message($part['content_type'], $part['part']);
                            }
                            
                            $account->save_to_templates($model_email);
                            
                        }
                    }
                }
            }
        }
        
    }
    
    
}

?>