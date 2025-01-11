<?php
/*
WordPress免登录发布接口,支持最新Wordpress4.9.4
版本号: T5
适用于火车头采集器7.6-9.6

更新说明
T5: 2018.10.28
1、增加了分类和标签的slug中文转拼音功能，可以在配置参数中打开
T4: 2018.09.25
1、增强了和各种插件的兼容性
T3: 2017.11.03
1、优化了验证标题重复
2、优化了附件、图片、缩略图的上传和生成
T2: 2017.10.23
1、增加了对多个分类方式的发布参数(post_taxonomy_list),使用方法请参考功能特性
2、修复BUG： 分类名称如果含有数字会导致分类错误
T1：2017.10.12
1、旧版发布接口重新设计,新版本号为T1,以后不再对旧版升级维护.旧版本支持3.X-4.8.2
2、修复BUG：当规则中没有发布模块中某个参数时会导致发布数据异常(会显示db:标签名)
3、优化strtoarray函数

*/
//-------------------配置参数开始,根据需要修改-------------------------
$post_author_default    	= 1;			  	//默认作者的id,默认为admin（这里是作者ID号码,并非作者名）
$post_status    		    = 'publish';	    //publish 立即发布 pending 审核  draft 草稿箱
$time_interval  	    	= 1;				//发布时间间隔,单位为秒 。可设置随机数值表达式,如12345 * rand(0,17),设置为负数可将发布时间设置为当前时间减去这里设置的时间
$post_next      	    	= 'now';		    //now:发布时间=当前时间+间隔时间值 //next: 发布时间=最后一篇时间+间隔时间值										           	
$post_ping      	    	= false;		    //发布后是否执行ping
$translate_slug 	    	= false;		    //是否将中文标题转换为MD5值,如需开启请设置为true或MD5值长度,建议设置为大于10,小于33的数字。
$secretWord     	    	= 'ZVP6VtL4MEswjpr';//接口密码,如果不需要密码,则设为$secretWord=false ;	
$checkTitle         		= false; 		    //检测标题是否重复
$postformat 		      	= false;		    //开启文章形式
$usePinyin              	= false;         	//开启分类和标签slug的拼音转换
//-------------------配置参数结束,以下请勿修改-------------------------

//开始
if(isset($_GET['action'])){
  $hm_action=$_GET['action'];
}else{
  die("操作被禁止>");
}

