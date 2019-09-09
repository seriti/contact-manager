<?php 
namespace App\Contact;

use Exception;
use Seriti\Tools\Csv;
use Seriti\Tools\Queue;
use Seriti\Tools\Audit;
use Seriti\Tools\Upload;
use Seriti\Tools\TABLE_QUEUE;

use App\Contact\TemplateImage;

use Psr\Container\ContainerInterface;


//static functions for saveme module
class Helpers {
    public static function addContactToGroup($db,$contact_id,$group_id,&$error) 
    {
        $error = '';
        $error_tmp = '';
        $info = '';

        $sql = 'SELECT COUNT(*) FROM '.TABLE_PREFIX.'group_link '.
               'WHERE group_id = "'.$db->escapeSql($group_id).'" AND contact_id = "'.$db->escapeSql($contact_id).'" ';
        $exists = $db->readSqlValue($sql);
        if($exists != 0 ) {
            $info = 'EXISTS';
        } else {
            $sql = 'INSERT INTO '.TABLE_PREFIX.'group_link (group_id,contact_id) '.
                   'VALUES("'.$db->escapeSql($group_id).'","'.$db->escapeSql($contact_id).'")';
            $db->executeSql($sql,$error_tmp);
            if($error_tmp !== '') {
                $error .= 'Contact id['.$contact_id.'] could not be added to Group id['.$group_id.'] ';
                if($this->debug) $error .= ': '.$error_tmp;
            } else {
                $info = 'ADDED' ;
            }
            
        }
        
        if($error === '') return $info; else return false;
    }

    public static function updateMessageInfo($db,$message_id) 
    {
        $error = '';
        $info_str = '';

        $process_id = 'CONTACT_MSG'.$message_id;
        $sql = 'SELECT process_status,COUNT(*) FROM '.TABLE_QUEUE.' '.
               'WHERE process_id = "'.$db->escapeSql($process_id).'" '.
               'GROUP BY process_status ';
        $info = $db->readSqlList($sql);
        if($info != 0 ) {
            if($info['NEW'] > 0) $info_str .= 'Message awaiting processing for '.$info['NEW']." contacts\r\n";
            if($info['DONE'] > 0) $info_str .= 'Message successfully sent to '.$info['DONE']." contacts \r\n";
            if($info['ERROR'] > 0) $info_str .= 'Messages not sent due to errors for '.$info['ERROR']." contacts\r\n";
        } else {
            $info_str .= 'Message not added to queue yet!';
        }

        $sql = 'UPDATE '.TABLE_PREFIX.'message SET info = "'.$db->escapeSql($info_str).'" '.
               'WHERE message_id = "'.$db->escapeSql($message_id).'" ';
        $db->executeSql($sql,$error);

        if($error === '') return true; else return false;
    }  


    public static function addMessageQueue($db,ContainerInterface $container,$message_id,$group_id,&$error) 
    {
        $error = '';
        $output = '';

        //get message group contacts
        $sql = 'SELECT C.contact_id,C.name '.
               'FROM '.TABLE_PREFIX.'group_link AS L '.
               'JOIN '.TABLE_PREFIX.'contact AS C ON(L.contact_id = C.contact_id) '.
               'WHERE L.group_id = "'.$db->escapeSql($group_id).'" ';
        $contacts = $db->readSqlList($sql); 

        if($contacts == 0) {
            $error = 'No contacts found for Group ID['.$group_id.']';
            $output = false;
        } else {
            $count = count($contacts);

            $queue = new Queue($db,$container,TABLE_QUEUE);
            $queue->setup();

            //each message must have a separate process for SetupMessageMailer() to prep attachments 
            $process_id = 'CONTACT_MSG'.$message_id;

            //don't want to clog up audit trail 
            $db->disableAudit();
            foreach($contacts as $contact_id => $name) {
                //item_key prevents same contact receiving a message twice
                $item_key = 'MSG'.$message_id.'-ID'.$contact_id;
                $item_data = ['message_id'=>$message_id,'contact_id'=>$contact_id,'name'=>$name];
                $queue->addItem($process_id,$item_key,$item_data,'NEW');
            }
            $db->enableAudit();

            $exist_no = $queue->getQueueInfo('EXIST');
            $add_no = $queue->getQueueInfo('ADDED');

            $output = "Message[$message_id]: Added $add_no contacts to queue.";
            if($exist_no != 0) $output .= " $exist_no contacts are allready in queue or have been processed before(a contact can only receive a message once).";

        } 

        return $output;
    }    

