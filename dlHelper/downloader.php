<?php
namespace dlHelper;

// Load PhpQuery Class
require(APP_ROOT . '/dlHelper/tools/phpQuery/phpQuery.php');

/**
 * Class downloader
 * @package dlHelper
 */
abstract class downloader
{
    // 配置参数
    public $url; // 网页地址
    public $maxPicNum; // 最多下载图片数
    public $carryCookie; // 携带 Cookie 请求
    public $savePath; // 保存路径
    private $savePathChnaged = false; // 已修改过保存路径？
    
    // 基本信息
    abstract protected function baseInfo();
    
    // 指定函数索引
    abstract protected function actionIndex();
    
    /***
     * 获取 Base Info
     */
    public function getBaseInfo()
    {
        return $this->baseInfo();
    }
    
    /**
     * 获取 Base Info Host
     */
    public function getHostRule()
    {
        return $this->getBaseInfo()['host'];
    }
    
    /**
     * 获取 Action Index
     * @return mixed
     */
    public function getActionIndex()
    {
        return $this->actionIndex();
    }
    
    /**
     * 执行 URL 对应 Function
     * @param $url
     * @throws \Exception
     */
    public function doFunctionByUrl($url=null)
    {
        $url = !is_null($url)?$url:$this->url;
        if(!$this->urlSupportValidator($url)){
            throw new \Exception('The Url Host Is Not Supported');
        }
        // 寻找对应 Function
        $functionName = $this->findFunctionNameByUrl($url);
        if(!is_null($functionName)){
            try {
                call_user_func_array([$this, $functionName], []);
            } catch (\Exception $e){
                throw new \Exception($e->getMessage());
            }
        }else{
            throw new \Exception('Can Not Find Function For This Path');
        }
    }
    
    /**
     * 获取 url host 正则表达式
     * @param null $urlHostRule
     * @return string
     */
    public function getUrlHostReg($urlHostRule=null)
    {
        $urlHostRule = !is_null($urlHostRule)?$urlHostRule:$this->getHostRule();
        $handledStr = str_replace(['*', '/'], ['(.*?)', '\/'], addslashes($urlHostRule));
        return '/^' . $handledStr . '/is';
    }
    
    /**
     * 获取 url path 正则表达式
     * @param $urlPathRule
     * @return string
     */
    public function getUrlPathReg($urlPathRule)
    {
        if (substr($urlPathRule, 0, 1) !== '/') {
            $urlPathRule = '/' . $urlPathRule;
        }
        $handledStr = str_replace(['{_ANY_}', '/'], ['(.*?)', '\/'], addslashes($urlPathRule));
        return '/^' . $handledStr . '/is';
    }
    
    /**
     * 验证 url 是否支持
     * @param $url
     * @return bool
     */
    public function urlSupportValidator($url=null)
    {
        $url = !is_null($url)?$url:$this->url;
        // 验证是否为 URL
        if(!lib::urlValidator($url)){
            // 这里已经有验证 url 格式的了，所以前面那些不用再验证吶
            return false;
        }
        // 验证 URL 在此类中是否支持
        $urlParse = parse_url($url);
        if(preg_match($this->getUrlHostReg(), $urlParse['host']) && !empty($this->findFunctionNameByUrl($url))){
            // 匹配 host 支持，并且 通过 url 能查找到对应的方法
            return true;
        }
        return false;
    }
    
    /**
     * 通过 url 寻找方法名
     * @param string $url
     * @return string
     */
    public function findFunctionNameByUrl($url=null)
    {
        if(is_null($url)){
            $url = $this->url;
        }
        // 拆分 URL
        $urlParse = parse_url($url);
        $urlPath = $urlParse['path'];
        // 去寻找吧
        $actionsIndex = $this->getActionIndex();
        foreach ($actionsIndex as $key=>$value){
            if(preg_match($this->getUrlPathReg($key), $urlPath)){
                return $value;
            }
        }
        return null;
    }
    
    /**
     * 保存路径，设置 $this->savePath 并输出
     * @param $replaceStrArr array 替换字符串 例：['{_HOST_}'=>'www.google.com']
     * @return string
     * echo $this->savePath(['{_HOST_}'=>'1','{_PATH_}'=>'2'])."\n"; // return and set
     * echo $this->savePath."\n";
     */
    private function savePath($replaceStrArr=null)
    {
        $savePath = $this->savePath;
        // 根据 $replaceStrArr 替换字符串
        if(!is_null(($replaceStrArr))) {
            $search = [];
            $replace = [];
            foreach ($replaceStrArr as $key => $value) {
                if(strpos($savePath, $key)===false){
                    // 未找到这个字符串
                    continue;
                }
                $search[] = $key;
                $replace[] = $value;
            }
            if(!empty($search)&&!empty($replace)) {
                // 执行替换操作
                $savePath = str_replace($search, $replace, $savePath);
            }
        }
        // 若是第一次修改
        if($this->savePathChnaged) {
            if(empty($replaceStrArr['{_DATE_}'])&&strpos($savePath,'{_DATE_}')!==false){
                $savePath = str_replace('{_DATE_}', date("Ymd"), $savePath);
            }
            if(empty($replaceStrArr['{_DATETIME_}'])&&strpos($savePath,'{_DATETIME_}')!==false){
                $savePath = str_replace('{_DATETIME_}', date("YmdHi"), $savePath);
            }
            $this->savePathChnaged = true; // 说明这个保存路径已经被老纸修改了
        }
        // 保存
        $this->savePath = $savePath;
        // 返回
        return $this->savePath;
    }
}