<?php

namespace PostTransition\Controller;

use Cake\Utility\Security;
use Cake\Utility\Hash;
use Cake\Routing\Router;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\ORM\TableRegistry;

trait PostTransitionControllerTrait
{
    private $__default_settings = [
        'nextPrefix' => 'next',
        'backPrefix' => 'back',
        'nowField' => 'now',
        'default' => [
            'value' => [],
        ],
        'post' => [],
    ];
    private $__settings;
    public $transitionModel;
    
    private function __postTransition($settings){
        //設定値の設定
        $this->__postSettingAdjustment($settings);
        
        if (empty($this->__settings['model'])){
            $this->__settings['model'] = $this->modelClass;
        }
        
        $this->transitionModel = TableRegistry::get($this->__settings['model']);
        
        //post_max_sizeオーバーのときのエラーチェック
        //postでかつデータが空の時=>post_max_file_sizeオーバー時の対応
        
        if ($this->request->is('post') && empty($this->request->data)) {
            $this->Flash->error(__d('post_transition', 'Post max size error.'));
            $this->__sessionTimeout();
        }
        
        
        //初期アクセス時の対応
        if (!$this->request->is('post') && !$this->request->is('put')){
            $this->__firstAction();
            return;
        }
        
        //セッション切れの処理
        if (!$this->request->session()->check($this->__settings['model'] . '.' . $this->request->data['hidden_key'])){
            $this->Flash->error(__d('post_transition', 'Session Timeout.'));
            return $this->__sessionTimeout();
        }
        
        //request->dataで来たデータから必要なprefixのついているものを抽出
        $keys = array_keys($this->request->data);
        $action_button_check = preg_grep('/^(' . $this->__settings['nextPrefix'] . '|' . $this->__settings['backPrefix'] . ')_/',$keys);
        if (empty($action_button_check)){
            //エラー
            throw new MethodNotAllowedException();
        }
        
        //一番目のものを取得する(複数はない前提)
        $action_data = array_shift($action_button_check);
        //next_action
        if (!preg_match('/^(' . $this->__settings['nextPrefix'] . '|' . $this->__settings['backPrefix'] . ')_(.*)$/',$action_data, $action)){
            
            //上部マッチで取っているはずだがもし流れた場合はエラー
            throw new MethodNotAllowedException();
        }

        $entity_data = $this->request->session()->read($this->__settings['model'] . '.' . $this->request->data['hidden_key']);
        $entity = $this->transitionModel->newEntity($entity_data);

        if (!empty($this->request->data[$this->__settings['nowField']])) {
            $nowField = $this->request->data[$this->__settings['nowField']];
        } else {
            $nowField = $entity->{$this->__settings['nowField']};
        }
        //何も設定がないときはdefaultを読む
        $validate_option = [];
        if (array_key_exists('validate_option', $this->__settings['post'][$nowField])){
            $validate_option = $this->__settings['post'][$nowField]['validate_option'];
        }
        
       
        $entity = $this->transitionModel->patchEntity($entity, $this->request->data(), $validate_option);
        
        if (
            $action[1] == $this->__settings['nextPrefix'] &&
            $entity->errors()
        ){
            $this->Flash->error(__d('post_transition', 'Validation could not pass.'));
            
            $entity_data = $entity->toArray();
            $this->request->session()->write($this->__settings['model'] . '.' . $this->request->data['hidden_key'], $entity_data);
            
            $this->_viewRender($entity, $entity->{$this->__settings['nowField']});
            
            return;
        }
        
        $mergedData = array_merge(
            $this->request->data,
            [$this->__settings['nowField'] => $action[2]]
        );
        
        $entity = $this->transitionModel->patchEntity($entity, $mergedData);
        
        $entity_data = $entity->toArray();

        $this->request->session()->write($this->__settings['model'] . '.' . $this->request->data['hidden_key'], $entity_data);
        
        $this->_viewRender($entity, $action[2]);
        
        return;
    }
    
    protected function _viewRender($entity, $action){
        
        $private_method = $this->__settings['post'][$action]['private'];
        if (method_exists($this, $private_method)){
            $this->{$private_method}($entity);
        }
        
        //viewのnowに現在の画面の値をセットする
        $entity->{$this->__settings['nowField']} = $action;
        $this->set(compact('entity'));
        if ($this->__settings['post'][$action]['render'] !== false){
            $this->render($this->__settings['post'][$action]['render']);
        }
        return;
    }
    
    private function __postSettingAdjustment($settings){
        $this->__settings = Hash::merge(
            $this->__default_settings,
            $settings
        );

        foreach ($this->__settings['post'] as $post_key => $post_val){
            if (is_string($post_val)){
                $this->__settings['post'][$post_val] = [
                    'render' => $post_val,
                    'private' => '__' . $post_val,
                    'validate_option' => [],
                ];
                //不要なものは削除
                unset($this->__settings['post'][$post_key]);
            } else {
                if (!array_key_exists('render', $post_val)){
                    $this->__settings['post'][$post_key]['render'] = $post_key;
                }
                if (!array_key_exists('private', $post_val)){
                    $this->__settings['post'][$post_key]['private'] = '__' . $post_key;
                }
                if (!array_key_exists('validate_option', $post_val)){
                    $this->__settings['post'][$post_key]['validate_option'] = [];
                }
            }
        }
        
    }
    
    private function __firstAction(){
        $hidden_key = Security::hash(time() . rand());
        if (is_object($this->__settings['default']['value'])){
            $entity = $this->__settings['default']['value'];
            $entity->hidden_key = $hidden_key;
        } else {
            $value = array_merge(
                $this->__settings['default']['value'],
                ['hidden_key' => $hidden_key]
            );
            $entity = $this->transitionModel->newEntity($value);
        }
        
        //セッションに空のデータを作成しておく
        $entity->{$this->__settings['nowField']} = $this->__settings['default']['post_setting'];
        $entity_data = $entity->toArray();
        $this->request->session()->write($this->__settings['model'] . '.' . $hidden_key, $entity_data);
        
        $this->_viewRender($entity, $this->__settings['default']['post_setting']);
    }
    
    private function __sessionTimeout(){
        //最初に戻る
        if (!empty($this->__settings['start_action'])){
            $start_action = $this->__settings['start_action'];
        } else {
            //設定がないときは自身のURLにリダイレクト(基本こっち
            //自身のURLがうまくRouter::urlで取れないので自身で作成しておく
            $start_action = Router::fullBaseUrl() . $this->request->here;
        }
        return $this->redirect($start_action);
    }
}