include_once "./wp-config.php";
if($post_ping) require_once("./wp-includes/comment.php");
extract($_POST);
if($hm_action== "list"){
    hm_print_catogary_list();
}elseif($hm_action== "shop"){
    hm_print_catogary_list_shop();
}
elseif($hm_action== "update"){
  hm_publish_pending_post();
}elseif($hm_action == "save"){
  //检查通讯密码
  if (isset($secretWord)&&($secretWord!=false)) {
    if (!isset($_GET['secret']) || $_GET['secret'] != $secretWord) {
      die('接口密码错误,请修改配置文件或者修改发布参数,保持两者统一。');
    }
  }


  //判断标题是否为空
  if ($post_title=='[标题]'||$post_title=='') {die('标题为空');}
  //检查标题是否重复
  if($checkTitle){
	  $post_title = trim(hm_strip_slashes($post_title));
    $sql = "SELECT `ID` FROM $wpdb->posts WHERE `post_title` = '$post_title'";
    $t_row = $wpdb->query($sql);
    // echo $t_row;
    if($t_row) {die('标题重复,发布成功');};
  }
  //判断标题是否为空
  if ($post_content=='[内容]'||$post_content=='') {die('内容为空');}
  //检查自定义文章类型
  update_post_meta($post_id,'post_type','single');
  if (empty($post_type) || strpos($post_type, '[') || strpos($post_type, ']')) {$post_type='post';}
  //检查自定义分类目录
  if (empty($post_taxonomy) || strpos($post_taxonomy, '[') || strpos($post_taxonomy, ']')) {$post_taxonomy='category';}
  //检查分类描述是否未设置
  if (empty($category_description) || strpos($category_description, '[') || strpos($category_description, ']')) {$category_description='';}
  //检查发布时间
  if (!isset($post_date) ||strlen($post_date)<8) $post_date=false;
  //检查作者
  if (empty($post_author)) {
    $post_author=$post_author_default;
  } else {
    $post_author=hm_add_author($post_author);
  }
  //标签转化为拼音
  if($usePinyin){
    $post_taxonomy_list['post_tag'] = $tag;
    $tag = '';
  }
 //贴子类型操作post_parent 
$wzlxtz = $_POST["wzlx"];
if($wzlxtz == 'normal'){
	$post_parent = '999999999';
	} else {$post_parent = '0';}

  $post_content = fileHandle('fujian',$post_content);
  fileHandle('thumb');



  hm_do_save_post(array('post_title'=>$post_title,
                        'post_content'=>$post_content,
                        'post_category'=>$post_category,
                        'post_excerpt'=>$post_excerpt,
                        'post_type'=>$post_type,
                        'post_taxonomy'=>$post_taxonomy,
                        'tags_input'=>$tag,
                        'post_date'=>$post_date,
                        'post_author'=>$post_author,
	                    'post_parent'=>$post_parent,
                        'fujianid'=>$fujianid));
  echo '发布成功';
}else{
  echo '非法操作['.$hm_action.']';
}

  //附件处理
  //$filename 附件名称
  //$content  标签内容，为空返回首张图片ID
  function fileHandle($filesnames, $content = null)
  {
      global $thumbid;
      if (!empty($_FILES[$filesnames.'0']['name'])) {
          require_once('./wp-load.php');
          require_once('./wp-admin/includes/file.php');
          require_once('./wp-admin/includes/image.php');
          $i = 0;
          while (isset($_FILES[$filesnames.$i])) {
              $fujian[$i] = $_FILES[$filesnames.$i];
              $filename = $fujian[$i]['name'];
              $fileExt=array_pop(explode(".", $filename));
              //附件保存格式【时间】
              $upFileTime=date("YmdHis");
              //更改上传文件的文件名为时间+随机数+后缀
              $fujian[$i]['name'] = $upFileTime."-".uniqid().".".$fileExt;
              $uploaded_file = wp_handle_upload($fujian[$i], array('test_form' => false));
              $content = str_replace("\'".$filename."\'", "\"".$uploaded_file[url]."\"", $content);
              $content = str_replace($filename, $uploaded_file[url], $content);
              if (isset($uploaded_file['error'])) {
                  echo "文件上传失败";
                  wp_die($uploaded_file['error']);
              }
              $file = $uploaded_file['file'];
              $new_file = iconv('GBK', 'UTF-8', $file);
              $url = iconv('GBK', 'UTF-8', $uploaded_file['url']);
              $type = $uploaded_file['type'];
              $attachment = array(
                  'guid' => $url,
                  'post_mime_type' => $type,
                  'post_title' => $filename,
                  'post_content' => '',
                  'post_status' => 'inherit'
                  );
              $attach_id = wp_insert_attachment($attachment, $new_file);
              if (strpos($fujian[$i]['type'], 'image') !== false) {
                  if(empty($thumbid) || $filesnames == 'thumb') $thumbid = $attach_id;
                  $attach_data = wp_generate_attachment_metadata($attach_id, $file);
                  $attach_data['file'] = iconv('GBK', 'UTF-8', $attach_data['file']);
                  foreach ($attach_data['sizes'] as $key => $sizes) {
                      $sizes['file'] = iconv('GBK', 'UTF-8', $sizes['file']);
                      $attach_data['sizes'][$key]['file'] = $sizes['file'];
                  }
              wp_update_attachment_metadata($attach_id, $attach_data);
              }
              $i++;
          }
      }
      return $content;
  }

function hm_tranlate($text)
{
global $translate_slug;
$pattern = '/[^\x00-\x80]/';
if (preg_match($pattern,$text)) {
  $htmlret = substr(md5($text),0,$translate_slug);
} else {
  $htmlret =  $text;
}
return $htmlret;
}

function hm_print_catogary_list()
{
$cats = get_categories("hierarchical=0&hide_empty=0");
foreach ((array) $cats as $cat) {
  echo '<<<'.$cat->cat_ID.'--'.$cat->cat_name.'>>>';
}
}
function hm_print_catogary_list_shop()
{
$cats = get_categories("hierarchical=0&hide_empty=0&taxonomy=shoptype&post_type=shop");
foreach ((array) $cats as $cat) {
  echo '<<<'.$cat->cat_ID.'--'.$cat->cat_name.'>>>';
}
}
function hm_get_post_time($post_next="normal")
{
  global $time_interval;
  global $wpdb;

  $time_difference = absint(get_option('gmt_offset')) * 3600;
  $tm_now = time()+$time_difference;

  if ($post_next=='now') {
    $tm=time()+$time_difference;
  } else { //if ($post_next=='next')
    $tm = time()+$time_difference;
    $posts = $wpdb->get_results( "SELECT post_date FROM $wpdb->posts ORDER BY post_date DESC limit 0,1" );
    foreach ( $posts as $post ) {
      $tm=strtotime($post->post_date);
    }
  }
  return $tm+$time_interval;
}

