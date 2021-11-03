<?php 
namespace App\Contact;

use App\Contact\Helpers;
use App\Contact\EMAIL_LIMIT_HOUR;

use Seriti\Tools\CURRENCY_ID;
use Seriti\Tools\Form;
use Seriti\Tools\Audit;
use Seriti\Tools\Task as SeritiTask;
use Seriti\Tools\TABLE_QUEUE;

class Task extends SeritiTask
{
    protected $limit_send = false;
    protected $batch_no = 10;
    //NB this is only displayed here, also needs to be set in Ajax Class
    protected $batch_send_no = 50;


    public function setup()
    {
        $this->addBlock('EMAIL',1,1,'Email tasks');

        //check within emails per hour limit
        if(defined('EMAIL_LIMIT_HOUR')) {
            $sql = 'SELECT COUNT(*) FROM `'.TABLE_QUEUE.'` '.
                   'WHERE `process_id` LIKE "CONTACT_MSG%" AND `process_complete` = 1  AND TIMESTAMPDIFF(MINUTE,`date_process`,NOW()) < 60 ';
            $emails_last_hour = $this->db->readSqlValue($sql);
            
            if($emails_last_hour >= EMAIL_LIMIT_HOUR) {
                $this->limit_send = true;
                $this->addMessage('You have exceeded your emails per hour limit('.EMAIL_LIMIT_HOUR.') you will have to wait until you can send more.'); 
            }    
        }
        

        $sql = 'SELECT `process_id`,COUNT(*) FROM `'.TABLE_QUEUE.'` '.
               'WHERE `process_id` LIKE "CONTACT_MSG%" AND `process_complete` = 0 AND `process_status` <> "ERROR" '.
               'GROUP BY `process_id`';
        $message_queue = $this->db->readSqlList($sql);
        if($message_queue != 0) {
            foreach($message_queue as $process_id=>$count) {
                
                $message_id = str_replace('CONTACT_MSG','',$process_id);
                $sql = 'SELECT `subject` FROM `'.TABLE_PREFIX.'message` WHERE `message_id` = "'.$message_id .'" ';
                $subject = $this->db->readSqlValue($sql);

                $task_id = 'PROCESS_MSG'.$message_id;
                $param = [];
                $param['ajax'] = true;
                $param['url'] = 'ajax?mode=EMAIL&message='.$message_id.'&send='.$this->batch_send_no;
                $param['flag_complete'] = 'DONE';
                $param['div_progress'] = 'div_ajax';
                $param['run_limit'] = $this->batch_no;
                $this->addTask('EMAIL',$task_id,'Process message: <b>'.$subject.'</b> queue(<b>'.$count.'</b> to process)',$param);


                $task_id = 'CLEAR_MSG'.$message_id;
                $this->addTask('EMAIL',$task_id,'Remove <b>'.$count.'</b> recipients for message: <b>'.$subject.'</b>');
            }
        } else {
            $this->addMessage('NO unprocessed message recpients found in queue.'); 
        }  
    }

    function processTask($id,$param = []) {
        $error = '';  
        $html = '';      
        
        if($id === 'EMAIL_QUEUE') {
            $location = 'queue';
            header('location: '.$location);
            exit;
        }

        if(substr($id,0,9) === 'CLEAR_MSG') {
            $message_id = str_replace('CLEAR_MSG','',$id);
            $process_id = 'CONTACT_MSG'.$message_id;
          
            if(!isset($param['process'])) $param['process'] = false;
                
            if($param['process']) {
                $sql = 'DELETE FROM `'.TABLE_QUEUE.'` WHERE `process_id` = "'.$this->db->escapeSql($process_id).'" '.
                       'AND `process_complete` = 0 AND `process_status` <> "ERROR" ';
                $reset_count = $this->db->executeSql($sql,$error);
                if($error === '') {   
                    $this->addMessage('EMAIL CLEAR: '.$reset_count.' emails in queue removed.');
                } else {
                    $this->addError('Could not clear email queue items:'.$error);
                }  
                          
                $audit_str = 'Contact tasks: '.$reset_count.' emails in queue removed';
                Audit::action($this->db,$this->user_id,$id,$audit_str);
            } else {
                $sql = 'SELECT `subject` FROM `'.TABLE_PREFIX.'message` WHERE `message_id` = "'.$message_id .'" ';
                $subject = $this->db->readSqlValue($sql);

                $html .= 'Please confirm that you wish to clear all unprocessed recipients for message: <b>'.$subject.'</b><br/>'.
                         '<form method="post" action="?mode=task&id='.$id.'" enctype="multipart/form-data">'.
                         ' <input type="hidden" name="process" value="1"><br/>'.
                         ' <input type="submit" name="submit" value="Clear queue" class="'.$this->classes['button'].'">'.
                         '</form>'; 
                      
                $this->addMessage($html);      
            }   
        }
        
        if(substr($id,0,11) === 'PROCESS_MSG') {
            if(!$this->limit_send) {
                $message_id = str_replace('PROCESS_MSG','',$id);
                $process_id = 'CONTACT_MSG'.$message_id;

                $sql = 'SELECT `subject` FROM `'.TABLE_PREFIX.'message` WHERE `message_id` = "'.$message_id .'" ';
                $subject = $this->db->readSqlValue($sql);

                $sql = 'SELECT COUNT(*) FROM `queue` WHERE `process_id` = "'.$this->db->escapeSql($process_id).'" '.
                       'AND `process_complete` = 0 AND `process_status` <> "ERROR" ';
                $count = $this->db->readSqlValue($sql);

                $html = 'MESSAGE QUEUE: Click <a id="link_email" href="javascript:server_task_setup()" onclick="link_download(\'link_email\')">'.
                        'HERE</a> to start processing message: <b>'.$subject.'</b> for <b>'.$count.'</b> recipients<br/>'.
                        '(This will process EMAIL messages in '.$this->batch_no.' batches of '.$this->batch_send_no.' Simply click link again to continue processing remaining EMAIL messages)';     
                $this->addMessage($html);
            }    
        }
        

           
    }
}
?>
                                                
