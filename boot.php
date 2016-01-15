<?php

/* Prevent direct access */
defined('ADAPT_STARTED') or die;

/* Lets extend base and make the default email account available */
\frameworks\adapt\base::extend('pget_email_account', function($_this){
    $account = $_this->store('email.account');
    if (is_null($account)){
        $account = new \extensions\email\model_email_account();
        if ($account->load_default()){
            $_this->store('email.account', $account);
        }
    }
    
    return $account;
});

/* Allow changing of the default */
\frameworks\adapt\base::extend('pset_email_account', function($_this, $account){
    $_this->store('email.account', $account);
});

?>