function hm_publish_pending_post()
{
global $wpdb;
$tm_now = time()+absint(get_option('gmt_offset')) * 3600;
$now_date=date("Y-m-d H:i:s",$tm_now);
$wpdb->get_results( "UPDATE $wpdb->posts set `post_status`='publish' WHERE `post_status`='pending' and `post_date`<'$now_date'" );
}

function hm_add_category($post_category, $post_taxonomy = 'category')
{
    if (!function_exists('wp_insert_category')) {include_once "./wp-admin/includes/taxonomy.php";}
    global $wpdb,$post_cate_meta,$post_parent_cate,$category_description,$usePinyin;
    $post_category_new=array();
    $post_category_list= array_unique(explode(",", $post_category));
    foreach ($post_category_list as $category) {
      $cat_ID =$category;
      if (!isInteger($cat_ID) || $cat_ID < 1) {
            $category = $wpdb->escape($category);
            if($usePinyin){
              require_once(__DIR__."/vendor/autoload.php");
              $pinyin = new \Overtrue\Pinyin\Pinyin();
              $categorySlug = $pinyin->permalink($category, '',PINYIN_UMLAUT_V);
            }
            $term = get_term_by('name',$category,$post_taxonomy,'ARRAY_A');
            $cat_ID = $term['term_id'];
            if($cat_ID == 0){
                //检查父分类是否存在和创建父分类->start
                if(!empty($post_parent_cate) && $post_parent_cate != '[父分类]')
                {
                    $parent = intval($post_parent_cate);
                    if($parent == 0){
                        $post_parent_cate = $wpdb->escape($post_parent_cate);
                        $term = get_term_by('name',$post_parent_cate,$post_taxonomy,'ARRAY_A');
                        $cat_ID = $term['term_id'];
                        if($parent == 0){
                            if($usePinyin){
                              $parent_categorySlug = $pinyin->permalink($post_parent_cate, '',PINYIN_UMLAUT_V);
                            }
                            if($usePinyin && !empty($post_parent_cate)){
                              $parent = wp_insert_category(array('cat_name'=>$post_parent_cate, 'category_nicename'=>$parent_categorySlug, 'taxonomy'=>$post_taxonomy));
                            }else{
                              $parent = wp_insert_category(array('cat_name'=>$post_parent_cate, 'taxonomy'=>$post_taxonomy));
                            }
                           
                        }
                    }
                    if($usePinyin && !empty($categorySlug)){
                      $cat_ID = wp_insert_category(array('cat_name'=>$category, 'category_nicename'=>$categorySlug, 'category_description'=>$category_description, 'category_parent'=>$parent, 'taxonomy'=>$post_taxonomy));
                    }else{
                      $cat_ID = wp_insert_category(array('cat_name'=>$category, 'category_description'=>$category_description, 'category_parent'=>$parent, 'taxonomy'=>$post_taxonomy));
                    }
                   
                    
                }else{
                  if($usePinyin && !empty($categorySlug)){
                    $cat_ID = wp_insert_category(array('cat_name'=>$category, 'category_nicename'=>$categorySlug, 'category_description'=>$category_description, 'taxonomy'=>$post_taxonomy));
                  }else{
                    $cat_ID = wp_insert_category(array('cat_name'=>$category, 'category_description'=>$category_description, 'taxonomy'=>$post_taxonomy));
                  }
                }
                //检查父分类是否存在和创建父分类->end
                 //定义分类信息->start
                 if (!empty($post_cate_meta)) {
                    foreach(array_unique(array_filter($post_cate_meta)) as $key => $value) {
                        $value = strtoarray($value);
                        add_term_meta($cat_ID, $key, $value);
                    }
                  }
                //定义分类信息->end
            }
        }
        array_push($post_category_new, $cat_ID);
    }
    return $post_category_new;
}

