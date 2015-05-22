<?php

namespace PostTransition\Controller;

use Cake\Utility\Security;
use Cake\Utility\Hash;
use Cake\Routing\Router;
use Cake\Network\Exception\MethodNotAllowedException;

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
    
    private function __postTransition($settings){
        $settings = Hash::merge(
            $this->__default_settings,
            $settings
        );
        if (empty($settings['model'])){
            $settings['model'] = $this->modelClass;
        }
        $this->{$settings['model']} = $this->loadModel($settings['model']);
        
        //初期アクセス時の対応
        if (!$this->request->is('post') && !$this->request->is('put')){
            
            $hidden_key = Security::hash(time() . rand());
            $value = array_merge(
                $settings['default']['value'],
                ['hidden_key' => $hidden_key]
            );
            //セッションに空のデータを作成しておく
            
            //独自メソッド
            $entity = $this->{$settings['model']}->newEntity($value);
            $private_method = '__' . $settings['default']['post_setting'];
            if (method_exists($this, $private_method)){
                $this->{$private_method}($entity);
            }
            
            $now = $settings['default']['post_setting'];
            $this->request->session()->write($settings['model'] . '.' . $hidden_key, [$settings['nowField'] => $now]);
            
            $this->set(compact('entity'));
            $this->render($settings['post'][$settings['default']['post_setting']]['render']);
            return;
        }
        
        //セッション切れの処理
        if (!$this->request->session()->check($settings['model'] . '.' . $this->request->data['hidden_key'])){
            //最初に戻る
            if (!empty($settings['start_action'])){
                $start_action = $settings['start_action'];
            } else {
                //設定がないときは自身のURLにリダイレクト(基本こっち
                //自身のURLがうまくRouter::urlで取れないので自身で作成しておく
                $start_action = Router::fullBaseUrl() . $this->request->here;
            }
            return $this->redirect($start_action);
        }
        
        //request->dataで来たデータから必要なprefixのついているものを抽出
        $keys = array_keys($this->request->data);
        $action_button_check = preg_grep('/^(' . $settings['nextPrefix'] . '|' . $settings['backPrefix'] . ')_/',$keys);
        if (empty($action_button_check)){
            //エラー
            throw new MethodNotAllowedException();
        }
        
        //一番目のものを取得する(複数はない前提)
        $action_data = array_shift($action_button_check);
        //next_action
        if (!preg_match('/^(' . $settings['nextPrefix'] . '|' . $settings['backPrefix'] . ')_(.*)$/',$action_data, $action)){
            
            //上部マッチで取っているはずだがもし流れた場合はエラー
            throw new MethodNotAllowedException();
        }
        
        //バリデーションの切り替え

        $readSession = $this->request->session()->read($settings['model'] . '.' . $this->request->data['hidden_key']);

        //何も設定がないときはdefaultを読む
        $validate_option = [];
        if (array_key_exists('validate_option', $settings['post'][$readSession[$settings['nowField']]])){
            $validate_option = $settings['post'][$readSession[$settings['nowField']]]['validate_option'];
        }
        
        $entity = $this->{$settings['model']}->newEntity(
            $this->request->data(), 
            //バリデーションの切り替えなど
            $validate_option
        );
        
        if (
            $action[1] == $settings['nextPrefix'] &&
            $entity->errors()
        ){
            //バリデーションエラー
            $private_method = '__' . $readSession[$settings['nowField']];
            if (method_exists($this, $private_method)){
                $this->{$private_method}($entity);
            }
            $this->set(compact('entity'));
            $this->render($readSession[$settings['nowField']]);
            return;
        }
        
        //バリデーションを通過したらセッションにあるデータも書き込む
        $mergedData = array_merge(
            $readSession,
            $this->request->data,
            [$settings['nowField'] => $action[2]]
        );
        
        $this->request->session()->write($settings['model'] . '.' . $this->request->data['hidden_key'], $mergedData);
        
        $entity = $this->{$settings['model']}->newEntity($mergedData);
        
        $private_method = '__' . $action[2];
        if (method_exists($this, $private_method)){
            $this->{$private_method}($entity);
        }
        
        $this->set(compact('entity'));
        if ($settings['post'][$action[2]]['render'] !== false){
            $this->render($settings['post'][$action[2]]['render']);
        }
        return;
    }
}
