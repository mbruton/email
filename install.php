<?php

/* Prevent Direct Access */
defined('ADAPT_STARTED') or die;

$adapt = $GLOBALS['adapt'];
$sql = $adapt->data_source->sql;

$adapt->data_source->on('adapt.error', function($error){
    print "<pre>" . print_r($error, true) . "</pre>";
});

$sql->create_table('email_account')
    ->add('email_account_id', 'bigint')
    ->add('name', 'varchar(64)') //Not required for user created accounts
    ->add('label', 'varchar(64)')
    ->add('description', 'text')
    ->add('priority', 'int', false) //Lowest is considered the default
    ->add('account_email', 'email_address')
    ->add('imap_hostname', 'varchar(256)')
    ->add('imap_port', 'int', true, '993')
    ->add('imap_username', 'varchar(256)')
    ->add('imap_password', 'varchar(256)')
    ->add('imap_options', 'varchar(128)')
    //->add('imap_mailbox', 'varchar(128)') //Not entirely sure why I added this?!
    ->add('smtp_hostname', 'varchar(256)')
    ->add('smtp_port', 'int', true)
    ->add('smtp_security', "enum('None', 'TLS', 'SSL')", true, 'None')
    ->add('smtp_username', 'varchar(256)')
    ->add('smtp_password', 'varchar(256)')
    ->add('date_created', 'datetime')
    ->add('date_synced', 'datetime')
    ->add('date_modified', 'timestamp')
    ->add('date_deleted', 'datetime')
    ->primary_key('email_account_id')
    ->execute();

//$sql->create_table('email_account_smtp')
//    ->add('email_account_smtp_id', 'bigint')
//    ->add('email_account_id', 'bigint')
//    ->add('local_hostname', 'varchar(256)')
//    ->add('smtp_hostname', 'varchar(256)', false)
//    ->add('smtp_port', 'int', false)
//    ->add('smtp_security', "enum('None', 'TLS', 'SSL')", false, 'None')
//    ->add('smtp_username', 'varchar(256)')
//    ->add('smtp_password', 'varchar(256)')
//    ->add('date_created', 'datetime')
//    ->add('date_modified', 'timestamp')
//    ->add('date_deleted', 'datetime')
//    ->primary_key('email_account_smtp_id')
//    ->foreign_key('email_account_id', 'email_account', 'email_account_id')
//    ->execute();
//
//$sql->create_table('email_account_imap')
//    ->add('email_account_imap_id', 'bigint')
//    ->add('email_account_id', 'bigint')
//    ->add('imap_hostname', 'varchar(256)', false)
//    ->add('imap_port', 'int', false)
//    ->add('imap_username', 'varchar(256)', false)
//    ->add('imap_password', 'varchar(256)')
//    ->add('imap_options', 'varchar(128)')
//    ->add('imap_mailbox', 'varchar(128)')
//    ->primary_key('email_account_smtp_id')
//    ->foreign_key('email_account_id', 'email_account', 'email_account_id')
//    ->execute();

$sql->create_table('email_folder')
    ->add('email_folder_id', 'bigint')
    ->add('email_account_id', 'bigint')
    ->add('parent_email_folder_id', 'bigint')
    ->add('type', "enum('Inbox', 'Outbox', 'Sent items', 'Drafts', 'Trash', 'Folder', 'Templates')", false, 'Folder')
    ->add('imap_name', 'varchar(256)')
    ->add('label', 'varchar(64)', false)
    ->add('description', 'text')
    ->add('date_created', 'datetime')
    ->add('date_synced', 'datetime')
    ->add('date_modified', 'timestamp')
    ->add('date_deleted', 'datetime')
    ->primary_key('email_folder_id')
    ->foreign_key('email_account_id', 'email_account', 'email_account_id')
    ->execute();
    

