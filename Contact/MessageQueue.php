<?php 
namespace App\Contact;

use Exception;
use Seriti\Tools\Queue;
use Seriti\Tools\Email;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Html;
use Seriti\Tools\Audit;

use App\Contact\Helpers;

class MessageQueue extends Queue 
{
    
    protected $mailer;
 
    public function setupMailer($message_id,&$error) {
        $error = '';

        //configure mailer
        $this->mailer = Helpers::setupBulkMessageMailer($this->db,$this->container,$message_id,$error);
        if($this->mailer === false) {
            //NB error message must start with "ERROR" to stop Task batch processing  
            $error = 'ERROR constructing message mailer: '.$error;
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
        $error_tmp = '';
        $message_str = '';
        $completed = false; 
        
        $email_type = $item['process_id'];
        $param = $item['process_data']; //message_id,contact_id
        
        $message_str .= str_replace('_',' ',$email_type).' ID['.$param['contact_id'].'] '.$param['name'].': ';

        $sql = 'SELECT name,email FROM '.TABLE_PREFIX.'contact WHERE contact_id = "'.$param['contact_id'].'" ';
        $contact = $this->db->readSqlRecord($sql);
        //use default message subject and body
        //OR customise to recipient
        $subject = '';
        $body = '';
        $this->mailer->sendBulkEmail($contact['email'],$contact['name'],$subject,$body,$error_tmp);
        if($error_tmp === '') {
            $message_str .= 'SUCCESS! email sent.';
            $completed = true;
            $item_update['process_result'] = 'Email sent!';
        } else {
            $message_str .= 'ERRORS: '.$error_tmp;
        }  
                
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
