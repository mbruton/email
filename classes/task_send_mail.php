<?php

namespace adapt\email{
    
    /* Prevent Direct Access */
    defined('ADAPT_STARTED') or die;
    
    class task_send_mail extends \adapt\scheduler\task{
        
        public function task(){
            parent::task();
            $output = "";
            
            $this->_log->label = "Send mail";
            $this->_log->save();
            
            /* Get a list of email accounts */
            $accounts = $this->data_source->sql
                ->select('*')
                ->from('email_account')
                ->where(
                    new sql_and(
                        new sql_cond('smtp_hostname', sql::IS_NOT, new sql_null()),
                        new sql_cond('date_deleted', sql::IS, new sql_null())
                    )
                )
                ->execute()
                ->results();
            
            $emails_to_send = array();
            $email_count = 0;
            
            foreach($accounts as $account_data) {
                $account = new model_email_account();
                
                if ($account->load_by_data($account_data)) {
                    $emails = $account->get_queued_email();

                    if (count($emails)) {
                        $email_count = count($emails);

                        $emails_to_send[] = array(
                            'account' => $account,
                            'emails' => $emails
                        );
                    }
                    
                }else{
                    $output .= "Failed to load email account #{$account['email_account_id']}\n";
                }
            }
            
            if ($email_count){
                
                $progress = 0;
                $progress_per_email = 100 / $email_count;
                
                
                foreach($emails_to_send as $email_set){
                    //$output .= print_r($email_set['emails'], true);
                    $account = $email_set['account'];
                    $emails = $email_set['emails'];
                    
                    foreach($emails as $email){
                        if ($email instanceof \adapt\email\model_email){
                            if ($email->send(false)){
                                $email->queued_to_send = 'No';
                                $email->save();
                                $output .= "Sent email #" . $email->email_id . "\n";
                            }else{
                                $output .= "Failed to send email #" . $email->email_id . "\n";
                            }
                        }
                        
                        $progress += $progress_per_email;
                        $this->set_progress(floor($progress));
                    }
                }
                
            }else{
                $output .= "Nothing to send\n";
            }
            
            return $output;
        }

    }
    
}

?>