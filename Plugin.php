<?php

/**
 * AIHelper 帮助你对接阿里云百炼大模型，为你的 Typecho 站点添加 AI 助手
 * 
 * @package AIHelper
 * @author 我是苏云曦吖
 * @version 1.0.0
 * @link https://www.yuisblog.com/
 *
 */

namespace TypechoPlugin\AIHelper;

use Typecho\Plugin\PluginInterface;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Password;

use Widget\Options;
use Widget\Archive;

use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'SimpleAPI.php';
 
class Plugin implements PluginInterface {
    
    private static $pluginName = "AIHelper";
    private static $tableName = "ai_helper";
    
    public static function activate() {
        // self::install();
        
        Helper::addAction('chat', 'AIHelper_Action');
        Helper::addRoute('aichat', '/aihelper/[act]/', 'AIHelper_Action');
        
        
        \Typecho\Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'addHeader');
        \Typecho\Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array(__CLASS__, "pushFileToBailian");
        \Typecho\Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array(__CLASS__, "pushFileToBailian");
    }
    
    public static function deactivate() {
        // self::uninstall();
        Helper::removeRoute('aichat');
    }
    
    public static function config(Form $form) {
        $appId = new Text('appId', null, _t(''), _t('<h2>AIHelper阿里云百炼智能助手设置</h2>应用ID'), _t('如果不知道自己的应用ID，到<a href="https://bailian.console.aliyun.com/#/app-center">阿里云百炼应用控制台</a>获取'));
        $apiKey = new Password('apiKey', null, _t(''), _t('API-KEY'), _t('如果不知道自己的API-KEY或还没创建API-KEY，到<a href="https://bailian.console.aliyun.com/?apiKey=1">阿里云百炼API-KEY控制台</a>创建或获取'));
        
        $autoUpdate = new Radio('autoUpdate', array('auto' => _t('自动推送'), 'manual' => _t('手动推送')), _t('auto'), _t('知识库更新方法'), _t('前者会在发布文章时直接提交到阿里云百炼的知识库中，后者则需要自己手动提交。如果前者提交超时可切换为后者<hr></hr>'));
        
        $mustLogin = new Radio('mustLogin', array('true' => _t('登录用户'), 'false' => _t('游客')), _t('true'), _t('<h2>安全设置</h2>调用AI助手身份'), _t('前者指必须登录才能调用AI助手，后者则是无需登录无限制调用'));
        $askInteval = new Text('askInteval', null, _t('120'), _t('提问间距（单位：秒）'), _t('开启登录用户提问才有更好的效果，不然智能做到普通的防刷。<hr></hr>'));
        
        $categoryId = new Text('categoryId', null, _t(''), _t('<h2>阿里云百炼知识库设置（手动推送无需填写）</h2> 文档类目 ID'), _t('您也可以在<a href="https://bailian.console.aliyun.com/knowledge-base?spm=a2c4g.11186623.0.0.5cad7152FP89O1#/data-center">数据管理</a>-非结构化数据页面，单击类目名称旁的 ID 图标获取。'));
        $workspaceId = new Text('workspaceId', null, _t(''), _t('业务空间 ID'), _t('在百炼的<a href="https://bailian.console.aliyun.com/knowledge-base?spm=a2c4g.11186623.0.0.5cad7152FP89O1#/home">控制台</a>首页，单击页面左上角业务空间详情图标获取。'));
        $knowledgeBaseId = new Text('knowledgeBaseId', null, _t(''), _t('知识库 ID'), _t('创建的知识库 ID，可在<a href="https://bailian.console.aliyun.com/#/knowledge-base">阿里云百炼知识库管理页面</a>中，知识库名称旁ID获取<hr></hr>'));
        
        $aliyunEndpoint = new Text('aliyunEndpoint', null, _t('bailian.cn-beijing.aliyuncs.com'), _t('阿里云接入地址'), _t('请参阅<a href="https://help.aliyun.com/zh/model-studio/developer-reference/api-bailian-2023-12-29-endpoint">百炼官方开发文档</a>，请注意这里接入点较少，请勿随意填写！'));
        $aliyunAccessKeyId = new Text('aliyunAccessKeyId', null, _t(''), _t('阿里云 AccessKey ID'), _t(''));
        $aliyunAccessKeyToken = new Text('aliyunAccessKeyToken', null, _t(''), _t('阿里云 AccessKey Token'), _t('这里 AccessKey 的信息如果不会获取可参阅官方手册！'));
        
        $buttonRight = new Text('buttonRight', null, _t('10'), _t('<h2>外观设置</h2>AI助手按钮靠左位置'), _t(''));
        $buttonBottom = new Text('buttonBottom', null, _t('100'), _t('AI助手按钮靠下位置'), _t(''));
        $chatboxHeight = new Text('chatboxHeight', null, _t('600'), _t('AI助手聊天框高度'), _t(''));
        $chatboxZIndex = new Text('chatboxZIndex', null, _t('100'), _t('AI助手聊天框Z-Index(不懂默认100即可)') ,_t(''));
        $titleMessage = new Text('titleMessage', null, _t('你好，我是官方 AI 助手'), _t('AI助手聊天框标题（欢迎信息）'), _t(''));
        $subtitleMessage = new Text('subtitleMessage', null, _t('您可以尝试点击下方的快捷入口开启体验！'), _t('AI助手聊天框副标题（欢迎信息）'), _t(''));
        $helperLogo = new Text('helperLogo', null, _t('https://img.alicdn.com/imgextra/i2/O1CN01Pda9nq1YDV0mnZ31H_!!6000000003025-54-tps-120-120.apng'), _t('AI助手图标'), _t(''));
        $initQuestion = new Textarea('initQuestion', null, _t('这博客是关于什么？;你是谁？;你好吗？'), _t('AI助手默认提示问题，每条用<code>;</code>分开（建议最多3条）'), _t(''));
        
        
        $form->addInput($appId);
        $form->addInput($apiKey);
        $form->addInput($autoUpdate);
        $form->addInput($mustLogin);
        $form->addInput($askInteval);
        $form->addInput($categoryId);
        $form->addInput($workspaceId);
        $form->addInput($knowledgeBaseId);
        $form->addInput($aliyunEndpoint);
        $form->addInput($aliyunAccessKeyId);
        $form->addInput($aliyunAccessKeyToken);
        $form->addInput($buttonRight);
        $form->addInput($buttonBottom);
        $form->addInput($chatboxHeight);
        $form->addInput($chatboxZIndex);
        $form->addInput($titleMessage);
        $form->addInput($subtitleMessage);
        $form->addInput($helperLogo);
        $form->addInput($initQuestion);
    }
    
    public static function personalConfig(Form $form) {}
    
    public static function pushFileToBailian($content, $archive) {
        if($archive->type != 'post' && $archive->type != 'page') {
            return ;
        }

        $config = Helper::options()->plugin('AIHelper');
        if($config->autoUpdate == 'manual') return ;
        
        if(empty($config->categoryId) || empty($config->workspaceId) || empty($config->knowledgeBaseId)) {
            throw new PluginException('请先设置 AIHelper 知识库推送部分！如无需自动推送请选择手动推送！');
        }

        if(empty($config->aliyunEndpoint) || empty($config->aliyunAccessKeyId) || empty($config->aliyunAccessKeyToken)) {
            throw new PluginException('请先设置阿里云RAM账号接入相关参数！如无需自动推送请选择手动推送！');
        }

        try{
            $client = new SimpleAPI($config->aliyunAccessKeyId, $config->aliyunAccessKeyToken, $config->aliyunEndpoint, $config->workspaceId, $config->categoryId);
            
            // 申请上传文档通道
            $lease = $client->applyFileUploadLease([
                'CategoryType' => 'UNSTRUCTURED',
                'FileName' => str_replace(array(" ", "?", "\\", "/", ":", "|", "*"), '-', $archive->title) . ".md",
                'Md5' => md5($archive->content),
                'SizeInBytes' => strlen($archive->content),
            ]);
    
            $upload = $client->uploadFile($archive->content, $lease['Param']['Url'], $lease['Param']['Headers'], $lease['Param']['Method']);
            $addFile = $client->addFile([
                'Parser' => 'DASHSCOPE_DOCMIND',
                'LeaseId' => $lease['FileUploadLeaseId'],
                'CategoryId' => $config->categoryId,
            ]);
            $submitJob = $client->submitIndexAddDocumentsJob([
                'IndexId' => $config->knowledgeBaseId,
                'SourceType' => 'DATA_CENTER_FILE',
                'DocumentIds' => json_encode([
                    $addFile['FileId']
                ]),
            ]);
        } catch (\Exception $e) {
            throw new PluginException('推送失败，错误：'.$e->getMessage());
        }
        return $archive;
    }
    
    private static function checkSession() {
        $_SESSION['helper_token'] = base64_encode('helper-'.mt_rand(1000000, 999999999));
    }
    
    public static function addHeader() {
        
        self::checkSession();
        
        $config = Helper::options()->plugin('AIHelper');
        $questions = explode(';', $config->initQuestion);
        $questionTemplate = "";
        foreach($questions as $question) {
            $questionTemplate = $questionTemplate . "{prompt: '" . $question . "'},";
        }
        $pluginUrl = Helper::options()->pluginUrl . '/AIHelper/';
        $template = <<<EOT
        <link rel="stylesheet" crossorigin href="https://g.alicdn.com/aliyun-documentation/web-chatbot-ui/0.0.24/index.css" />
        <script type="module" crossorigin src="https://g.alicdn.com/aliyun-documentation/web-chatbot-ui/0.0.24/index.js"></script>
        <script>
          window.CHATBOT_CONFIG = {
            endpoint: "/aihelper/chat/",
            displayByDefault: false,
            aiChatOptions: { 
              conversationOptions: { 
                conversationStarters: [
                  {$questionTemplate}
                ]
              },
              displayOptions: { 
                height: {$config->chatboxHeight},
              },
              personaOptions: { 
                assistant: {
                  name: '{$config->titleMessage}',
                  avatar: '{$config->helperLogo}',
                  tagline: '{$config->subtitleMessage}',
                }
              }
            },
            dataProcessor: {
              rewritePrompt(prompt) {
                return prompt;
              }
            }
          };
        </script>
        <style>
          :root {
            /* webchat 工具栏的颜色 */
            --webchat-toolbar-background-color: #1464E4;
            /* webchat 工具栏文字和按钮的颜色 */
            --webchat-toolbar-text-color: #FFF;
          }
          /* webchat 对话框如果被遮挡，可以尝试通过 z-index、bottom、right 等设置来调整位置 */
          .webchat-container {
            z-index: {$config->chatboxZIndex};
            bottom: 10px;
            right: 10px;
          }
          /* webchat 的唤起按钮如果被遮挡，可以尝试通过 z-index、bottom、right 等设置来调整位置 */
          .webchat-bubble-tip {
            z-index: 99;
            bottom: {$config->buttonBottom}px;
            right: {$config->buttonRight}px;
          }
             .nlux-markdown-container code {
                 font-size: 1rem;
             }
             .nlux-markdown-container  .code-block{
                overflow: auto;
             }
             .nlux-markdown-container  a{
                color: blue;
             }
        </style>
        EOT;
        print($template);
    }
    
}

?>