function add_category($post_category, $post_taxonomy = 'category')
{
    if (!function_exists('wp_insert_category')) {include_once "./wp-admin/includes/taxonomy.php";}
    global $wpdb,$usePinyin;
    if($usePinyin){
      require_once(__DIR__."/vendor/autoload.php");
      $pinyin = new \Overtrue\Pinyin\Pinyin();
    }
    $post_category_new=array();
    $post_category_list= array_unique(explode(",", $post_category));
    foreach ($post_category_list as $category) {
        $cat_ID =$category;
        if (!isInteger($cat_ID) || $cat_ID < 1) {
            $category = $wpdb->escape($category);
            $term = get_term_by('name',$category,$post_taxonomy,'ARRAY_A');
            $cat_ID = $term['term_id'];
            if($cat_ID == 0){
              if($usePinyin){
                $categorySlug = $pinyin->permalink($category, '',PINYIN_UMLAUT_V);
              }
              if($usePinyin && !empty($categorySlug)){
                $cat_ID = wp_insert_category(array('cat_name'=>$category, 'category_nicename'=>$categorySlug, 'taxonomy'=>$post_taxonomy));
              }else{
                $cat_ID = wp_insert_category(array('cat_name'=>$category, 'taxonomy'=>$post_taxonomy));
              }
            }
        }
        array_push($post_category_new, $cat_ID);
    }
    return $post_category_new;
}

function isInteger($value){
  return is_numeric($value) && is_int($value+0);
}

function hm_add_author($post_author)
{
global $wpdb,$post_author_default;
$User_ID =intval($post_author);
if ($User_ID == 0) {
  $pattern = '/[^\x00-\x80]/';
  if (preg_match($pattern,$post_author)) {
    $LoginName = substr(md5($post_author),0,10);
  } else {
    $LoginName =  $post_author;
  }
  $User_ID = $wpdb->get_col("SELECT ID FROM $wpdb->users WHERE user_login = '$LoginName' ORDER BY ID");
  $User_ID = $User_ID[0];
  if (empty($User_ID)) {
    $website = 'http://'.$_SERVER['HTTP_HOST'];
    $userdata = array(
                  'user_login'  =>  "$LoginName",
                  'first_name'	=>	$post_author,
                  'user_nicename'    =>  $post_author,
                  'display_name'    =>  $post_author,
                  'nickname'    =>  $post_author,
                  'user_url'    =>  $website,
                  'role'    =>  'contributor',
                  'user_pass'   =>  NULL);
    $User_ID = wp_insert_user( $userdata );
  }
  $post_author = $User_ID;
} else {
  $post_author = $post_author_default;
}
return $post_author;
}

function hm_strip_slashes($str)
{
  if (get_magic_quotes_gpc()) {
    return stripslashes($str);
  } else {
    return $str;
  }
}
function checkDatetime($str){
  $date = strtotime($str);
  if($date > 31500000){
    return true;
  }else{
    return false;
  }
}

function formatdate($date){
  $d = date('Y-m-d');
  if(strpos($date, 'today') !== false){
    return str_replace('today at', $d, $date);
  }

  if(strpos($date, 'Today') !== false){
    return str_replace('Today at', $d, $date);
  }

  $dd = date('Y-m-d', time()-84600);
  if(strpos($date, 'yesterday') !== false){
    return str_replace('yesterday at', $d, $date);
  }

  if(strpos($date, 'Yesterday') !== false){
    return str_replace('yesterday at', $d, $date);
  }
}

  //字符串转换为数组
  //字符串的格式必须为   //$str = 'eo_description$$seo_description|||seo_keywords$$seo_keywords|||seo_title$$seo_title';

  function strtoarray($str){
    if(strpos($str, '|||') !== false){
        $str = explode('|||', $str);
        if(strpos($str[0],'$$') !== false){
          foreach($str as $k => $v){
            $v = explode('$$', $v);
            $r[$v[0]] = $v[1];
          }
          $str = $r;
        }
    }
    return $str;
  }



