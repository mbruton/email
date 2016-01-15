<?php

namespace adapt\email{
    
    /* Prevent Direct Access */
    defined('ADAPT_STARTED') or die;
    
    class task_check_mail extends \adapt\scheduler\task{
        
        public function task(){
            parent::task();
            
            /* Children should override this with the code they wish to run */
            return "Called task_check_mail";
        }

    }
    
}

?>