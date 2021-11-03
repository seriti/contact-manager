<?php 
namespace App\Contact;

use Seriti\Tools\Upload;

class MessageFile extends Upload 
{
  //configure
    public function setup($param = []) 
    {
        $id_prefix = 'MSG'; 

        $param = ['row_name'=>'Document',
                  'pop_up'=>true,
                  'col_label'=>'file_name_orig',
                  'update_calling_page'=>true,
                  'prefix'=>$file_prefix,//will prefix file_name if used, but file_id.ext is unique 
                  'upload_location'=>$id_prefix]; 
        parent::setup($param);

        $param = [];
        $param['table']     = TABLE_PREFIX.'message';
        $param['key']       = 'message_id';
        $param['label']     = 'subject';
        $param['child_col'] = 'location_id';
        $param['child_prefix'] = $id_prefix ;
        $param['show_sql'] = 'SELECT CONCAT("Attachments for message: ",`subject`) FROM `'.TABLE_PREFIX.'message` WHERE `message_id` = "{KEY_VAL}"';
        $this->setupMaster($param);

        $this->addAction(array('type'=>'edit','text'=>'edit details of','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R','icon_text'=>'delete'));

        $this->info['ADD'] = 'If you have Mozilla Firefox or Google Chrome you should be able to drag and drop files directly from your file explorer.'.
                             'Alternatively you can click [Add Documents] button to select multiple documents for upload using [Shift] or [Ctrl] keys. '.
                             'Finally you need to click [Upload selected Documents] button to upload documents to server.';

        //$access['read_only'] = true;                         
        //$this->modifyAccess($access); p
    }
}
?>
