# PostTransition
PostTransition for Cakephp3

複数ページ遷移を簡単にpostで管理するプラグインです。

次ページ等の移動はpostで行うため複数タブにも対応が可能です。

また、画面の順番の概念を持たないのでどこの画面に遷移をすることも可能です。

(たとえば確認画面から途中の画面に戻って修正して再度確認画面に戻るなど)

##使い方

### setやgetを使用していない場合はこちらでOK  

TopicsController.php

```
<?php
namespace App\Controller;

use App\Controller\AppController;
use PostTransition\Controller\PostTransitionControllerTrait;

class TopicsController extends AppController
{
    use PostTransitionControllerTrait;
    /**
     * Index method
     *
     * @return void
     */
    public function index()
    {
    }
    
    public function add()
    {
        $settings = [
            //次へボタンのprefix(default はnext)
            //'nextPrefix' => 'next',
            //前へボタンのprefix(default はback)
            //'backPrefix' => 'back',
            //現在の状態をセッションで管理するキー(defaultはnow)
            //'nowField' => 'now',
            //扱うモデル
            'model' => 'Topics',
            //初期遷移時の設定
            'default' => [
                //初期値
                //'value' => [],
                //初期画面の設定はpost配列内のどの設定か
                'post_setting' => 'index1',
            ],
            //privateの第三引数に特定の値を引き渡す
            'param' => [],
            //post時の設定
            'post' => [
                //key値は、ボタン名や独自メソッドへのアクセスに使用
                'index1' => [
                    //使用するview
                    'render' => 'index1',
                    //バリデーションをかける際のentity作成第二引数の設定
                    'validate_option' => ['validate' => 'index1']
                ],
                'index2' => [
                    'render' => 'index2',
                    'validate_option' => ['validate' => 'index2']
                ],
                'index3' => [
                    'render' => 'index3',
                    'validate_option' => ['validate' => 'index3']
                ],
                'save' => [
                    'render' => false,
                    'validate_option' => ['validate' => false]
                ],
            ],
        ];
        $this->__postTransition($settings);
    }
    
    //index1の画面描画前(entityセット直前でフック)
    private function __index1($entity, $data, $param){
       $entity->hoge4 = 'are';
    }
    
    //save処理
    /*
     * $entity $this->Form->createにsetするためのEntity validationを通過した際にはsetが走っていないEntity
     * $data 保存処理などに利用するためのデータの配列。$entityはsetが走っていないため、別途newEntityをして保存する
     * $param 設定値で引き渡すと設定した値
     */
    private function __save($entity, $data, $param){
        debug($entity);
        debug($data);
        //save 処理においては配列データを使用してEntityを走らせたい
        $saveEntity = $this->Topics->newEntity($data);
        if ($this->Topics->save($saveEntity)){
            //
        }
    }
}
```

index1.ctp

```
index1<br />
<?php //__index1にてentityにセットした値が入る?>
hoge4:<?= $entity->hoge4;?><br />
<?= $this->Form->create($entity);?>

<?= $this->Form->input('hoge1',['required' => false]);?>
<?= $this->Form->input('hidden_key');?>
<?= $this->Form->input('now');?>

<?php //押すと、index1のバリデーションがかかった後index2.ctpが表示?>
<?= $this->Form->submit('submit', ['name' => 'next_index2']);?>
<?= $this->Form->end();?>
```

index2.ctp
```
index2<br />
hoge1:<?= $entity->hoge1;?>

<?= $this->Form->create($entity);?>

<?= $this->Form->input('hoge2',['required' => false]);?>
<?= $this->Form->input('hidden_key');?>
<?= $this->Form->input('now');?>

<?php //バリデーションをかけず画面1に戻る?>
<?= $this->Form->submit('back', ['name' => 'back_index1']);?>
<?php //index2のバリデーションをかけて画面3にすすむ?>
<?= $this->Form->submit('next', ['name' => 'next_index3']);?>
<?= $this->Form->end();?>
```

index3.ctp
```
index3<br />
hoge1:<?= $entity->hoge1;?><br />
hoge2:<?= $entity->hoge2;?><br />
<?= $this->Form->create($entity);?>

<?= $this->Form->input('hoge3',['required' => false]);?>
<?= $this->Form->input('hidden_key');?>
<?= $this->Form->input('now');?>

<?php //バリデーションをかけず画面1に戻る?>
<?= $this->Form->submit('back1', ['name' => 'back_index1']);?>
<?php //バリデーションをかけず画面2に戻る?>
<?= $this->Form->submit('back2', ['name' => 'back_index2']);?>
<?php //バリデーションをかけてsave処理にすすむ?>
<?= $this->Form->submit('next', ['name' => 'next_save']);?>
<?= $this->Form->end();?>

```

### パスワードなどsetやgetでフォームの項目で使用している場合はこちらを参考に

