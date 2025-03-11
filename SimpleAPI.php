<?php

/********************************************************************************
 *
 * 
 * 阿里云百炼插件的简易 API，不知道为啥官方提供的 SDK 会报错，所以自己写一个
 * 
 * @date 2025-03-08
 * @author 我是苏云曦吖
 * @version 1.0.0
 * @website https://www.yuisblog.com/
 * 
 */

namespace TypechoPlugin\AIHelper;

class SimpleAPI {

    /* String */
    private $accessKeyId = "";

    /* String */
    private $accessKeySecret = "";

    /* String */
    private $endpoint = "";

    /* String */
    private $workspaceId = "";

    /* String */
    private $categoryId = "";

    /* String */
    private $ALGORITHM = "ACS3-HMAC-SHA256";

    public function __construct(string $accessKeyId, 
                                string $accessKeySecret, 
                                string $endpoint, 
                                string $workspaceId, 
                                string $categoryId) {
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->endpoint = $endpoint;
        $this->workspaceId = $workspaceId;
        $this->categoryId = $categoryId;
    }

    /**
     * 
     * 申请上传租约用的
     *  
     * @param array $params
     * @return array
     * 
     */
    public function applyFileUploadLease(array $params) {
        $canonicalUri = sprintf("/%s/datacenter/category/%s", $this->workspaceId, $this->categoryId);
        $request = $this->createRequest('POST', $canonicalUri, $this->endpoint, 'ApplyFileUploadLease', '2023-12-29');
        $request['body'] = http_build_query($params);
        $this->getAuthorization($request);
        return $this->extractData($this->callApi($request));
    }

    /**
     * 
     * 上传到临时OSS链接
     * 
     * @param string $content
     * @param string $uri
     * @param array $headers
     * @param string $method
     * @return object|string
     * 
     */
    public function uploadFile(string $content, string $uri, array $headers, string $method = 'PUT') {
        // $stream = $this->createUploadStream($content);
        // echo $uri;
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用SSL证书验证，请注意，这会降低安全性，不应在生产环境中使用（不推荐！！！）
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回而不是输出内容
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->convertHeadersToArray($headers)); // 添加请求头

        curl_setopt_array($ch, [
            CURLOPT_URL => $uri,
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_INFILESIZE => strlen($content),
        ]);

