# PostTransition
PostTransition for Cakephp3

複数ページ遷移を簡単にpostで管理するプラグインです。

次ページ等の移動はpostで行うため複数タブにも対応が可能です。

また、画面の順番の概念を持たないのでどこの画面に遷移をすることも可能です。

(たとえば確認画面から途中の画面に戻って修正して再度確認画面に戻るなど)

##使い方

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
            //privateの第二引数に特定の値を引き渡す
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
    private function __index1($entity, $param){
       $entity->hoge4 = 'are';
    }
    
    //save処理
    private function __save($entity, $param){
        debug($entity);
        //save 処理
        exit;
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
