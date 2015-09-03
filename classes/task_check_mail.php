<?php

namespace extensions\email{
    
    /* Prevent Direct Access */
    defined('ADAPT_STARTED') or die;
    
    class task_check_mail extends \extensions\scheduler\task{
        
        public function task(){
            parent::task();
            
            /* Children should override this with the code they wish to run */
            return "Called task_check_mail";
        }

    }
    
}

?>