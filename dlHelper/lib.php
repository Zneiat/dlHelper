<?php
namespace dlHelper;

class lib
{
    /**
     * 读取命令行键盘输入
     * @param string $promptText 提示字符
     * @return string
     */
    static function readInput($promptText='')
    {
        fwrite(STDOUT, $promptText);
        return trim(fgets(STDIN));
    }
    
    /**
     * 立刻输出
     * @param string $msgText 消息文字
     * @param string $type 标签类型
     */
    static function nowShow($msgText=null, $type=null)
    {
        if(!is_null($msgText)) {
            $msgTag = '';
            $tagsList = ['_s' => '[SUCCESS]', '_o' => '[OK]', '_e' => '[ERROR]', '_i' => '[INFO]', '_w' => '[WARNING]'];
            if (!is_null($type)) {
                $msgTag = !empty($tagsList[$type]) ? $tagsList[$type] : '[' . $type . ']';
            }
            echo $msgTag.' '.$msgText;
        }
        if(is_null($msgText) && $type=='_inputError'){
            echo "[WARNING] The Input Is Invalid. Please Try Again.\n\n";
        }
        if(is_null($msgText) && $type=='_urlNotSupport'){
            echo "[WARNING] This Downloader Does Not Support This Url.\n\n";
        }
    }
    
    /**
     * 验证是否为URL地址
     * @param $value
     * @param $httpType
     * @return bool
     */
    static function urlValidator($value, $httpType='https|http')
    {
        // 限制长度以免DOS攻击
        if(is_string($value)&&strlen($value)<2000){
            if(preg_match('/^('.$httpType.'):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(?::\d{1,5})?(?:$|[?\/#])/i', $value)){
                return true;
            }
        }
        return false;
    }
    
    static function getByUrl($url, $httpHeader=[])
    {
        $url = preg_replace('/ /', '%20', $url);
        $curl = curl_init($url);
        
        // 主要配置
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 仅返回结果，不自动打印
        curl_setopt($curl, CURLOPT_HEADER, false); // 返回结果不包含头信息
        @curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // 自动跳转
        @curl_setopt($curl, CURLOPT_MAXREDIRS, 10); // 最多跳转次数
    
        // 模拟访问
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.76 Safari/537.36"); // 请求用户代理头
        if(!empty($httpHeader)) {
            $newHttpHeader = [];
            foreach ($httpHeader as $key=>$value){
                if(strtolower($key)=='referer') {
                    @curl_setopt($curl, CURLOPT_REFERER, $value);
                }else if(strtolower($key)=='cookie'){
                    @curl_setopt ($curl, CURLOPT_COOKIE, $value);
                }else {
                    $newHttpHeader[] = $key.': '.$value;
                }
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $newHttpHeader);
        }
        curl_setopt($curl, CURLOPT_PROXY, '127.0.0.1'); // 代理访问地址
        curl_setopt($curl, CURLOPT_PROXYPORT, '1080'); // 代理访问端口
    
        // 关闭SSL验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        // 获取结果
        $curlResult = curl_exec($curl);
        // 判断状态
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errorCodeList = [400,401,403,404,500,502,503,301,302];
        if(in_array($httpStatus, $errorCodeList)){
            throw new \Exception('The Requested URL Returned Error: '.$httpStatus);
        }
    
        // 返回结果及文件路径
        if($curlResult){
            curl_close($curl);
            return $curlResult;
        } else {
            $errorMsg = 'Curl Error: '.curl_error($curl);
            curl_close($curl);
            throw new \Exception($errorMsg);
        }
    }
    