function hm_do_save_post($post_detail)
{
  global $post,$post_author,$post_ping,$post_status,$translate_slug,$post_next,$post_meta,$post_parent,$comment,$commentdate,$commentauthor,$wpdb,$postformat,$post_format,$post_taxonomy_list,$thumbid;
  extract($post_detail);
  $post_title=trim(hm_strip_slashes($post_title));
  $post_name=$post_title;
  if ($translate_slug) $post_name=hm_tranlate($post_name);
  $post_name=sanitize_title( $post_name);
  if ( strlen($post_name) < 2 ) $post_name="";
  $post_content=hm_strip_slashes($post_content);
  $tags_input=str_replace("|||",",",$tags_input);
  if (isset($post_date) && $post_date && checkDatetime($post_date)) {
    $tm=strtotime($post_date);
    $time_difference =  absint(get_option('gmt_offset')) * 3600;
    $post_date=date("Y-m-d H:i:s",$tm);
    $post_date_gmt = gmdate('Y-m-d H:i:s', $tm-$time_difference);
  } else {
    $tm=hm_get_post_time($post_next);
    $time_difference = absint(get_option('gmt_offset')) * 3600;
    $post_date=date("Y-m-d H:i:s",$tm);
    $post_date_gmt = gmdate('Y-m-d H:i:s', $tm-$time_difference);
    if ($post_status=='next') $post_status='publish';
  }
  $post_category=hm_add_category($post_category, $post_taxonomy);
  $post_data = compact('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_type', 'post_title', 'post_category', 'post_status', 'post_excerpt', 'post_name','tags_input','post_parent');
  //$post_data = add_magic_quotes($post_data);转义字符
  $postID = wp_insert_post($post_data);
//发布类型(words=动态;single=文章;normal=贴子)
$wzlxcs = $_POST["wzlx"];
update_post_meta($postID,'post_type',$wzlxcs);
//如果是贴子类型

if($wzlxcs == 'normal'){
	update_post_meta($postID,'bbs_floor','1');
	update_post_meta($postID,'last_comment_time',strtotime($post_date));
	echo '要发布为贴子,时间：'.$post_date;
	}

  //设定缩略图
  set_post_thumbnail( $postID, $thumbid );

  //自定义分类方式(taxonomy)
  if($post_taxonomy != 'category' && !empty($post_taxonomy)){
      wp_set_object_terms($postID, $post_category, $post_taxonomy);
  }

  //多个自定义分类方式(taxonomy)
  if(!empty($post_taxonomy_list)){
    foreach($post_taxonomy_list as $k => $v){
      $v = strtoarray($v);
      if(is_array($v)){
        foreach($v as $kk => $vv){
          $vv = add_category($vv, $k);
          wp_set_object_terms($postID, $vv, $k);
        }
      }else{
        $v = add_category($v,$k);
        wp_set_object_terms($postID, $v, $k);
      }
    }
  }

  //归档文章形式->start
  if(!empty($post_format) && $postformat == true){
    if($post_format == 'post-format-image' || $post_format == 'post-format-video' || $post_format == 'post-format-aside'){
      wp_set_post_terms($postID, $post_format, 'post_format');
    }
  }
  //归档文章形式->end

    //发布自定义栏目
    if (!empty($post_meta)) {
      foreach($post_meta as $key => $value) {
        $ret = add_post_meta($postID,$key,$value,true);
        if(!$ret){
          delete_post_meta($postID, $key);
          add_post_meta($postID,$key,$value,true);
        }
      }
    }

  //发布评论->start
  if(!empty($comment)){
    //格式化评论内容
    $comment = str_replace(array("\r\n", "\r", "\n"), "", $comment);
    $arraycomment = explode('|||', $comment);
    //格式化评论时间
    $commentdate = str_replace(array("\r\n", "\r", "\n"), "", $commentdate);
    $arraycommentdate = explode('|||', $commentdate);
    //格式化评论作者
    $commentauthor = str_replace(' ','',$commentauthor);
    $commentauthor = str_replace(array("\r\n", "\r", "\n"), "", $commentauthor);
    $arraycommentauthor = explode('|||', $commentauthor);
    //评论计数
    $comment_count = count($arraycomment) -1 ;
    //更新文章评论数
    $wpdb->get_results("UPDATE $wpdb->posts set `comment_count` = $comment_count WHERE `ID` = $postID");
    //写入评论
    foreach($arraycommentauthor as $k => $v){
      //判断评论时间
      if($v != ''){
        $format="Y-m-d H:i:s";
        $d = formatdate($arraycommentdate[$k]);
        $d = strtotime($d);
        if($d != ''){
          $date = date($format,$d);
          $gmtdate = gmdate($format, $d);
        }else{
          $date = date($format);
          $gmtdate = gmdate($format);
        }
        //写入数据库
        $res = $wpdb->get_results("INSERT INTO $wpdb->comments (`comment_post_ID`,`comment_author`,`comment_date`,`comment_date_gmt`,`comment_content`,`user_id`) VALUES ($postID,'$v','$date','$gmtdate','$arraycomment[$k]',1)");
      }
    }
  }
  //发布评论->end

  // 自定PING,需要再网站后台设置->撰写->更新服务器 下面填写PING地址
  if ($post_ping)  generic_ping();
}
?>
