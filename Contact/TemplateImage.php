<?php 
namespace App\Contact;

use Seriti\Tools\Upload;

class TemplateImage extends Upload 
{
  //configure
    public function setup($param = []) 
    {
        $id_prefix = 'TMP'; 

        $param = ['row_name'=>'Image',
                  'pop_up'=>true,
                  'col_label'=>'file_name_orig',
                  'update_calling_page'=>true,
                  'prefix'=>$id_prefix];
        parent::setup($param);

        //limit to web viewable images
        $this->allow_ext = array('Images'=>array('jpg','jpeg','gif','png')); 

        $param = [];
        $param['table']     = TABLE_PREFIX.'template';
        $param['key']       = 'template_id';
        $param['label']     = 'name';
        $param['child_col'] = 'location_id';
        $param['child_prefix'] = $id_prefix ;
        $param['show_sql'] = 'SELECT CONCAT("Images for template: ",name) FROM '.TABLE_PREFIX.'template WHERE template_id = "{KEY_VAL}"';
        $this->setupMaster($param);

        $this->addAction(array('type'=>'edit','text'=>'edit details of','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R','icon_text'=>'delete'));

        $this->info['ADD'] = 'If you have Mozilla Firefox or Google Chrome you should be able to drag and drop files directly from your file explorer.'.
                             'Alternatively you can click [Add Images] button to select multiple images for upload using [Shift] or [Ctrl] keys. '.
                             'Finally you need to click [Upload selected images] button to upload images to server.';
        
        //NB: only need to add non-standard file cols here, or if you need to modify standard file col setup
        $this->addFileCol(array('id'=>'link_id','type'=>'STRING','title'=>'Link ID','upload'=>true,'hint'=>'Use simple text identifier for linking into template'));
    }
}
?>
