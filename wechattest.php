<?php
header("Content-type: text/html; charset=utf-8");
define("TOKEN", "molu");
$wechatObj = new wechatTest();
/*
 * 当用户向公众号发送消息的时候，微信公众号会带上一些参数来访问设置的URL
 * 其中echostr是当验证的时候才会带的参数，这些都可以通过GET来获取
 */
if (isset($_GET['echostr'])) {
    $wechatObj->valid();
} else {
    $wechatObj->responseMsg();
}

class wechatTest
{
    // 验证签名
    public function valid()
    {
        $echoStr = $_GET['echostr'];
        $signature = $_GET['signature'];
        $timestamp = $_GET['timestamp'];
        $nonce = $_GET['nonce'];
        $token = TOKEN;
        $array = array(
            $timestamp,
            $nonce,
            $token
        );
        // 排序
        sort($array);
        // 将排序好的数组拼接为字符串,第一个参数为中间连接的字符形式
        $tmpstr = implode('', $array);
        // 用sha1加密
        $tmpstr = sha1($tmpstr);
        if ($tmpstr == $signature) {
            echo $echoStr;
            exit();
        }
    }
    
    /*
     * 响应消息
     */ 
    public function responseMsg()
    {
        // 获取POST到URL上的XML数据包
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        
        if (! empty($postStr)) {
            // 将数据载入对象SimpleXMLElement中，第三个参数表示将CDATA合并为文本节点
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            // 获取消息的类型
            $RX_TYPE = trim($postObj->MsgType);
            switch ($RX_TYPE) {
                case "event":
                    $result = $this->receiveEvent($postObj);
                    break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
                default:
                    $result = $this->transmitText($postObj, $postObj->Content);
            }
            // 主要是将信息输出，这样回复的消息才会传出去
            echo $result; // 很重要
        } else {
            echo "";
            exit();
        }
    }
    
    /*
     * 处理事件消息（关注与取消关注时的效果）
     */
    public function receiveEvent($postObj)
    {
        $content = "";
        switch ($postObj->Event) {
            case "subscribe":
                $content = "欢迎关注molu说";
                break;
            case "unsubscribe":
                $content = "";
                break;
        }
        $result = $this->transmitText($postObj, $content);
        return $result;
    }
    
    /*
     * 处理文本消息
     */ 
    public function receiveText($postObj)
    {
        $keyword = trim($postObj->Content);
        
        // 当为问号的时候，将回复的内容设为当前的时间，否则返回文本的翻译
        if ($keyword == "？" || $keyword == '?') {
            $msgType = "text";
            $content = date("Y-m-d H:i:s", time());
            $result = $this->transmitText($postObj, $content);
        } else {
            $result=$this->youdaoDic($postObj, $keyword);
        }
        return $result;
    }
    
    /*
     * 处理文本消息的回复
     */ 
    public function transmitText($object, $content)
    {
        $textTpl = "<xml>
                      <ToUserName><![CDATA[%s]]></ToUserName>
                      <FromUserName><![CDATA[%s]]></FromUserName>
                      <CreateTime>%s</CreateTime>
                      <MsgType><![CDATA[text]]></MsgType>
                      <Content><![CDATA[%s]]></Content>
                      <FuncFlag>0</FuncFlag>
                      </xml>";
        $result = sprintf($textTpl, $object->FromUserName, $object->toUserName, time(), $content);
        return $result;
    }
   
    /*
     * 调用有道词典API实现翻译
     * 参数说明：
     * 　type - 返回结果的类型，固定为data
     * 　doctype - 返回结果的数据格式，xml或json或jsonp
     * 　version - 版本，当前最新版本为1.1
     * 　q - 要翻译的文本，必须是UTF-8编码，字符长度不能超过200个字符，需要进行urlencode编码
     * 　only - 可选参数，dict表示只获取词典数据，translate表示只获取翻译数据，默认为都获取
     * 　注： 词典结果只支持中英互译，翻译结果支持英日韩法俄西到中文的翻译以及中文到英语的翻译
     * errorCode：
     * 　0 - 正常
     * 　20 - 要翻译的文本过长
     * 　30 - 无法进行有效的翻译
     * 　40 - 不支持的语言类型
     * 　50 - 无效的key
     * 　60 - 无词典结果，仅在获取词典结果生效
     */
    public function youdaoDic($object, $words)
    {
        $keyfrom = 'molushuo';
        $apikey = '375011110';
        $doctype = 'xml';
        // 这个地址返回的是XML文件
        $url_youdao = 'http://fanyi.youdao.com/openapi.do?keyfrom=' . $keyfrom . '&key=' . $apikey;
        $url_youdao = $url_youdao . '&type=data&doctype=' . $doctype . '&version=1.1&q=' . $words;
        
        // 将XML文件载入对象中
        $xmlStyle = simplexml_load_file($url_youdao);
        
        $errorCode = $xmlStyle->errorCode; // 获取错误码
        $paras = $xmlStyle->translation->paragraph; // 获取翻译的内容
        
        switch ($errorCode) {
            case 0:
                return $this->transmitText($object, $paras);
                break;
            case 20:
                return $this->transmitText($object, '要翻译的文本过长');
                break;
            case 30:
                return $this->transmitText($object, '无法进行有效翻译');
                break;
            case 40:
                return $this->transmitText($object, '不支持的语言类型');
                break;
            case 50:
                return $this->transmitText($object, '无效的key');
                break;
            case 60:
                return $this->transmitText($object, '无词典结果，仅在获取词典结果生效');
                break;
        }
    }
}
?>