    //constructs message mailer object for bulk sends and also single/test sends
    public static function setupMessageMailer($db,ContainerInterface $container,$message_id,&$subject,&$body,&$error) 
    {
        $error = '';
        $subject = '';
        $body = '';
        $error_tmp = '';

        //$mailer = clone $container['mail']; ???
        $mailer = clone $container['mail'];

        $sql = 'SELECT M.message_id,M.template_id,M.subject,M.body_html, '.
                      'T.name as template,T.template_html '.
               'FROM con_message AS M '.
               'JOIN con_template AS T ON(M.template_id = T.template_id)'.
               'WHERE M.message_id = "'.$db->escapeSql($message_id).'" ';
        $message = $db->readSqlRecord($sql); 
        if($message == 0 ) {
            $error = 'Invaid message ID['.$message_id.']';
        } else {
            $subject = $message['subject'];
            $body = str_replace('{CONTENT}',$message['body_html'],$message['template_html']);
            
            //Template images
            $location_id = 'TMP'.$message['template_id'];
            $sql = 'SELECT file_id,file_name_orig,link_id FROM '.TABLE_PREFIX.'file '.
                   'WHERE location_id = "'.$location_id.'" ORDER BY file_id ';
            $template_files = $db->readSqlArray($sql);
            if($template_files != 0) {
                //get any embedded images for template wherever they might be
                $images = new Upload($db,$container,TABLE_PREFIX.'file');
                $images->setup(['location'=>'TMP','interface'=>'download']);

                foreach($template_files as $file_id => $file) {
                    $image_link = $file['link_id'];
                    //message templates format
                    $template_link = '{IMAGE:'.$image_link.'}';
                    //phpmailer expects following format
                    $mailer_link = '<img src="cid:'.$image_link.'">';
                    
                    $image_name = $file['file_name_orig'];
                    $image_path = $images->fileDownload($file_id,'FILE'); 
                    if(substr($image_path,0,5) !== 'Error' and file_exists($image_path)) {
                        $body = str_replace($template_link,$mailer_link,$body);
                        $mailer->AddEmbeddedImage($image_path,$image_link,$image_name);
                    } else {
                        $error .= 'Error fetching template image['.$image_name.'] for message!'; 
                    }   
                }   
            }

            //message attachments
            $location_id = 'MSG'.$message['message_id'];
            $sql = 'SELECT file_id,file_name_orig,file_size FROM '.TABLE_PREFIX.'file '.
                   'WHERE location_id = "'.$location_id.'" ORDER BY file_id ';
            $message_files = $db->readSqlArray($sql);
            if($message_files != 0) {
                $body .= '<br/>Please see attached documents('.count($message_files).').';

                //get any embedded images for template wherever they might be
                $files = new Upload($db,$container,TABLE_PREFIX.'file');
                $files->setup(['location'=>'MSG','interface'=>'download']);

                foreach($message_files as $file_id => $file) {
                    $file_name = $file['file_name_orig'];
                    $file_path = $files->fileDownload($file_id,'FILE'); 
                    if(substr($file_path,0,5) !== 'Error' and file_exists($file_path)) {
                        $mailer->addAttachment($file_path,$file_name);
                    } else {
                        $error .= 'Error fetching attachment['.$file_name.'] for message!'; 
                    }   
                } 
            }
        } 

        if($error === '') return $mailer; else return false; 
    }

    public static function setupBulkMessageMailer($db,ContainerInterface $container,$message_id,&$error) 
    {
        $error = '';
        $subject = '';
        $body = '';
        $error_tmp = '';

        $mailer = self::setupMessageMailer($db,$container,$message_id,$subject,$body,$error_tmp);
        if($error_tmp !== '') {
            $error .= 'Could not setup message: '.$error;
        } else {
            $from = ''; //will use default
            $param = [];
            $param['format'] = 'html';
            //NB: default is ALL but since we are using setupMessageMailer() to add attachements rather than via $param['attach'] we do not want to reset ALL
            $param['reset'] = 'TO'; 
            
            $mailer->setupBulkMail($from,$subject,$body,$param,$error_tmp);
            if($error_tmp != '') { 
                $error .= 'Error setting up queue mailer for message['. $message_id.']:'.$error_tmp; 
            }
                 
        } 

        if($error === '') return $mailer; else return false; 
    }