    /**
     * 下载并保存文件，显示进度
     * @param $url string 文件URL
     * @param $filePath string 保存文件路径，文件后缀用{*}自动后缀
     * @param array $httpHeader 请求头
     * @return mixed|string
     */
    static function downloadFileByUrl($url, $filePath, $httpHeader=[])
    {
        $url = preg_replace('/ /', '%20', $url);
        $fileAutoExt = false;
        if(strpos($filePath,'{*}')!==false){
            $filePath = str_replace('{*}','',$filePath);
            $fileAutoExt = true;
        }
            
        // 定义好需要的变量
        $fileMimeTypes = null;
        $curlFile = fopen($filePath, 'w');
        if(!$curlFile){
            echo "[DlFile] Could Not Open '$filePath' For Writing.\n";
            return null;
        }
        
        echo "[DlFile] Start Request Url: $url.\n";
        // 获取头信息
        $headerFunction = function ($ch, $data) use (&$fileMimeTypes) {
            list ($key, $value) = array_map('trim', explode(':', $data, 2));
            if (strtolower($key) == 'content-type') {
                $fileMimeTypes = $value;
            }
            return strlen($data);
        };
        // 显示进度
        $tmpDownloaded = 0;
        $progressFunction = function ($resource, $download_size, $downloaded, $upload_size, $uploaded) use (&$showProgressNum, &$fileMimeTypes, &$tmpDownloaded) {
            $ratio = $download_size > 0 ? round($downloaded / $download_size, 2) : 0;
            if($tmpDownloaded!=number_format($downloaded/1048576, 2)||$tmpDownloaded==0) {
                printf("[DlFile] %-3s [%-30s] %s\r", ($ratio * 100) . '%', str_repeat('=', ($ratio * 29)) . (($ratio > 0) ? '>' : ''), (($download_size > 0) ? number_format($download_size / 1048576, 2) . 'MB/' : '') . number_format($downloaded / 1048576, 2) . 'MB ');
            }
            $tmpDownloaded = number_format($downloaded/1048576, 2);
        };
        // 写入数据
        $writeFunction = function ($ch, $data) use (&$curlFile){
            fwrite($curlFile, $data);
            return strlen($data);
        };
        
        $curl = curl_init($url);
        // 主要配置
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 仅返回结果，不自动打印
        curl_setopt($curl, CURLOPT_HEADER, false); // 返回结果不包含头信息
        @curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // 自动跳转
        @curl_setopt($curl, CURLOPT_MAXREDIRS, 10); // 最多跳转次数
        
        // 模拟访问
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.76 Safari/537.36"); // 请求用户代理头
        if(!empty($httpHeader)) {
            $newHttpHeader = [];
            foreach ($httpHeader as $key=>$value){
                if(strtolower($key)=='referer') {
                    @curl_setopt($curl, CURLOPT_REFERER, $value);
                }else if(strtolower($key)=='cookie'){
                    @curl_setopt ($curl, CURLOPT_COOKIE, $value);
                }else {
                    $newHttpHeader[] = $key.': '.$value;
                }
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $newHttpHeader);
        }
        curl_setopt($curl, CURLOPT_PROXY, '127.0.0.1'); // 代理访问地址
        curl_setopt($curl, CURLOPT_PROXYPORT, '1080'); // 代理访问端口
        
        // 自定义
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, $headerFunction); // 头函数
        curl_setopt($curl, CURLOPT_NOPROGRESS, false); // 关闭不显示进度
        curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, $progressFunction); // 进度函数
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, $writeFunction); // 写入数据函数
        // curl_setopt($curl, CURLOPT_BUFFERSIZE, 500000); // 每次调用时要读取的缓冲区大小（单位：Bytes）
        
        // 关闭SSL验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        // 获取结果
        $curlResult = curl_exec($curl);
        fclose($curlFile);
        // 判断状态
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errorCodeList = [400,401,403,404,500,502,503,301,302];
        if(in_array($httpStatus, $errorCodeList)){
            @unlink($filePath);
            echo "\n[DlFile] The Requested URL Returned Error: $httpStatus.\n";
            return null;
        }
        
        // 返回结果及文件路径
        if($curlResult){
            curl_close($curl);
            // 自动后缀改名
            if($fileAutoExt){
                if(!class_exists('\dlHelper\tools\mimeTypes',false)){
                    require_once (__DIR__.'/tools/mimeTypes.php');
                }
                $fileExt = (new \dlHelper\tools\mimeTypes())->getExtension(mime_content_type($filePath));
                @unlink($filePath.$fileExt); // 删除已有的
                @rename($filePath,$filePath.$fileExt); // 修改名称
                $filePath = $filePath.$fileExt;
            }
            echo "\n[DlFile] SUCCESS! File Saved To $filePath.\n";
            return $filePath;
        } else {
            $errorMsg = "\n[DlFile] Curl Error: ".curl_error($curl).".\n";
            curl_close($curl);
            echo $errorMsg;
            return null;
        }
    }
    
    /**
     * 截取字符串，超出显示省略号
     * @param $text
     * @param $length
     * @return string
     */
    static function subtext($text, $length)
    {
        return (mb_strlen($text, 'utf8')>$length)?mb_substr($text, 0, $length, 'utf8').'...':$text;
    }
}