yii2-qiwi-basic
==============

Очень простой враппер для qiwi rest протокола. От yii2 только настройки берутся из параметров.

## Установка

1. Скопировать файлы в **vendor/ribris/yii2-qiwi-basic**

2. В файл **config/web.php** добавить: 
```php
    Yii::$classMap['ribris\qiwi\Qiwi'] = '@app/vendor/ribris/yii2-qiwi-basic/Qiwi.php';
    Yii::$classMap['ribris\qiwi\QiwiServer'] = '@app/vendor/ribris/yii2-qiwi-basic/QiwiServer.php';
```

3. В файл **config/params.php** добавить: 
```php
    return [
        // параметры...

        /*QIWI*/
        'QIWI_SHOP_ID' => 'ваш shop id',
        'QIWI_REST_ID' => 'ваш rest id',
        'QIWI_REST_PWD' => 'ваш rest пароль',
        'QIWI_NOTIFICATION_PWD' => 'ваш пароль для уведомлений',
    ];
```

## Использование:

### Выставление/проверка счета

```php
use ribris\qiwi\Qiwi;

class QiwiController
{

    public function actionCreateBill()
    {

        // какая то логика для получения нужных параметров счета 
        // $bill_id, $phone, $amount, 'RUB', $description

        return Qiwi::createBill($bill_id, $phone, $amount, 'RUB', $description);
    }

    public function actionUpdateBill()
    {
        // какая то логика для получения нужного $bill_id

        return Qiwi::checkBill($bill_id);
    }

}
```

### Получение уведомлений

```php

use ribris\qiwi\Qiwi;
use ribris\qiwi\QiwiServer;

class QiwiController
{

    public $enableCsrfValidation = false;

    //...

    public function actionResponse()
    {
        $returnCode = 5; // не ОК

        if($notify = QiwiServer::request()) {

            // например так
            if($Bill = Bill::findOne($notify['bill_id'])) {

                $Bill->status = $notify['status'];
                $Bill->save();

                $returnCode = 0;// OK
            }
        }

        QiwiServer::response($returnCode);
    }

}

```