    public static function sendMessage($db,ContainerInterface $container,$message_id,$email_address,&$error) 
    {
        $error = '';
        $subject = '';
        $body = '';
        $error_tmp = '';

        $mailer = self::setupMessageMailer($db,$container,$message_id,$subject,$body,$error_tmp);
        if($error_tmp !== '') {
            $error .= 'Could not setup message: '.$error;
        } else {
            $mail_from = ''; //use default MAIL_FROM
            $mail_to = $email_address;
            $param = [];
            $param['format'] = 'html';
            //NB: default is ALL but since we are using setupMessageMailer() to add attachements rather than via $param['attach'] we do not want to reset ALL
            $param['reset'] = 'TO'; 
            $mailer->sendEmail($mail_from,$mail_to,$subject,$body,$error_tmp,$param);
            if($error_tmp != '') { 
                $error .= 'Error sending message to email['. $mail_to.']:'.$error_tmp; 
            } 
        }

        if($error === '') return true; else return false; 
    }    

    public static function checkContactFormat($format,$line,&$error )  
    {
        $error = '';
                
        if($format === 'GOOGLE') {
            if($line[0] !== 'Name') $error .= 'First column header['.$line[0].'] NOT = "Name"<br/>';  
            if($line[1] !== 'Given Name') $error .= 'Second column header['.$line[1].'] NOT = "Given Name"<br/>'; 
            if($line[2] !== 'Additional Name') $error .= 'Third column header['.$line[2].'] NOT = "Additional Name"<br/>'; 
            if($line[3] !== 'Family Name') $error .= 'Fourth column header['.$line[3].'] NOT = "Family Name"<br/>'; 
        } 
        
        if($format === 'OUTLOOK') {
            if($line[0] !== 'First Name') $error .= 'First column header['.$line[0].'] NOT = "First Name"<br/>';  
            if($line[1] !== 'Middle Name') $error .= 'Second column header['.$line[1].'] NOT = "Middle Name"<br/>'; 
            if($line[2] !== 'Last Name') $error .= 'Third column header['.$line[2].'] NOT = "Last Name"<br/>'; 
            if($line[3] !== 'Title') $error .= 'Fourth column header['.$line[3].'] NOT = "Title"<br/>'; 
        } 

        if($format === 'SERITI') {
            if($line[0] !== 'Name') $error .= 'First column header['.$line[0].'] NOT = "Name"<br/>';  
            if($line[1] !== 'Surname') $error .= 'Second column header['.$line[1].'] NOT = "Surname"<br/>'; 
            if($line[2] !== 'Email') $error .= 'Third column header['.$line[2].'] NOT = "Email"<br/>'; 
            if($line[3] !== 'Alternative email') $error .= 'Third column header['.$line[3].'] NOT = "Alternative email"<br/>'; 
            if($line[4] !== 'Landline') $error .= 'Fourth column header['.$line[4].'] NOT = "Landline"<br/>'; 
            if($line[5] !== 'Mobile') $error .= 'Fourth column header['.$line[5].'] NOT = "Mobile"<br/>';
            if($line[6] !== 'Notes') $error .= 'Fourth column header['.$line[6].'] NOT = "Notes"<br/>';
            if($line[7] !== 'Keywords') $error .= 'Fourth column header['.$line[7].'] NOT = "Keywords"<br/>';
        }
        
        if($error === '') return true; else return false;  
    }

