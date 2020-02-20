<?php 
namespace App\Contact;

use Exception;
use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\Html;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Audit;

class Contact extends Table 
{
    function afterUpdate($id,$edit_type,$form) {
        $error = '';
        if($edit_type === 'INSERT') {
            $sql='UPDATE '.TABLE_PREFIX.'contact SET create_date = "'.date('Y-m-d').'", guid = UUID() '.
                 'WHERE contact_id = "'.$this->db->escapeSql($id).'"';
            $this->db->executeSql($sql,$error);
            if($error !== '') {
                throw new Exception('CONTACT_INSERT_ERROR: could not assign create date and guid values to new contact');
            }
        }
    }  

    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Contact','col_label'=>'name','distinct'=>true];
        parent::setup($param);

        $this->info['EDIT']='Enter any additional contact data into notes text field. All fields are searchable.'.
                            'Finally you need to click [Submit] button at bottom of page to save contact data to server.';                         


        $this->addTableCol(array('id'=>'contact_id','type'=>'INTEGER','title'=>'ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'surname','type'=>'STRING','title'=>'Surname','required'=>false));
        $this->addTableCol(array('id'=>'email','type'=>'EMAIL','title'=>'Email'));
        $this->addTableCol(array('id'=>'email_alt','type'=>'EMAIL','title'=>'Alternative email','required'=>false));
        $this->addTableCol(array('id'=>'tel','type'=>'STRING','title'=>'Landline','required'=>false));
        $this->addTableCol(array('id'=>'cell','type'=>'STRING','title'=>'Mobile','required'=>false));
        $this->addTableCol(array('id'=>'url','type'=>'URL','title'=>'URL','size'=>24,'required'=>false,'list'=>false));
        $this->addTableCol(array('id'=>'address','type'=>'TEXT','title'=>'Address','required'=>false,'list'=>false));
        $this->addTableCol(array('id'=>'notes','type'=>'TEXT','title'=>'Notes','required'=>false,'list'=>false));
        $this->addTableCol(array('id'=>'create_date','type'=>'DATE','title'=>'Create date','edit'=>false,'list'=>false));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','new'=>'OK','hint'=>'Set to HIDE to remove from future communications'));


        //this slows things down quite a bit so disable if you dont need it, and searchXtra() below
        $this->addSql('JOIN','LEFT JOIN '.TABLE_PREFIX.'group_link AS G ON(T.contact_id = G.contact_id)');

        $this->addSortOrder('create_date DESC,surname,name ','Create date, Surname, Name','DEFAULT');

        $this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        //$this->addAction(array('type'=>'view','text'=>'view','icon_text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $this->addSearch(array('name','surname','email','email_alt','tel','cell','url','address','notes','status'),array('rows'=>3));
        $this->addSearchXtra('G.group_id','Message Group');

        $this->addSelect('status','(SELECT "OK") UNION ALL (SELECT "HIDE")');
        $this->addSelect('G_group_id','SELECT group_id,name FROM '.TABLE_PREFIX.'group ORDER BY name');

    } 

    //removes contact from any groups
    protected function afterDelete($id) {
        $error = '';
        $sql = 'DELETE FROM '.TABLE_PREFIX.'group_link '.
               'WHERE contact_id = "'.$this->db->escapeSql($id).'" ';
        $this->db->executeSql($sql,$error);

    } 

    //overwrite Table class function so can add contacts to a group
    protected function viewTableActions() 
    {
        $html = '';
        $actions = array();
        $actions['SELECT'] = 'Action for selected '.$this->row_name_plural;
        $action_email = '';

        if(!$this->access['read_only']) {
            if($this->access['edit']) {
                $actions['ADD_GROUP'] = 'Add '.$this->row_name_plural.' to a group';
            }
            if($this->access['delete']) $actions['DELETE']='Delete selected '.$this->row_name_plural;
            if($this->access['email']) {
                $actions['EMAIL'] = 'Email selected '.$this->row_name_plural;
                if(isset($_POST['action_email'])) $action_email = Secure::clean('email',$_POST['action_email']);
            }
               
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
                     'if(table_action.options[action_index].value==\'ADD_GROUP\') group_select.style.display = \'inline\'; '.
                       '}</script>';

            $param = array();
            $param['class'] = $this->classes['action'];
            $sql = 'SELECT group_id,name FROM '.TABLE_PREFIX.'group ORDER BY name';
            $html .= '<span id="group_select" style="display:none"> group&raquo;'.
                     Form::sqlList($sql,$this->db,'group_id',$group_id,$param).
                     '</span>';    

            $html .= '<span id="action_email_select" style="display:none"> to&raquo;'.
                     Form::textInput('action_email',$action_email,$param).
                     '</span>&nbsp;'.
                     '<input type="submit" name="action_submit" value="Proceed" class="'.$this->classes['button'].'">';

            $html .= '</div>';
        } 
         
        return $html; 
    }  

    protected function updateTable() {
        $error_tmp = '';
        $html = '';
        $action_count = 0;
        $audit_str = '';
                
        $action = Secure::clean('basic',$_POST['table_action']);
        if($action === 'SELECT') {
           $this->addError('You have not selected any action to perform on '.$this->row_name_plural.'!');
        } else {
            if($action === 'ADD_GROUP') {
                $group_id = Secure::clean('integer',$_POST['group_id']);
                $audit_str .= 'Add '.$this->row_name_plural.' to group ID['.$group_id.'] :';
            }
            if($action === 'EMAIL') {
                $action_email = $_POST['action_email'];
                Validate::email('Action email',$action_email,$error_tmp);
                if($error_tmp != '') $this->addError('Invalid action email!');
                $audit_str .= 'Email '.$this->table.' '.$this->row_name_plural.' to '.$action_email.' :';
            }
            if($action === 'DELETE') {
                 $audit_str .= 'Delete '.$this->table.' '.$this->row_name_plural.' :';
            }  
        }
        
            
        if(!$this->errors_found) {
            $email_table = [];
            foreach($_POST as $key => $value) {
                if(substr($key,0,8) === 'checked_') {
                    $action_count++;
                    $key_id = Secure::clean('basic',substr($key,8));
                    $record = $this->view($key_id);
                    if($record == 0) {
                        $this->addError($this->row_name.' ID['.$key_id.'] no longer exists!');
                    } else {
                        $label = $record['name'].' '.$record['surname'].'('.$record['email'].')';
                        $audit_str .= $this->row_name.' ID['.$key_id.'] ';
                        
                        if($action === 'DELETE') {
                            $response = $this->delete($key_id);
                            if($response['status'] === 'OK') {
                                $this->addMessage('Successfully deleted '.$this->row_name.' ID['.$key_id.'] '.$label);
                            }  
                        } 

                        if($action === 'ADD_GROUP') {
                            $info = Helpers::addContactToGroup($this->db,$key_id,$group_id,$error_tmp);
                            if( $error_tmp !== '') {
                                $this->addError('Could not add '.$this->row_name.' ID['.$key_id.'] '.$label.': '.$error_tmp);
                            } else {
                                if($info === 'EXISTS') $this->addMessage($this->row_name.' '.$label.' already in Group ID['.$group_id.']') ;
                                if($info === 'ADDED') $this->addMessage($this->row_name.' '.$label.' ADDED to Group ID['.$group_id.']') ;
                            }  
                        }  

                        if($action === 'EMAIL') $email_table[] = $this->view($key_id);
                    }
                }   
            }
        } 
        
        if($action_count == 0) $this->addError('NO '.$this->row_name_plural.' selected for action!');
                        
        if(!$this->errors_found and $action === 'EMAIL') {
            $param = ['format'=>'html'];
            $from = ''; //default will be used
            $to = $action_email;
            $subject = SITE_NAME.' '.$this->row_name_plural;
            $body = '<h1>Please see '.$this->row_name.' data below:</h1>'.
                    Html::arrayDumpHtml($email_table);

            $mailer = $this->getContainer('mail');
            if($mailer->sendEmail($from,$to,$subject,$body,$error_tmp,$param)) {
                $this->addMessage('SUCCESS sending data to['.$to.']'); 
            } else {
                $this->addError('FAILURE emailing data to['.$to.']:'.$error_tmp); 
            }
        }  
        
        if(!$this->errors_found) {
            $this->afterUpdateTable($action); 

            $audit_action = $action.'_'.strtoupper($this->table);   
            Audit::action($this->db,$this->user_id,$audit_action,$audit_str);
        }  
        
        $this->mode = 'list';
        $html .= $this->viewTable();
            
        return $html;
    } 
}
?>