        if($method == 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        } else if($method == 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POST, true);
        }

        $response = curl_exec($ch);
        if(curl_errno($ch)) {
            throw new Exception('阿里云文档上传失败，cURL error: ' . curl_error($ch));
        } else {
            // var_dump($response);
            curl_close($ch);
        }
        return $response;
    }

    /**
     * 
     * 添加文件到数据仓库
     * 
     * @param array $params
     * @return array
     * 
     */
    public function addFile(array $params) {
        $canonicalUri = sprintf("/%s/datacenter/file", $this->workspaceId);
        $request = $this->createRequest('PUT', $canonicalUri, $this->endpoint, 'AddFile', '2023-12-29');
        $request['body'] = http_build_query($params);
        $this->getAuthorization($request);
        return $this->extractData($this->callApi($request));
    }

    /**
     * 
     * 提交索引添加文档任务
     * 
     * @param array $params
     * @return array
     * 
     */
    public function submitIndexAddDocumentsJob(array $params) {
        $canonicalUri = sprintf("/%s/index/add_documents_to_index", $this->workspaceId);
        $request = $this->createRequest('POST', $canonicalUri, $this->endpoint, 'SubmitIndexAddDocumentsJob', '2023-12-29');
        $request['body'] = http_build_query($params);
        $this->getAuthorization($request);
        return $this->extractData($this->callApi($request));
    }

    // private function createUploadStream(string $content) {
    //     $stream = fopen('php://temp', 'r+');
    //     fwrite($stream, $content);
    //     rewind($stream);
    //     return $stream;
    // }

    // 提取API返回结果数据
    private function extractData(array $result) {
        if($result['Status'] != '200') {
            if(isset($result['Message'])) {
                throw new Exception("阿里云API请求失败，错误信息：" . $result['Message']);
            }
            return null;
        } else {
            if(isset($result['Data'])) {
                return $result['Data'];
            }
        }
        return [];
    }

    /* 下面时根据阿里云官网文档写的，再错我也没办法了 */
    private function createRequest($httpMethod, $canonicalUri, $host, $xAcsAction, $xAcsVersion) {
        $headers = [
            'host' => $host,
            'x-acs-action' => $xAcsAction,
            'x-acs-version' => $xAcsVersion,
            'x-acs-date' => gmdate('Y-m-d\TH:i:s\Z'),
            'x-acs-signature-nonce' => bin2hex(random_bytes(16)),
        ];
        return [
            'httpMethod' => $httpMethod,
            'canonicalUri' => $canonicalUri,
            'host' => $host,
            'headers' => $headers,
            'queryParam' => [],
            'body' => null,
        ];
    }

    private function getAuthorization(&$request) {
        $request['queryParam'] = $this->processObject($request['queryParam']);
        $canonicalQueryString = $this->buildCanonicalQueryString($request['queryParam']);
        $hashedRequestPayload = hash('sha256', $request['body'] ?? '');
        $request['headers']['x-acs-content-sha256'] = $hashedRequestPayload;

        $canonicalHeaders = $this->buildCanonicalHeaders($request['headers']);
        $signedHeaders = $this->buildSignedHeaders($request['headers']);

        $canonicalRequest = implode("\n", [
            $request['httpMethod'],
            $request['canonicalUri'],
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeaders,
            $hashedRequestPayload,
        ]);

        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
        $stringToSign = "{$this->ALGORITHM}\n$hashedCanonicalRequest";

        $signature = strtolower(bin2hex(hash_hmac('sha256', $stringToSign, $this->accessKeySecret, true)));
        $authorization = "{$this->ALGORITHM} Credential={$this->accessKeyId},SignedHeaders=$signedHeaders,Signature=$signature";

        $request['headers']['Authorization'] = $authorization;
    }

    private function callApi($request) {
        try {
            // 通过cURL发送请求
            $url = "https://" . $request['host'] . $request['canonicalUri'];

            // 添加请求参数到URL
            if (!empty($request['queryParam'])) {
                $url .= '?' . http_build_query($request['queryParam']);
            }
            // echo $url;
            // 初始化cURL会话
            $ch = curl_init();

            // 设置cURL选项
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用SSL证书验证，请注意，这会降低安全性，不应在生产环境中使用（不推荐！！！）
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回而不是输出内容
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->convertHeadersToArray($request['headers'])); // 添加请求头

            // 根据请求类型设置cURL选项
            switch ($request['httpMethod']) {
                case "GET":
                    break;
                case "POST":
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $request['body']);
                    break;
                case "DELETE":
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                    break;
                case "PUT":
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $request['body']);
                    break;
                default:
                    // echo "Unsupported HTTP method: " . $request['body'];
                    throw new Exception("Unsupported HTTP method: ".$request['httpMethod']);
            }

            // 发送请求
            $result = curl_exec($ch);

            // 检查是否有错误发生
            if (curl_errno($ch)) {
                throw new \Exception("Failed to send request: " . curl_error($ch));
            } else {
                // echo $result;
                return json_decode($result, true);
            }

        } catch (Exception $e) {
            throw new \Exception("Error: " . $e->getMessage());
        } finally {
            // 关闭cURL会话
            curl_close($ch);
        }
    }

    function formDataToString($formData)
    {
        $res = self::processObject($formData);
        return http_build_query($res);
    }

    function processObject($value)
    {
        // 如果值为空，则无需进一步处理
        if ($value === null) {
            return;
        }
        $tmp = [];
        foreach ($value as $k => $v) {
            if (0 !== strpos($k, '_')) {
                $tmp[$k] = $v;
            }
        }
        return self::flatten($tmp);
    }

    private static function flatten($items = [], $delimiter = '.', $prepend = '')
    {
        $flatten = [];
        foreach ($items as $key => $value) {
            $pos = \is_int($key) ? $key + 1 : $key;

            if (\is_object($value)) {
                $value = get_object_vars($value);
            }

            if (\is_array($value) && !empty($value)) {
                $flatten = array_merge(
                    $flatten,
                    self::flatten($value, $delimiter, $prepend . $pos . $delimiter)
                );
            } else {
                if (\is_bool($value)) {
                    $value = true === $value ? 'true' : 'false';
                }
                $flatten["$prepend$pos"] = $value;
            }
        }
        return $flatten;
    }


    private function convertHeadersToArray($headers)
    {
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = "$key: $value";
        }
        return $headerArray;
    }


    private function buildCanonicalQueryString($queryParams)
    {

        ksort($queryParams);
        // Build and encode query parameters
        $params = [];
        foreach ($queryParams as $k => $v) {
            if (null === $v) {
                continue;
            }
            $str = rawurlencode($k);
            if ('' !== $v && null !== $v) {
                $str .= '=' . rawurlencode($v);
            } else {
                $str .= '=';
            }
            $params[] = $str;
        }
        return implode('&', $params);
    }

    private function buildCanonicalHeaders($headers)
    {
        // Sort headers by key and concatenate them
        uksort($headers, 'strcasecmp');
        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
        }
        return $canonicalHeaders;
    }

    private function buildSignedHeaders($headers)
    {
        // Build the signed headers string
        $signedHeaders = array_keys($headers);
        sort($signedHeaders, SORT_STRING | SORT_FLAG_CASE);
        return implode(';', array_map('strtolower', $signedHeaders));
    }

}

?>