    public static function importContact($db,$update = false,$format,$line,&$error) 
    {
        $error_tmp = '';
        $error = '';
        $data = [];
        $status = 'NONE';
        
        $name = '';
        $given_name = '';
        $add_name = '';
        $family_name = '';
        $notes = '';
        $group = '';
        $address = '';
        $url = '';
        $email = [];
        $phone_type = [];
        $phone_num = [];
        
        if($format === 'GOOGLE') {
            //echo implode(',',$line).'<br/>';
            //name is entire name and ONLY used to check for valid line
            $name = Csv::csvStrip($line[0]);
            $given_name = Csv::csvStrip($line[1]);
            $add_name = Csv::csvStrip($line[2]);
            $family_name = Csv::csvStrip($line[3]);
            
            $notes = Csv::csvStrip($line[25]);
            $group = Csv::csvStrip($line[26]);
            $email[] = Csv::csvStrip($line[28]);
            $email[] = Csv::csvStrip($line[30]);
            $email[] = Csv::csvStrip($line[32]);
            $email[] = Csv::csvStrip($line[34]);
            $phone_type[] = Csv::csvStrip($line[35]);
            $phone_num[] = Csv::csvStrip($line[36]);
            $phone_type[] = Csv::csvStrip($line[37]);
            $phone_num[] = Csv::csvStrip($line[38]);
            $phone_type[] = Csv::csvStrip($line[39]);
            $phone_num[] = Csv::csvStrip($line[40]);
            $phone_type[] = Csv::csvStrip($line[41]);
            $phone_num[] = Csv::csvStrip($line[42]);
            
            $address = Csv::csvStrip($line[44]);
            $url = Csv::csvStrip($line[61]);
        } 
        
        if($format === 'OUTLOOK') {
            //name is first name repeated and ONLY used to check for valid line
            //only referencing most obvious fields, vast number gnored
            $name = Csv::csvStrip($line[0]);
            $given_name = Csv::csvStrip($line[0]);
            $add_name = Csv::csvStrip($line[1]);
            $family_name = Csv::csvStrip($line[2]);
          
            $notes = Csv::csvStrip($line[13]);
            $group = Csv::csvStrip($line[87]);//col CJ
            
            $email[] = Csv::csvStrip($line[14]);
            $email[] = Csv::csvStrip($line[15]);
            $email[] = Csv::csvStrip($line[16]);
            $email[] = '';
            $phone_type[] = 'primary';
            $phone_num[] = Csv::csvStrip($line[17]);
            $phone_type[] = 'home1';
            $phone_num[] = Csv::csvStrip($line[18]);
            $phone_type[] = 'home2';
            $phone_num[] = Csv::csvStrip($line[19]);
            $phone_type[] = 'mobile'; //NB used as marker for ['cell']
            $phone_num[] = Csv::csvStrip($line[20]);
            
            $address = Csv::csvStrip($line[23]);
            $url = Csv::csvStrip($line[6]);
        }

        if($format === 'SERITI') {
            $name = Csv::csvStrip($line[0]);
            $given_name = $name;
            $family_name = Csv::csvStrip($line[1]);
          
            $email[] = Csv::csvStrip($line[2]);
            $email[] = Csv::csvStrip($line[3]);
            $email[] = '';
            $email[] = '';
            $phone_type[] = 'primary';
            $phone_num[] = Csv::csvStrip($line[4]);
            $phone_type[] = 'mobile'; 
            $phone_num[] = Csv::csvStrip($line[5]);
          
            $notes = Csv::csvStrip($line[6]);

            //$address = Csv::csvStrip($line[23]);
            //$url = Csv::csvStrip($line[6]);
        } 
        
        //only save contacts with at least one email or phone number
        if($name !== '' and ($email[0] !== '' or $phone_num[0] !== '')){
            $found = false;
            
            if($email[0] !== '') {
                $sql = 'SELECT * FROM '.TABLE_PREFIX.'contact WHERE email = "'. $db->escapeSql($email[0]).'" ';
                $contact = $db->readSqlRecord($sql);  
                if($contact != 0) $found = true;
            }
            
            /* turned off as multiple contacts may share one office number but will have different emails
            if(!$found and $phone_num[0]!=='') {
                $sql='SELECT * FROM '.TABLE_PREFIX.'contacts WHERE tel = "'. $db->escapeSql($phone_num[0]).'" OR '.
                         'cell = "'. $db->escapeSql($phone_num[0]).'" ';
                $contact= $db->readSqlRecord($sql);  
                if($contact!=0) $found=true;
            } 
            */
            
            if($error ==='') {
                $data['create_date'] = date('Y-m-d');
                $data['name'] = $given_name;
                $data['surname'] = trim($add_name.' '.$family_name);
                
                if($notes !== '') $data['notes'] = $notes."\r\n";
                
                if($email[0] !== '') $data['email'] = $email[0];
                if($email[1] !== '') $data['email_alt'] = $email[1];
                
                foreach($phone_type as $key => $value) {
                    if($value !== '') {
                        if(stripos($value,'mobile') !== false) {
                            if(!isset($data['cell'])) $data['cell'] = $phone_num[$key];
                        } else {
                            if(!isset($data['tel'])) $data['tel'] = $phone_num[$key];
                        }    
                    }  
                }   
                 
                if($address !== '') $data['address'] = $address;
                if($url !== '') $data['url'] = $url;
                
                if($found) {
                    $status = 'FOUND';
                    if($update) {
                        $where = array('contact_id'=>$contact['contact_id']);
                        
                        $contact_id = $db->updateRecord(TABLE_PREFIX.'contact',$data,$where,$error_tmp);
                        if($error_tmp !== '') {
                            $error .= 'Could NOT update Contact:'.$error_tmp; 
                        } else {
                            $status.= '_UPDATE'; 
                        } 
                    }  
                } else {  
                    $contact_id = $db->insertRecord(TABLE_PREFIX.'contact',$data,$error_tmp);
                    if($error_tmp !== '') {
                        $error .= 'Could NOT create Contact:'.$error_tmp; 
                    } else {
                        $status = 'NEW'; 
                    }  
                }  
            }   
        } else {
            $status = 'EMPTY'; 
        }  
        
        if($error !== '') return false; else return $status; 
    }
    
    
}


?>
