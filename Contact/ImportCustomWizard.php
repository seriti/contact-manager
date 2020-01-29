<?php 
namespace App\Contact;

use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Doc;
use Seriti\Tools\Email;
use Seriti\Tools\Calc;
use Seriti\Tools\Secure;
use Seriti\Tools\DbInterface;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;
use Seriti\Tools\ContainerHelpers;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_TEMP;

use App\Contact\Helpers;

use Psr\Container\ContainerInterface;

//NB: legacy code, does not use Wizard class
class ImportCustomWizard
{
    use IconsClassesLinks;
    use MessageHelpers;
    use ContainerHelpers;
   
    protected $container;
    protected $container_allow = ['user'];

    protected $db;
    protected $debug = false;

    protected $mode = '';
    protected $errors = array();
    protected $errors_found = false; 
    protected $messages = array();

    protected $user_id;

    //define whatever types of contact links you want to import
    protected $link_types = array('USER_ALL'=>'All system users','USER_ADMIN'=>'All ADMIN zone users','USER_XXX'=>'All XXX zone users','WHATEVER'=>'All WHATEVER table records with email');
    protected $upload_dir = BASE_UPLOAD.UPLOAD_TEMP;
    protected $max_size = 1000000;

    public function __construct(DbInterface $db, ContainerInterface $container) 
    {
        $this->db = $db;
        $this->container = $container;
               
        if(defined('\Seriti\Tools\DEBUG')) $this->debug = \Seriti\Tools\DEBUG;
    }

    public function process()
    {
        $html = '';
        $error = '';
        $count = 0;
        $update_contact = false;

        $this->mode = 'contacts';
        if(isset($_GET['mode'])) $this->mode = Secure::clean('basic',$_GET['mode']);

        if($this->mode === 'import') {

            $link_type = Secure::clean('basic',$_POST['link_type']);
            if(!array_key_exists($link_type,$this->link_types)) {
                $this->addError('Selected link type['.$link_type.'] INVALID!');
            }  

            //all imported contacts will be added to this group
            $link_group_id = Secure::clean('integer',$_POST['link_group_id']);
            if($link_group_id === 'NONE') $this->addError('You must specify which group to link contacts to.');
            
            if(isset($_POST['update_contact']) and $_POST['update_contact'] === 'YES') {
                $update_contact = true;
            } else {
                $update_contact = false;
            }  
            
            if($this->errors_found) {
                $this->mode = 'contacts'; 
            } else {
                $insert = 0;
                $found = 0;
                $update = 0;

                $import_param = [];
                $import_param['format'] = 'LINKED';
                $import_param['group_id'] = $link_group_id;

                //NB: you can have whatever logic you like here 
                if($link_type === 'WHATEVER') {
                    $sql = 'SELECT WHATEVER_id,name,email,cell,tel FROM WHATEVER '.
                           'WHERE TRIM(email) <> "" ';
                    $records = $this->db->readSqlArray($sql); 
                    if($records == 0) {
                        $this->addError('NO WHATEVER records found to import');
                    } else {
                        foreach($records as $record_id => $record) {
                            $contact = [];
                            $contact['link_type'] = 'WHATEVER';
                            $contact['link_id'] = $record_id;
                            $contact['name'] = $record['name'];
                            if(isset($record['cell'])) $contact['cell'] = $record['cell'];
                            if(isset($record['tel'])) $contact['tel'] = $record['tel'];

                            //emaployers can have multiple emails separated by ; or , or CR
                            $emails = Email::extractEmails($record['email']);
                            $contact['email'] = $emails[0];
                            if(isset($emails[1])) $contact['email_alt'] = $emails[1];

                            $import_result = Helpers::importContact($this->db,$update_contact,$contact,$import_param,$error);
                            if($error !== '') {
                                $this->addError($error);
                            } else {
                                if(substr($import_result,0,5) === 'FOUND') $found++;  
                                if($import_result === 'FOUND_UPDATE') $update++;
                                if($import_result === 'NEW') $insert++;
                            }
                        }
                    }
                }  
                
                //import system users from a user zone or all users.
                if(substr($link_type,0,4) === 'USER') {
                    $sql = 'SELECT user_id, name, email '.
                           'FROM user_admin WHERE status <> "HIDE" ';

                    if($link_type === 'USER_ADMIN') $sql .= 'AND zone = "ALL" OR zone = "ADMIN" ';       
                    if($link_type === 'USER_XXX') $sql .= 'AND zone = "XXX" '; 
                    
                    $users = $this->db->readSqlArray($sql); 
                    if($users == 0) {
                        $this->addError('NO users found to import');
                    } else {
                        foreach($users as $user_id => $user) {
                            $contact = [];
                            $contact['link_type'] = 'USER';
                            $contact['link_id'] = $user_id;
                            $contact['name'] = $user['name'];
                            $contact['email'] = $user['email'];

                            $import_result = Helpers::importContact($this->db,$update_contact,$contact,$import_param,$error);
                            if($error !== '') {
                                $this->addError($error);
                            } else {
                                if(substr($import_result,0,5) === 'FOUND') $found++;  
                                if($import_result === 'FOUND_UPDATE') $update++;
                                if($import_result === 'NEW') $insert++;
                            }

                        }

                    }
                }
                                
                $this->addMessage('Imported <strong>'.$insert.'</strong> NEW contacts.');
                $this->addMessage('Found <strong>'.$found.'</strong> Existing contacts(based on email address).');
                $this->addMessage('Updated <strong>'.$update.'</strong> contacts.');
            }  
        }

        if($this->mode === 'contacts') {
            $html .= '<div id="edit_div">'.
                     '<form method="post" id="import_csv_file" action="?mode=import" enctype="multipart/form-data">';
    
            $html .= '<div class="row">';
            $list_param = [];
            $list_param['class'] = 'form-control edit_input';     
            $html .= '<div class="'.$this->classes['col_label'].'">Select import link type:</div><div class="col-sm-6">'.
                     Form::arrayList($this->link_types,'link_type',$link_type,true,$list_param).
                     '</div>';
            $html .= '</div>';

            $html .= '<div class="row">';
            $link_param = [];
            $link_param['xtra'] = ['NONE'=>'Select group you wish to link contacts to.'];
            $link_param['class'] = 'form-control edit_input';  
            $sql = 'SELECT group_id,name FROM '.TABLE_PREFIX.'group ORDER BY name ';   
            $html .= '<div class="'.$this->classes['col_label'].'">Select import link group:</div><div class="col-sm-6">'.
                     Form::sqlList($sql,$this->db,'link_group_id',$link_group_id,$link_param).
                     '</div>';
            $html .= '</div>';
    
            $html .= '<div class="row">';
            $html .= '<div class="'.$this->classes['col_label'].'">Update contacts with same email?:</div><div class="col-sm-6">'.
                     Form::checkBox('update_contact','YES',$update_contact,'edit_input').
                     '</div>';     
            $html .= '</div>';
    
            $html .= '<div class="row">';
            $html .= '<div class="col-sm-6"><input type="submit" class="btn btn-primary" value="Import and link selected contact types"></div>';
            $html .= '</div>';
    
            $html .= '</form></div>';
        }

        $html = $this->viewMessages().$html;
            
        return $html;
    }
}
?>