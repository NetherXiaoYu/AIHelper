<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class AIHelper_Action extends Typecho_Widget implements Widget_Interface_Do {
    
    private $config;
    
    public function __construct($request, $response, $params = NULL) {
        parent::__construct($request, $response, $params);
        
        $this->config = Helper::options()->plugin('AIHelper');
        
        if(method_exists($this, $this->request->act) && in_array($this->request->act, ['chat'])) {
            call_user_func(array($this, $this->request->act));
        } else {
            $this->default();
        }
    }

    public function default() {
        $this->response->setStatus(404);
        exit(404);
    }

    public function chat() {
        
        if(!isset($_SERVER['REQUEST_METHOD'])){
            exit;
        }
        
        // 初始化首页
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {  
            $this->default();
            exit();
        }  

        if(empty($this->config->appId) || empty($this->config->apiKey)) {
            $this->outputError("未配置AppId或ApiKey，请在插件设置中配置后再试！");
            exit();
        }

        if(empty($this->config->askInteval) || empty($this->config->mustLogin)) {
            $this->outputError("请先配置插件！");
            exit();
        }
        
        $user = Typecho_Widget::widget('Widget_User');

        // 验证是否登录
        if(!$user->hasLogin() && $this->config->mustLogin == 'true') {
            $this->outputError("你需要先登录才能使用，请先[登录](/admin/login.php)！");
            exit();
        }

        // 验证令牌以及提问间隔
        if(!isset($_SESSION['helper_token'])) {
            $this->outputError("错误的请求，请刷新页面再试！");
            exit();
        } else {
            $token = $_SESSION['helper_token'];
            if(isset($_SESSION[$token]) && time() - $_SESSION[$token] < $this->config->askInteval) {
                $this->outputError("你提问的太快了，请稍后再试！");
                exit();
            }
        }
        $_SESSION[$token] = time();
        // exit("/");

        $url = sprintf("https://dashscope.aliyuncs.com/api/v1/apps/%s/completion", $this->config->appId);
        $headers = [ // 请求头数组
            'Authorization: Bearer ' . $this->config->apiKey, // 认证头部
            'Content-Type: application/json', // 内容类型
            'X-DashScope-SSE: enable' // SSE启用标志
        ];

        $prompt = "";

        $json = file_get_contents('php://input'); 
        // 尝试将JSON转换为关联数组
        $data = json_decode($json, true);
        // 检查JSON解析是否成功
        if (json_last_error() === JSON_ERROR_NONE) {
            $prompt = $data['prompt'] ?? null; // 提取prompt字段值
        }
   
        /**
         * 构建请求体
         */
        $requestBody = [ // 请求体数组
            'input' => ['prompt' => $prompt], // 输入项
            'parameters' => ['incremental_output' => true], // 参数设置
            'debug' => new stdClass(), // 调试选项（空对象）
        ];

        $this->call_with_stream($url, $headers, $requestBody);
    }

    private function call_with_stream($url, $headers, $requestBody) {
        /**
         * 初始化cURL会话并设置相关属性
         */
        $ch = curl_init();

        curl_setopt_array($ch, [ // 设置cURL选项
            CURLOPT_URL => $url, // 目标URL
            CURLOPT_POST => true, // 使用POST方法
            CURLOPT_POSTFIELDS => json_encode($requestBody), // POST数据
            CURLOPT_HTTPHEADER => $headers, // 请求头
            CURLOPT_WRITEFUNCTION => function ($curl, $data) { // 自定义写回调
                echo $data; // 输出数据
                ob_flush(); // 刷新输出缓冲
                flush(); // 刷新所有输出缓冲
                return strlen($data); // 返回数据长度
            },
            CURLOPT_BUFFERSIZE => 128, // 缓冲区大小
            CURLOPT_TIMEOUT => 300, // 超时时间
            CURLOPT_RETURNTRANSFER => false, // 是否返回传输数据
            CURLOPT_HEADER => false // 不包含HTTP头部
        ]);
        
        if (ob_get_level()) {
            ob_end_flush(); // 关闭输出缓冲
        }

        /**
         * 执行cURL会话并获取响应
         */
        $response = curl_exec($ch);

        /**
         * 错误处理及响应输出
         */
        if (curl_errno($ch)) { // 检查是否存在cURL错误
            http_response_code(500); // 设置HTTP响应状态码为500
            echo 'Curl error: ' . curl_error($ch); // 输出错误信息
        }

        curl_close($ch); // 关闭cURL会话
    }

    private function outputError(string $error) {
        $sessionid = md5(time());
        $uuid = $this->generateUUIDv4();
        $content = <<<EOT
        id:1 event:result :HTTP_STATUS/200
        data:{"output":{"session_id":"{$sessionid}","finish_reason":"stop","text":"{$error}"},"usage":{"models":[{"input_tokens":9999,"output_tokens":9999,"model_id":"system"}]},"request_id":"{$uuid}"}
        EOT;
        echo $content;
        
        ob_flush(); // 刷新输出缓冲
        flush(); // 刷新所有输出缓冲
        ob_end_flush(); // 关闭输出缓冲
        // exit;
    }

    private function generateUUIDv4() {
        // 生成 16 字节的加密安全随机字节
        $data = random_bytes(16);
    
        // 转换为十六进制字符串（32 个字符）
        $hex = bin2hex($data);
    
        // 按 UUID 格式插入连字符（8-4-4-4-12）
        $uuid = sprintf(
            '%08s-%04s-%04x-%04x-%012s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            0x4000 | (hexdec(substr($hex, 12, 4)) & 0x0fff), // 设置版本位（4）
            0x8000 | (hexdec(substr($hex, 16, 4)) & 0x3fff), // 设置变体位（10xx）
            substr($hex, 20)
        );
    
        return $uuid;
    }
    
    public function action() {
        $this->on($this->request);
    }
    
}

?>
