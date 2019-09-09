<?php
namespace App\Contact;

use Seriti\Tools\SetupModuleData;

class SetupData extends SetupModuledata
{

    public function setupSql()
    {
        $this->tables = ['contact','group','group_link','message','template','file'];

        $this->addCreateSql('contact',
                            'CREATE TABLE `TABLE_NAME` (
                              `contact_id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(64) NOT NULL,
                              `surname` varchar(64) NOT NULL,
                              `email` varchar(250) NOT NULL,
                              `email_alt` varchar(250) NOT NULL,
                              `tel` varchar(64) NOT NULL,
                              `address` text NOT NULL,
                              `notes` text NOT NULL,
                              `create_date` date NOT NULL DEFAULT \'0000-00-00\',
                              `url` varchar(250) NOT NULL,
                              `cell` varchar(64) NOT NULL,
                              PRIMARY KEY (`contact_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8'); 

        $this->addCreateSql('group',
                            'CREATE TABLE `TABLE_NAME` (
                              `group_id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(64) NOT NULL,
                              `notes` text NOT NULL,
                              PRIMARY KEY (`group_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('group_link',
                            'CREATE TABLE `TABLE_NAME` (
                              `link_id` int(11) NOT NULL AUTO_INCREMENT,
                              `group_id` int(11) NOT NULL,
                              `contact_id` int(11) NOT NULL,
                              PRIMARY KEY (`link_id`),
                              UNIQUE KEY `idx_group_link1` (`group_id`,`contact_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('message',
                            'CREATE TABLE `TABLE_NAME` (
                              `message_id` int(11) NOT NULL AUTO_INCREMENT,
                              `create_date` date NOT NULL DEFAULT \'0000-00-00\',
                              `template_id` int(11) NOT NULL,
                              `subject` varchar(250) NOT NULL,
                              `body_markdown` text NOT NULL,
                              `body_html` text NOT NULL,
                              `info` text NOT NULL,
                              PRIMARY KEY (`message_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8'); 

        $this->addCreateSql('template',
                            'CREATE TABLE `TABLE_NAME` (
                              `template_id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(64) NOT NULL,
                              `template_markdown` text NOT NULL,
                              `template_html` text NOT NULL,
                              PRIMARY KEY (`template_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');

        $this->addCreateSql('file',
                            'CREATE TABLE `TABLE_NAME` (
                              `file_id` int(10) unsigned NOT NULL,
                              `link_id` varchar(255) NOT NULL,
                              `file_name` varchar(255) NOT NULL,
                              `file_name_tn` varchar(255) NOT NULL,
                              `file_name_orig` varchar(255) NOT NULL,
                              `file_text` longtext NOT NULL,
                              `file_date` date NOT NULL DEFAULT \'0000-00-00\',
                              `file_size` int(11) NOT NULL,
                              `location_id` varchar(64) NOT NULL,
                              `location_rank` int(11) NOT NULL,
                              `encrypted` tinyint(1) NOT NULL,
                              `file_ext` varchar(16) NOT NULL,
                              `file_type` varchar(16) NOT NULL,
                              PRIMARY KEY (`file_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');   

               
        //initialisation
        $this->addInitialSql('INSERT INTO `TABLE_PREFIXcontact` (name,surname,email,cell,notes,create_date) '.
                             'VALUES("Spongebob","Squarepants","bob@squarepants.com","+27 123 456 7890","My first fantasy contact",CURDATE())');
        

        //updates use time stamp in ['YYYY-MM-DD HH:MM'] format, must be unique and sequential
        //$this->addUpdateSql('YYYY-MM-DD HH:MM','Update TABLE_PREFIX--- SET --- "X"');
    }
}


  
?>
