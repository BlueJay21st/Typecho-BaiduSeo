<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 百度SEO
 * 
 * @package BaiduSeo
 * @author BlueJay
 * @version 1.0.0
 * @link https://www.cwlog.net/
 */
class BaiduSeo_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('BaiduSeo_Plugin', 'publish_push');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('BaiduSeo_Plugin', 'auto_push');
        return _t('请设置接口调用地址');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $api = new Typecho_Widget_Helper_Form_Element_Text('api', NULL, 'NULL', _t('接口调用地址'), _t('站长工具-普通收录-资源提交-API提交-接口调用地址<br>(格式如下：http://data.zz.baidu.com/urls?site=https://www.cwlog.net&token=xxxxxxxxxxx)'));
        $form->addInput($api->addRule('required', _t('请填写接口调用地址')));
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {}
    
    /**
     * 发布文章时使用接口推送
     * 
     * @access public
     * @return void
     */
    public static function publish_push($content, $edit)
    {
        $api = Typecho_Widget::widget('Widget_Options')->plugin('BaiduSeo')->api;
        if($api === 'NULL' || strpos($api, 'data.zz.baidu.com') !== 7) exit('<script>alert("请为BaiduSeo插件配置正确的接口调用地址");location.href="'.$siteUrl.'/admin/manage-posts.php";</script>');
        $db = Typecho_Db::get();
        $siteUrl = Typecho_Widget::widget('Widget_Options')->index;

        $content['cid'] = $edit->cid;
        $content['slug'] = $edit->slug;
        
        //获取分类缩略名
        $content['category'] = urlencode(current(Typecho_Common::arrayFlatten($db->fetchAll($db->select()->from('table.metas')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $content['cid'])
            ->where('table.metas.type = ?', 'category')
            ->order('table.metas.order', Typecho_Db::SORT_ASC)), 'slug')));

        //获取并格式化文章创建时间
        $content['created'] = $edit->created;
        $created = new Typecho_Date($content['created']);
        $content['year'] = $created->year; $content['month'] = $created->month; $content['day'] = $created->day;

        //生成URL
        $url = Typecho_Common::url(Typecho_Router::url($content['type'], $content), $siteUrl);

        //发送请求
        $urls = array(0=>$url);
        $ch = curl_init();
        $options =  array(
            CURLOPT_URL => $api,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        
        $res = json_decode($result, true);
        if(isset($res['error'])) exit('<script>alert("链接提交百度接口失败！错误代码：'.$res['error'].'，错误信息：'.$res['message'].'。");location.href="'.$siteUrl.'/admin/manage-posts.php";</script>');
    }
    
        /**
     * 用户浏览文章时自动推送
     * 
     * @access public
     * @return void
     */
    public static function auto_push()
    {
        echo PHP_EOL.'<script>
(function(){
  var bp = document.createElement("script");
  var curProtocol = window.location.protocol.split(":")[0];
  if (curProtocol === "https"){
    bp.src = "https://zz.bdstatic.com/linksubmit/push.js";
  }else{
    bp.src = "http://push.zhanzhang.baidu.com/push.js";
  }
    var s = document.getElementsByTagName("script")[0];
    s.parentNode.insertBefore(bp, s);
})();
</script>'.PHP_EOL;
    }
}
