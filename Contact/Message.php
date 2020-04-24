<?php 
namespace App\Contact;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Html;
use Seriti\Tools\Audit;
use Seriti\Tools\Email;

use App\Contact\Helpers;

class Message extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Message','col_label'=>'subject'];
        parent::setup($param);

        $this->info['EDIT'] = 'You can use markdown and or raw html in message body field. '.
                              'The <a href="https://www.markdownguide.org/basic-syntax" target="_blank">markdown</a> interpreter is '.
                              '<a href="http://parsedown.org" target="_blank">Parsedown</a> and this allows you to simply create many '.
                              'standard html elements like headings,lists,bold,italic,underline and also more complex layouts like tables.'.
                              'After any changes you need to click [submit] button at bottom of form to save changes. ';
        
        //widens value column
        $this->classes['col_value'] = 'col-sm-9 col-lg-10 edit_value';
        
        $this->addTableCol(array('id'=>'message_id','type'=>'INTEGER','title'=>'Message ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'template_id','type'=>'INTEGER','title'=>'Using Template','join'=>'name FROM '.TABLE_PREFIX.'template WHERE template_id'));
        $this->addTableCol(array('id'=>'email_from','type'=>'EMAIL','title'=>'Email FROM address','new'=>MAIL_FROM));
        $this->addTableCol(array('id'=>'subject','type'=>'STRING','title'=>'Subject','hint'=>'This is email Subject text'));
        $this->addTableCol(array('id'=>'body_markdown','type'=>'TEXT','secure'=>false,'title'=>'Body','rows'=>20,
                                 'hint'=>'Uses <a href="http://parsedown.org/tests/" target="_blank">parsedown</a> extended <a href="https://www.markdownguide.org/basic-syntax" target="_blank">markdown</a> format, or raw html','list'=>false));
        //shows markdown as converted to html
        $this->addTableCol(array('id'=>'body_html','type'=>'TEXT','html'=>true,'secure'=>false,'title'=>'Body','edit'=>false));
        $this->addTableCol(array('id'=>'create_date','type'=>'DATE','title'=>'Created','edit'=>false));
        $this->addTableCol(array('id'=>'info','type'=>'TEXT','title'=>'Info','edit'=>false));

        $this->addSortOrder('message_id DESC','Most recent first','DEFAULT');

        $this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $this->addSelect('template_id','SELECT template_id,name FROM '.TABLE_PREFIX.'template ORDER BY name');

        $this->addSearch(array('message_id','subject','body_html','create_date','info'),array('rows'=>2));

        $this->setupFiles(array('table'=>TABLE_PREFIX.'file','location'=>'MSG','max_no'=>10,'title'=>'Attachments',
                                'icon'=>'<span class="glyphicon glyphicon-folder-open" aria-hidden="true"></span>&nbsp;&nbsp;manage',
                                'list'=>true,'list_no'=>5,'storage'=>STORAGE,
                                'link_url'=>'message_file','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));
        

        
    }
    
    protected function modifyRowValue($col_id,$data,&$value) {
        if($col_id === 'body_html') {
            $value = substr($value,0,500).'....';           
        }   
    } 

    protected function viewTableActions() 
    {
        $html = '';
        $actions = array();
        $actions['SELECT'] = 'Action for selected '.$this->row_name_plural;
        $action_email = '';
        $group_id = '';

        if(!$this->access['read_only']) {
            if($this->access['email']) {
                $actions['EMAIL'] = 'Email test message';
                if(isset($_POST['action_email'])) $action_email = Secure::clean('email',$_POST['action_email']);
            }
            
            $actions['QUEUE_ADD'] = 'Add group contacts to processing queue';
            $actions['UPDATE_INFO'] = 'Update message info';
        }  
        
        if(count($actions) > 0) {
            //select action list
            $param = array();
            $param['class'] = $this->classes['action'];
            $param['onchange'] = 'javascript:change_table_action()';
            $action_id = '';
            $html .= '<div id="action_div">';
          
            $html .= '<span style="padding:8px;"><input type="checkbox" id="checkbox_all"></span>'.
                     '<script type="text/javascript">'.
                     '$("#checkbox_all").click(function () {$(".checkbox_action").prop(\'checked\', $(this).prop(\'checked\'));});'.
                     '</script>';
          
            $html .= Form::arrayList($actions,'table_action',$action_id,true,$param);
                 
            $html .= '<script type="text/javascript">'.
                     'function change_table_action() {'.
                     'var table_action = document.getElementById(\'table_action\');'.
                     'var action_index = table_action.selectedIndex; '.
                     'var action_email_select = document.getElementById(\'action_email_select\');'.
                     'var group_select = document.getElementById(\'group_select\');'.
                     'action_email_select.style.display = \'none\'; '.
                     'group_select.style.display = \'none\'; '.
                     'if(table_action.options[action_index].value==\'EMAIL\') action_email_select.style.display = \'inline\'; '.
                     'if(table_action.options[action_index].value==\'QUEUE_ADD\') group_select.style.display = \'inline\'; '.
                     '}</script>';

            $param = array();
            $param['class'] = $this->classes['action'];
            $param['xtra'] = ['ALL'=>'All valid contacts'];
            $sql = 'SELECT group_id,name FROM '.TABLE_PREFIX.'group ORDER BY name';
            $html .= '<span id="group_select" style="display:none"> group&raquo;'.
                     Form::sqlList($sql,$this->db,'group_id',$group_id,$param).
                     '</span>';          

            unset($param['xtra']);        
            $html .= '<span id="action_email_select" style="display:none"> to&raquo;'.
                     Form::textInput('action_email',$action_email,$param).
                     '</span>&nbsp;'.
                     '<input type="submit" name="action_submit" value="Proceed" class="'.$this->classes['button'].'">';

            $html .= '</div>';
        } 
         
        return $html; 
    }

    protected function updateTable() 
    {
        $error_tmp = '';
        $html = '';
        $action_count = 0;
        $audit_str = '';
                
        $action = Secure::clean('basic',$_POST['table_action']);
        if($action === 'SELECT') {
           $this->addError('You have not selected any action to perform on '.$this->row_name_plural.'!');
        } else {
            if($action === 'EMAIL') {
                $action_email = $_POST['action_email'];
                Validate::email('Action email',$action_email,$error_tmp);
                if($error_tmp != '') $this->addError('Invalid action email!');
                $audit_str .= 'Email test messages to '.$action_email.' :';
            }
            if($action === 'QUEUE_ADD') {
                $group_id = Secure::clean('integer',$_POST['group_id']);
                $audit_str .= 'Queue messages to contact group['.$group_id.'] :';
            }  
        }
        
            
        if(!$this->errors_found) {
            foreach($_POST as $key => $value) {
                if(substr($key,0,8) === 'checked_') {
                    $action_count++;
                    $message_id = Secure::clean('basic',substr($key,8));
                    $audit_str .= 'Message ID['.$message_id.'] ';

                    if($action === 'EMAIL') {
                        $mailer = $this->getContainer('mail');
                        Helpers::SendMessage($this->db,$this->container,$message_id,$action_email,$error_tmp);
                        $success_str = 'Successfully sent message ID['.$message_id.'] to '.$action_email;
                    }    
                    if($action === 'QUEUE_ADD') {
                        $success_str = Helpers::addMessageQueue($this->db,$this->container,$message_id,$group_id,$error_tmp);
                        Helpers::updateMessageInfo($this->db,$message_id);
                    }    
                    if($action === 'UPDATE_INFO') {
                        Helpers::updateMessageInfo($this->db,$message_id);
                        $success_str = 'Updated message ID['.$message_id.'] Info';
                    }    
                    
                    if($error_tmp !== '') {
                       $this->addError('Error with message ID['.$message_id.']:'.$error_tmp);
                    } else {
                       $this->addMessage($success_str);
                    }   
                }   
            }
        } 
        
        if($action_count == 0) $this->addError('NO messages selected for action!');
        
        if(!$this->errors_found) {
            $audit_action = $action.'_'.strtoupper($this->table);   
            Audit::action($this->db,$this->user_id,$audit_action,$audit_str);
        }  
        
        $this->mode = 'list';
        $html .= $this->viewTable();
            
        return $html;
    }

    protected function beforeUpdate($id,$edit_type,&$form,&$error_str) 
    {
        $from = explode('@',$form['email_from']);

        $found = false;
        $restrict_emails = Email::extractEmails(EMAIL_FROM_RESTRICT);
        $restrict_domains = '';
        foreach($restrict_emails as $address) {
            $restrict = explode('@',$address);
            $restrict_domains .= '@'.$restrict[1].' ';
            if(stripos($restrict[1],$from[1]) !== false) $found = true;
        }

        if(!$found) {
            $error_str .= 'From email address domain['.$from[1].'] must have one of following domains: "'.$restrict_domains.'"" ';
        }
        

    }


    protected function afterUpdate($id,$edit_type,$form) 
    {
        //converts page markdown into html and save 
        $text = $form['body_markdown'];
        if($text !== '') {
            $html = Html::markdownToHtml($text);      
            $sql = 'UPDATE '.TABLE_PREFIX.'message SET body_html = "'.$this->db->escapeSql($html).'" '.
                   'WHERE message_id = "'.$this->db->escapeSql($id).'"';
            $this->db->executeSql($sql,$error_tmp);
        }  

        if($edit_type === 'INSERT') {
            $sql = 'UPDATE '.TABLE_PREFIX.'message SET create_date = "'.date('Y-m-d').'", info = "Message not queued yet!" '.
                   'WHERE message_id = "'.$this->db->escapeSql($id).'"';
            $this->db->executeSql($sql,$error_tmp);
        }
    }  
    
}
