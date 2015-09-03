<?php

namespace extensions\email{
    
    /* Prevent Direct Access */
    defined('ADAPT_STARTED') or die;
    
    class task_send_mail extends \extensions\scheduler\task{
        
        public function task(){
            /* Children should override this with the code they wish to run */
            return "Called task_send_mail";
        }

    }
    
}

?>