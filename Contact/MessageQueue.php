<?php 
namespace App\Contact;

use Exception;
use Seriti\Tools\Queue;
use Seriti\Tools\Email;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Html;
use Seriti\Tools\Audit;
use Seriti\Tools\BASE_URL;

use App\Contact\Helpers;

class MessageQueue extends Queue 
{
    
    protected $mailer;
    protected $raw_subject;
    protected $raw_body;
    protected $personalise = false;
 
    public function setupMailer($message_id,&$error) {
        $error = '';

        //configure mailer with message details
        $this->mailer = Helpers::setupBulkMessageMailer($this->db,$this->container,$message_id,$error);
        if($this->mailer === false) {
            //NB error message must start with "ERROR" to stop Task batch processing  
            $error = 'ERROR constructing message mailer: '.$error;
        } else {
            //these may contain {XXX} substitution blocks. 
            $this->raw_subject = $this->mailer->getSetting('subject');
            $this->raw_body = $this->mailer->getSetting('body');
            if(strpos($this->raw_subject,'{') !== false  or strpos($this->raw_body,'{') !== false) {
                $this->personalise = true;
            }    

        }
        
        //prevent clogging of audit trail with queue item updates
        $this->db->disableAudit();

        if($error === '') return true; else return false;
    }

    public function closeMailer() {
        $this->mailer->closeBulkEmail();
        $this->db->enableAudit();
    }  

    public function processItem($id,$item = []) {
        $item_update = [];
        $error = '';
        $error_tmp = '';
        $message_str = '';
        $completed = false; 
        
        $email_type = $item['process_id'];
        $param = $item['process_data']; //message_id,contact_id
        
        $message_str .= str_replace('_',' ',$email_type).' ID['.$param['contact_id'].'] '.$param['name'].': ';

        $sql = 'SELECT name, surname, email, guid, status '.
               'FROM '.TABLE_PREFIX.'contact WHERE contact_id = "'.$param['contact_id'].'" ';
        $contact = $this->db->readSqlRecord($sql);
        
        if($contact == 0) {
            $error = 'Contact['.$param['name'].'] ID['.$param['contact_id'].'] no longer exists.';
        } else {
            if($contact['status'] === 'HIDE') $error .= 'Contact has status HIDE ';
        }
            
        if($error === '') {

            if($this->personalise) {
                $unsubscribe_url = BASE_URL.'contact?mode=unsubscribe&guid='.$contact['guid'];
                $unsubscribe_link = '<a href="'.$unsubscribe_url.'">Unsubcribe</a>';
                $search = ['{NAME}','{SURNAME}','{EMAIL}','{UNSUBSCRIBE_LINK}','{UNSUBSCRIBE_URL}'];
                $replace = [$contact['name'],$contact['surname'],$contact['email'],$unsubscribe_link,$unsubscribe_url];
                //subject should never contain links
                $subject = str_replace($search,$replace,$this->raw_subject);
                $body = str_replace($search,$replace,$this->raw_body);
            } else {
                //message subject and body allready set
                $subject = '';
                $body = '';    
            }
            
            $this->mailer->sendBulkEmail($contact['email'],$contact['name'],$subject,$body,$error_tmp);
            if($error_tmp === '') {
                $message_str .= 'SUCCESS! email sent.';
                $completed = true;
                $item_update['process_result'] = 'Email sent!';
            } else {
                $error .= $error_tmp;
            }
        } 

        //use default message subject and body
        if($error !== '') $message_str .= 'ERRORS: '.$error;
        $this->addMessage($message_str);
        
        if($completed) {
            $item_update['process_complete'] = true;
            $item_update['process_status'] = 'DONE';
        } else {  
            //NB: this will prevent automatic update of queue item if = false
            //return false;
            $item_update['process_status'] = 'ERROR';
        }

        return $item_update;  
    }

    
    
}
?>