$sql->create_table('email')
    ->add('email_id', 'bigint')
    ->add('email_folder_id', 'bigint', false)
    ->add('name', 'varchar(64)') //Used by templates
    ->add('imap_message_id', "varchar(256)")
    ->add('imap_date', 'datetime')
    ->add('imap_message_fetched', "enum('Yes', 'No')", false, 'No')
    ->add('template', "enum('Yes', 'No')", false, 'No')
    ->add('draft', "enum('Yes', 'No')", false, 'No')
    ->add('sent', "enum('Yes', 'No')", false, 'No')
    ->add('queued_to_send', "enum('Yes', 'No')", false, 'No')
    ->add('received', "enum('Yes', 'No')", false, 'No')
    ->add('seen', "enum('Yes', 'No')", false, 'No')
    ->add('flagged', "enum('Yes', 'No')", false, 'No')
    ->add('answered', "enum('Yes', 'No')", false, 'No')
    ->add('sender_name', 'name')
    ->add('sender_email', 'email_address', false)
    ->add('sender_host', 'varchar(256)')
    ->add('subject', 'varchar(512)')
    ->add('date_created', 'datetime')
    ->add('date_sent', 'datetime')
    ->add('date_modified', 'timestamp')
    ->add('date_deleted', 'datetime')
    ->primary_key('email_id')
    ->foreign_key('email_folder_id', 'email_folder', 'email_folder_id', \frameworks\adapt\sql::ON_DELETE_CASCADE)
    ->execute();

$sql->create_table('email_part')
    ->add('email_part_id', 'bigint')
    ->add('email_id', 'bigint')
    ->add('content_type', 'varchar(128)', false)
    ->add('content_encoding', 'varchar(128)')
    ->add('content', 'longblob')
    ->add('content_id', 'varchar(128)')
    ->add('filename', 'varchar(64)')
    ->add('date_created', 'datetime')
    ->add('date_modified', 'timestamp')
    ->add('date_deleted', 'datetime')
    ->primary_key('email_part_id')
    ->foreign_key('email_id', 'email', 'email_id')
    ->execute();

$sql->create_table('email_recipient')
    ->add('email_recipient_id', 'bigint')
    ->add('email_id', 'bigint')
    ->add('recipient_type', "enum('to', 'cc', 'bcc')", false, 'to')
    ->add('recipient_name', 'name')
    ->add('recipient_email', 'email_address')
    ->add('date_created', 'datetime')
    ->add('date_modified', 'timestamp')
    ->add('date_deleted', 'datetime')
    ->primary_key('email_recipient_id')
    ->foreign_key('email_id', 'email', 'email_id')
    ->execute();

/* Create the default mail account */
$account = new model_email_account();
$account->name = 'default';
$account->label = 'Default';
//$account->account_email = 'matt@example.com';
$account->description = 'This is the default account used to send emails.';
$account->priority = 1;
$account->save();


/* Add a new task for checking email */
//$task = new \extensions\scheduler\model_task();
//$task->bundle_name = 'email';
//$task->name = 'check_mail';
//$task->status = 'waiting';
//$task->label = "Check email";
//$task->description = "Checks for new email";
//$task->class_name = "\\extensions\\email\\task_check_mail";
//$task->minutes = "0,15,30,45";
//$task->hours = "*";
//$task->days_of_month = "*";
//$task->days_of_week = "*";
//$task->months = "*";
//$task->save();

/* Add a new task for sending out queued email */
//$task = new \extensions\scheduler\model_task();
//$task->bundle_name = 'email';
//$task->name = 'send_mail';
//$task->status = 'waiting';
//$task->label = "Sends queued email";
//$task->description = "Sends out any email that have been queued.";
//$task->class_name = "\\extensions\\email\\task_send_mail";
//$task->minutes = "0,5,10,15,20,25,30,35,40,45,50,55";
//$task->hours = "*";
//$task->days_of_month = "*";
//$task->days_of_week = "*";
//$task->months = "*";
//$task->save();




?>