TopicsController.php
```
<?php
namespace App\Controller;

use App\Controller\AppController;
use App\Form\TopicForm;
use Cake\ORM\TableRegistry;
use PostTransition\Controller\PostTransitionFormControllerTrait;

class TopicsController extends AppController
{
    use PostTransitionFormControllerTrait;

        public function initialize()
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
    }

    public function add()
    {
        $topicForm = new TopicForm();
        $settings = [
            //次へボタンのprefix(default はnext)
            //'nextPrefix' => 'next',
            //前へボタンのprefix(default はback)
            //'backPrefix' => 'back',
            //現在の状態をセッションで管理するキー(defaultはnow)
            //'nowField' => 'now',
            //扱うモデル
            'model' => $topicForm,
            //初期遷移時の設定
            'default' => [
                //初期値
                'value' => [
                    // 'hoge1' => 'hoge'
                ],
                //初期画面の設定はpost配列内のどの設定か
                'post_setting' => 'index1',
            ],
            'param' => 'hogehoge',
            //post時の設定
            'post' => [
                //key値は、ボタン名や独自メソッドへのアクセスに使用
                'index1' => [
                    //使用するview
                    'render' => 'index1',
                    //バリデーションをかける際のentity作成第二引数の設定
                    'validate_option' => ['validate' => 'index1']
                ],
                'index2' => [
                    'render' => 'index2',
                    'validate_option' => ['validate' => 'index2']
                ],
                'index3' => [
                    'render' => 'index3',
                    'validate_option' => ['validate' => 'index3']
                ],
                'save' => [
                    'render' => false,
                    'validate_option' => ['validate' => false]
                ],
            ],
        ];
        $this->__postTransition($settings);
    }

    //index1の画面描画前(entityセット直前でフック)
    private function __index1($data, $param){
        var_dump($data);
        var_dump($param);
    }

    //save処理
    private function __save($data, $param){
        $this->Topics = TableRegistry::get('Topics');
        debug($this->Topics->newEntity($data));
        //save 処理
    }
}

```

TopicForm.php  
(バリデーションの処理をTableではなくForm側に持たせる)
```
<?php

namespace App\Form;

use Cake\Form\Form;
use Cake\Form\Schema;
use Cake\Validation\Validator;

class TopicForm extends Form
{
    //ここでバリデーションを記述
    protected function _buildValidator(Validator $validator)
    {
        // $validator
        //     ->notEmpty('hoge1', 'required singer_id');
        return $validator;
    }

    public function validationIndex1(Validator $validator)
    {
        $validator
            ->notEmpty('hoge1', 'required singer_id');
        return $validator;
    }

    public function validationIndex2(Validator $validator)
    {
        $validator
            ->notEmpty('hoge2', 'required singer_id');
        return $validator;
    }

    public function validationIndex3(Validator $validator)
    {
        $validator
            ->notEmpty('hoge2', 'required singer_id');
        return $validator;
    }

    //ドキュメントには他にもメソッドいたけどこれだけあれば最低限必要なことはできそう
}

```
index1.ctp
```
index1<br />
<?= $this->Form->create($contactForm);?>
<?= $this->Form->input('hoge1',['required' => false]);?>
<?= $this->Form->input('hidden_key');?>
<?= $this->Form->input('now');?>

<?php //押すと、index1のバリデーションがかかった後index2.ctpが表示?>
<?= $this->Form->submit('submit', ['name' => 'next_index2']);?>
<?= $this->Form->end();?>
```

index2.ctp
```
index2<br />
hoge1:<?= $this->request->data['hoge1'];?>

<?= $this->Form->create($contactForm);?>

<?= $this->Form->input('hoge2',['required' => false]);?>
<?= $this->Form->input('hidden_key');?>
<?= $this->Form->input('now');?>

<?php //バリデーションをかけず画面1に戻る?>
<?= $this->Form->submit('back', ['name' => 'back_index1']);?>
<?php //index2のバリデーションをかけて画面3にすすむ?>
<?= $this->Form->submit('next', ['name' => 'next_index3']);?>
<?= $this->Form->end();?>
```

index3.ctp
```
index3<br />
hoge1:<?= $this->request->data['hoge1'];?><br />
hoge2:<?= $this->request->data['hoge2'];?><br />
<?= $this->Form->create($contactForm);?>

<?= $this->Form->input('hoge3',['required' => false]);?>
<?= $this->Form->input('hidden_key');?>
<?= $this->Form->input('now');?>

<?php //バリデーションをかけず画面1に戻る?>
<?= $this->Form->submit('back1', ['name' => 'back_index1']);?>
<?php //バリデーションをかけず画面2に戻る?>
<?= $this->Form->submit('back2', ['name' => 'back_index2']);?>
<?php //バリデーションをかけてsave処理にすすむ?>
<?= $this->Form->submit('next', ['name' => 'next_save']);?>
<?= $this->Form->end();?>
```
