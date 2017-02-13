<?php
namespace dlHelper;
class app
{
    public $conf; // 配置文件内容
    
    public function __construct($config = [])
    {
        $this->conf = $config;
    }
    
    /**
     * 运行
     */
    public function run()
    {
        echo "+-----------------------------------------------------------------+\n";
        echo "|                         Download Helper                         |\n";
        echo "+-----------------------------------------------------------------+\n";
        echo "|        Hello, Welcome To Use. Now, You Think And Configure.     |\n";
        echo "+-----------------------------------------------------------------+\n";
        echo "|        Author: Zneiat <zneiat@163.com>.                         |\n";
        echo "|                     GitHub: https://github.com/Zneiat.          |\n";
        echo "+-----------------------------------------------------------------+\n";
        echo "\n";
        
        // 定义有用的变量
        $dlUrl = null;
        
        // ==============================
        //  选择下载器
        // ==============================
        echo "-----------------------> Select Downloader <-----------------------\n";
        $dlerList = $this->getDownloaderList();
        $dlerClassName = null;
        do {
            echo "Please Select A Downloader In The List Below. It Will Serve You.\n ";
            // 显示下载器选择列表
            foreach ($dlerList as $num => $item) {
                echo ($num + 1) . '.' . ucwords($item['className']) . '  ';
            }
            // 键入内容
            $dlerSerial = intval(lib::readInput("\nInput Serial: "));
            if(empty($dlerSerial) || ($dlerSerial-1)<0){
                lib::nowShow(null, '_inputError');
                continue;
            }
            if(empty($dlerList[$dlerSerial-1])){
                // 未找到这个下载器
                lib::nowShow("Please Input A Downloader Serial In The List Below.\n\n", '_w');
                continue;
            }
            $dlerClassName = $dlerList[$dlerSerial-1]['className'];
            lib::nowShow("You Selected: ".ucwords($dlerClassName)." Downloader.\n", "_o");
            break;
        } while (true);
    
        // ==============================
        //  带你去找下载器哥哥们啦... 2333
        // ==============================
        /* @var $dler \dlHelper\downloader */
        $dlerClassName = '\\dlHelper\\downloaders\\'.$dlerClassName;
        $dler = new $dlerClassName;
        
        // ==============================
        //  输入URL地址
        // ==============================
        echo "---------------------------> Input URL <---------------------------\n";
        do {
            echo "Please Input A Page Link. Downloader Can Save Any Pic On This Page.\n";
            $dlUrl = lib::readInput('Input URL: ');
            if(empty($dlUrl)){
                lib::nowShow(null, '_inputError');
                continue;
            }
            // 验证 URL 格式是否正确，并验证下载器是否支持
            if(!$dler->urlSupportValidator($dlUrl)){
                lib::nowShow(null, '_urlNotSupport');
                continue;
            }
            $dler -> url = $dlUrl;
            lib::nowShow("Will Download This Url: ".$dlUrl."\n", "_o");
            break;
        } while (true);
        
        // ==============================
        //  根据 URL 执行对应操作
        // ==============================
        echo "-------------------------> Running! Now! <-------------------------\n";
        // 首先完成最后的配置
        $dler -> maxPicNum = $this->conf['defaultMaxPicNum']; /** @see $conf */
        $dler -> carryCookie = $this->conf['defaultCarryCookie'];
        $dler -> savePath = $this->conf['savePath'];
        // 好的，执行！
        try {
            $dler -> doFunctionByUrl();
        } catch (\Exception $e) {
            lib::nowShow('Fatal Error:'.$e->getMessage(), '_e');
        }
        echo "\n";
    }
    
    /**
     * 获取下载器列表
     * @return array
     */
    public function getDownloaderList()
    {
        $downloaderList = [];
        $downloadersPath = APP_ROOT.'/dlHelper/downloaders';
        if(!is_dir($downloadersPath)){
            lib::nowShow("No Downloader Dir Found. Can Not Run.\n",'_e');
            exit();
        }
        $filesNames = array_filter(array_values(array_diff(scandir($downloadersPath), array('.', '..'))));
        if(empty($filesNames)){
            lib::nowShow("No Downloader File Found. Can Not Run.\n",'_e');
            exit();
        }
        foreach ($filesNames as $name) {
            if(pathinfo($name, PATHINFO_EXTENSION)=='php' && is_file($downloadersPath . DIRECTORY_SEPARATOR . $name) && is_readable($downloadersPath . DIRECTORY_SEPARATOR . $name)) {
                $downloaderList[] = [
                    'fullPath' => $downloadersPath.$name,
                    'className' => pathinfo($name, PATHINFO_FILENAME)
                ];
            }
        }
        return $downloaderList;
    }
}