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
        'param' => [],
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
        
        //必要なパラメータがpostされているかのチェック
        if (!array_key_exists($this->__settings['nowField'], $this->request->data)){
            throw new MethodNotAllowedException();
        }
        $nowField = $this->request->data[$this->__settings['nowField']];
        
        $validate_option = [];
        if (array_key_exists('validate_option', $this->__settings['post'][$nowField])){
            $validate_option = $this->__settings['post'][$nowField]['validate_option'];
        }
        
        $mergedData = array_merge(
            $entity_data,
            $this->request->data()
        );
        
        $entity = $this->transitionModel->newEntity($mergedData, $validate_option);
        
        if (
            $action[1] == $this->__settings['nextPrefix'] &&
            $entity->errors()
        ){
            $this->Flash->error(__d('post_transition', 'Validation could not pass.'));
            $this->request->session()->write($this->__settings['model'] . '.' . $this->request->data['hidden_key'], $mergedData);
            
            $this->_viewRender($entity, $mergedData, $entity->{$this->__settings['nowField']}, $this->__settings['param']);
            return;
        }
        
        unset($this->request->data['now']);
        $mergedData = array_merge(
            $mergedData,
            [$this->__settings['nowField'] => $action[2]]
        );
        
        // エラーを通り抜けた際には、useSetter => falseのデータを引き渡す
        $entity = $this->__setterFalseEntityData($mergedData);
        $entity->now = $action[2];

        $this->request->session()->write($this->__settings['model'] . '.' . $this->request->data['hidden_key'], $mergedData);
        
        //postデータ自体が残っていると動作に不都合があるため
        unset($this->request->data['now']);
        $this->_viewRender($entity, $mergedData, $action[2], $this->__settings['param']);
        
        return;
    }
    
    protected function _viewRender($entity, $data, $action, $param){
        $private_method = $this->__settings['post'][$action]['private'];
        if (method_exists($this, $private_method)){
            $this->{$private_method}($entity, $data, $param);
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
            $value = $entity->toArray();
        } else {
            $value = array_merge(
                $this->__settings['default']['value'],
                ['hidden_key' => $hidden_key]
            );
            
            $entity = $this->__setterFalseEntityData($value);
        }
        
        //セッションに空のデータを作成しておく
        $entity->{$this->__settings['nowField']} = $this->__settings['default']['post_setting'];
        $value[$this->__settings['nowField']] = $this->__settings['default']['post_setting'];

        $this->request->session()->write($this->__settings['model'] . '.' . $hidden_key, $value);
        
        $this->_viewRender($entity, $value, $this->__settings['default']['post_setting'], $this->__settings['param']);
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
    
    private function __setterFalseEntityData($data) {
        //src\ORM\Table 2013行目付近参照
        $newEntityClass = $this->transitionModel->entityClass();
        //useSetters falseをセットすることでフォームにセットする値についてsetterを発動させない
        return new $newEntityClass($data, ['source' => $this->transitionModel->registryAlias(), 'useSetters' => false]);
    }
}
